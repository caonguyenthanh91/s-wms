<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = 'localhost';
$db   = 'cnt_smart_wms';
$user = 'root';
$pass = ''; // Mật khẩu mặc định của XAMPP thường để trống


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Lỗi kết nối: " . $e->getMessage());
}
?>
