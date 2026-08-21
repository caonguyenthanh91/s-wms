<?php
// Session should already be started by index.php
require_once __DIR__ . '/../config/session_init.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Manager', 'Admin'], true)) {
	echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Manager trở lên.</div>';
	return;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(300);
ini_set('memory_limit', '512M');

$logDir = __DIR__ . '/../assets/database/';
if (!is_dir($logDir)) {
	@mkdir($logDir, 0777, true);
}
$logFile = $logDir . 'export_debug_' . date('Ymd') . '.log';

function debug_log($message, $level = 'INFO') {
	global $logFile;
	$timestamp = date('Y-m-d H:i:s');
	$logMessage = "[{$timestamp}] [{$level}] {$message}\n";
	@file_put_contents($logFile, $logMessage, FILE_APPEND);
	return $logMessage;
}

debug_log("=== PAGE LOAD ===");
debug_log("POST: " . json_encode($_POST));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action']) && $_POST['export_action'] === 'export_xlsx') {
	debug_log("✓ EXPORT REQUEST DETECTED");
	
	try {
		debug_log("Step 1: Including dependencies");
		require_once __DIR__ . '/../config/db.php';
		require_once __DIR__ . '/../assets/vendor/autoload.php';
		debug_log("✓ Dependencies loaded");

		debug_log("Step 2: Query database");
		$stmt = $pdo->query(
			"SELECT
				CONCAT('B032-', UPPER(TRIM(s.shelf_id))) AS shelf_full,
				UPPER(TRIM(p.product_id)) AS product_full,
				SUM(i.quantity) AS current_stock
			 FROM inventory i
			 INNER JOIN shelves s ON s.id = i.shelf_id
			 INNER JOIN products p ON p.id = i.product_id
			 WHERE i.quantity > 0
			 GROUP BY i.shelf_id, i.product_id
			 ORDER BY s.shelf_id ASC, p.product_id ASC"
		);
		debug_log("✓ Database query executed");

		debug_log("Step 3: Create spreadsheet");
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Inventory Snapshot');
		debug_log("✓ Spreadsheet created");

		debug_log("Step 4: Add headers");
		$headers = ['Mã vị trí full', 'Mã hàng full', 'Tồn kho hiện tại'];
		$sheet->fromArray($headers, null, 'A1');

		debug_log("Step 5: Process data");
		$dataRows = [];
		$rowCount = 0;
		foreach ($stmt as $row) {
			$dataRows[] = [
				$row['shelf_full'],
				$row['product_full'],
				(int) $row['current_stock']
			];
			$rowCount++;
		}
		debug_log("✓ Processed {$rowCount} rows");

		if (count($dataRows) > 0) {
			$sheet->fromArray($dataRows, null, 'A2');
		}

		$sheet->getStyle('A1:C1')->getFont()->setBold(true);
		$sheet->getColumnDimension('A')->setWidth(24);
		$sheet->getColumnDimension('B')->setWidth(24);
		$sheet->getColumnDimension('C')->setWidth(18);

		debug_log("Step 6: Save file");
		$exportTimestamp = date('Ymd_His');
		$filename = 'inventory_' . $exportTimestamp . '.xlsx';
		$exportDir = __DIR__ . '/../assets/database/';

		if (!file_exists($exportDir)) {
			if (!@mkdir($exportDir, 0777, true)) {
				throw new Exception("Cannot create directory: {$exportDir}");
			}
		}

		if (!is_writable($exportDir)) {
			$perms = substr(sprintf('%o', fileperms($exportDir)), -4);
			throw new Exception("Directory not writable (Perms: {$perms}): {$exportDir}");
		}

		$filepath = $exportDir . $filename;
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save($filepath);
		debug_log("✓ File saved: {$filepath}");

		if (!file_exists($filepath)) {
			throw new Exception("File not created: {$filepath}");
		}

		$filesize = filesize($filepath);
		debug_log("✓ File size: " . number_format($filesize) . " bytes");

		if ($filesize === 0) {
			throw new Exception("File is empty!");
		}

		$_SESSION['export_success'] = true;
		$_SESSION['export_filename'] = $filename;
		debug_log("✓ EXPORT SUCCESS");
		
		// Clear output buffer and redirect
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;

	} catch (Exception $e) {
		debug_log("✗ EXCEPTION: " . $e->getMessage(), 'ERROR');
		debug_log("File: " . $e->getFile() . " Line: " . $e->getLine(), 'ERROR');
		$_SESSION['export_error'] = "Lỗi xuất file: " . htmlspecialchars($e->getMessage());
		$_SESSION['export_error_time'] = date('Y-m-d H:i:s');
		
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;

	} catch (Throwable $e) {
		debug_log("✗ FATAL: " . $e->getMessage(), 'FATAL');
		$_SESSION['export_error'] = "Lỗi nghiêm trọng: " . htmlspecialchars($e->getMessage());
		$_SESSION['export_error_time'] = date('Y-m-d H:i:s');
		
		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;
	}
}
?>

<?php
// Session should already be started by index.php
require_once __DIR__ . '/../config/session_init.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Manager', 'Admin'], true)) {
	echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Manager trở lên.</div>';
	return;
}

// Get list of exported files
$exportDir = __DIR__ . '/../assets/database/';
$exportedFiles = [];
if (is_dir($exportDir)) {
	$files = array_filter(
		scandir($exportDir, SCANDIR_SORT_DESCENDING),
		fn($f) => strpos($f, 'inventory_') === 0 && pathinfo($f, PATHINFO_EXTENSION) === 'xlsx'
	);
	
	foreach ($files as $file) {
		$filepath = $exportDir . $file;
		// Check if file still exists and is readable
		if (file_exists($filepath) && is_readable($filepath)) {
			$exportedFiles[] = [
				'name' => $file,
				'size' => filesize($filepath),
				'date' => filemtime($filepath),
				'path' => 'assets/database/' . $file
			];
		}
	}
}

// Display success/error messages
$success_msg = $_SESSION['export_success'] ?? false;
$error_msg = $_SESSION['export_error'] ?? false;
$error_time = $_SESSION['export_error_time'] ?? false;
$export_filename = $_SESSION['export_filename'] ?? false;

// Clear session messages after displaying
unset($_SESSION['export_success']);
unset($_SESSION['export_error']);
unset($_SESSION['export_error_time']);
unset($_SESSION['export_filename']);
?>

<div class="max-w-6xl mx-auto space-y-6">
	<?php if ($success_msg && $export_filename): ?>
	<div class="bg-green-50 border border-green-200 rounded-lg p-4">
		<h4 class="text-green-800 font-bold mb-2">✓ Xuất file thành công!</h4>
		<p class="text-green-700">File <strong><?php echo htmlspecialchars($export_filename); ?></strong> đã được lưu và sẵn sàng tải xuống.</p>
	</div>
	<?php endif; ?>

	<?php if ($error_msg): ?>
	<div class="bg-red-50 border border-red-200 rounded-lg p-4">
		<h4 class="text-red-800 font-bold mb-2">✗ Lỗi xuất file!</h4>
		<p class="text-red-700 mb-3"><?php echo $error_msg; ?></p>
		<?php if ($error_time): ?>
		<p class="text-red-600 text-xs mb-2">Lỗi xảy ra: <?php echo $error_time; ?></p>
		<?php endif; ?>
		<p class="text-red-600 text-sm">
			<a href="?page=system_check" class="underline font-bold hover:text-red-900">→ Kiểm tra Quyền Hệ Thống</a> 
			(chi tiết error log)
		</p>
	</div>
	<?php endif; ?>

	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-xl font-bold text-gray-800 mb-2">Xuất dữ liệu tồn kho (.xlsx)</h3>
		<p class="text-sm text-gray-600 mb-4">
			Xuất snapshot tồn kho tại thời điểm thao tác. Hệ thống chỉ truy vấn database khi nhấn nút Xuất file XLSX.
		</p>
		<form method="POST" class="inline-block">
			<button type="submit" name="export_action" value="export_xlsx" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-md font-bold hover:bg-emerald-700 transition">
				📥 Xuất file XLSX
			</button>
		</form>
	</div>

	<?php if (!empty($exportedFiles)): ?>
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-xl font-bold text-gray-800 mb-4">📁 Các file đã xuất (<?php echo count($exportedFiles); ?>)</h3>
		<div class="overflow-x-auto">
			<table class="w-full text-sm">
				<thead class="bg-gray-100 border-b">
					<tr>
						<th class="text-left p-3">Tên file</th>
						<th class="text-center p-3">Dung lượng</th>
						<th class="text-center p-3">Ngày xuất</th>
						<th class="text-center p-3">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($exportedFiles as $file): ?>
					<tr class="border-b hover:bg-gray-50">
						<td class="p-3"><code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($file['name']); ?></code></td>
						<td class="text-center p-3"><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
						<td class="text-center p-3" title="<?php echo date('d/m/Y H:i:s', $file['date']); ?>">
							<?php echo date('d/m/Y', $file['date']); ?>
						</td>
						<td class="text-center p-3">
							<a href="<?php echo htmlspecialchars($file['path']); ?>" download class="inline-flex items-center gap-1 bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 transition">
								📥 Tải xuống
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 text-sm">
		<strong>ℹ️ Ghi chú:</strong> Trang này không tải dữ liệu tồn kho khi mở. Dữ liệu chỉ được lấy khi bạn bấm nút Xuất file XLSX. Các file đã xuất sẽ được lưu trữ và hiển thị ở phía dưới để người dùng khác có thể tải xuống mà không cần xuất lại.
	</div>
</div>

<?php if ($success_msg): ?>
<script>
	// Auto reload page after 2 seconds to show the new file in the list
	setTimeout(function() {
		window.location.href = '?page=data_export';
	}, 2000);
</script>
<?php endif; ?>
