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
    if ($value === null || $value === '') return date('Y-m-d H:i:s');
    if (is_numeric($value)) {
        $ts = (int)round(((float)$value - 25569) * 86400);
        if ($ts > 0) return gmdate('Y-m-d H:i:s', $ts);
    }
    $value = trim((string)$value);
    if ($value === '') return date('Y-m-d H:i:s');
    $ts = strtotime($value);
    return $ts === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $ts);
}

function export_temp_is_header_row(array $columns) {
    $n = array_map(function($c) { return strtolower(trim((string)$c)); }, $columns);
    return array_slice($n, 0, 6) === ['command', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at']
        || array_slice($n, 0, 7) === ['id', 'command', 'for_product', 'product_id', 'total_qty', 'bucket_qty', 'created_at'];
}

function export_temp_extract_import_fields(array $row) {
    $c = [];
    for ($i = 0; $i < 7; $i++) $c[$i] = trim((string)($row[$i] ?? ''));
    $hasLegacyId = isset($row[6]) && trim((string)$row[6]) !== '';
    if ($hasLegacyId) return ['command'=>strtoupper($c[1]),'for_product'=>$c[2],'product_id'=>strtoupper($c[3]),'total_qty'=>$c[4],'bucket_qty'=>$c[5],'created_at'=>$c[6]];
    return ['command'=>strtoupper($c[0]),'for_product'=>$c[1],'product_id'=>strtoupper($c[2]),'total_qty'=>$c[3],'bucket_qty'=>$c[4],'created_at'=>$c[5]];
}

function export_temp_build_records(array $rows) {
    $records = []; $errors = [];
    foreach ($rows as $rowIndex => $row) {
        $c = [];
        for ($i = 0; $i < 7; $i++) $c[$i] = trim((string)($row[$i] ?? ''));
        if ($rowIndex === 0 && export_temp_is_header_row($c)) continue;
        if (implode('', $c) === '') continue;
        $f = export_temp_extract_import_fields($row);
        $totalQty  = (int)preg_replace('/[^0-9\-]/', '', $f['total_qty']);
        $bucketQty = (int)preg_replace('/[^0-9\-]/', '', $f['bucket_qty']);
        if ($f['command'] === '' || $f['product_id'] === '' || $totalQty <= 0 || $bucketQty <= 0) {
            $errors[] = 'Dòng ' . ($rowIndex + 1) . ' thiếu command/product_id hoặc số lượng không hợp lệ';
            continue;
        }
        $records[] = [
            'command'    => $f['command'],
            'for_product'=> $f['for_product'],
            'product_id' => $f['product_id'],
            'total_qty'  => $totalQty,
            'bucket_qty' => $bucketQty,
            'created_at' => export_temp_normalize_datetime(is_numeric($f['created_at']) ? (int)round((float)$f['created_at']) : $f['created_at']),
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
        $product_id = trim($_POST['product_id'] ?? '');
        if ($product_id === '') {
            echo json_encode(['success' => false, 'message' => 'Chua chon san pham']);
            break;
        }

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
        $stmt->execute([strtoupper($product_id)]);
        $shelves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_stock = 0;
        foreach ($shelves as &$shelf) {
            $shelf['qty'] = (float)$shelf['qty'];
            $total_stock += $shelf['qty'];
        }

        echo json_encode([
            'success'     => true,
            'shelves'     => $shelves,
            'total_stock' => (float)$total_stock,
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
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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

    case 'import_export_temp':
        require_role(['Admin','Leader','Manager','Staff']);
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
            $stmt = $pdo->prepare('INSERT INTO export_temp (command, for_product, product_id, total_qty, bucket_qty, created_at) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($records as $r) $stmt->execute([$r['command'], $r['for_product'], $r['product_id'], $r['total_qty'], $r['bucket_qty'], $r['created_at']]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Import thành công', 'imported_count' => count($records), 'warning_count' => count($errors), 'errors' => $errors]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
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

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}