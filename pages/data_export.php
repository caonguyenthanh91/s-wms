<?php
// Session đã được khởi tạo tập trung bởi config/session_init.php (require trong index.php)
// trước khi include trang này -> không tự gọi session_start() ở đây để không phá vỡ
// cấu hình cookie/rolling-timeout/remember-me dùng chung cho toàn hệ thống.
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

// ============================================================
// Export: Tồn kho hiện tại (xuất snapshot)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action']) && $_POST['export_action'] === 'export_xlsx') {
	debug_log("✓ EXPORT REQUEST DETECTED (inventory)");

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

// ============================================================
// Export: Lịch sử nhập xuất kho (từ bảng transactions)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action']) && $_POST['export_action'] === 'export_history_xlsx') {
	debug_log("✓ EXPORT REQUEST DETECTED (history)");

	try {
		require_once __DIR__ . '/../config/db.php';
		require_once __DIR__ . '/../assets/vendor/autoload.php';

		$historyDate = trim($_POST['history_date'] ?? '');
		$historyType = trim($_POST['history_type'] ?? '');

		if ($historyDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyDate)) {
			throw new Exception("Vui lòng chọn ngày hợp lệ.");
		}
		if (!in_array($historyType, ['IN', 'OUT'], true)) {
			throw new Exception("Vui lòng chọn loại giao dịch IN hoặc OUT.");
		}

		debug_log("History export params: date={$historyDate} type={$historyType}");

		$stmt = $pdo->prepare(
			"SELECT
				CONCAT('B032-', UPPER(TRIM(s.shelf_id))) AS shelf_full,
				UPPER(TRIM(p.product_id)) AS product_full,
				t.quantity AS quantity,
				t.type AS trans_type,
				t.created_by AS created_by,
				t.created_at AS created_at
			 FROM transactions t
			 INNER JOIN shelves s ON s.id = t.shelf_id
			 INNER JOIN products p ON p.id = t.product_id
			 WHERE t.type = ? AND DATE(t.created_at) = ?
			 ORDER BY t.created_at ASC"
		);
		$stmt->execute([$historyType, $historyDate]);

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Transaction History');

		$headers = ['Mã vị trí full', 'Mã hàng full', 'Số lượng', 'Loại', 'Người thực hiện', 'Thời điểm'];
		$sheet->fromArray($headers, null, 'A1');

		$dataRows = [];
		$rowCount = 0;
		foreach ($stmt as $row) {
			$dataRows[] = [
				$row['shelf_full'],
				$row['product_full'],
				(int) $row['quantity'],
				$row['trans_type'],
				$row['created_by'],
				$row['created_at']
			];
			$rowCount++;
		}
		debug_log("✓ Processed {$rowCount} history rows");

		if (count($dataRows) > 0) {
			$sheet->fromArray($dataRows, null, 'A2');
		}

		$sheet->getStyle('A1:F1')->getFont()->setBold(true);
		$sheet->getColumnDimension('A')->setWidth(24);
		$sheet->getColumnDimension('B')->setWidth(24);
		$sheet->getColumnDimension('C')->setWidth(12);
		$sheet->getColumnDimension('D')->setWidth(10);
		$sheet->getColumnDimension('E')->setWidth(20);
		$sheet->getColumnDimension('F')->setWidth(20);

		$exportTime = date('His');
		$filename = 'history_' . $historyType . '_' . str_replace('-', '', $historyDate) . '_' . $exportTime . '.xlsx';
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
		debug_log("✓ History file saved: {$filepath}");

		if (!file_exists($filepath)) {
			throw new Exception("File not created: {$filepath}");
		}

		$filesize = filesize($filepath);
		if ($filesize === 0) {
			throw new Exception("File is empty!");
		}

		$_SESSION['export_history_success'] = true;
		$_SESSION['export_history_filename'] = $filename;
		debug_log("✓ HISTORY EXPORT SUCCESS");

		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;

	} catch (Exception $e) {
		debug_log("✗ EXCEPTION: " . $e->getMessage(), 'ERROR');
		$_SESSION['export_history_error'] = "Lỗi xuất lịch sử: " . htmlspecialchars($e->getMessage());
		$_SESSION['export_history_error_time'] = date('Y-m-d H:i:s');

		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;

	} catch (Throwable $e) {
		debug_log("✗ FATAL: " . $e->getMessage(), 'FATAL');
		$_SESSION['export_history_error'] = "Lỗi nghiêm trọng: " . htmlspecialchars($e->getMessage());
		$_SESSION['export_history_error_time'] = date('Y-m-d H:i:s');

		if (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ?page=data_export', true, 302);
		exit;
	}
}

// ============================================================
// Xóa 1 file đã xuất (chỉ Admin) - kiểm tra quyền cả server-side, không chỉ ẩn nút
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action']) && $_POST['export_action'] === 'delete_export_file') {
	debug_log("✓ DELETE FILE REQUEST DETECTED");

	$backInvPage = max(1, (int) ($_POST['inv_page'] ?? 1));
	$backHistPage = max(1, (int) ($_POST['hist_page'] ?? 1));
	$redirectUrl = '?page=data_export&inv_page=' . $backInvPage . '&hist_page=' . $backHistPage;

	if ($role !== 'Admin') {
		debug_log("✗ DELETE DENIED: role={$role}", 'WARN');
		$_SESSION['export_delete_error'] = 'Bạn không có quyền xóa file (cần role Admin).';
		if (ob_get_level() > 0) ob_end_clean();
		header('Location: ' . $redirectUrl, true, 302);
		exit;
	}

	$fileType = trim((string) ($_POST['file_type'] ?? ''));
	$filenameRaw = trim((string) ($_POST['filename'] ?? ''));
	$filename = basename($filenameRaw);
	$expectedPrefix = ($fileType === 'history') ? 'history_' : 'inventory_';

	$isValidName = $filename !== ''
		&& $filename === $filenameRaw
		&& strpos($filename, $expectedPrefix) === 0
		&& pathinfo($filename, PATHINFO_EXTENSION) === 'xlsx';

	$exportDir = __DIR__ . '/../assets/database/';
	$filepath = $exportDir . $filename;

	if (!$isValidName) {
		debug_log("✗ DELETE REJECTED: invalid filename '{$filenameRaw}'", 'WARN');
		$_SESSION['export_delete_error'] = 'Tên file không hợp lệ.';
	} elseif (!file_exists($filepath)) {
		debug_log("✗ DELETE FAILED: not found {$filepath}", 'WARN');
		$_SESSION['export_delete_error'] = 'File không tồn tại hoặc đã bị xóa trước đó.';
	} elseif (!@unlink($filepath)) {
		debug_log("✗ DELETE FAILED: unlink error {$filepath}", 'ERROR');
		$_SESSION['export_delete_error'] = 'Không thể xóa file (kiểm tra quyền ghi thư mục).';
	} else {
		$deletedBy = $user['username'] ?? 'unknown';
		debug_log("✓ DELETED FILE by {$deletedBy}: {$filename}");
		$_SESSION['export_delete_success'] = 'Đã xóa file ' . $filename . '.';
	}

	if (ob_get_level() > 0) ob_end_clean();
	header('Location: ' . $redirectUrl, true, 302);
	exit;
}
?>

<?php
// $user/$role đã được xác thực ở khối role-check phía trên (đầu file) - nếu không đủ quyền
// thì `return;` đã dừng include tại đó rồi nên không cần kiểm tra lại lần 2 ở đây.
$isAdmin = ($role === 'Admin');
$filesPerPage = 10;

// Get list of exported inventory files
$exportDir = __DIR__ . '/../assets/database/';
$exportedFiles = [];
if (is_dir($exportDir)) {
	$files = array_filter(
		scandir($exportDir, SCANDIR_SORT_DESCENDING),
		fn($f) => strpos($f, 'inventory_') === 0 && pathinfo($f, PATHINFO_EXTENSION) === 'xlsx'
	);

	foreach ($files as $file) {
		$filepath = $exportDir . $file;
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

// Get list of exported history files
$exportedHistoryFiles = [];
if (is_dir($exportDir)) {
	$historyFilesRaw = array_filter(
		scandir($exportDir, SCANDIR_SORT_DESCENDING),
		fn($f) => strpos($f, 'history_') === 0 && pathinfo($f, PATHINFO_EXTENSION) === 'xlsx'
	);

	foreach ($historyFilesRaw as $file) {
		$filepath = $exportDir . $file;
		if (file_exists($filepath) && is_readable($filepath)) {
			$exportedHistoryFiles[] = [
				'name' => $file,
				'size' => filesize($filepath),
				'date' => filemtime($filepath),
				'path' => 'assets/database/' . $file
			];
		}
	}
}

// Display success/error messages (inventory export)
$success_msg = $_SESSION['export_success'] ?? false;
$error_msg = $_SESSION['export_error'] ?? false;
$error_time = $_SESSION['export_error_time'] ?? false;
$export_filename = $_SESSION['export_filename'] ?? false;

unset($_SESSION['export_success']);
unset($_SESSION['export_error']);
unset($_SESSION['export_error_time']);
unset($_SESSION['export_filename']);

// Display success/error messages (history export)
$success_history_msg = $_SESSION['export_history_success'] ?? false;
$error_history_msg = $_SESSION['export_history_error'] ?? false;
$error_history_time = $_SESSION['export_history_error_time'] ?? false;
$export_history_filename = $_SESSION['export_history_filename'] ?? false;

unset($_SESSION['export_history_success']);
unset($_SESSION['export_history_error']);
unset($_SESSION['export_history_error_time']);
unset($_SESSION['export_history_filename']);

// Thông báo xóa file (dùng chung cho cả 2 danh sách)
$delete_success_msg = $_SESSION['export_delete_success'] ?? false;
$delete_error_msg = $_SESSION['export_delete_error'] ?? false;
unset($_SESSION['export_delete_success']);
unset($_SESSION['export_delete_error']);

// Phân trang danh sách file (10 file / trang), giữ nguyên trang qua query string
$invPage = max(1, (int) ($_GET['inv_page'] ?? 1));
$invTotal = count($exportedFiles);
$invTotalPages = max(1, (int) ceil($invTotal / $filesPerPage));
$invPage = min($invPage, $invTotalPages);
$exportedFilesPage = array_slice($exportedFiles, ($invPage - 1) * $filesPerPage, $filesPerPage);

$histPage = max(1, (int) ($_GET['hist_page'] ?? 1));
$histTotal = count($exportedHistoryFiles);
$histTotalPages = max(1, (int) ceil($histTotal / $filesPerPage));
$histPage = min($histPage, $histTotalPages);
$exportedHistoryFilesPage = array_slice($exportedHistoryFiles, ($histPage - 1) * $filesPerPage, $filesPerPage);

function de_page_url($invPage, $histPage) {
	return '?page=data_export&inv_page=' . (int) $invPage . '&hist_page=' . (int) $histPage;
}

$today = date('Y-m-d');
?>

<div class="max-w-6xl mx-auto space-y-6">
	<?php if ($success_msg && $export_filename): ?>
	<div class="bg-green-50 border border-green-200 rounded-lg p-4">
		<h4 class="text-green-800 font-bold mb-2">✓ Xuất file tồn kho thành công!</h4>
		<p class="text-green-700">File <strong><?php echo htmlspecialchars($export_filename); ?></strong> đã được lưu và sẵn sàng tải xuống.</p>
	</div>
	<?php endif; ?>

	<?php if ($error_msg): ?>
	<div class="bg-red-50 border border-red-200 rounded-lg p-4">
		<h4 class="text-red-800 font-bold mb-2">✗ Lỗi xuất file tồn kho!</h4>
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

	<?php if ($success_history_msg && $export_history_filename): ?>
	<div class="bg-green-50 border border-green-200 rounded-lg p-4">
		<h4 class="text-green-800 font-bold mb-2">✓ Xuất file lịch sử thành công!</h4>
		<p class="text-green-700">File <strong><?php echo htmlspecialchars($export_history_filename); ?></strong> đã được lưu và sẵn sàng tải xuống.</p>
	</div>
	<?php endif; ?>

	<?php if ($error_history_msg): ?>
	<div class="bg-red-50 border border-red-200 rounded-lg p-4">
		<h4 class="text-red-800 font-bold mb-2">✗ Lỗi xuất file lịch sử!</h4>
		<p class="text-red-700 mb-3"><?php echo $error_history_msg; ?></p>
		<?php if ($error_history_time): ?>
		<p class="text-red-600 text-xs mb-2">Lỗi xảy ra: <?php echo $error_history_time; ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ($delete_success_msg): ?>
	<div class="bg-green-50 border border-green-200 rounded-lg p-4">
		<h4 class="text-green-800 font-bold mb-1">✓ Đã xóa file</h4>
		<p class="text-green-700 text-sm"><?php echo htmlspecialchars($delete_success_msg); ?></p>
	</div>
	<?php endif; ?>

	<?php if ($delete_error_msg): ?>
	<div class="bg-red-50 border border-red-200 rounded-lg p-4">
		<h4 class="text-red-800 font-bold mb-1">✗ Không xóa được file</h4>
		<p class="text-red-700 text-sm"><?php echo htmlspecialchars($delete_error_msg); ?></p>
	</div>
	<?php endif; ?>

	<!-- Layout 50:50 -->
	<div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
		<!-- Bên trái: Xuất tồn kho hiện tại -->
		<div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
			<h3 class="text-xl font-bold text-gray-800 mb-2">Xuất dữ liệu tồn kho (.xlsx)</h3>
			<!-- <p class="text-sm text-gray-600 mb-4">
				Xuất snapshot tồn kho tại thời điểm thao tác. Hệ thống chỉ truy vấn database khi nhấn nút Xuất file XLSX.
			</p> -->
			<form method="POST" class="mb-4">
				<button type="submit" name="export_action" value="export_xlsx" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-md font-bold hover:bg-emerald-700 transition h-[42px]">
					📥 Xuất file XLSX
				</button>
			</form>
			<p>
				<br>
				<br>
			</p>

			<?php if (!empty($exportedFiles)): ?>
			<div class="flex-1">
				<h4 class="text-sm font-bold text-gray-700 mb-2">📁 Các file đã xuất (<?php echo count($exportedFiles); ?>)</h4>
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-gray-100 border-b">
							<tr>
								<th class="text-left p-2">Tên file</th>
								<!-- <th class="text-center p-2">Dung lượng</th>
								<th class="text-center p-2">Ngày xuất</th> -->
								<th class="text-center p-2">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($exportedFilesPage as $file): ?>
							<tr class="border-b hover:bg-gray-50">
								<td class="p-2">
									<code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($file['name']); ?></code>
									<p class="text-begin p-2">
										<?php echo date('d/m/Y', $file['date']); ?>  ||
										<?php echo number_format($file['size'] / 1024, 2); ?> KB
									</p>
								</td>

								<td class="text-center p-2">
									<div class="inline-flex items-center gap-1">
										<a href="<?php echo htmlspecialchars($file['path']); ?>" download class="inline-flex items-center gap-1 bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 transition">
											📥 Tải
										</a>
										<?php if ($isAdmin): ?>
										<form method="POST" onsubmit="return confirm('Xóa file <?php echo htmlspecialchars(addslashes($file['name'])); ?>? Không thể hoàn tác.');" class="inline">
											<input type="hidden" name="export_action" value="delete_export_file">
											<input type="hidden" name="file_type" value="inventory">
											<input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
											<input type="hidden" name="inv_page" value="<?php echo (int) $invPage; ?>">
											<input type="hidden" name="hist_page" value="<?php echo (int) $histPage; ?>">
											<button type="submit" class="inline-flex items-center gap-1 bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition">
												🗑️ Xóa
											</button>
										</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ($invTotalPages > 1): ?>
				<div class="flex items-center justify-between mt-3 text-xs text-gray-600">
					<a href="<?php echo de_page_url($invPage - 1, $histPage); ?>"
					   class="px-3 py-1 rounded border <?php echo $invPage <= 1 ? 'pointer-events-none opacity-40 border-gray-200' : 'border-gray-300 hover:bg-gray-100'; ?>">‹ Trước</a>
					<span>Trang <?php echo $invPage; ?> / <?php echo $invTotalPages; ?></span>
					<a href="<?php echo de_page_url($invPage + 1, $histPage); ?>"
					   class="px-3 py-1 rounded border <?php echo $invPage >= $invTotalPages ? 'pointer-events-none opacity-40 border-gray-200' : 'border-gray-300 hover:bg-gray-100'; ?>">Sau ›</a>
				</div>
				<?php endif; ?>
			</div>
			<?php else: ?>
			<div class="flex-1 flex items-center justify-center text-sm text-gray-400 italic">
				Chưa có file tồn kho nào được xuất.
			</div>
			<?php endif; ?>
		</div>

		<!-- Bên phải: Xuất lịch sử nhập xuất -->
		<div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
			<h3 class="text-xl font-bold text-gray-800 mb-2">Xuất lịch sử nhập xuất kho (.xlsx)</h3>
			<!-- <p class="text-sm text-gray-600 mb-4">
				Xuất dữ liệu giao dịch từ bảng <code>transactions</code> theo ngày và loại giao dịch đã chọn.
			</p> -->
			<form method="POST" class="mb-4 space-y-3">
				<button type="submit" name="export_action" value="export_history_xlsx" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-md font-bold hover:bg-emerald-700 transition h-[42px]">
					📥 Xuất file XLSX
				</button>
				<div class="flex flex-wrap items-center gap-3">
					<label class="text-sm font-semibold text-gray-700" for="history_date"></label>
					<input type="date" id="history_date" name="history_date" class="border border-gray-300 rounded-md px-3 py-2 text-sm" value="<?php echo htmlspecialchars($today); ?>" max="<?php echo htmlspecialchars($today); ?>" required>
					<label class="inline-flex items-center gap-1 text-sm">
						<input type="radio" name="history_type" value="IN"> IN (Nhập)
					</label>
					<label class="inline-flex items-center gap-1 text-sm">
						<input type="radio" name="history_type" value="OUT" checked> OUT (Xuất)
					</label>
				</div>
				
			</form>

			<?php if (!empty($exportedHistoryFiles)): ?>
			<div class="flex-1">
				<h4 class="text-sm font-bold text-gray-700 mb-2">📁 Các file đã xuất (<?php echo count($exportedHistoryFiles); ?>)</h4>
				<div class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-gray-100 border-b">
							<tr>
								<th class="text-left p-2">Tên file</th>
								<!-- <th class="text-center p-2">Dung lượng</th>
								<th class="text-center p-2">Ngày xuất</th> -->
								<th class="text-center p-2">Thao tác</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($exportedHistoryFilesPage as $file): ?>
							<tr class="border-b hover:bg-gray-50">
								<td class="p-2">
									<code class="text-xs bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($file['name']); ?></code>
									<p class="text-begin p-2">
										<?php echo date('d/m/Y', $file['date']); ?> ||
										<?php echo number_format($file['size'] / 1024, 2); ?> KB
									</p>
								</td>

								<td class="text-center p-2">
									<div class="inline-flex items-center gap-1">
										<a href="<?php echo htmlspecialchars($file['path']); ?>" download class="inline-flex items-center gap-1 bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 transition">
											📥 Tải
										</a>
										<?php if ($isAdmin): ?>
										<form method="POST" onsubmit="return confirm('Xóa file <?php echo htmlspecialchars(addslashes($file['name'])); ?>? Không thể hoàn tác.');" class="inline">
											<input type="hidden" name="export_action" value="delete_export_file">
											<input type="hidden" name="file_type" value="history">
											<input type="hidden" name="filename" value="<?php echo htmlspecialchars($file['name']); ?>">
											<input type="hidden" name="inv_page" value="<?php echo (int) $invPage; ?>">
											<input type="hidden" name="hist_page" value="<?php echo (int) $histPage; ?>">
											<button type="submit" class="inline-flex items-center gap-1 bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition">
												🗑️ Xóa
											</button>
										</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ($histTotalPages > 1): ?>
				<div class="flex items-center justify-between mt-3 text-xs text-gray-600">
					<a href="<?php echo de_page_url($invPage, $histPage - 1); ?>"
					   class="px-3 py-1 rounded border <?php echo $histPage <= 1 ? 'pointer-events-none opacity-40 border-gray-200' : 'border-gray-300 hover:bg-gray-100'; ?>">‹ Trước</a>
					<span>Trang <?php echo $histPage; ?> / <?php echo $histTotalPages; ?></span>
					<a href="<?php echo de_page_url($invPage, $histPage + 1); ?>"
					   class="px-3 py-1 rounded border <?php echo $histPage >= $histTotalPages ? 'pointer-events-none opacity-40 border-gray-200' : 'border-gray-300 hover:bg-gray-100'; ?>">Sau ›</a>
				</div>
				<?php endif; ?>
			</div>
			<?php else: ?>
			<div class="flex-1 flex items-center justify-center text-sm text-gray-400 italic">
				Chưa có file lịch sử nào được xuất.
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 text-sm">
		<strong>ℹ️ Ghi chú:</strong> Trang này không tải dữ liệu khi mở. Dữ liệu chỉ được lấy khi bạn bấm nút Xuất file XLSX. Các file đã xuất sẽ được lưu trữ và hiển thị ở phía dưới để mọi người dùng (mọi level) có thể tải xuống mà không cần xuất lại.
	</div>
</div>

<?php if ($success_msg || $success_history_msg): ?>
<script>
	// Auto reload page after 2 seconds to show the new file in the list
	setTimeout(function() {
		window.location.href = '?page=data_export';
	}, 2000);
</script>
<?php endif; ?>
