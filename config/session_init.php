<?php
// Centralized session + auth bootstrap — include this everywhere instead of calling
// session_start() directly, so every entry point (pages AND api.php) agrees on the
// same lifetime and the same "remember me" restore logic.
// (Mixing ini_set values, or restoring the login cookie in only some entry points,
// causes users to appear logged out mid-work even though their cookie is still valid.)

if (!defined('AUTH_SESSION_LIFETIME')) {
    // 16 giờ: đủ cho 1 ca dài (kể cả tăng ca) mà không bị gián đoạn giữa lúc kiểm kê.
    // Được "trượt" (rolling) mỗi request có đăng nhập nên chỉ khi NGƯNG hoạt động
    // liên tục quá thời gian này thì mới thực sự bị đăng xuất.
    define('AUTH_SESSION_LIFETIME', 16 * 60 * 60);
}

if (!defined('AUTH_COOKIE_NAME')) {
    define('AUTH_COOKIE_NAME', 'wms_auth');
}

if (!function_exists('session_auth_cookie_secure')) {
    function session_auth_cookie_secure(): bool {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', AUTH_SESSION_LIFETIME);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 1);

    session_set_cookie_params([
        'lifetime' => AUTH_SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => session_auth_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Phiên đang có người dùng đăng nhập -> "trượt" hạn cookie PHPSESSID mỗi request
    // (không chỉ đặt 1 lần lúc đăng nhập), để làm việc liên tục nhiều giờ (VD: kiểm kê
    // cả ca) không bị hết hạn giữa chừng.
    if (!empty($_SESSION['user']) && !headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + AUTH_SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => session_auth_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('ensure_user_token_schema')) {
    function ensure_user_token_schema(PDO $pdo) {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $columns = $pdo->query('SHOW COLUMNS FROM log_users')->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = [];
            foreach ($columns as $column) {
                $columnName = $column['Field'] ?? '';
                if ($columnName !== '') $columnNames[$columnName] = true;
            }
            if (!isset($columnNames['remember_token']))
                $pdo->exec('ALTER TABLE log_users ADD COLUMN remember_token VARCHAR(64) DEFAULT NULL AFTER status');
            if (!isset($columnNames['remember_token_expires']))
                $pdo->exec('ALTER TABLE log_users ADD COLUMN remember_token_expires DATETIME DEFAULT NULL AFTER remember_token');
        } catch (Throwable $e) {}
    }
}

if (!function_exists('issue_auth_cookie')) {
    function issue_auth_cookie(PDO $pdo, array $user) {
        ensure_user_token_schema($pdo);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + AUTH_SESSION_LIFETIME);
        $stmt = $pdo->prepare('UPDATE log_users SET remember_token = ?, remember_token_expires = ? WHERE id = ?');
        $stmt->execute([hash('sha256', $token), $expires, $user['id']]);

        setcookie(AUTH_COOKIE_NAME, $user['id'] . ':' . $token, [
            'expires'  => time() + AUTH_SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => session_auth_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('clear_auth_cookie')) {
    function clear_auth_cookie(PDO $pdo) {
        if (isset($_SESSION['user']['id'])) {
            ensure_user_token_schema($pdo);
            $stmt = $pdo->prepare('UPDATE log_users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?');
            $stmt->execute([$_SESSION['user']['id']]);
        }
        setcookie(AUTH_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => session_auth_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('restore_session_from_cookie')) {
    function restore_session_from_cookie(PDO $pdo) {
        if (isset($_SESSION['user']) || empty($_COOKIE[AUTH_COOKIE_NAME])) return;
        $parts = explode(':', $_COOKIE[AUTH_COOKIE_NAME], 2);
        if (count($parts) !== 2) return;
        [$userId, $token] = $parts;
        if (!ctype_digit($userId) || $token === '') return;

        ensure_user_token_schema($pdo);
        $stmt = $pdo->prepare('SELECT id, username, full_name, role, status, remember_token, remember_token_expires FROM log_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u || !$u['status'] || !$u['remember_token'] || !$u['remember_token_expires']) return;
        if (strtotime($u['remember_token_expires']) < time()) return;
        if (!hash_equals($u['remember_token'], hash('sha256', $token))) return;

        $_SESSION['user'] = ['id'=>$u['id'],'username'=>$u['username'],'full_name'=>$u['full_name'],'role'=>$u['role']];
        issue_auth_cookie($pdo, $_SESSION['user']); // rolling expiry
    }
}

if (!function_exists('current_user')) {
    function current_user() {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }
}

if (!function_exists('require_role')) {
    function require_role(array $roles) {
        $u = current_user();
        if (!$u || !in_array($u['role'], $roles)) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
    }
}

// Khôi phục đăng nhập từ cookie "remember me" ở MỌI entry point (trang lẫn api.php).
// Trước đây việc này chỉ chạy trong api.php, nên khi mở lại/tải lại 1 trang PHP
// (VD: index.php) mà session PHP đã bị dọn (GC) thì người dùng bị đưa về màn hình
// đăng nhập dù cookie "remember me" vẫn còn hạn — đây là nguyên nhân chính khiến
// timeout "không đủ dùng" dù đã đặt 8h.
if (isset($pdo) && $pdo instanceof PDO) {
    restore_session_from_cookie($pdo);
}
