<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kết nối database
require_once 'config/db.php';

// Xử lý yêu cầu AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    
    // Lấy dữ liệu JSON từ JavaScript
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['qr_code']) || empty($input['qr_code'])) {
        echo json_encode(['status' => 'error', 'message' => 'Không có dữ liệu QR code']);
        exit;
    }
    
    $qrData = trim($input['qr_code']);
    $response = [
        'status' => 'success',
        'message' => 'Đã quét thành công: ' . htmlspecialchars($qrData),
        'data' => $qrData
    ];
    
    // ===== PHẦN XỬ LÝ DỮ LIỆU QR CODE =====
    // TODO: Thêm logic kiểm tra/cập nhật database ở đây
    // Ví dụ:
    // $stmt = $pdo->prepare("SELECT * FROM products WHERE product_code = ?");
    // $stmt->execute([$qrData]);
    // $product = $stmt->fetch();
    
    echo json_encode($response);
    exit;
}

// Nếu không phải AJAX POST, hiển thị UI scanner
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quét Mã QR - Hệ Thống Quản Lý Kho</title>
    <link rel="stylesheet" href="../assets/css/customs.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen qr-scanner-container flex flex-col items-center justify-center p-4">
        <!-- Header -->
        <div class="text-center text-white mb-8">
            <h1 class="text-4xl font-bold mb-2">📱 Quét Mã QR</h1>
            <p class="text-lg opacity-90">Quét mã vị trí, mã sản phẩm bằng camera của bạn</p>
        </div>
        
        <!-- Scanner Container -->
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
            <!-- QR Reader -->
            <div id="reader" class="reader w-full mb-6"></div>
            
            <!-- Result Display -->
            <div id="result" class="hidden bg-green-50 border-2 border-green-500 rounded-lg p-4 text-center">
                <p class="text-sm text-gray-600 mb-2">Kết quả quét</p>
                <p id="result-text" class="text-2xl font-bold text-green-600 font-mono break-all"></p>
            </div>
            
            <!-- Info Display -->
            <div id="info" class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 text-center mt-4">
                <p class="text-sm text-gray-600">Trạng thái:</p>
                <p id="info-text" class="text-lg font-semibold text-blue-600">Đang chờ quét...</p>
            </div>
            
            <!-- Action Buttons -->
            <div class="grid grid-cols-2 gap-4 mt-6">
                <button onclick="stopScanner()" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 rounded-lg transition">
                    Dừng
                </button>
                <button onclick="startScanner()" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 rounded-lg transition">
                    Tiếp Tục
                </button>
            </div>
            
            <!-- Back Button -->
            <div class="mt-4">
                <a href="javascript:history.back()" class="block text-center text-gray-600 hover:text-gray-800 font-semibold py-2 border-2 border-gray-300 rounded-lg transition">
                    ← Quay Lại
                </a>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="text-white text-center mt-8 max-w-md">
            <p class="text-sm opacity-75">
                💡 Mẹo: Đảm bảo camera được cho phép truy cập, ánh sáng đủ, và QR code nằm trong khung hình
            </p>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let isScannerRunning = false;

        // Cấu hình và khởi tạo trình quét
        function initScanner() {
            if (html5QrcodeScanner) return; // Nếu đã có scanner, không khởi tạo lại
            
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                false
            );

            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            isScannerRunning = true;
            updateInfoText('Scanner đang hoạt động...');
        }

        // Hàm gọi khi quét thành công
        function onScanSuccess(decodedText, decodedResult) {
            // Phát hiệu âm (tuỳ chọn)
            playSound();
            
            // Hiển thị kết quả
            document.getElementById('result').classList.remove('hidden');
            document.getElementById('result').classList.add('pulse');
            document.getElementById('result-text').innerText = decodedText;
            
            updateInfoText('✅ Quét thành công');
            
            // Gửi dữ liệu tới PHP để xử lý
            sendDataToPHP(decodedText);
        }

        // Hàm xử lý lỗi quét
        function onScanFailure(error) {
            // Bỏ qua các lỗi, không spam console
            // console.warn(`Lỗi quét: ${error}`);
        }

        // Gửi dữ liệu QR tới PHP qua AJAX
        function sendDataToPHP(qrCodeValue) {
            fetch('scan_qr.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ qr_code: qrCodeValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateInfoText('✅ ' + data.message);
                    // Có thể thêm thêm xử lý ở đây (lưu vào bảng, cập nhật giao diện, v.v.)
                } else {
                    updateInfoText('❌ Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Lỗi gửi dữ liệu:', error);
                updateInfoText('❌ Lỗi kết nối server');
            });
        }

        // Cập nhật text thông tin
        function updateInfoText(text) {
            document.getElementById('info-text').innerText = text;
        }

        // Dừng scanner
        function stopScanner() {
            if (html5QrcodeScanner && isScannerRunning) {
                html5QrcodeScanner.pause(true);
                isScannerRunning = false;
                updateInfoText('⏸️ Scanner đã dừng');
            }
        }

        // Tiếp tục scanner
        function startScanner() {
            if (html5QrcodeScanner && !isScannerRunning) {
                html5QrcodeScanner.resume();
                isScannerRunning = true;
                updateInfoText('▶️ Scanner đang hoạt động...');
            }
        }

        // Phát tiếng "beep" khi quét thành công
        function playSound() {
            // Sử dụng Web Audio API để tạo beep sound
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                
                oscillator.frequency.value = 800; // Tần số (Hz)
                oscillator.type = 'sine';
                
                gain.gain.setValueAtTime(0.3, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.1);
            } catch (e) {
                // Nếu Web Audio API không khả dụng, bỏ qua
            }
        }

        // Khởi tạo scanner khi trang tải xong
        document.addEventListener('DOMContentLoaded', function() {
            initScanner();
        });

        // Dừng scanner khi rời khỏi trang
        window.addEventListener('beforeunload', function() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
            }
        });
    </script>
</body>
</html>
