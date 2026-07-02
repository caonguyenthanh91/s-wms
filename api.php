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
        $all = isset($_GET['all']);
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
        $command = strtoupper(trim($_POST['command'] ?? ''));
        if ($command === '') {
            echo json_encode(['success' => false, 'message' => 'Chua chon ma chi thi']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT
                e.id,
                e.product_id,
                e.for_product,
                e.total_qty,
                e.bucket_qty,
                p.product_name,
                p.unit
             FROM export_temp e
             LEFT JOIN products p ON e.product_id = p.product_id
             WHERE e.command = ?
             ORDER BY e.product_id"
        );
        $stmt->execute([$command]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['total_qty'] = (int)$item['total_qty'];
            $item['bucket_qty'] = (int)$item['bucket_qty'];
            $item['num_pages'] = max(1, (int)ceil($item['total_qty'] / max(1, $item['bucket_qty'])));
        }

        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'get_shelf_inventory':
        require_role(['Admin','Leader','Manager','Staff']);
        $product_id = trim($_POST['product_id'] ?? '');
        if ($product_id === '') {
            echo json_encode(['success' => false, 'message' => 'Chua chon san pham']);
            break;
        }

        $stmt = $pdo->prepare(
            "SELECT
                t.shelf_id,
                SUM(CASE WHEN t.type = 'IN' THEN t.quantity ELSE -t.quantity END) AS qty,
                COALESCE(s.shelf_name, 'N/A') AS shelf_name
             FROM transactions t
             LEFT JOIN shelves s ON t.shelf_id = s.shelf_id
             WHERE t.product_id = ?
             GROUP BY t.shelf_id, s.shelf_name
             HAVING SUM(CASE WHEN t.type = 'IN' THEN t.quantity ELSE -t.quantity END) > 0
             ORDER BY t.shelf_id"
        );
        $stmt->execute([$product_id]);
        $shelves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_stock = 0;
        foreach ($shelves as &$shelf) {
            $shelf['qty'] = (float)$shelf['qty'];
            $total_stock += $shelf['qty'];
        }

        echo json_encode([
            'success' => true,
            'shelves' => $shelves,
            'total_stock' => (float)$total_stock,
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
        $stmt = $pdo->prepare("SELECT product_id, product_name FROM products WHERE product_id = ?");
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

    case 'outbound_submit':
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
            if (!$product) throw new Exception("Sản phẩm $product_id không tồn tại trong hệ thống!");
            $p_pk = $product['id'];

            $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE shelf_id = ? AND product_id = ?");
            $stmt->execute([$s_pk, $p_pk]);
            $inv = $stmt->fetch();
            if (!$inv || $inv['quantity'] < $qty) {
                throw new Exception("Số lượng xuất ($qty) vượt quá tồn kho hiện có trên kệ!");
            }

            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$qty, $inv['id']]);

            $stmt = $pdo->prepare("UPDATE shelves SET current_usage = current_usage - ? WHERE id = ?");
            $stmt->execute([$qty, $s_pk]);

            $user = current_user();
            $created_by = $user['username'] ?? 'system';
            $stmt = $pdo->prepare("INSERT INTO transactions (product_id, shelf_id, quantity, type, created_by, created_at) VALUES (?, ?, ?, 'OUT', ?, NOW())");
            $stmt->execute([$p_pk, $s_pk, $qty, $created_by]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'search_sku':
        $pid = strtoupper($_GET['product_id'] ?? '');
                $stmt = $pdo->prepare("SELECT p.product_name AS product_name,
                                                                            s.shelf_id,
                                                                            i.quantity,
                                                                            'INVENTORY' AS source,
                                                                            NULL AS pallet_id
                                                            FROM inventory i 
                                                            JOIN products p ON i.product_id = p.id 
                                                            JOIN shelves s ON i.shelf_id = s.id
                                                            WHERE p.product_id = ? AND i.quantity > 0 
                                                            AND (s.status != 'Deactive' OR s.status IS NULL)");
                $stmt->execute([$pid]);
                $invRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("SELECT COALESCE(p.product_name, '') AS product_name,
                                                                            CONCAT('TEMP-', it.pallet_id) AS shelf_id,
                                                                            SUM(it.qty) AS quantity,
                                                                            'IMPORT_TEMP' AS source,
                                                                            it.pallet_id AS pallet_id
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

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}