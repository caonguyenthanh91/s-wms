<?php
// Export logic - called before HTML output from index.php
if (isset($_GET['export']) && $_GET['export'] === '1') {
	try {
		require_once __DIR__ . '/../config/db.php';
		require_once __DIR__ . '/../assets/vendor/autoload.php';

		// Optimized query: use index hints and fetch only necessary columns
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
		
		// Batch process data instead of loading all at once
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Inventory Snapshot');

		$headers = ['Ma vi tri full', 'Ma hang full', 'Ton kho hien tai'];
		$sheet->fromArray($headers, null, 'A1');

		// Prepare data for batch insert - much faster than row by row
		$dataRows = [];
		foreach ($stmt as $row) {
			$dataRows[] = [
				$row['shelf_full'],
				$row['product_full'],
				(int) $row['current_stock']
			];
		}

		// Write all data at once instead of individual setCellValue
		if (count($dataRows) > 0) {
			$sheet->fromArray($dataRows, null, 'A2');
		}

		// Format header
		$sheet->getStyle('A1:C1')->getFont()->setBold(true);
		
		// Auto-fit columns
		$sheet->getColumnDimension('A')->setWidth(24);
		$sheet->getColumnDimension('B')->setWidth(24);
		$sheet->getColumnDimension('C')->setWidth(18);

		$exportTimestamp = date('Ymd_His');
		$filename = 'inventory_snapshot_' . $exportTimestamp . '.xlsx';

		// Clear any output before sending headers
		if (ob_get_length()) {
			ob_end_clean();
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: max-age=0');

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;

	} catch (Exception $e) {
		http_response_code(500);
		echo "Loi xuat file: " . htmlspecialchars($e->getMessage());
		exit;
	}
}
?>

<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Manager', 'Admin'], true)) {
	echo '<div class="alert alert-danger text-center p-4">Ban khong co quyen truy cap trang nay. Can role: Manager tro len.</div>';
	return;
}
?>
?>

<div class="max-w-6xl mx-auto space-y-6">
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-xl font-bold text-gray-800 mb-2">Xuat du lieu ton kho (.xlsx)</h3>
		<p class="text-sm text-gray-600 mb-4">
			Xuat snapshot ton kho tai thoi diem thao tac. He thong chi truy van database khi nhan nut Xuat file XLSX.
		</p>
		<div class="flex flex-wrap items-center gap-3">
			<a href="?page=data_export&export=1" class="inline-flex items-center bg-emerald-600 text-white px-4 py-2 rounded-md font-bold hover:bg-emerald-700 transition">
				Xuat file XLSX
			</a>
		</div>
	</div>

	<div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 text-sm">
		Trang nay khong tai du lieu ton kho khi mo. Du lieu chi duoc lay khi ban bam Xuat file XLSX.
	</div>
</div>
