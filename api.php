<?php
require_once 'config/db.php';
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
session_start();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_role(array $roles) {
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }
}

function ensure_export_temp_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $columns = $pdo->query('SHOW COLUMNS FROM export_temp')->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = [];
        foreach ($columns as $column) {
            $columnName = $column['Field'] ?? '';
            if ($columnName !== '') $columnNames[$columnName] = true;
        }

        if (!isset($columnNames['order_code'])) {
            $pdo->exec('ALTER TABLE export_temp ADD COLUMN order_code VARCHAR(120) DEFAULT NULL AFTER created_at');
        }

        $indexes = $pdo->query('SHOW INDEX FROM export_temp')->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = [];
        foreach ($indexes as $index) {
            $keyName = $index['Key_name'] ?? '';
            if ($keyName !== '') $indexNames[$keyName] = true;
            if ($keyName !== 'PRIMARY' && (int)($index['Non_unique'] ?? 1) === 0 && ($index['Column_name'] ?? '') === 'product_id') {
                $pdo->exec("ALTER TABLE export_temp DROP INDEX `{$keyName}`");
            }
        }
        if (!isset($indexNames['idx_export_temp_command']))
            $pdo->exec('ALTER TABLE export_temp ADD INDEX idx_export_temp_command (command)');
        if (!isset($indexNames['idx_export_temp_product_id']))
            $pdo->exec('ALTER TABLE export_temp ADD INDEX idx_export_temp_product_id (product_id)');
    } catch (Throwable $e) {}
}

function ensure_export_log_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS export_log (
            id INT(11) NOT NULL AUTO_INCREMENT,
            command VARCHAR(50) NOT NULL,
            case_no VARCHAR(50) NOT NULL,
            product_id VARCHAR(50) NOT NULL,
            quantity INT(11) NOT NULL,
            created_by VARCHAR(50) NOT NULL,
            status ENUM('picking','packing','pickup') NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_export_log_command_case (command, case_no),
            KEY idx_export_log_product (product_id),
            KEY idx_export_log_status (status),
            KEY idx_export_log_created_by (created_by),
            KEY idx_export_log_created_at (created_at),
            KEY idx_export_log_command_case_status (command, case_no, status),
            KEY idx_export_log_command_case_product_status (command, case_no, product_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function ensure_check_log_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS check_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tem1_raw TEXT NOT NULL,
            tem2_raw TEXT NOT NULL,
            tem1_product VARCHAR(120) DEFAULT NULL,
            tem1_qty INT DEFAULT NULL,
            tem2_product VARCHAR(120) DEFAULT NULL,
            tem2_qty INT DEFAULT NULL,
            is_product_match TINYINT(1) NOT NULL DEFAULT 0,
            is_qty_match TINYINT(1) NOT NULL DEFAULT 0,
            result_code VARCHAR(50) DEFAULT NULL,
            result_message VARCHAR(255) DEFAULT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'check box',
            scanned_by VARCHAR(50) NOT NULL,
            scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_check_log_scanned_at (scanned_at),
            KEY idx_check_log_scanned_by (scanned_by),
            KEY idx_check_log_type (type),
            KEY idx_check_log_result_code (result_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function ensure_wms_inventory_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS wms_inventory (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            box_code VARCHAR(120) NOT NULL,
            product_id VARCHAR(120) NOT NULL,
            quantity DECIMAL(18,5) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_wms_inventory_box_product (box_code, product_id),
            KEY idx_wms_inventory_box_code (box_code),
            KEY idx_wms_inventory_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function parse_php_ini_size_to_bytes($value): int {
    $value = trim((string)$value);
    if ($value === '') return 0;

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    switch ($unit) {
        case 'g':
            $number *= 1024;
        case 'm':
            $number *= 1024;
        case 'k':
            $number *= 1024;
            break;
    }

    return (int)round($number);
}

function format_bytes_human(int $bytes): string {
    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function upload_error_message(int $errorCode): string {
    $uploadMax = ini_get('upload_max_filesize') ?: 'không xác định';
    $postMax = ini_get('post_max_size') ?: 'không xác định';

    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'File vượt quá giới hạn upload_max_filesize của PHP (' . $uploadMax . '). post_max_size hiện tại: ' . $postMax . '.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'File vượt quá giới hạn MAX_FILE_SIZE của form HTML.';
        case UPLOAD_ERR_PARTIAL:
            return 'File chỉ được tải lên một phần. Hãy thử lại.';
        case UPLOAD_ERR_NO_FILE:
            return 'Chưa nhận được file upload.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Máy chủ thiếu thư mục tạm để lưu file upload.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Máy chủ không ghi được file upload xuống đĩa.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload bị chặn bởi một PHP extension trên máy chủ.';
        case UPLOAD_ERR_OK:
            return 'Upload thành công.';
        default:
            return 'Lỗi upload không xác định (mã ' . $errorCode . ').';
    }
}

function upload_request_too_large_message(): string {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxRaw = ini_get('post_max_size') ?: '0';
    $uploadMaxRaw = ini_get('upload_max_filesize') ?: '0';
    $postMaxBytes = parse_php_ini_size_to_bytes($postMaxRaw);
    $uploadMaxBytes = parse_php_ini_size_to_bytes($uploadMaxRaw);

    $detail = 'Không nhận được dữ liệu file upload.';
    if ($contentLength > 0) {
        $detail .= ' Kích thước request hiện tại khoảng ' . format_bytes_human($contentLength) . '.';
    }

    if ($postMaxBytes > 0 || $uploadMaxBytes > 0) {
        $detail .= ' Giới hạn máy chủ: upload_max_filesize=' . $uploadMaxRaw . ', post_max_size=' . $postMaxRaw . '.';
    }

    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $detail .= ' Request đã vượt quá post_max_size của PHP.';
    }

    return $detail;
}

function export_command_case_pickup_status(PDO $pdo, string $command): array {
    $stmt = $pdo->prepare(
        "SELECT e.case_no,
                COUNT(*) AS export_rows,
                COALESCE(p.pickup_logs, 0) AS pickup_logs,
                CASE WHEN COALESCE(p.pickup_logs, 0) > 0 THEN 1 ELSE 0 END AS is_ok
         FROM export_temp e
         LEFT JOIN (
             SELECT case_no, COUNT(*) AS pickup_logs
             FROM export_log
             WHERE command = ? AND status = 'pickup'
             GROUP BY case_no
         ) p ON p.case_no = e.case_no
         WHERE e.command = ?
         GROUP BY e.case_no, p.pickup_logs
         ORDER BY e.case_no ASC"
    );
    $stmt->execute([$command, $command]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['case_no'] = strtoupper(trim((string)$row['case_no']));
        $row['export_rows'] = (int)$row['export_rows'];
        $row['pickup_logs'] = (int)$row['pickup_logs'];
        $row['is_ok'] = (int)$row['is_ok'] === 1;
    }

    return $rows;
}

function export_case_packing_progress(PDO $pdo, string $command, string $caseNo): array {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_qty), 0) AS required_total
         FROM export_temp
         WHERE command = ? AND case_no = ?"
    );
    $stmt->execute([$command, $caseNo]);
    $requiredTotal = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity), 0) AS packed_total
         FROM export_log
         WHERE command = ? AND case_no = ? AND status = 'packing'"
    );
    $stmt->execute([$command, $caseNo]);
    $packedTotal = (int)$stmt->fetchColumn();

    return [
        'required_total' => $requiredTotal,
        'packed_total' => $packedTotal,
        'is_packed_done' => $requiredTotal > 0 && $packedTotal >= $requiredTotal,
    ];
}

function export_temp_column_index(string $letters) {
    $index = 0;
    $letters = strtoupper($letters);
    for ($i = 0; $i < strlen($letters); $i++)

        $index = ($index * 26) + (ord($letters[$i]) - 64);
    return $index - 1;
}

function export_temp_parse_csv_rows(string $filePath) {
    $handle = fopen($filePath, 'rb');
    if (!$handle) throw new Exception('Không thể đọc file CSV');
    $firstLine = fgets($handle);
    if ($firstLine === false) { fclose($handle); return []; }
    $delimiters = [',', ';', "\t"];
    $delimiter = ','; $maxCount = -1;
    foreach ($delimiters as $candidate) {
        $count = substr_count($firstLine, $candidate);
        if ($count > $maxCount) { $maxCount = $count; $delimiter = $candidate; }
    }
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

function export_temp_parse_xlsx_rows(string $filePath) {
    if (!class_exists('ZipArchive')) throw new Exception('Máy chủ chưa bật ZipArchive để đọc file XLSX');
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) throw new Exception('Không thể mở file XLSX');
    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml !== false) {
        $sharedStringsDoc = @simplexml_load_string($sharedStringsXml);
        if ($sharedStringsDoc && isset($sharedStringsDoc->si)) {
            foreach ($sharedStringsDoc->si as $si) {
                if (isset($si->t)) { $sharedStrings[] = (string)$si->t; continue; }
                $text = '';
                if (isset($si->r)) foreach ($si->r as $run) $text .= (string)$run->t;
                $sharedStrings[] = $text;
            }
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new Exception('Không tìm thấy dữ liệu sheet1 trong file XLSX');
    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData)) throw new Exception('Nội dung XLSX không hợp lệ');
    $rows = [];
    foreach ($sheet->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string)$cell['r'];
            preg_match('/[A-Z]+/i', $ref, $matches);
            $colIdx = isset($matches[0]) ? export_temp_column_index($matches[0]) : count($row);
            $type = (string)$cell['t'];
            $value = '';
            if ($type === 'inlineStr') $value = (string)($cell->is->t ?? '');
            elseif ($type === 's') $value = $sharedStrings[(int)($cell->v ?? 0)] ?? '';
            elseif ($type === 'b') $value = ((string)($cell->v ?? '0') === '1') ? '1' : '0';
            else $value = (string)($cell->v ?? '');
            $row[$colIdx] = trim($value);
        }
        if (!empty($row)) { ksort($row); $rows[] = array_values($row); }
    }
    return $rows;
}

function export_temp_parse_spreadsheet_rows(string $filePath, string $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'csv') return export_temp_parse_csv_rows($filePath);
    if ($ext === 'xlsx') return export_temp_parse_xlsx_rows($filePath);
    throw new Exception('Chỉ hỗ trợ file .xlsx hoặc .csv');
}

function export_temp_normalize_datetime($value) {
    if ($value === null || $value === '') return date('Y-m-d');

    if (is_numeric($value)) {
        $excelValue = (float)$value;
        if ($excelValue > 0) {
            $base = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
            $seconds = (int)round($excelValue * 86400);
            return $base->modify("+{$seconds} seconds")->format('Y-m-d');
        }
    }

    $value = trim((string)$value);
    if ($value === '') return date('Y-m-d');

    foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'd.m.Y'] as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    return $ts === false ? date('Y-m-d') : date('Y-m-d', $ts);
}

function export_temp_is_header_row(array $columns) {
    $n = array_map(function($c) { return strtolower(trim((string)$c)); }, $columns);
    return array_slice($n, 0, 10) === ['id', 'command', 'case_no', 'transport_type', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at', 'order_code']
        || array_slice($n, 0, 9) === ['command', 'case_no', 'transport_type', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at', 'order_code']
        || array_slice($n, 0, 9) === ['id', 'command', 'case_no', 'transport_type', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at']
        || array_slice($n, 0, 8) === ['command', 'case_no', 'transport_type', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at'];
}

function wms_inventory_normalize_header_label(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function wms_inventory_find_header_map(array $row): ?array {
    $map = [];

    foreach ($row as $index => $value) {
        $label = wms_inventory_normalize_header_label((string)$value);
        if ($label === 'mã sản phẩm') {
            $map['product_id'] = $index;
        } elseif ($label === 'mã thùng') {
            $map['box_code'] = $index;
        } elseif ($label === 'sl đvt chính') {
            $map['quantity'] = $index;
        }
    }

    return isset($map['box_code'], $map['product_id']) ? $map : null;
}

function wms_inventory_parse_quantity($value) {
    $raw = trim((string)$value);
    if ($raw === '') return null;

    $normalized = str_replace(',', '.', preg_replace('/\s+/u', '', $raw) ?? $raw);
    if (!preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
        return null;
    }

    return is_numeric($matches[0]) ? (float)$matches[0] : null;
}

function wms_inventory_build_records(array $rows): array {
    $records = [];
    $errors = [];
    $seen = [];
    $headerMap = null;

    foreach ($rows as $rowIndex => $row) {
        $values = [];
        foreach ($row as $cell) {
            $values[] = trim((string)$cell);
        }

        if (implode('', $values) === '') {
            continue;
        }

        if ($headerMap === null) {
            $detectedMap = wms_inventory_find_header_map($values);
            if ($detectedMap !== null) {
                $headerMap = $detectedMap;
                continue;
            }

            $headerMap = [
                'product_id' => 0,
                'box_code' => 1,
            ];
        }

        $boxCode = strtoupper(trim((string)($values[$headerMap['box_code']] ?? '')));
        $productId = strtoupper(trim((string)($values[$headerMap['product_id']] ?? '')));
        $quantity = 0;
        if (isset($headerMap['quantity'])) {
            $parsedQty = wms_inventory_parse_quantity($values[$headerMap['quantity']] ?? '');
            $quantity = $parsedQty === null ? 0 : $parsedQty;
        }

        if ($boxCode === '' || $productId === '') {
            $errors[] = 'Dòng ' . ($rowIndex + 1) . ' thiếu Mã sản phẩm hoặc Mã thùng hợp lệ';
            continue;
        }

        $uniqueKey = $boxCode . '|' . $productId;
        if (isset($seen[$uniqueKey])) {
            $errors[] = 'Dòng ' . ($rowIndex + 1) . ' trùng cặp Mã thùng + Mã sản phẩm, đã bỏ qua';
            continue;
        }

        $seen[$uniqueKey] = true;
        $records[] = [
            'box_code' => $boxCode,
            'product_id' => $productId,
            'quantity' => $quantity,
        ];
    }

    return [$records, $errors];
}

function export_temp_map_import_fields(array $columns, bool $hasIdColumn) {
    if ($hasIdColumn) {
        return [
            'command'        => strtoupper($columns[1]),
            'case_no'        => strtoupper($columns[2]),
            'transport_type' => strtoupper($columns[3]),
            'for_product'    => $columns[4],
            'product_id'     => strtoupper($columns[5]),
            'total_qty'      => $columns[6],
            'bucket_qty'     => $columns[7],
            'created_at'     => $columns[8],
            'order_code'     => $columns[9],
        ];
    }

    return [
        'command'        => strtoupper($columns[0]),
        'case_no'        => strtoupper($columns[1]),
        'transport_type' => strtoupper($columns[2]),
        'for_product'    => $columns[3],
        'product_id'     => strtoupper($columns[4]),
        'total_qty'      => $columns[5],
        'bucket_qty'     => $columns[6],
        'created_at'     => $columns[7],
        'order_code'     => $columns[8],
    ];
}

function export_temp_import_row_score(array $fields) {
    $totalQty = (int)preg_replace('/[^0-9\-]/', '', (string)$fields['total_qty']);
    $bucketQty = (int)preg_replace('/[^0-9\-]/', '', (string)$fields['bucket_qty']);

    $score = 0;
    if ($fields['command'] !== '') $score += 2;
    if ($fields['case_no'] !== '') $score += 1;
    if ($fields['product_id'] !== '') $score += 2;
    if ($totalQty > 0) $score += 2;
    if ($bucketQty > 0) $score += 2;
    if ((string)$fields['created_at'] !== '') $score += 1;

    return $score;
}

function export_temp_extract_import_fields(array $row, ?bool $hasIdColumn = null) {
    $c = [];
    for ($i = 0; $i < 10; $i++) $c[$i] = trim((string)($row[$i] ?? ''));

    if ($hasIdColumn !== null) {
        return export_temp_map_import_fields($c, $hasIdColumn);
    }

    $withoutId = export_temp_map_import_fields($c, false);
    $withId = export_temp_map_import_fields($c, true);
    $withoutIdScore = export_temp_import_row_score($withoutId);
    $withIdScore = export_temp_import_row_score($withId);

    return $withIdScore > $withoutIdScore ? $withId : $withoutId;
}

function export_temp_build_records(array $rows) {
    $records = []; $errors = [];
    $hasIdColumn = null;
    $headerSkipped = false;

    foreach ($rows as $rowIndex => $row) {
        $c = [];
        for ($i = 0; $i < 9; $i++) $c[$i] = trim((string)($row[$i] ?? ''));
        if (implode('', $c) === '') continue;

        if (!$headerSkipped) {
            $headerSkipped = true;
            $n = array_map(function($v) { return strtolower(trim((string)$v)); }, $row);
            $hasIdColumn = array_slice($n, 0, 1) === ['id'];
            continue;
        }

        if (export_temp_is_header_row($row)) {
            $n = array_map(function($v) { return strtolower(trim((string)$v)); }, $row);
            $hasIdColumn = array_slice($n, 0, 1) === ['id'];
            continue;
        }

        $f = export_temp_extract_import_fields($row, $hasIdColumn);
        $totalQty  = (int)preg_replace('/[^0-9\-]/', '', $f['total_qty']);
        $bucketQty = (int)preg_replace('/[^0-9\-]/', '', $f['bucket_qty']);
        if ($f['command'] === '' || $f['product_id'] === '' || $totalQty <= 0 || $bucketQty <= 0) {
            $errors[] = 'Dòng ' . ($rowIndex + 1) . ' thiếu command/product_id hoặc số lượng không hợp lệ';
            continue;
        }
        $records[] = [
            'command'        => $f['command'],
            'case_no'        => $f['case_no'] !== '' ? $f['case_no'] : '001',
            'transport_type' => $f['transport_type'] !== '' ? $f['transport_type'] : 'SEA',
            'for_product'    => $f['for_product'],
            'product_id'     => $f['product_id'],
            'total_qty'      => $totalQty,
            'bucket_qty'     => $bucketQty,
            'created_at'     => export_temp_normalize_datetime($f['created_at']),
            'order_code'     => strtoupper(trim((string)($f['order_code'] ?? ''))),
        ];
    }
    return [$records, $errors];
}

switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!$username || !$password) { echo json_encode(['success'=>false,'message'=>'Thiếu username hoặc password']); break; }
        $stmt = $pdo->prepare('SELECT id, username, password, full_name, role, status FROM log_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) { echo json_encode(['success'=>false,'message'=>'Người dùng không tồn tại']); break; }

        $ok = false;
        if ($u['password'] === md5($password)) $ok = true;
        if (!$ok && password_verify($password, $u['password'])) $ok = true;

        if (!$ok) { echo json_encode(['success'=>false,'message'=>'Sai mật khẩu']); break; }
        if (!$u['status']) { echo json_encode(['success'=>false,'message'=>'Tài khoản bị khóa']); break; }

        $_SESSION['user'] = ['id'=>$u['id'],'username'=>$u['username'],'full_name'=>$u['full_name'],'role'=>$u['role']];
        $stmt = $pdo->prepare('UPDATE log_users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$u['id']]);
        echo json_encode(['success'=>true,'user'=>$_SESSION['user']]);
        break;

    case 'logout':
        session_unset(); session_destroy();
        echo json_encode(['success'=>true]);
        break;

    case 'get_current_user':
        echo json_encode(['user'=>current_user()]);
        break;

    case 'get_dashboard_stats':
        $stmt = $pdo->query("SELECT 
                            COUNT(*) as total_shelves, 
                            SUM(CASE WHEN current_usage > 0 THEN 1 ELSE 0 END) as shelves_with_stock, 
                            SUM(CASE WHEN current_usage = 0 THEN 1 ELSE 0 END) as empty_shelves 
                            FROM shelves
                            WHERE (status != 'Deactive' OR status IS NULL)");
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    case 'get_shelves':
        $q = $_GET['q'] ?? '';
        $all = isset($_GET['all']) && (string)$_GET['all'] === '1';

        // The query is already calculating sku_count per shelf, which is correct for level 1 and 2 views.
        // No changes needed here as it already provides the necessary data.
        // The client-side JS will handle the grouping and summation for the top-level view.
        $sql = "SELECT s.*, (SELECT COUNT(id) FROM inventory i WHERE i.shelf_id = s.id AND i.quantity > 0) as sku_count FROM shelves s";
        $params = [];
        if ($q) {
            $sql .= " WHERE s.shelf_id LIKE ? OR s.shelf_name LIKE ?";
            $params = ["%$q%", "%$q%"];
        }
        $sql .= " ORDER BY s.shelf_id ASC";
        if (!$all) $sql .= " LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'check_shelf':
        $code = strtoupper($_POST['shelf_id'] ?? '');
        if (strpos($code, 'B032-') === 0) {
            $code = substr($code, 5);
        }
        $stmt = $pdo->prepare("SELECT * FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
        $stmt->execute([$code]);
        $shelf = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => !!$shelf, 'data' => $shelf]);
        break;

    case 'get_products':
        $q = $_GET['q'] ?? '';
        if ($q) {
            $stmt = $pdo->prepare("SELECT product_id, product_name, unit FROM products WHERE product_id LIKE ? OR product_name LIKE ? ORDER BY product_id ASC LIMIT 50");
            $stmt->execute(["%$q%", "%$q%"]);
        } else {
            $stmt = $pdo->query("SELECT product_id, product_name, unit FROM products ORDER BY product_id ASC LIMIT 50");
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_export_items':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        $searchType = strtolower(trim($_POST['search_type'] ?? 'command'));
        $keyword = strtoupper(trim($_POST['keyword'] ?? ($_POST['command'] ?? '')));

        if (!in_array($searchType, ['command', 'product_id'], true)) {
            echo json_encode(['success' => false, 'message' => 'Kiểu tìm kiếm không hợp lệ']);
            break;
        }
        if ($keyword === '') {
            echo json_encode(['success' => false, 'message' => $searchType === 'command' ? 'Chưa chọn mã chỉ thị' : 'Chưa nhập mã linh kiện']);
            break;
        }

        if ($searchType === 'command') {
            $stmt = $pdo->prepare(
                "SELECT MIN(e.id) AS id,
                        e.command,
                        e.product_id,
                        MAX(e.for_product) AS for_product,
                        SUM(e.total_qty) AS total_qty,
                        SUM(e.total_qty) AS bucket_qty,
                        p.product_name,
                        p.unit
                 FROM export_temp e
                 LEFT JOIN products p ON e.product_id = p.product_id
                 WHERE e.command = ?
                 GROUP BY e.command, e.product_id, p.product_name, p.unit
                 ORDER BY e.product_id ASC"
            );
            $stmt->execute([$keyword]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT MIN(e.id) AS id, MAX(e.command) AS command, e.product_id,
                        MAX(e.for_product) AS for_product,
                        SUM(e.total_qty) AS total_qty, SUM(e.total_qty) AS bucket_qty,
                        GROUP_CONCAT(DISTINCT e.command ORDER BY e.command SEPARATOR ', ') AS command_list,
                        p.product_name, p.unit
                 FROM export_temp e
                 LEFT JOIN products p ON e.product_id = p.product_id
                 WHERE e.product_id = ?
                 GROUP BY e.product_id, p.product_name, p.unit"
            );
            $stmt->execute([$keyword]);
        }

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            $item['total_qty']  = (int)$item['total_qty'];
            $item['bucket_qty'] = (int)$item['bucket_qty'];
            $item['num_pages']  = $searchType === 'product_id'
                ? 1
                : max(1, (int)ceil($item['total_qty'] / max(1, $item['bucket_qty'])));
            $item['search_type'] = $searchType;
            $item['is_editable'] = false;
        }

        echo json_encode([
            'success'     => true,
            'search_type' => $searchType,
            'keyword'     => $keyword,
            'items'       => $items,
        ]);
        break;

    case 'get_shelf_inventory':
        require_role(['Admin','Leader','Manager','Staff']);
        $product_id = strtoupper(trim($_POST['product_id'] ?? ''));
        $command = strtoupper(trim($_POST['command'] ?? ''));

        if ($product_id === '') {
            echo json_encode(['success' => false, 'message' => 'Chua chon san pham']);
            break;
        }

        // Lấy danh sách vị trí có tồn kho
        $stmt = $pdo->prepare(
            "SELECT s.shelf_id, COALESCE(s.shelf_name, s.shelf_id) AS shelf_name, i.quantity AS qty
             FROM inventory i
             JOIN products p ON p.id = i.product_id
             JOIN shelves s ON s.id = i.shelf_id
             WHERE p.product_id = ?
               AND i.quantity > 0
               AND (s.status != 'Deactive' OR s.status IS NULL)
             ORDER BY i.quantity ASC, s.shelf_id ASC"
        );
        $stmt->execute([$product_id]);
        $shelves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_stock = 0;
        foreach ($shelves as &$shelf) {
            $shelf['qty'] = (float)$shelf['qty'];
            $total_stock += $shelf['qty'];
        }

        // Nếu có command, tính số lượng còn lại từ export_temp & export_log
        $required_qty = 0;
        $picked_qty = 0;
        if ($command !== '') {
            ensure_export_temp_schema($pdo);
            ensure_export_log_schema($pdo);

            // Lấy số lượng yêu cầu từ export_temp
            $stmt = $pdo->prepare(
                "SELECT SUM(total_qty) AS qty FROM export_temp WHERE command = ? AND product_id = ?"
            );
            $stmt->execute([$command, $product_id]);
            $row = $stmt->fetch();
            $required_qty = (int)($row['qty'] ?? 0);

            // Lấy số lượng đã picking từ export_log
            $stmt = $pdo->prepare(
                "SELECT SUM(quantity) AS qty FROM export_log WHERE command = ? AND product_id = ? AND status = 'picking'"
            );
            $stmt->execute([$command, $product_id]);
            $row = $stmt->fetch();
            $picked_qty = (int)($row['qty'] ?? 0);
        }

        echo json_encode([
            'success'       => true,
            'shelves'       => $shelves,
            'total_stock'   => (float)$total_stock,
            'required_qty'  => $required_qty,
            'picked_qty'    => $picked_qty,
            'remaining_qty' => max(0, $required_qty - $picked_qty),
        ]);
        break;

    case 'get_aux_stock_by_product':
        require_role(['Admin','Leader','Manager','Staff']);
        $product_id = strtoupper(trim($_GET['product_id'] ?? ''));
        if ($product_id === '') {
            echo json_encode(['success' => false, 'message' => 'Chua chon san pham']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT it.pallet_id,
                    SUM(it.qty) AS qty
             FROM import_temp it
             WHERE UPPER(TRIM(it.part_no)) = ?
               AND (it.status IS NULL OR TRIM(it.status) = '')
             GROUP BY it.pallet_id
             HAVING SUM(it.qty) > 0
             ORDER BY SUM(it.qty) DESC, it.pallet_id ASC"
        );
        $stmt->execute([$product_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_aux = 0;
        foreach ($rows as &$row) {
            $row['qty'] = (float)$row['qty'];
            $total_aux += $row['qty'];
        }

        echo json_encode([
            'success' => true,
            'product_id' => $product_id,
            'total_aux' => (float)$total_aux,
            'pallets' => $rows,
        ]);
        break;

    case 'get_import_temp_by_pallet':
        require_role(['Admin','Leader','Manager','Staff']);
        $pallet_id = strtoupper($_GET['pallet_id'] ?? '');
                $stmt = $pdo->prepare("SELECT it.part_no AS product_id,
                                                                            COALESCE(p.product_name, '') AS product_name,
                                                                            SUM(it.qty) AS quantity
                                                             FROM import_temp it
                                                             LEFT JOIN products p ON p.product_id = it.part_no
                                                             WHERE it.pallet_id = ?
                                                                 AND (it.status IS NULL OR it.status = '')
                                                             GROUP BY it.part_no, p.product_name
                                                             HAVING SUM(it.qty) > 0
                                                             ORDER BY it.part_no ASC");
        $stmt->execute([$pallet_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_import_temp_status_by_pallet':
        $pallet_id = strtoupper(trim($_GET['pallet_id'] ?? ''));
        if ($pallet_id === '') {
            echo json_encode([]);
            break;
        }

        $stmt = $pdo->prepare("SELECT it.part_no AS product_id,
                                      COALESCE(p.product_name, '') AS product_name,
                                      SUM(it.qty) AS quantity,
                                      NULLIF(TRIM(it.status), '') AS status
                               FROM import_temp it
                               LEFT JOIN products p ON p.product_id = it.part_no
                               WHERE UPPER(TRIM(it.pallet_id)) = ?
                               GROUP BY it.part_no, p.product_name, NULLIF(TRIM(it.status), '')
                               HAVING SUM(it.qty) > 0
                               ORDER BY it.part_no ASC, status ASC");
        $stmt->execute([$pallet_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_pending_pallets':
        require_role(['Admin','Leader','Manager','Staff']);
        $stmt = $pdo->query("SELECT pallet_id, MAX(created_at) as created_at, MAX(created_by) as created_by, COUNT(*) as sku_count 
                            FROM import_temp 
                            WHERE status IS NULL OR status = ''
                            GROUP BY pallet_id 
                            ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_pallet_transfer_summary':
        require_role(['Admin','Leader','Manager','Staff']);
        $keyword = strtoupper(trim($_GET['keyword'] ?? ''));
        $like = '%' . $keyword . '%';

        $sql = "SELECT
                    COUNT(*) AS total_pallets,
                    SUM(CASE WHEN x.is_transferred = 1 THEN 1 ELSE 0 END) AS transferred_pallets,
                    SUM(CASE WHEN x.is_transferred = 0 THEN 1 ELSE 0 END) AS pending_pallets
                FROM (
                    SELECT
                        pallet_id,
                        CASE WHEN MAX(CASE WHEN status IS NOT NULL AND TRIM(status) <> '' THEN 1 ELSE 0 END) = 1 THEN 1 ELSE 0 END AS is_transferred
                    FROM import_temp
                    WHERE (? = '' OR UPPER(pallet_id) LIKE ?)
                    GROUP BY pallet_id
                ) x";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$keyword, $like]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'total_pallets' => (int)($summary['total_pallets'] ?? 0),
            'transferred_pallets' => (int)($summary['transferred_pallets'] ?? 0),
            'pending_pallets' => (int)($summary['pending_pallets'] ?? 0),
            'keyword' => $keyword,
        ]);
        break;

    case 'mark_pallet_transferred':
        require_role(['Admin','Leader','Manager','Staff']);
        $pallet_id = strtoupper($_POST['pallet_id'] ?? '');
        $shelf_id = strtoupper(trim($_POST['shelf_id'] ?? ''));
        if ($pallet_id === '' || $shelf_id === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu mã pallet hoặc mã kệ']);
            break;
        }

        $stmt = $pdo->prepare("UPDATE import_temp SET status = ? WHERE pallet_id = ? AND (status IS NULL OR status = '')");
        $stmt->execute([$shelf_id, $pallet_id]);
        echo json_encode(['success' => true]);
        break;

    case 'check_pallet_unique':
        require_role(['Admin','Leader','Manager','Staff']);
        $pallet_id = strtoupper($_POST['pallet_id'] ?? '');
        if (empty($pallet_id)) {
            echo json_encode(['success' => false, 'message' => 'Mã pallet không được trống']);
            break;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM import_temp WHERE pallet_id = ?");
        $stmt->execute([$pallet_id]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            echo json_encode(['success' => false, 'message' => 'Mã Pallet này đã tồn tại trong lịch sử nhận hàng!']);
        } else {
            echo json_encode(['success' => true]);
        }
        break;

    case 'import_temp_submit':
        require_role(['Admin','Leader','Manager','Staff']);
        $pallet_id = strtoupper($_POST['pallet_id'] ?? '');
        $part_no = strtoupper($_POST['product_id'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 0);

        if (empty($pallet_id) || empty($part_no) || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            break;
        }

        try {
            // Kiểm tra sản phẩm tồn tại một lần nữa ở server side
            $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
            $stmt->execute([$part_no]);
            if (!$stmt->fetch()) throw new Exception("Sản phẩm $part_no không tồn tại");

            $user = current_user();
            $created_by = $user['username'] ?? 'system';

            $stmt = $pdo->prepare("INSERT INTO import_temp (pallet_id, part_no, qty, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pallet_id, $part_no, $qty, $created_by]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
    
        case 'get_products_on_shelf':
        require_role(['Admin','Leader','Manager','Staff']);
        $shelf_id_code = strtoupper($_GET['shelf_id'] ?? '');
        if (strpos($shelf_id_code, 'B032-') === 0) {
            $shelf_id_code = substr($shelf_id_code, 5);
        }
        if (empty($shelf_id_code)) {
            echo json_encode(['success' => false, 'message' => 'Mã kệ không được trống.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT s.id as shelf_pk, p.product_id, p.product_name, i.quantity 
                              FROM inventory i 
                              JOIN products p ON i.product_id = p.id 
                              JOIN shelves s ON i.shelf_id = s.id 
                              WHERE s.shelf_id = ? AND i.quantity > 0 
                              AND (s.status != 'Deactive' OR s.status IS NULL)");
        $stmt->execute([$shelf_id_code]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'products' => $products]);
        break;

    case 'check_shelf_existence_and_content':
        require_role(['Admin','Leader','Manager','Staff']);
        $shelf_id_code = strtoupper($_GET['shelf_id'] ?? '');
        if (strpos($shelf_id_code, 'B032-') === 0) {
            $shelf_id_code = substr($shelf_id_code, 5);
        }
        if (empty($shelf_id_code)) {
            echo json_encode(['success' => false, 'message' => 'Mã kệ không được trống.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT id, shelf_name FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
        $stmt->execute([$shelf_id_code]);
        $shelf = $stmt->fetch(PDO::FETCH_ASSOC);

        $exists = !!$shelf;
        $has_products = false;
        if ($exists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE shelf_id = ? AND quantity > 0");
            $stmt->execute([$shelf['id']]);
            $has_products = $stmt->fetchColumn() > 0;
        }
        echo json_encode(['success' => true, 'exists' => $exists, 'has_products' => $has_products, 'shelf_name' => $shelf['shelf_name'] ?? null]);
        break;

    case 'transfer_products':
        require_role(['Admin', 'Leader', 'Manager']);
        $current_shelf_id_code = strtoupper($_POST['current_shelf_id'] ?? '');
        $new_shelf_id_code = strtoupper($_POST['new_shelf_id'] ?? '');
        $products_to_transfer = json_decode($_POST['products_to_transfer'] ?? '[]', true); // Array of {product_id, quantity}

        if (empty($current_shelf_id_code) || empty($new_shelf_id_code) || empty($products_to_transfer)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            break;
        }

        try {
            $pdo->beginTransaction();
            $user = current_user();
            $created_by = $user['username'] ?? 'system'; // Use username for transaction logging

            // 1. Get primary keys for shelves and validate
            $stmt = $pdo->prepare("SELECT id, current_usage FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
            $stmt->execute([$current_shelf_id_code]);
            $current_shelf = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current_shelf) throw new Exception("Kệ nguồn ($current_shelf_id_code) không tồn tại hoặc không hoạt động!");
            $current_shelf_pk = $current_shelf['id'];

            $stmt->execute([$new_shelf_id_code]);
            $new_shelf = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$new_shelf) throw new Exception("Kệ đích ($new_shelf_id_code) không tồn tại hoặc không hoạt động!");
            $new_shelf_pk = $new_shelf['id'];

            if ($current_shelf_pk === $new_shelf_pk) {
                throw new Exception("Kệ nguồn và kệ đích không được trùng nhau!");
            }

            // Prepare product data and validate quantities
            $product_pks = [];
            foreach ($products_to_transfer as $item) {
                $product_code = strtoupper($item['product_id']);
                $qty_to_transfer = (int)$item['quantity'];

                if ($qty_to_transfer <= 0) continue;

                // Get product PK
                $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
                $stmt->execute([$product_code]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) throw new Exception("Sản phẩm $product_code không tồn tại trong hệ thống!");
                $product_pks[$product_code] = $product['id'];

                // Get current quantity on source shelf and validate
                $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE shelf_id = ? AND product_id = ?");
                $stmt->execute([$current_shelf_pk, $product_pks[$product_code]]);
                $current_inv = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$current_inv || $current_inv['quantity'] < $qty_to_transfer) {
                    throw new Exception("Số lượng sản phẩm $product_code trên kệ nguồn ($current_shelf_id_code) không đủ để chuyển ($qty_to_transfer yêu cầu, {$current_inv['quantity']} hiện có)!");
                }
            }

            // Perform transfers
            foreach ($products_to_transfer as $item) {
                $product_code = strtoupper($item['product_id']);
                $qty_to_transfer = (int)$item['quantity'];
                $p_pk = $product_pks[$product_code];

                if ($qty_to_transfer <= 0) continue;

                // Decrement quantity on current shelf
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE shelf_id = ? AND product_id = ?");
                $stmt->execute([$qty_to_transfer, $current_shelf_pk, $p_pk]);

                // Update current shelf usage
                $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage - ? WHERE id = ?");
                $stmt->execute([$qty_to_transfer, $current_shelf_pk]);

                // Record OUT transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'OUT', ?, NOW())");
                $stmt->execute([$p_pk, $current_shelf_pk, $qty_to_transfer, $created_by]);

                // Increment/Insert quantity on new shelf
                $stmt = $pdo->prepare("SELECT id FROM inventory WHERE shelf_id = ? AND product_id = ?");
                $stmt->execute([$new_shelf_pk, $p_pk]);
                $inv_on_new_shelf = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($inv_on_new_shelf) {
                    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                    $stmt->execute([$qty_to_transfer, $inv_on_new_shelf['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO inventory (shelf_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$new_shelf_pk, $p_pk, $qty_to_transfer]);
                }

                // Update new shelf usage
                $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage + ? WHERE id = ?");
                $stmt->execute([$qty_to_transfer, $new_shelf_pk]);

                // Record IN transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'IN', ?, NOW())");
                $stmt->execute([$p_pk, $new_shelf_pk, $qty_to_transfer, $created_by]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Điều chuyển sản phẩm thành công!']);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'add_product':
        // Only Admin or Leader can add products
        require_role(['Admin','Leader','Manager']);
        $sku = strtoupper($_POST['product_id'] ?? '');
        $name = !empty($_POST['product_name']) ? $_POST['product_name'] : "Sản phẩm $sku";
        $unit = !empty($_POST['unit']) ? $_POST['unit'] : 'Cái';
        if (empty($sku)) { echo json_encode(['success' => false, 'message' => 'Mã sản phẩm (SKU) không được để trống.']); break; }
        try {
            $stmt = $pdo->prepare("INSERT INTO products (product_id, product_name, unit) VALUES (?, ?, ?)");
            $stmt->execute([$sku, $name, $unit]);
            echo json_encode(['success' => true, 'message' => 'Thêm sản phẩm thành công!']);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                echo json_encode(['success' => false, 'message' => 'Mã sản phẩm đã tồn tại. Vui lòng chọn mã khác.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi thêm sản phẩm: ' . $e->getMessage()]);
            }
        }
        break;

    case 'check_product':
        $sku = strtoupper($_GET['product_id'] ?? '');
        $stmt = $pdo->prepare("SELECT product_id, product_name, unit FROM products WHERE product_id = ?");
        $stmt->execute([$sku]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => !!$product, 'data' => $product]);
        break;

    case 'get_inventory_by_shelf':
        $sid = strtoupper($_GET['shelf_id'] ?? '');
        if (strpos($sid, 'B032-') === 0) {
            $sid = substr($sid, 5);
        }
                $stmt = $pdo->prepare("SELECT p.product_id, p.product_name, i.quantity, 'INVENTORY' AS source, NULL AS pallet_id
                                                            FROM inventory i 
                                                            JOIN products p ON i.product_id = p.id 
                                                            JOIN shelves s ON i.shelf_id = s.id 
                                                            WHERE s.shelf_id = ? AND i.quantity > 0 
                                                            AND (s.status != 'Deactive' OR s.status IS NULL)");
                $stmt->execute([$sid]);
                $invRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("SELECT it.part_no AS product_id,
                                                                            COALESCE(p.product_name, '') AS product_name,
                                                                            SUM(it.qty) AS quantity,
                                                                            'IMPORT_TEMP' AS source,
                                                                            it.pallet_id AS pallet_id
                                                             FROM import_temp it
                                                             LEFT JOIN products p ON p.product_id = it.part_no
                                                             WHERE (it.pallet_id = ? OR CONCAT('TEMP-', it.pallet_id) = ?)
                                                                 AND (it.status IS NULL OR it.status = '')
                                                             GROUP BY it.part_no, p.product_name, it.pallet_id
                                                             HAVING SUM(it.qty) > 0
                                                             ORDER BY it.part_no");
                $stmt->execute([$sid, $sid]);
                $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rows = array_merge($invRows, $tempRows);
                echo json_encode($rows);
        break;

        case 'get_inventory_current_by_shelf':
                $sid = strtoupper($_GET['shelf_id'] ?? '');
                if (strpos($sid, 'B032-') === 0) {
                        $sid = substr($sid, 5);
                }

                $stmt = $pdo->prepare("SELECT p.product_id, p.product_name, SUM(i.quantity) AS quantity, 'INVENTORY' AS source
                                                             FROM inventory i
                                                             JOIN products p ON i.product_id = p.id
                                                             JOIN shelves s ON i.shelf_id = s.id
                                                             WHERE s.shelf_id = ?
                                                                 AND i.quantity > 0
                                                                 AND (s.status != 'Deactive' OR s.status IS NULL)
                                                             GROUP BY p.product_id, p.product_name
                                                             ORDER BY p.product_id");
                $stmt->execute([$sid]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

    case 'inbound_submit':
        require_role(['Admin','Leader','Manager','Staff']);
        $shelf_id = strtoupper($_POST['shelf_id'] ?? '');
        if (strpos($shelf_id, 'B032-') === 0) {
            $shelf_id = substr($shelf_id, 5);
        }
        $product_id = strtoupper($_POST['product_id']);
        $qty = (int)$_POST['quantity'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
            $stmt->execute([$shelf_id]);
            $shelf = $stmt->fetch();
            if (!$shelf) throw new Exception("Kệ $shelf_id không tồn tại!");
            $s_pk = $shelf['id'];

            $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception("Mã sản phẩm $product_id không tồn tại trong hệ thống!");
            $p_pk = $product['id'];

            $stmt = $pdo->prepare("SELECT id FROM inventory WHERE shelf_id = ? AND product_id = ?");
            $stmt->execute([$s_pk, $p_pk]);
            $inv = $stmt->fetch();
            if ($inv) {
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$qty, $inv['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO inventory (shelf_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$s_pk, $p_pk, $qty]);
            }

            $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage + ? WHERE id = ?");
            $stmt->execute([$qty, $s_pk]);

            $user = current_user();
            $created_by = $user['username'] ?? 'system';
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'IN', ?, NOW())");
            $stmt->execute([$p_pk, $s_pk, $qty, $created_by]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'outbound_basic_submit':
        require_role(['Admin','Leader','Manager','Staff']);
        $shelf_id = strtoupper($_POST['shelf_id'] ?? '');
        if (strpos($shelf_id, 'B032-') === 0) {
            $shelf_id = substr($shelf_id, 5);
        }
        $product_id = strtoupper($_POST['product_id'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 0);

        $debug_context = [
            'flow' => 'outbound_basic_submit',
            'request' => [
                'shelf_id' => $shelf_id,
                'product_id' => $product_id,
                'quantity' => $qty,
            ],
            'resolved' => [
                'shelf_pk' => null,
                'product_pk' => null,
            ],
            'inventory_rows' => [],
            'inventory_total_before' => 0,
            'deduct_plan' => [],
            'db_effect' => [
                'updated_rows' => 0,
            ],
        ];

        try {
            $pdo->beginTransaction();

            if ($qty <= 0) {
                throw new Exception('Số lượng xuất không hợp lệ.');
            }

            $stmt = $pdo->prepare("SELECT id FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
            $stmt->execute([$shelf_id]);
            $shelf = $stmt->fetch();
            if (!$shelf) throw new Exception("Kệ $shelf_id không tồn tại!");
            $s_pk = (int)$shelf['id'];
            $debug_context['resolved']['shelf_pk'] = $s_pk;

            $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception("Sản phẩm $product_id không tồn tại trong hệ thống!");
            $p_pk = (int)$product['id'];
            $debug_context['resolved']['product_pk'] = $p_pk;

            // Lock toàn bộ dòng tồn dương của cùng kệ + mã hàng để tránh đọc lệch.
            $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelf_id = ? AND product_id = ? AND quantity > 0 ORDER BY id ASC FOR UPDATE");
            $stmt->execute([$s_pk, $p_pk]);
            $invRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalAvailable = 0;
            foreach ($invRows as $row) {
                $invId = (int)$row['id'];
                $invQty = (int)$row['quantity'];
                $debug_context['inventory_rows'][] = [
                    'inventory_id' => $invId,
                    'quantity' => $invQty,
                ];
                $totalAvailable += $invQty;
            }
            $debug_context['inventory_total_before'] = $totalAvailable;

            if ($totalAvailable < $qty) {
                throw new Exception("Số lượng xuất ($qty) vượt quá tồn kho hiện có trên kệ!");
            }

            $remainingToDeduct = $qty;
            foreach ($invRows as $row) {
                if ($remainingToDeduct <= 0) break;

                $invId = (int)$row['id'];
                $invQty = (int)$row['quantity'];
                $deductQty = min($invQty, $remainingToDeduct);
                if ($deductQty <= 0) continue;

                $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                $stmt->execute([$deductQty, $invId, $deductQty]);

                $updated = (int)$stmt->rowCount();
                $debug_context['deduct_plan'][] = [
                    'inventory_id' => $invId,
                    'before_qty' => $invQty,
                    'deduct_qty' => $deductQty,
                    'update_rowcount' => $updated,
                ];
                $debug_context['db_effect']['updated_rows'] += $updated;

                if ($updated !== 1) {
                    throw new Exception('Không thể cập nhật tồn kho do thay đổi đồng thời. Vui lòng thử lại.');
                }

                $remainingToDeduct -= $deductQty;
            }

            if ($remainingToDeduct > 0) {
                throw new Exception('Không thể trừ đủ số lượng yêu cầu. Vui lòng thử lại.');
            }

            $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage - ? WHERE id = ?");
            $stmt->execute([$qty, $s_pk]);

            $user = current_user();
            $created_by = $user['username'] ?? 'system';
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'OUT', ?, NOW())");
            $stmt->execute([$p_pk, $s_pk, $qty, $created_by]);

            $transactionTime = date('Y-m-d H:i:s');
            $pdo->commit();

            echo json_encode([
                'success' => true,
                'transaction_time' => $transactionTime,
                'shelf_id' => $shelf_id,
                'product_id' => $product_id,
                'quantity' => $qty,
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => $debug_context,
            ]);
        }
        break;

    case 'outbound_submit':
        require_role(['Admin','Leader','Manager','Staff']);
        $shelf_id = strtoupper($_POST['shelf_id'] ?? '');
        if (strpos($shelf_id, 'B032-') === 0) {
            $shelf_id = substr($shelf_id, 5);
        }
        $product_id = strtoupper($_POST['product_id']);
        $qty = (int)$_POST['quantity'];
        $command = strtoupper(trim($_POST['command'] ?? ''));
        $case_no = strtoupper(trim($_POST['case_no'] ?? '001'));
        $is_picking = (int)($_POST['is_picking'] ?? 0);

        // Đảm bảo bảng export_log tồn tại TRƯỚC transaction
        if ($is_picking && $command) {
            ensure_export_log_schema($pdo);
        }

        $debug_context = [
            'flow' => 'outbound_submit',
            'request' => [
                'shelf_id' => $shelf_id,
                'product_id' => $product_id,
                'quantity' => $qty,
                'command' => $command,
                'case_no' => $case_no,
                'is_picking' => $is_picking,
            ],
            'resolved' => [
                'shelf_pk' => null,
                'product_pk' => null,
            ],
            'inventory_before' => [
                'inventory_id' => null,
                'quantity' => null,
            ],
            'db_effect' => [
                'inventory_update_rowcount' => 0,
            ],
        ];

        try {
            $pdo->beginTransaction();
            if ($qty <= 0) {
                throw new Exception('Số lượng xuất không hợp lệ.');
            }

            $stmt = $pdo->prepare("SELECT id FROM shelves WHERE shelf_id = ? AND (status != 'Deactive' OR status IS NULL)");
            $stmt->execute([$shelf_id]);
            $shelf = $stmt->fetch();
            if (!$shelf) throw new Exception("Kệ $shelf_id không tồn tại!");
            $s_pk = $shelf['id'];
            $debug_context['resolved']['shelf_pk'] = (int)$s_pk;

            $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception("Sản phẩm $product_id không tồn tại trong hệ thống!");
            $p_pk = $product['id'];
            $debug_context['resolved']['product_pk'] = (int)$p_pk;

            $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelf_id = ? AND product_id = ? FOR UPDATE");
            $stmt->execute([$s_pk, $p_pk]);
            $inv = $stmt->fetch();
            if ($inv) {
                $debug_context['inventory_before']['inventory_id'] = (int)$inv['id'];
                $debug_context['inventory_before']['quantity'] = (int)$inv['quantity'];
            }

            if (!$inv || $inv['quantity'] < $qty) {
                throw new Exception("Số lượng xuất ($qty) vượt quá tồn kho hiện có trên kệ!");
            }

            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
            $stmt->execute([$qty, $inv['id'], $qty]);
            $debug_context['db_effect']['inventory_update_rowcount'] = (int)$stmt->rowCount();
            if ($stmt->rowCount() !== 1) {
                throw new Exception("Số lượng xuất ($qty) vượt quá tồn kho hiện có trên kệ!");
            }

            $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage - ? WHERE id = ?");
            $stmt->execute([$qty, $s_pk]);

            $user = current_user();
            $created_by = $user['username'] ?? 'system';
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'OUT', ?, NOW())");
            $stmt->execute([$p_pk, $s_pk, $qty, $created_by]);

            if ($is_picking && $command) {
                $stmt = $pdo->prepare("INSERT INTO export_log (command, case_no, product_id, quantity, created_by, status, created_at) VALUES (?, ?, ?, ?, ?, 'picking', NOW())");
                $stmt->execute([$command, $case_no, $product_id, $qty, $created_by]);
            }

            $transactionTime = date('Y-m-d H:i:s');

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'transaction_time' => $transactionTime,
                'shelf_id' => $shelf_id,
                'product_id' => $product_id,
                'quantity' => $qty,
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'debug' => $debug_context,
            ]);
        }
        break;

    case 'search_sku':
        $pid = strtoupper($_GET['product_id'] ?? '');
                $stmt = $pdo->prepare("SELECT p.product_name AS product_name,
                                                                            s.shelf_id,
                                                                            i.quantity,
                                                                            'INVENTORY' AS source,
                                                                            itx.pallet_id AS pallet_id
                                                            FROM inventory i 
                                                            JOIN products p ON i.product_id = p.id 
                                                            JOIN shelves s ON i.shelf_id = s.id
                                                            LEFT JOIN (
                                                                SELECT
                                                                    UPPER(TRIM(part_no)) AS part_no_key,
                                                                    UPPER(TRIM(status)) AS status_key,
                                                                    NULLIF(TRIM(GROUP_CONCAT(DISTINCT pallet_id ORDER BY pallet_id SEPARATOR ', ')), '') AS pallet_id
                                                                FROM import_temp
                                                                WHERE status IS NOT NULL
                                                                    AND TRIM(status) <> ''
                                                                GROUP BY UPPER(TRIM(part_no)), UPPER(TRIM(status))
                                                            ) itx
                                                                ON itx.part_no_key = UPPER(TRIM(p.product_id))
                                                                AND itx.status_key = UPPER(TRIM(s.shelf_id))
                                                            WHERE p.product_id = ? AND i.quantity > 0 
                                                            AND (s.status != 'Deactive' OR s.status IS NULL)
                                                            ORDER BY s.shelf_id ASC");
                $stmt->execute([$pid]);
                $invRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("SELECT COALESCE(p.product_name, '') AS product_name,
                                                                            CONCAT('TEMP-', it.pallet_id) AS shelf_id,
                                                                            SUM(it.qty) AS quantity,
                                                                            'IMPORT_TEMP' AS source,
                                                                            NULL AS pallet_id
                                                             FROM import_temp it
                                                             LEFT JOIN products p ON p.product_id = it.part_no
                                                             WHERE it.part_no = ?
                                                                 AND (it.status IS NULL OR it.status = '')
                                                             GROUP BY p.product_name, it.pallet_id
                                                             HAVING SUM(it.qty) > 0
                                                             ORDER BY it.pallet_id");
                $stmt->execute([$pid]);
                $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $rows = array_merge($invRows, $tempRows);
                echo json_encode($rows);
        break;
    
     case 'update_shelf':
        require_role(['Admin', 'Leader', 'Manager']);
        $id = (int)($_POST['id'] ?? 0);
        $new_shelf_id = strtoupper(trim($_POST['shelf_id'] ?? ''));
        $name = trim($_POST['shelf_name'] ?? '');
        $status = $_POST['status'] ?? 'Active'; // Active hoặc Deactive

        if (!$id || !$new_shelf_id) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            break;
        }
        try {
            // Cập nhật mã kệ và trạng thái (Hỗ trợ yêu cầu đổi mã kệ linh hoạt)
            $stmt = $pdo->prepare("UPDATE shelves SET shelf_id = ?, shelf_name = ?, status = ? WHERE id = ?");
            $stmt->execute([$new_shelf_id, $name, $status, $id]);
            echo json_encode(['success' => true, 'message' => 'Cập nhật mã vị trí thành công!']);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) echo json_encode(['success' => false, 'message' => 'Mã kệ này đã tồn tại!']);
            else echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'inventory_adjustment':
        require_role(['Admin', 'Manager']);
        $shelf_id_code = strtoupper($_POST['shelf_id'] ?? '');
        $product_id_code = strtoupper($_POST['product_id'] ?? '');
        $new_qty = (int)($_POST['quantity'] ?? 0);

        if (empty($shelf_id_code) || empty($product_id_code)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin kệ hoặc sản phẩm.']);
            break;
        }

        try {
            $pdo->beginTransaction();
            
            // Lấy ID thực của kệ và sản phẩm
            $stmt = $pdo->prepare("SELECT id FROM shelves WHERE shelf_id = ?");
            $stmt->execute([$shelf_id_code]);
            $shelf = $stmt->fetch();
            if (!$shelf) throw new Exception("Kệ $shelf_id_code không tồn tại.");

            $stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ?");
            $stmt->execute([$product_id_code]);
            $product = $stmt->fetch();
            if (!$product) throw new Exception("Sản phẩm $product_id_code không tồn tại.");

            $s_pk = $shelf['id'];
            $p_pk = $product['id'];

            // Kiểm tra tồn kho hiện tại để tính toán chênh lệch (diff)
            $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelf_id = ? AND product_id = ?");
            $stmt->execute([$s_pk, $p_pk]);
            $inv = $stmt->fetch();
            $old_qty = $inv ? (int)$inv['quantity'] : 0;
            $diff = $new_qty - $old_qty;

            if ($inv) {
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $inv['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO inventory (shelf_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$s_pk, $p_pk, $new_qty]);
            }

            // Cập nhật current_usage của kệ dựa trên chênh lệch
            $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage + ? WHERE id = ?");
            $stmt->execute([$diff, $s_pk]);

            // Ghi log giao dịch loại ADJ (Adjustment)
            $user = current_user();
            $created_by = $user['username'] ?? 'system';
            $type = $diff > 0 ? 'ADJ_IN' : 'ADJ_OUT';
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$p_pk, $s_pk, abs($diff), $type, $created_by]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Điều chỉnh tồn kho thành công!']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'add_shelf':
        require_role(['Admin','Leader','Manager']);
        $sid = strtoupper($_POST['shelf_id']);
        $name = $_POST['shelf_name'];
        $l0 = $_POST['level0_val'];
        $l1 = $_POST['level1_val'];
        $l2 = $_POST['level2_val'];
        $l3 = $_POST['level3_val'];
        $l4 = $_POST['level4_val'];
        $cap = $_POST['capacity'];
        $stmt = $pdo->prepare("INSERT INTO shelves (shelf_id, shelf_name, level0_val, level1_val, level2_val, level3_val, level4_val, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$sid, $name, $l0, $l1, $l2, $l3, $l4, $cap]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'add_user':
        // Admin and Manager can create users
        require_role(['Admin', 'Manager']);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'Staff';
        if (!$username || !$password) { echo json_encode(['success'=>false,'message'=>'Thiếu thông tin']); break; }
        if (!in_array($role, ['Admin','Leader','Manager','Staff'])) { echo json_encode(['success'=>false,'message'=>'Role không hợp lệ']); break; }
        try {
            $stmt = $pdo->prepare('INSERT INTO log_users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, 1)');
            // Store md5 as requested
            $stmt->execute([$username, md5($password), $full, $role]);
            echo json_encode(['success'=>true]);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) echo json_encode(['success'=>false,'message'=>'Username đã tồn tại']);
            else echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        break;

    case 'update_user':
        require_role(['Admin', 'Manager']);
        $id = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
        if (!$id || !in_array($role, ['Admin','Leader','Manager','Staff'])) {
            echo json_encode(['success'=>false,'message'=>'Dữ liệu không hợp lệ']);
            break;
        }
        $stmt = $pdo->prepare('UPDATE log_users SET role = ?, status = ? WHERE id = ?');
        $stmt->execute([$role, $status, $id]);
        echo json_encode(['success'=>true]);
        break;

    case 'get_users':
        require_role(['Admin', 'Manager']);
        $stmt = $pdo->query('SELECT id, username, full_name, role, status, last_login, created_at FROM log_users ORDER BY id DESC');
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_export_search_suggestions':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        $searchType = strtolower(trim($_GET['search_type'] ?? 'command'));
        $keyword = strtoupper(trim($_GET['keyword'] ?? ''));
        if ($keyword === '') { echo json_encode(['success' => true, 'items' => []]); break; }
        if ($searchType === 'product_id') {
            $stmt = $pdo->prepare(
                "SELECT product_id AS value, SUM(total_qty) AS total_qty,
                        GROUP_CONCAT(DISTINCT command ORDER BY command SEPARATOR ', ') AS command_list,
                        MAX(created_at) AS last_created_at
                 FROM export_temp WHERE product_id LIKE ?
                 GROUP BY product_id ORDER BY MAX(created_at) DESC LIMIT 8"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT command AS value, DATE(MAX(created_at)) AS command_date, COUNT(*) AS row_count
                 FROM export_temp WHERE command LIKE ?
                 GROUP BY command ORDER BY MAX(created_at) DESC LIMIT 8"
            );
        }
        $stmt->execute(["%{$keyword}%"]);
        echo json_encode(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'get_export_invoice_detail':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_GET['command'] ?? ''));
        $caseNo = strtoupper(trim($_GET['case_no'] ?? ''));

        if ($command === '' || $caseNo === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command hoặc case_no']);
            break;
        }

        if (!preg_match('/^[A-Z0-9]{6}$/', $command) || !preg_match('/^[A-Z0-9]{3}$/', $caseNo)) {
            echo json_encode(['success' => false, 'message' => 'Format không hợp lệ: command phải 6 ký tự, case_no phải 3 ký tự']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT e.product_id,
                    MAX(e.for_product) AS for_product,
                    SUM(e.total_qty) AS total_qty
             FROM export_temp e
             WHERE e.command = ? AND e.case_no = ?
             GROUP BY e.product_id
             ORDER BY e.product_id ASC"
        );
        $stmt->execute([$command, $caseNo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            echo json_encode(['success' => false, 'message' => "Không tìm thấy dữ liệu packing cho command=$command, case_no=$caseNo trong export_temp"]);
            break;
        }

        $requiredMap = [];
        $items = [];
        $requiredTotal = 0;

        foreach ($rows as $row) {
            $pid = strtoupper(trim((string)$row['product_id']));
            $qty = (int)$row['total_qty'];
            $requiredMap[$pid] = $qty;
            $requiredTotal += $qty;
            $items[$pid] = [
                'product_id' => $pid,
                'for_product' => $row['for_product'] ?? '',
                'required_qty' => $qty,
                'picked_qty' => 0,
                'packed_qty' => 0,
                'pickup_qty' => 0,
            ];
        }

        // Lấy picking log cho case_no này
        $stmt = $pdo->prepare(
            "SELECT product_id, status, SUM(quantity) AS qty
             FROM export_log
             WHERE command = ? AND case_no = ?
             GROUP BY product_id, status"
        );
        $stmt->execute([$command, $caseNo]);
        $logRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusTotals = ['picking' => 0, 'packing' => 0, 'pickup' => 0];

        foreach ($logRows as $log) {
            $pid = strtoupper(trim((string)$log['product_id']));
            $status = strtolower(trim((string)$log['status']));
            $qty = (int)$log['qty'];

            if (!isset($statusTotals[$status])) {
                continue;
            }

            $statusTotals[$status] += $qty;

            if (!isset($items[$pid])) {
                continue;
            }

            if ($status === 'picking') $items[$pid]['picked_qty'] = $qty;
            if ($status === 'packing') $items[$pid]['packed_qty'] = $qty;
            if ($status === 'pickup') $items[$pid]['pickup_qty'] = $qty;
        }

        // Tính picking: số product_id đã picking đủ trong đúng case_no hiện tại.
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT product_id) AS cnt
             FROM (
                SELECT product_id FROM export_log
                WHERE command = ? AND case_no = ? AND status = 'picking'
                GROUP BY product_id
                HAVING SUM(quantity) >= (
                    SELECT SUM(total_qty) FROM export_temp
                    WHERE command = ? AND case_no = ? AND product_id = export_log.product_id
                )
            ) AS picked"
        );
        $stmt->execute([$command, $caseNo, $command, $caseNo]);
        $pickingDoneRow = $stmt->fetch();
        $pickingDoneProducts = (int)($pickingDoneRow['cnt'] ?? 0);

        // Tính tổng product của đúng case_no hiện tại.
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT product_id) AS cnt
             FROM export_temp
             WHERE command = ? AND case_no = ?"
        );
        $stmt->execute([$command, $caseNo]);
        $caseTotalProductsRow = $stmt->fetch();
        $caseTotalProducts = (int)($caseTotalProductsRow['cnt'] ?? 0);

        // Tính packing: số product_id đã packing đủ cho case_no này
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT product_id) AS cnt
             FROM (
                SELECT product_id FROM export_log
                WHERE command = ? AND case_no = ? AND status = 'packing'
                GROUP BY product_id
                HAVING SUM(quantity) >= (
                    SELECT SUM(total_qty) FROM export_temp
                    WHERE command = ? AND case_no = ? AND product_id = export_log.product_id
                )
            ) AS packed"
        );
        $stmt->execute([$command, $caseNo, $command, $caseNo]);
        $packingDoneRow = $stmt->fetch();
        $packingDoneProducts = (int)($packingDoneRow['cnt'] ?? 0);

        // Tính pickup: kiểm tra case_no này có pickup chưa
        $stmt = $pdo->prepare(
            "SELECT SUM(quantity) AS qty FROM export_log
             WHERE command = ? AND case_no = ? AND status = 'pickup'"
        );
        $stmt->execute([$command, $caseNo]);
        $pickupRow = $stmt->fetch();
        $pickupCaseTotal = (int)($pickupRow['qty'] ?? 0);

        $itemList = array_values($items);
        foreach ($itemList as &$item) {
            $item['remain_qty'] = max(0, (int)$item['required_qty'] - (int)$item['packed_qty']);
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'case_no' => $caseNo,
            'required_total' => $requiredTotal,
            'status_totals' => $statusTotals,
            'is_picked_done' => $requiredTotal > 0 && $statusTotals['picking'] >= $requiredTotal,
            'is_packed_done' => $requiredTotal > 0 && $statusTotals['packing'] >= $requiredTotal,
            'is_pickup_done' => $requiredTotal > 0 && $statusTotals['pickup'] >= $requiredTotal,
            // Thống kê chi tiết cho packing.php (đều trong cùng command + case_no hiện tại)
            'picking_done_products' => $pickingDoneProducts,
            'case_total_products' => $caseTotalProducts,
            'packing_done_products' => $packingDoneProducts,
            'total_products' => count($items),
            'pickup_case_total' => $pickupCaseTotal,
            'items' => $itemList,
        ]);
        break;

    case 'get_pickup_cases_by_command':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_GET['command'] ?? ''));
        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thieu command']);
            break;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM export_temp WHERE command = ?");
        $stmt->execute([$command]);
        $exists = (int)$stmt->fetchColumn() > 0;
        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Command khong ton tai trong export_temp']);
            break;
        }

        $cases = export_command_case_pickup_status($pdo, $command);
        $okCount = 0;
        foreach ($cases as $caseRow) {
            if (!empty($caseRow['is_ok'])) $okCount++;
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'cases' => $cases,
            'total_cases' => count($cases),
            'ok_cases' => $okCount,
            'wait_cases' => max(0, count($cases) - $okCount),
        ]);
        break;

    case 'pickup_submit':
        // New pickup flow: Verify matched pallet QR + shipping mark QR, then record pickup
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        $caseNo = strtoupper(trim($_POST['case_no'] ?? ''));

        if ($command === '' || $caseNo === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command hoặc case_no']);
            break;
        }

        // Verify case_no belongs to command
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT product_id) as distinct_products
             FROM export_temp
             WHERE command = ? AND case_no = ?"
        );
        $stmt->execute([$command, $caseNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['distinct_products'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Kiện không thuộc invoice']);
            break;
        }

        $packingProgress = export_case_packing_progress($pdo, $command, $caseNo);
        if (empty($packingProgress['is_packed_done'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Kiện này chưa packing đủ, không thể pickup',
                'required_total' => (int)$packingProgress['required_total'],
                'packed_total' => (int)$packingProgress['packed_total'],
            ]);
            break;
        }

        try {
            $pdo->beginTransaction();

            $user = current_user();
            $createdBy = $user['username'] ?? 'system';

            // Insert pickup records for all products in this case
            $stmt = $pdo->prepare(
                "INSERT INTO export_log (command, case_no, product_id, quantity, created_by, status, created_at)
                 SELECT DISTINCT e.command, e.case_no, e.product_id, 1, ?, 'pickup', NOW()
                 FROM export_temp e
                 WHERE e.command = ? AND e.case_no = ?
                 ON DUPLICATE KEY UPDATE quantity = quantity + 1, created_at = NOW()"
            );
            $stmt->execute([$createdBy, $command, $caseNo]);
            $insertedRows = (int)$stmt->rowCount();

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'command' => $command,
                'case_no' => $caseNo,
                'inserted_rows' => $insertedRows,
                'message' => "Đã ghi nhận pickup cho kiện $caseNo của invoice $command"
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'pickup_scan_case':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        $caseNo = strtoupper(trim($_POST['case_no'] ?? ''));

        if ($command === '' || $caseNo === '') {
            echo json_encode(['success' => false, 'message' => 'Thieu command hoac case_no']);
            break;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM export_temp WHERE command = ? AND case_no = ?");
        $stmt->execute([$command, $caseNo]);
        $matchedRows = (int)$stmt->fetchColumn();
        if ($matchedRows <= 0) {
            echo json_encode(['success' => false, 'message' => 'Case_no khong thuoc command da quet']);
            break;
        }

        $packingProgress = export_case_packing_progress($pdo, $command, $caseNo);
        if (empty($packingProgress['is_packed_done'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Case_no chua packing du, khong the pickup',
                'required_total' => (int)$packingProgress['required_total'],
                'packed_total' => (int)$packingProgress['packed_total'],
            ]);
            break;
        }

        try {
            $pdo->beginTransaction();

            $user = current_user();
            $createdBy = $user['username'] ?? 'system';

            $stmt = $pdo->prepare(
                "INSERT INTO export_log (command, case_no, product_id, quantity, created_by, status, created_at)
                 SELECT e.command, e.case_no, e.product_id, 1, ?, 'pickup', NOW()
                 FROM export_temp e
                 WHERE e.command = ? AND e.case_no = ?"
            );
            $stmt->execute([$createdBy, $command, $caseNo]);
            $insertedRows = (int)$stmt->rowCount();

            $cases = export_command_case_pickup_status($pdo, $command);
            $okCount = 0;
            $scannedCase = null;
            foreach ($cases as $caseRow) {
                if (!empty($caseRow['is_ok'])) $okCount++;
                if ($caseRow['case_no'] === $caseNo) $scannedCase = $caseRow;
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'command' => $command,
                'case_no' => $caseNo,
                'inserted_rows' => $insertedRows,
                'scanned_case' => $scannedCase,
                'cases' => $cases,
                'total_cases' => count($cases),
                'ok_cases' => $okCount,
                'wait_cases' => max(0, count($cases) - $okCount),
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'export_log_scan':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        $caseNo = strtoupper(trim($_POST['case_no'] ?? ''));
        $productId = strtoupper(trim($_POST['product_id'] ?? ''));
        $qty = (int)($_POST['quantity'] ?? 0);
        $status = strtolower(trim($_POST['status'] ?? 'packing'));

        if ($command === '' || $caseNo === '' || $productId === '' || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu quét không hợp lệ']);
            break;
        }

        if (!in_array($status, ['picking', 'packing', 'pickup'], true)) {
            echo json_encode(['success' => false, 'message' => 'Status không hợp lệ']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT SUM(total_qty) AS required_qty
             FROM export_temp
             WHERE command = ? AND case_no = ? AND product_id = ?"
        );
        $stmt->execute([$command, $caseNo, $productId]);
        $requiredQty = (int)($stmt->fetchColumn() ?: 0);

        if ($requiredQty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Mã hàng không thuộc invoice đã quét']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(quantity), 0) AS logged_qty
             FROM export_log
             WHERE command = ? AND case_no = ? AND product_id = ? AND status = ?"
        );
        $stmt->execute([$command, $caseNo, $productId, $status]);
        $loggedQty = (int)($stmt->fetchColumn() ?: 0);

        if ($loggedQty + $qty > $requiredQty) {
            echo json_encode(['success' => false, 'message' => "Vượt quá số lượng yêu cầu. Hiện tại: $loggedQty, thêm: $qty, yêu cầu: $requiredQty"]);
            break;
        }

        try {
            $pdo->beginTransaction();

            $user = current_user();
            $createdBy = $user['username'] ?? 'system';

            $stmt = $pdo->prepare(
                "INSERT INTO export_log (command, case_no, product_id, quantity, created_by, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$command, $caseNo, $productId, $qty, $createdBy, $status]);

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity), 0)
                 FROM export_log
                 WHERE command = ? AND case_no = ? AND product_id = ? AND status = ?"
            );
            $stmt->execute([$command, $caseNo, $productId, $status]);
            $loggedQtyByStatus = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(total_qty), 0)
                 FROM export_temp
                 WHERE command = ? AND case_no = ?"
            );
            $stmt->execute([$command, $caseNo]);
            $requiredTotal = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT status, COALESCE(SUM(quantity), 0) AS qty
                 FROM export_log
                 WHERE command = ? AND case_no = ?
                 GROUP BY status"
            );
            $stmt->execute([$command, $caseNo]);
            $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $statusTotals = ['picking' => 0, 'packing' => 0, 'pickup' => 0];
            foreach ($statusRows as $row) {
                $s = strtolower(trim((string)$row['status']));
                if (isset($statusTotals[$s])) {
                    $statusTotals[$s] = (int)$row['qty'];
                }
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'command' => $command,
                'case_no' => $caseNo,
                'product_id' => $productId,
                'status' => $status,
                'scan_qty' => $qty,
                'required_qty' => $requiredQty,
                'logged_qty_by_status' => $loggedQtyByStatus,
                'required_total' => $requiredTotal,
                'status_totals' => $statusTotals,
                'is_picked_done' => $requiredTotal > 0 && $statusTotals['picking'] >= $requiredTotal,
                'is_packed_done' => $requiredTotal > 0 && $statusTotals['packing'] >= $requiredTotal,
                'is_pickup_done' => $requiredTotal > 0 && $statusTotals['pickup'] >= $requiredTotal,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'import_export_temp':
        require_role(['Admin']);
        ensure_export_temp_schema($pdo);
        if (!isset($_FILES['excel_file'])) { echo json_encode(['success' => false, 'message' => 'Chưa chọn file import']); break; }
        $file = $_FILES['excel_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { echo json_encode(['success' => false, 'message' => 'Tải file lên thất bại']); break; }
        try {
            $rows = export_temp_parse_spreadsheet_rows($file['tmp_name'], $file['name']);
            [$records, $errors] = export_temp_build_records($rows);
            if (!$records) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy dữ liệu hợp lệ trong file import', 'errors' => $errors]);
                break;
            }
            $clearExisting = ($_POST['clear_existing'] ?? '1') === '1';
            $pdo->beginTransaction();
            if ($clearExisting) $pdo->exec('DELETE FROM export_temp');
            $stmt = $pdo->prepare('INSERT INTO export_temp (command, case_no, transport_type, for_product, product_id, total_qty, bucket_qty, created_at, order_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($records as $r) $stmt->execute([$r['command'], $r['case_no'], $r['transport_type'], $r['for_product'], $r['product_id'], $r['total_qty'], $r['bucket_qty'], $r['created_at'], $r['order_code']]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Import thành công', 'imported_count' => count($records), 'warning_count' => count($errors), 'errors' => $errors]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'import_wms_inventory':
        require_role(['Manager', 'Admin']);
        ensure_wms_inventory_schema($pdo);
        if (!isset($_FILES['excel_file'])) {
            echo json_encode(['success' => false, 'message' => upload_request_too_large_message()]);
            break;
        }
        $file = $_FILES['excel_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode([
                'success' => false,
                'message' => 'Tải file lên thất bại: ' . upload_error_message((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)),
                'upload_error_code' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ]);
            break;
        }

        try {
            $rows = export_temp_parse_spreadsheet_rows($file['tmp_name'], $file['name']);
            [$records, $errors] = wms_inventory_build_records($rows);

            if (!$records) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Không tìm thấy dữ liệu hợp lệ để import wms_inventory',
                    'errors' => $errors,
                ]);
                break;
            }

            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM wms_inventory');

            $stmt = $pdo->prepare(
                'INSERT INTO wms_inventory (box_code, product_id, quantity) VALUES (?, ?, ?)'
            );

            foreach ($records as $record) {
                $stmt->execute([$record['box_code'], $record['product_id'], $record['quantity']]);
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Import wms_inventory thành công',
                'imported_count' => count($records),
                'warning_count' => count($errors),
                'errors' => $errors,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_export_temp_command_case_rows':
        require_role(['Admin']);
        ensure_export_temp_schema($pdo);

        $dateRaw = trim($_GET['date'] ?? '');
        $hasDateFilter = $dateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw);

        if ($hasDateFilter) {
            $stmt = $pdo->prepare(
                "SELECT
                    e.command,
                    e.case_no,
                    MAX(e.transport_type) AS transport_type,
                    MAX(e.for_product) AS for_product,
                    DATE(MIN(e.created_at)) AS export_date,
                    MIN(e.created_at) AS first_created_at,
                    COUNT(*) AS item_lines,
                    COUNT(DISTINCT e.product_id) AS product_count,
                    SUM(e.total_qty) AS total_qty
                 FROM export_temp e
                 WHERE DATE(e.created_at) = ?
                 GROUP BY e.command, e.case_no
                 ORDER BY MIN(e.created_at) DESC, e.command ASC, e.case_no ASC"
            );
            $stmt->execute([$dateRaw]);
        } else {
            $stmt = $pdo->query(
                "SELECT
                    e.command,
                    e.case_no,
                    MAX(e.transport_type) AS transport_type,
                    MAX(e.for_product) AS for_product,
                    DATE(MIN(e.created_at)) AS export_date,
                    MIN(e.created_at) AS first_created_at,
                    COUNT(*) AS item_lines,
                    COUNT(DISTINCT e.product_id) AS product_count,
                    SUM(e.total_qty) AS total_qty
                 FROM export_temp e
                 GROUP BY e.command, e.case_no
                 ORDER BY MIN(e.created_at) DESC, e.command ASC, e.case_no ASC"
            );
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['item_lines'] = (int)($row['item_lines'] ?? 0);
            $row['product_count'] = (int)($row['product_count'] ?? 0);
            $row['total_qty'] = (int)($row['total_qty'] ?? 0);
        }
        unset($row);

        echo json_encode([
            'success' => true,
            'rows' => $rows,
        ]);
        break;

    case 'update_export_temp_case_date':
        require_role(['Admin']);
        ensure_export_temp_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        $caseNo = strtoupper(trim($_POST['case_no'] ?? ''));
        $newDate = trim($_POST['export_date'] ?? '');

        if ($command === '' || $caseNo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu command/case_no hoặc ngày không hợp lệ (YYYY-MM-DD)']);
            break;
        }

        $stmt = $pdo->prepare(
            "UPDATE export_temp
             SET created_at = CONCAT(?, ' ', TIME(created_at))
             WHERE command = ? AND case_no = ?"
        );
        $stmt->execute([$newDate, $command, $caseNo]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy dữ liệu để cập nhật']);
            break;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Đã cập nhật ngày xuất cho cặp command/case_no',
            'updated_rows' => $stmt->rowCount(),
        ]);
        break;

    case 'delete_export_temp_case_rows':
        require_role(['Admin']);
        ensure_export_temp_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        $caseNo = strtoupper(trim($_POST['case_no'] ?? ''));

        if ($command === '' || $caseNo === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command hoặc case_no']);
            break;
        }

        $stmt = $pdo->prepare('DELETE FROM export_temp WHERE command = ? AND case_no = ?');
        $stmt->execute([$command, $caseNo]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy dòng để xóa']);
            break;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Đã xóa dữ liệu theo cặp command/case_no',
            'deleted_rows' => $stmt->rowCount(),
        ]);
        break;

    case 'update_export_item':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        $id       = (int)($_POST['id'] ?? 0);
        $totalQty  = (int)($_POST['total_qty'] ?? 0);
        $bucketQty = (int)($_POST['bucket_qty'] ?? 0);
        if ($id <= 0 || $totalQty <= 0 || $bucketQty <= 0) { echo json_encode(['success' => false, 'message' => 'Dữ liệu cập nhật không hợp lệ']); break; }
        $stmt = $pdo->prepare('UPDATE export_temp SET total_qty = ?, bucket_qty = ? WHERE id = ? LIMIT 1');
        $stmt->execute([$totalQty, $bucketQty, $id]);
        if ($stmt->rowCount() === 0) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy dòng dữ liệu cần cập nhật']); break; }
        echo json_encode(['success' => true, 'message' => 'Đã cập nhật số lượng', 'num_pages' => max(1, (int)ceil($totalQty / max(1, $bucketQty)))]);
        break;

    case 'get_export_cases_for_print':
        require_role(['Staff', 'Leader', 'Manager', 'Admin']);
        ensure_export_temp_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu mã chỉ thị (command)']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT
                e.command,
                e.case_no,
                e.transport_type,
                e.created_at,
                CONCAT('[', e.command, '][', e.case_no, ']') AS command_case_id,
                COUNT(DISTINCT e.product_id) AS distinct_products,
                COUNT(*) AS total_items_in_case,
                GROUP_CONCAT(DISTINCT e.for_product ORDER BY e.for_product SEPARATOR ', ') AS for_product,
                GROUP_CONCAT(DISTINCT e.product_id ORDER BY e.product_id SEPARATOR ', ') AS product_list,
                'Case Picking Ticket' AS product_name,
                'items' AS unit
            FROM export_temp e
            WHERE e.command = ?
                AND COALESCE(TRIM(e.case_no), '') <> ''
            GROUP BY e.command, e.case_no, e.transport_type, e.created_at
            ORDER BY e.case_no ASC"
        );
        $stmt->execute([$command]);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cases as &$case) {
            $case['total_items_in_case'] = (int)$case['total_items_in_case'];
            $case['distinct_products'] = (int)$case['distinct_products'];
            $case['is_editable'] = false;
            $case['num_pages'] = 1;
            $case['bucket_qty'] = $case['total_items_in_case'];
            $case['product_id'] = $case['command_case_id'];
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'cases' => $cases,
        ]);
        break;

    case 'get_monitor_board':
        // Public board for the TV monitor - no login required (Guest role in readme).
        // Picking: count(distinct product_id) mà picking_qty >= required_qty
        // Packing: count(distinct case_no) mà mỗi product_id trong case đó đều đủ packing
        // Pickup: count(distinct case_no) có status='pickup'
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $dateRaw = trim($_GET['date'] ?? '');
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateRaw ?: date('Y-m-d'));
        $date = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

        $stmt = $pdo->prepare(
            "SELECT cmd.command,
                    cmd.total_items,
                    cmd.picking_items,
                    COALESCE(pc.packing_items, 0) AS packing_items,
                    cs.total_cases,
                    cs.transport_type,
                    cs.for_product,
                    cs.export_date,
                    COALESCE(pu.picked_cases, 0) AS picked_cases,
                    pu.last_pickup_at,
                    cmd.first_created_at
             FROM (
                 SELECT r.command,
                        COUNT(DISTINCT r.product_id) AS total_items,
                        COUNT(DISTINCT CASE WHEN COALESCE(l.picking_qty, 0) >= r.required_qty THEN r.product_id ELSE NULL END) AS picking_items,
                        MIN(r.first_created_at) AS first_created_at
                 FROM (
                     SELECT command,
                            product_id,
                            SUM(total_qty) AS required_qty,
                            MIN(created_at) AS first_created_at
                     FROM export_temp
                     WHERE DATE(created_at) = ?
                     GROUP BY command, product_id
                 ) r
                 LEFT JOIN (
                     SELECT command,
                            product_id,
                            SUM(CASE WHEN status = 'picking' THEN quantity ELSE 0 END) AS picking_qty
                     FROM export_log
                     GROUP BY command, product_id
                 ) l ON l.command = r.command AND l.product_id = r.product_id
                 GROUP BY r.command
             ) cmd
             INNER JOIN (
                 SELECT command,
                        COUNT(DISTINCT case_no) AS total_cases,
                        MAX(transport_type) AS transport_type,
                        MAX(for_product) AS for_product,
                        DATE(MIN(created_at)) AS export_date
                 FROM export_temp
                 WHERE DATE(created_at) = ?
                 GROUP BY command
             ) cs ON cs.command = cmd.command
             LEFT JOIN (
                 SELECT done_cases.command,
                        COUNT(*) AS packing_items
                 FROM (
                     SELECT r.command,
                            r.case_no
                     FROM (
                         SELECT command,
                                case_no,
                                SUM(total_qty) AS required_qty
                         FROM export_temp
                         WHERE DATE(created_at) = ?
                         GROUP BY command, case_no
                     ) r
                     LEFT JOIN (
                         SELECT command,
                                case_no,
                                SUM(quantity) AS packing_qty
                         FROM export_log
                         WHERE status = 'packing'
                         GROUP BY command, case_no
                     ) pk ON pk.command = r.command AND pk.case_no = r.case_no
                     GROUP BY r.command, r.case_no
                     HAVING COALESCE(MAX(pk.packing_qty), 0) >= MAX(r.required_qty)
                 ) done_cases
                 GROUP BY done_cases.command
             ) pc ON pc.command = cmd.command
             LEFT JOIN (
                 SELECT command,
                        COUNT(DISTINCT case_no) AS picked_cases,
                        MAX(created_at) AS last_pickup_at
                 FROM export_log
                 WHERE status = 'pickup'
                 GROUP BY command
             ) pu ON pu.command = cmd.command
             ORDER BY cmd.first_created_at ASC, cmd.command ASC"
        );
        $stmt->execute([$date, $date, $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['command'] = strtoupper(trim((string)($row['command'] ?? '')));
            $row['transport_type'] = strtoupper(trim((string)($row['transport_type'] ?? ''))) ?: 'SEA';
            $row['for_product'] = trim((string)($row['for_product'] ?? ''));
            $row['export_date'] = (string)($row['export_date'] ?? $date);
            // Items: picking, packing (now by distinct product_id and case_no)
            $row['total_items'] = (int)($row['total_items'] ?? 0);
            $row['picking_items'] = (int)($row['picking_items'] ?? 0);
            $row['packing_items'] = (int)($row['packing_items'] ?? 0);
            $row['picking_wait_items'] = max(0, $row['total_items'] - $row['picking_items']);
            $row['packing_wait_items'] = max(0, $row['total_cases'] - $row['packing_items']);
            // Cases: pickup
            $row['total_cases'] = (int)($row['total_cases'] ?? 0);
            $row['picked_cases'] = (int)($row['picked_cases'] ?? 0);
            $row['packing_wait_cases'] = max(0, $row['total_cases'] - $row['packing_items']);
            $row['pickup_wait_cases'] = max(0, $row['total_cases'] - $row['picked_cases']);
            $row['pickup_done'] = $row['total_cases'] > 0 && $row['picked_cases'] >= $row['total_cases'];
        }
        unset($row);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'server_time' => date('c'),
            'count' => count($rows),
            'rows' => $rows,
        ]);
        break;

    case 'get_command_flight_board':
        require_role(['Leader','Manager','Admin']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $dateRaw = trim($_GET['date'] ?? '');
        $dateObj = DateTime::createFromFormat('Y-m-d', $dateRaw ?: date('Y-m-d'));
        $date = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

        $stmt = $pdo->prepare(
            "SELECT cmd.command,
                    cmd.total_items,
                    cmd.picking_items,
                    cmd.packing_items,
                    cs.total_cases,
                    COALESCE(cp.picked_cases, 0) AS picked_cases,
                    cp.last_pickup_at,
                    cmd.first_created_at
             FROM (
                 SELECT r.command,
                        SUM(r.required_qty) AS total_items,
                        COALESCE(SUM(l.picking_qty), 0) AS picking_items,
                        COALESCE(SUM(l.packing_qty), 0) AS packing_items,
                        MIN(r.first_created_at) AS first_created_at
                 FROM (
                     SELECT command,
                            product_id,
                            SUM(total_qty) AS required_qty,
                            MIN(created_at) AS first_created_at
                     FROM export_temp
                     WHERE DATE(created_at) = ?
                     GROUP BY command, product_id
                 ) r
                 LEFT JOIN (
                     SELECT command,
                            product_id,
                            SUM(CASE WHEN status = 'picking' THEN quantity ELSE 0 END) AS picking_qty,
                            SUM(CASE WHEN status = 'packing' THEN quantity ELSE 0 END) AS packing_qty
                     FROM export_log
                     GROUP BY command, product_id
                 ) l ON l.command = r.command AND l.product_id = r.product_id
                 GROUP BY r.command
             ) cmd
             INNER JOIN (
                 SELECT command,
                        COUNT(DISTINCT case_no) AS total_cases
                 FROM export_temp
                 WHERE DATE(created_at) = ?
                 GROUP BY command
             ) cs ON cs.command = cmd.command
             LEFT JOIN (
                 SELECT ec.command,
                        SUM(CASE WHEN pl.has_pickup = 1 THEN 1 ELSE 0 END) AS picked_cases,
                        MAX(pl.last_pickup_at) AS last_pickup_at
                 FROM (
                     SELECT DISTINCT command, case_no
                     FROM export_temp
                     WHERE DATE(created_at) = ?
                 ) ec
                 LEFT JOIN (
                     SELECT command,
                            case_no,
                            1 AS has_pickup,
                            MAX(created_at) AS last_pickup_at
                     FROM export_log
                     WHERE status = 'pickup'
                     GROUP BY command, case_no
                 ) pl ON pl.command = ec.command AND pl.case_no = ec.case_no
                 GROUP BY ec.command
             ) cp ON cp.command = cmd.command
             ORDER BY cmd.first_created_at DESC, cmd.command DESC"
        );
        $stmt->execute([$date, $date, $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['command'] = strtoupper(trim((string)($row['command'] ?? '')));
            // Items: picking, packing
            $row['total_items'] = (int)($row['total_items'] ?? 0);
            $row['picking_items'] = (int)($row['picking_items'] ?? 0);
            $row['packing_items'] = (int)($row['packing_items'] ?? 0);
            // Cases: pickup
            $row['total_cases'] = (int)($row['total_cases'] ?? 0);
            $row['picked_cases'] = (int)($row['picked_cases'] ?? 0);
            $row['pickup_done'] = $row['total_cases'] > 0 && $row['picked_cases'] >= $row['total_cases'];
            $row['pickup_wait_cases'] = max(0, $row['total_cases'] - $row['picked_cases']);
        }

        echo json_encode([
            'success' => true,
            'date' => $date,
            'count' => count($rows),
            'rows' => $rows,
        ]);
        break;

    case 'get_invoices_by_date':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);

        $dateRaw = trim($_GET['date'] ?? '');
        if (!$dateRaw || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            $dateRaw = date('Y-m-d');
        }
        $date = $dateRaw;

        $stmt = $pdo->prepare(
            "SELECT e.command,
                    MIN(e.created_at) AS created_at,
                    COUNT(*) AS item_count,
                    COUNT(DISTINCT e.case_no) AS case_count
             FROM export_temp e
             WHERE DATE(e.created_at) = ?
             GROUP BY e.command
             ORDER BY MIN(e.created_at) DESC"
        );
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'date' => $date,
            'invoices' => $rows
        ]);
        break;

    case 'get_invoice_items_by_date':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);

        $command = strtoupper(trim($_GET['command'] ?? ''));
        $dateRaw = trim($_GET['date'] ?? '');
        if (!$dateRaw || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            $dateRaw = date('Y-m-d');
        }
        $date = $dateRaw;

        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT e.product_id,
                    COALESCE(p.product_name, '') AS product_name,
                    COALESCE(p.unit, 'pcs') AS unit,
                    MAX(e.for_product) AS for_product,
                    GROUP_CONCAT(DISTINCT UPPER(TRIM(e.case_no)) ORDER BY UPPER(TRIM(e.case_no)) SEPARATOR ',') AS case_no,
                    SUM(e.total_qty) AS total_qty,
                    MAX(e.created_at) AS created_at
             FROM export_temp e
             LEFT JOIN products p ON e.product_id = p.product_id
             WHERE DATE(e.created_at) = ? AND e.command = ?
             GROUP BY e.product_id
             ORDER BY e.product_id ASC"
        );
        $stmt->execute([$date, $command]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['total_qty'] = (int)$item['total_qty'];
            $item['case_no'] = (string)($item['case_no'] ?? '');
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'date' => $date,
            'items' => $items
        ]);
        break;

    case 'get_cases_by_invoice':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);

        $command = strtoupper(trim($_GET['command'] ?? ''));
        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT DISTINCT e.case_no,
                    MAX(e.for_product) AS for_product,
                    MAX(e.transport_type) AS transport_type,
                    MAX(e.created_at) AS created_at,
                    COUNT(DISTINCT e.product_id) AS item_count
             FROM export_temp e
             WHERE e.command = ?
             GROUP BY e.case_no
             ORDER BY e.case_no ASC"
        );
        $stmt->execute([$command]);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cases as &$case) {
            $case['item_count'] = (int)$case['item_count'];
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'cases' => $cases
        ]);
        break;

    case 'insert_export_log_print_case':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_POST['command'] ?? ''));
        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command']);
            break;
        }

        try {
            $user = current_user();
            $created_by = $user['username'] ?? 'system';

            $stmt = $pdo->prepare(
                "INSERT INTO export_log (command, case_no, product_id, quantity, created_by, status, created_at)
                 VALUES (?, '', '', 0, ?, 'print_case', NOW())"
            );
            $stmt->execute([$command, $created_by]);

            echo json_encode(['success' => true, 'message' => 'Đã ghi nhận in tem packing']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_check_box_tem1_inventory':
        require_role(['Admin','Leader','Manager','Staff']);

        $boxCode = strtoupper(trim((string)($_GET['box_code'] ?? '')));
        if ($boxCode === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu mã thùng Tem 1']);
            break;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT UPPER(TRIM(product_id)) AS product_id,
                        SUM(COALESCE(quantity, 0)) AS quantity
                 FROM wms_inventory
                 WHERE UPPER(TRIM(box_code)) = ?
                 GROUP BY UPPER(TRIM(product_id))
                 ORDER BY product_id ASC"
            );
            $stmt->execute([$boxCode]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy dữ liệu tồn kho cho mã thùng ' . $boxCode]);
                break;
            }

            if (count($rows) > 1) {
                echo json_encode(['success' => false, 'message' => 'Mã thùng ' . $boxCode . ' có nhiều mã hàng trong wms_inventory, không thể đối chiếu tự động']);
                break;
            }

            $row = $rows[0];
            $productId = strtoupper(trim((string)($row['product_id'] ?? '')));
            $quantity = isset($row['quantity']) ? (float)$row['quantity'] : null;

            if ($productId === '' || $quantity === null) {
                echo json_encode(['success' => false, 'message' => 'Dữ liệu mã thùng ' . $boxCode . ' không hợp lệ trong wms_inventory']);
                break;
            }

            echo json_encode([
                'success' => true,
                'box_code' => $boxCode,
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'check_box_log':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_check_log_schema($pdo);

        $tem1Raw = trim((string)($_POST['tem1_raw'] ?? ''));
        $tem2Raw = trim((string)($_POST['tem2_raw'] ?? ''));
        $tem1Product = strtoupper(trim((string)($_POST['tem1_product'] ?? '')));
        $tem2Product = strtoupper(trim((string)($_POST['tem2_product'] ?? '')));
        $tem1Qty = isset($_POST['tem1_qty']) ? (int)$_POST['tem1_qty'] : null;
        $tem2Qty = isset($_POST['tem2_qty']) ? (int)$_POST['tem2_qty'] : null;
        $isProductMatch = (int)($_POST['is_product_match'] ?? 0) === 1 ? 1 : 0;
        $isQtyMatch = (int)($_POST['is_qty_match'] ?? 0) === 1 ? 1 : 0;
        $resultCode = trim((string)($_POST['result_code'] ?? ''));
        $resultMessage = trim((string)($_POST['result_message'] ?? ''));
        $type = trim((string)($_POST['type'] ?? 'check box'));

        if ($tem1Raw === '' || $tem2Raw === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu nội dung tem để lưu log']);
            break;
        }

        if ($type === '') {
            $type = 'check box';
        }

        try {
            $user = current_user();
            $scannedBy = $user['username'] ?? 'system';

            $stmt = $pdo->prepare(
                "INSERT INTO check_log (
                    tem1_raw, tem2_raw,
                    tem1_product, tem1_qty,
                    tem2_product, tem2_qty,
                    is_product_match, is_qty_match,
                    result_code, result_message,
                    type, scanned_by, scanned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->execute([
                $tem1Raw,
                $tem2Raw,
                $tem1Product !== '' ? $tem1Product : null,
                $tem1Qty,
                $tem2Product !== '' ? $tem2Product : null,
                $tem2Qty,
                $isProductMatch,
                $isQtyMatch,
                $resultCode !== '' ? $resultCode : null,
                $resultMessage !== '' ? $resultMessage : null,
                $type,
                $scannedBy
            ]);

            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_incomplete_cases_by_command':
        require_role(['Admin','Leader','Manager','Staff']);
        ensure_export_temp_schema($pdo);
        ensure_export_log_schema($pdo);

        $command = strtoupper(trim($_GET['command'] ?? ''));
        $type = strtolower(trim($_GET['type'] ?? 'all'));

        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Thiếu command']);
            break;
        }

        $data = [];

        // Picking: Sản phẩm chưa picking xong (group by command + product_id chỉ)
        if ($type === 'picking' || $type === 'all') {
            $stmt = $pdo->prepare(
                "SELECT
                    required.product_id,
                    required.order_code,
                    required.required_qty,
                    COALESCE(picked.picked_qty, 0) AS picked_qty,
                    (required.required_qty - COALESCE(picked.picked_qty, 0)) AS remaining_qty
                FROM
                    (
                        SELECT
                            command,
                            product_id,
                            MAX(order_code) AS order_code,
                            SUM(total_qty) as required_qty
                        FROM export_temp
                        WHERE command = ?
                        GROUP BY command, product_id
                    ) AS required
                LEFT JOIN
                    (SELECT command, product_id, SUM(quantity) as picked_qty FROM export_log WHERE command = ? AND status = 'picking' GROUP BY command, product_id) AS picked
                ON required.command = picked.command AND required.product_id = picked.product_id
                WHERE required.required_qty > COALESCE(picked.picked_qty, 0)
                ORDER BY required.product_id"
            );
            $stmt->execute([$command, $command]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as &$item) {
                $item['required_qty'] = (int)$item['required_qty'];
                $item['picked_qty'] = (int)$item['picked_qty'];
                $item['remaining_qty'] = (int)$item['remaining_qty'];
            }
        }

        // Packing: Kiện chưa packing xong
        if ($type === 'packing' || $type === 'all') {
            $stmt = $pdo->prepare(
                "SELECT
                    required.case_no,
                    required.product_id,
                    required.order_code,
                    required.required_qty,
                    COALESCE(packed.packed_qty, 0) AS packed_qty
                FROM
                    (
                        SELECT
                            command,
                            case_no,
                            product_id,
                            MAX(order_code) AS order_code,
                            SUM(total_qty) as required_qty
                        FROM export_temp
                        WHERE command = ?
                        GROUP BY command, case_no, product_id
                    ) AS required
                LEFT JOIN
                    (SELECT command, case_no, product_id, SUM(quantity) as packed_qty FROM export_log WHERE command = ? AND status = 'packing' GROUP BY command, case_no, product_id) AS packed
                ON required.command = packed.command AND required.case_no = packed.case_no AND required.product_id = packed.product_id
                WHERE required.required_qty > COALESCE(packed.packed_qty, 0)
                ORDER BY required.case_no, required.product_id"
            );
            $stmt->execute([$command, $command]);
            $incompleteItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("DEBUG Packing - Command: $command, Items count: " . count($incompleteItems));
            error_log("DEBUG Packing - Raw items: " . json_encode($incompleteItems));

            $result = [];
            foreach ($incompleteItems as $item) {
                $caseNo = $item['case_no'];
                error_log("DEBUG Packing - Processing case_no: '$caseNo', type: " . gettype($caseNo));
                if (!isset($result[$caseNo])) {
                    $result[$caseNo] = [
                        'case_no' => $caseNo,
                        'incomplete_items_count' => 0,
                        'items' => []
                    ];
                }
                $result[$caseNo]['items'][] = [
                    'product_id' => $item['product_id'],
                    'order_code' => $item['order_code'],
                    'required_qty' => (int)$item['required_qty'],
                    'packed_qty' => (int)$item['packed_qty']
                ];
                $result[$caseNo]['incomplete_items_count']++;
            }
            $data = array_values($result);
            error_log("DEBUG Packing - Final data count: " . count($data));
            error_log("DEBUG Packing - Final data: " . json_encode($data));
        }

        // Pickup: Kiện chưa được pickup
        if ($type === 'pickup' || $type === 'all') {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT e.case_no
                 FROM export_temp e
                 LEFT JOIN (
                     SELECT DISTINCT case_no
                     FROM export_log
                     WHERE command = ? AND status = 'pickup'
                 ) p ON e.case_no = p.case_no
                 WHERE e.command = ? AND p.case_no IS NULL
                 ORDER BY e.case_no ASC"
            );
            $stmt->execute([$command, $command]);
            $data = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        echo json_encode([
            'success' => true,
            'command' => $command,
            'type' => $type,
            'data' => $data
        ]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}