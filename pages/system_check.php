<?php
// System check page - để debug và kiểm tra quyền hệ thống
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

// Chỉ admin mới có quyền truy cập
if ($role !== 'Admin') {
	echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Admin.</div>';
	return;
}

$results = [];

// 1. Kiểm tra quyền thư mục
$dirs_to_check = [
	'assets/database' => __DIR__ . '/../assets/database',
	'assets' => __DIR__ . '/../assets',
	'pages' => __DIR__,
];

foreach ($dirs_to_check as $name => $dir_path) {
	$results[$name] = [
		'path' => $dir_path,
		'exists' => is_dir($dir_path),
		'readable' => is_readable($dir_path),
		'writable' => is_writable($dir_path),
		'permissions' => substr(sprintf('%o', fileperms($dir_path)), -4),
	];
}

// 2. Kiểm tra PHP permissions
$php_results = [
	'PHP Version' => phpversion(),
	'PHP User' => function_exists('get_current_user') ? get_current_user() : 'N/A',
	'Disable Functions' => ini_get('disable_functions') ?: 'None',
];

// 3. Tạo thử file test
$test_file = __DIR__ . '/../assets/database/test_write_' . time() . '.txt';
$test_dir = __DIR__ . '/../assets/database/';

// Tạo thư mục nếu chưa tồn tại
if (!is_dir($test_dir)) {
	$mkdir_result = @mkdir($test_dir, 0755, true);
	$results['mkdir_result'] = $mkdir_result ? 'Success' : 'Failed';
}

$write_test = [
	'path' => $test_file,
	'attempt' => 'Trying to write test file...'
];

if (is_writable($test_dir)) {
	$handle = @fopen($test_file, 'w');
	if ($handle) {
		fwrite($handle, 'Test write: ' . date('Y-m-d H:i:s'));
		fclose($handle);
		$write_test['result'] = 'SUCCESS - File created';
		$write_test['size'] = filesize($test_file);
		
		// Delete test file
		@unlink($test_file);
		$write_test['cleaned'] = true;
	} else {
		$write_test['result'] = 'FAILED - Cannot open file for writing';
	}
} else {
	$write_test['result'] = 'FAILED - Directory not writable';
}

// 4. Kiểm tra memory limit
$memory_results = [
	'Memory Limit' => ini_get('memory_limit'),
	'Max Upload Size' => ini_get('upload_max_filesize'),
	'Max Post Size' => ini_get('post_max_size'),
];

?>

<div class="max-w-6xl mx-auto space-y-6 p-6">
	<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
		<h2 class="text-xl font-bold text-blue-900 mb-2">🔍 Kiểm tra Quyền Hệ Thống</h2>
		<p class="text-blue-700">Trang này giúp bạn debug các vấn đề liên quan đến quyền file và thư mục</p>
	</div>

	<!-- Kiểm tra Thư Mục -->
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-lg font-bold text-gray-800 mb-4">📁 Quyền Thư Mục</h3>
		<div class="overflow-x-auto">
			<table class="w-full text-sm border-collapse">
				<thead>
					<tr class="bg-gray-100 border-b">
						<th class="text-left p-3 border">Thư Mục</th>
						<th class="text-center p-3 border">Tồn Tại</th>
						<th class="text-center p-3 border">Đọc</th>
						<th class="text-center p-3 border">Ghi</th>
						<th class="text-center p-3 border">Quyền (oct)</th>
						<th class="text-left p-3 border">Đường dẫn</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($results as $name => $info): 
						if (is_array($info) && isset($info['path'])):
					?>
					<tr class="border-b hover:bg-gray-50">
						<td class="p-3 border font-bold"><?php echo $name; ?></td>
						<td class="text-center p-3 border">
							<span class="<?php echo $info['exists'] ? 'text-green-600 font-bold' : 'text-red-600'; ?>">
								<?php echo $info['exists'] ? '✓' : '✗'; ?>
							</span>
						</td>
						<td class="text-center p-3 border">
							<span class="<?php echo $info['readable'] ? 'text-green-600 font-bold' : 'text-red-600'; ?>">
								<?php echo $info['readable'] ? '✓' : '✗'; ?>
							</span>
						</td>
						<td class="text-center p-3 border">
							<span class="<?php echo $info['writable'] ? 'text-green-600 font-bold' : 'text-red-600'; ?>">
								<?php echo $info['writable'] ? '✓' : '✗'; ?>
							</span>
						</td>
						<td class="text-center p-3 border"><code><?php echo $info['permissions']; ?></code></td>
						<td class="p-3 border text-xs text-gray-600 break-all"><?php echo $info['path']; ?></td>
					</tr>
					<?php endif; endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Test Ghi File -->
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-lg font-bold text-gray-800 mb-4">✍️ Test Ghi File</h3>
		<div class="border rounded-lg p-4 <?php echo strpos($write_test['result'], 'SUCCESS') !== false ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'; ?>">
			<p class="font-mono text-sm">
				<strong>Kết quả:</strong> 
				<span class="<?php echo strpos($write_test['result'], 'SUCCESS') !== false ? 'text-green-700 font-bold' : 'text-red-700 font-bold'; ?>">
					<?php echo $write_test['result']; ?>
				</span>
			</p>
			<?php if (isset($write_test['size'])): ?>
			<p class="font-mono text-sm text-gray-600">File size: <?php echo $write_test['size']; ?> bytes</p>
			<?php endif; ?>
			<p class="font-mono text-xs text-gray-500 mt-2">Đường dẫn: <?php echo $write_test['path']; ?></p>
		</div>
	</div>

	<!-- PHP Info -->
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-lg font-bold text-gray-800 mb-4">⚙️ Thông Tin PHP & Server</h3>
		<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
			<?php foreach (array_merge($php_results, $memory_results) as $key => $value): ?>
			<div class="border rounded-lg p-3">
				<p class="text-xs text-gray-600 font-semibold"><?php echo $key; ?></p>
				<p class="font-mono text-sm text-gray-800 font-bold"><?php echo $value; ?></p>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Log File Check -->
	<div class="bg-white p-6 rounded-lg shadow-md">
		<h3 class="text-lg font-bold text-gray-800 mb-4">📋 Error Log</h3>
		<?php
		$log_file = __DIR__ . '/../assets/database/export_error.log';
		if (file_exists($log_file) && filesize($log_file) > 0):
		?>
		<div class="bg-gray-50 border rounded-lg p-3 max-h-96 overflow-y-auto">
			<pre class="text-xs font-mono text-red-700 whitespace-pre-wrap break-words"><?php 
				echo htmlspecialchars(file_get_contents($log_file));
			?></pre>
		</div>
		<p class="text-xs text-gray-600 mt-2">
			📁 <?php echo $log_file; ?>
			(<?php echo number_format(filesize($log_file)); ?> bytes)
		</p>
		<?php else: ?>
		<p class="text-gray-500 italic">Chưa có lỗi được ghi log</p>
		<?php endif; ?>
	</div>

	<!-- Khuyến nghị -->
	<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
		<h4 class="font-bold text-yellow-900 mb-2">💡 Khuyến nghị:</h4>
		<ul class="text-sm text-yellow-800 list-disc list-inside space-y-1">
			<?php if (!$results['assets/database']['writable']): ?>
			<li><strong>Thư mục /assets/database không có quyền ghi!</strong> 
				Liên hệ admin server để thay đổi quyền: <code>chmod 755 /path/to/assets/database</code>
			</li>
			<?php endif; ?>
			<?php if (strpos($write_test['result'], 'FAILED') !== false): ?>
			<li><strong>Test ghi file thất bại!</strong> Kiểm tra lại quyền hệ thống hoặc liên hệ hosting provider</li>
			<?php endif; ?>
			<li>Nếu vẫn gặp lỗi, hãy xem <strong>Error Log</strong> ở trên để biết chi tiết</li>
		</ul>
	</div>
</div>
