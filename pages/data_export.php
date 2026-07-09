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

if (isset($_GET['export']) && $_GET['export'] === '1') {
	require_once __DIR__ . '/../config/db.php';
	require_once __DIR__ . '/../assets/vendor/autoload.php';

	$rows = [];
	$stmt = $pdo->query(
		"SELECT
			CONCAT('B032-', UPPER(TRIM(s.shelf_id))) AS shelf_full,
			UPPER(TRIM(p.product_id)) AS product_full,
			SUM(i.quantity) AS current_stock
		 FROM inventory i
		 INNER JOIN shelves s ON s.id = i.shelf_id
		 INNER JOIN products p ON p.id = i.product_id
		 WHERE i.quantity > 0
		 GROUP BY s.shelf_id, p.product_id
		 ORDER BY s.shelf_id ASC, p.product_id ASC"
	);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle('Inventory Snapshot');

	$headers = ['Ma vi tri full', 'Ma hang full', 'Ton kho hien tai'];
	$sheet->fromArray($headers, null, 'A1');

	$line = 2;
	foreach ($rows as $row) {
		$sheet->setCellValue('A' . $line, (string) $row['shelf_full']);
		$sheet->setCellValue('B' . $line, (string) $row['product_full']);
		$sheet->setCellValue('C' . $line, (int) $row['current_stock']);
		$line++;
	}

	$sheet->getStyle('A1:C1')->getFont()->setBold(true);
	$sheet->getColumnDimension('A')->setWidth(24);
	$sheet->getColumnDimension('B')->setWidth(24);
	$sheet->getColumnDimension('C')->setWidth(18);

	$exportTimestamp = date('Ymd_His');
	$filename = 'inventory_snapshot_' . $exportTimestamp . '.xlsx';

	if (ob_get_length()) {
		ob_end_clean();
	}

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Cache-Control: max-age=0');

	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
	exit;
}
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
