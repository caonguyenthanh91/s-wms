<?php 
require_once 'config/db.php'; 
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 28800);
    session_set_cookie_params(28800);
    session_start();
}
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';
$page = $_GET['page'] ?? 'inbound';

$bootstrapCssPath = __DIR__ . '/assets/css/bootstrap.min.css';
$fontAwesomeCssPath = __DIR__ . '/assets/css/all.min.css';
$customCssPath = __DIR__ . '/assets/css/customs.css';

$assetBaseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($assetBaseUrl === '') {
    $assetBaseUrl = '/';
}

if ($assetBaseUrl !== '/') {
    $assetBaseUrl .= '/';
}

$bootstrapCssVersion = file_exists($bootstrapCssPath) ? (string) filemtime($bootstrapCssPath) : '1';
$fontAwesomeCssVersion = file_exists($fontAwesomeCssPath) ? (string) filemtime($fontAwesomeCssPath) : '1';
$customCssVersion = file_exists($customCssPath) ? (string) filemtime($customCssPath) : '1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart WMS - Hệ Thống Kho Thông Minh</title>
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $assetBaseUrl; ?>assets/img/icon.png">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/bootstrap.min.css?v=<?php echo $bootstrapCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/all.min.css?v=<?php echo $fontAwesomeCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/customs.css?v=<?php echo $customCssVersion; ?>">
    <script src="<?php echo $assetBaseUrl; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/tailwindcss.js"></script>

</head>
<body class="bg-gray-100 font-sans">
    <div id="app-container" class="min-h-screen flex flex-col md:flex-row sidebar-expanded">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-full md:w-64 bg-slate-800 text-white flex-shrink-0 transition-all duration-300 overflow-hidden">
            <button type="button" id="sidebar-brand-toggle" onclick="toggleSidebar()" class="w-full p-6 text-2xl font-bold border-b border-slate-700 whitespace-nowrap flex items-center justify-start text-left hover:bg-slate-700/40 transition" title="Mo/Rut gon menu">
                <span class="sidebar-title-full">S-WMS</span>
                <span class="sidebar-title-collapsed hidden"><small>S-WMS</small></span>
                <span class="ml-auto text-xs opacity-80 sidebar-toggle-hint">Menu</span>
            </button>
            <nav id="sidebar-nav" class="p-4 space-y-2">
                <?php if (in_array($role, ['Admin', 'Manager'])): ?>
                <a href="?page=dashboard" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'dashboard' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📊</span>
                    <span class="ml-3 sidebar-nav-text">Dashboard</span>
                </a>
                <a href="?page=layout" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'layout' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📅</span>
                    <span class="ml-3 sidebar-nav-text">Layout</span>
                </a>
                <a href="?page=products" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'products' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🏷️</span>
                    <span class="ml-3 sidebar-nav-text">Sản Phẩm</span>
                </a>
                <a href="?page=shelves" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'shelves' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🛒</span>
                    <span class="ml-3 sidebar-nav-text">Kệ hàng</span>
                </a>
                <a href="?page=admin" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'admin' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🔧</span>
                    <span class="ml-3 sidebar-nav-text">Quản trị</span>
                </a>
                <?php endif; ?>
                <?php if (in_array($role, ['Admin', 'Leader', 'Manager'])): ?>
                <a href="?page=transfer" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'transfer' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🔄</span>
                    <span class="ml-3 sidebar-nav-text">Pallet >>> Kệ</span>
                </a>
                <a href="?page=change" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'change' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">↔️</span>
                    <span class="ml-3 sidebar-nav-text">Đổi kệ</span>
                </a>
                <a href="?page=print" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'print' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🖨️</span>
                    <span class="ml-3 sidebar-nav-text">In Phiếu [Mới]</span>
                </a>
                
                <?php endif; ?>
                <a href="?page=import" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'import' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🚚</span>
                    <span class="ml-3 sidebar-nav-text">Nhận hàng (Pallet)</span>
                </a>
                <a href="?page=inbound" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'inbound' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📥</span>
                    <span class="ml-3 sidebar-nav-text">Nhập Kho</span>
                </a>
                <a href="?page=outbound" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'outbound' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📤</span>
                    <span class="ml-3 sidebar-nav-text">Xuất Kho</span>
                </a>
                
                <a href="?page=inventory" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'inventory' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📦</span>
                    <span class="ml-3 sidebar-nav-text">Tồn Kho</span>
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0">
            <header class="bg-white shadow p-4 flex justify-between items-center">
                <button onclick="toggleSidebar()" class="p-2 rounded hover:bg-gray-100 hidden md:block">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h2 class="text-xl font-semibold text-gray-800 uppercase">
                    <?php 
                        echo $page; 
                    ?>
                </h2>
                <div class="flex items-center gap-4">
                        <div id="auth-block" class="text-sm text-gray-500">Đang tải...</div>
                    </div>
            </header>

            <div class="p-6">
                <?php
                    // Cấu hình quyền truy cập trang (Access Control List)
                    $allowed_pages = ['import', 'inbound', 'outbound', 'inventory', 'print']; // Quyền chung
                    if (in_array($role, ['Admin', 'Leader', 'Manager'])) {
                        $allowed_pages[] = 'transfer';
                        $allowed_pages[] = 'change'; // Add the new page here                                             
                        
                    }

                    if (in_array($role, ['Admin', 'Manager'])) {
                        $allowed_pages[] = 'admin';
                        $allowed_pages[] = 'dashboard';
                        $allowed_pages[] = 'layout';
                        $allowed_pages[] = 'products';
                        $allowed_pages[] = 'shelves';
                    }

                    if (in_array($page, $allowed_pages)) {
                        include "pages/$page.php";
                    } else {
                        include "pages/import.php";
                    }
                ?>
            </div>
        </main>
    </div>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/chart.umd.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/html5-qrcode.min.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/qrcode.min.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/app.js"></script>
    <div id="qr-scanner-modal" class="fixed inset-0 bg-black bg-opacity-70 hidden z-[9999] items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="flex justify-between items-center px-4 py-3 border-b">
                <h3 id="qr-scanner-title" class="font-bold text-gray-800">Quet Ma QR</h3>
                <button type="button" onclick="closeQRScannerModal()" class="text-gray-500 hover:text-gray-800 text-xl leading-none">&times;</button>
            </div>
            <div class="p-4">
                <div id="qr-reader" class="w-full"></div>
                <div id="qr-scan-result" class="hidden mt-3 p-3 rounded bg-blue-50 text-blue-800 text-sm font-mono break-all"></div>
            </div>
            <div class="px-4 pb-4 flex justify-end gap-2">
                <button type="button" onclick="applyQRCodeToTarget()" class="px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700 text-sm">Ap dung</button>
                <button type="button" onclick="closeQRScannerModal()" class="px-3 py-2 rounded bg-gray-200 text-gray-800 hover:bg-gray-300 text-sm">Dong</button>
            </div>
        </div>
    </div>
    <script>
        let qrScannerInstance = null;
        let qrScannerTargetId = '';
        let qrScannerValue = '';

        function openQRScannerModal(targetId, label) {
            qrScannerTargetId = targetId;
            qrScannerValue = '';

            const title = document.getElementById('qr-scanner-title');
            if (title) {
                title.textContent = 'Quet ' + (label || 'Ma QR');
            }

            const result = document.getElementById('qr-scan-result');
            if (result) {
                result.classList.add('hidden');
                result.textContent = '';
            }

            const modal = document.getElementById('qr-scanner-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            startQRScanner();
        }

        function closeQRScannerModal() {
            const modal = document.getElementById('qr-scanner-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            stopQRScanner();
        }

        function startQRScanner() {
            if (typeof Html5QrcodeScanner === 'undefined') {
                alert('QR scanner library chua san sang. Vui long chay setup.php va tai lai trang.');
                return;
            }

            if (qrScannerInstance) {
                return;
            }

            qrScannerInstance = new Html5QrcodeScanner(
                'qr-reader',
                {
                    fps: 10,
                    qrbox: { width: 240, height: 240 },
                    rememberLastUsedCamera: true,
                    supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
                },
                false
            );

            qrScannerInstance.render(onQRScanSuccess, function() {});
        }

        function stopQRScanner() {
            if (!qrScannerInstance) {
                return;
            }

            qrScannerInstance.clear().catch(function() {}).finally(function() {
                qrScannerInstance = null;
            });
        }

        function onQRScanSuccess(decodedText) {
            qrScannerValue = (decodedText || '').trim();
            const result = document.getElementById('qr-scan-result');
            if (result) {
                result.classList.remove('hidden');
                result.textContent = qrScannerValue;
            }
            playQRScanBeep();
        }

        function applyQRCodeToTarget() {
            if (!qrScannerTargetId || !qrScannerValue) {
                return;
            }

            const target = document.getElementById(qrScannerTargetId);
            if (!target) {
                return;
            }

            if (target.tagName === 'SELECT') {
                const exists = Array.from(target.options).some(function(opt) { return opt.value === qrScannerValue; });
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = qrScannerValue;
                    opt.textContent = qrScannerValue + ' (QR)';
                    target.appendChild(opt);
                }
            }

            target.value = qrScannerValue;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            target.focus();

            closeQRScannerModal();
        }

        function playQRScanBeep() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();

                oscillator.connect(gain);
                gain.connect(audioContext.destination);

                oscillator.frequency.value = 900;
                gain.gain.setValueAtTime(0.25, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.12);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.12);
            } catch (e) {}
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('qr-scanner-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeQRScannerModal();
                }
            }
        });

        document.getElementById('qr-scanner-modal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeQRScannerModal();
            }
        });

        function isMobileSidebarMode() {
            return window.matchMedia('(max-width: 900px) and (orientation: portrait)').matches;
        }

        function toggleSidebar(forceCollapsed) {
            const appContainer = document.getElementById('app-container');
            if (!appContainer) return;

            if (isMobileSidebarMode()) {
                if (typeof forceCollapsed === 'boolean') {
                    appContainer.classList.toggle('mobile-sidebar-open', !forceCollapsed);
                    return;
                }
                appContainer.classList.toggle('mobile-sidebar-open');
                return;
            }

            if (typeof forceCollapsed === 'boolean') {
                appContainer.classList.toggle('sidebar-collapsed', forceCollapsed);
                return;
            }

            appContainer.classList.toggle('sidebar-collapsed');
        }

        function applyPdaSidebarDefault() {
            const currentPage = '<?php echo addslashes($page); ?>';
            const pdaOptimizedPages = ['import', 'inbound', 'outbound', 'transfer', 'change', 'inventory'];
            const isSmallViewport = window.matchMedia('(max-width: 900px)').matches;
            const appContainer = document.getElementById('app-container');

            if (appContainer && isMobileSidebarMode()) {
                appContainer.classList.remove('mobile-sidebar-open');
                appContainer.classList.remove('sidebar-collapsed');
                return;
            }

            if (isSmallViewport && pdaOptimizedPages.includes(currentPage)) {
                toggleSidebar(true);
            }
        }

        // Auth: load current user and provide login/logout
        function loadAuth() {
            $.getJSON('api.php?action=get_current_user', function(res){
                const block = $('#auth-block');
                const user = res.user;
                if (user && user.username) {
                    block.html(`<span class="font-medium">Xin chào, ${user.username}</span> <button id=\"btn-logout\" class=\"ml-3 text-blue-600 underline text-sm\">Đăng xuất</button>`);
                    $('#btn-logout').on('click', function(){ $.post('api.php?action=logout', {}, function(){ location.reload(); }); });
                } else {
                    block.html(`<button id=\"btn-login\" class=\"text-white bg-blue-600 px-3 py-1 rounded\">Đăng nhập</button>`);
                    $('#btn-login').on('click', function(){ showLogin(); });
                }
            });
        }

        function showLogin() {
            const modal = $("<div id='login-modal' class='fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center'><div class='bg-white p-6 rounded w-96'>\n                <h3 class='font-bold mb-3'>Đăng nhập</h3>\n                <input id='li-user' placeholder='username' class='w-full border p-2 mb-2'/>\n                <input id='li-pass' type='password' placeholder='password' class='w-full border p-2 mb-3'/>\n                <div class='flex justify-end gap-2'>\n                    <button id='li-cancel' class='px-3 py-1'>Hủy</button>\n                    <button id='li-submit' class='bg-blue-600 text-white px-3 py-1 rounded'>Đăng nhập</button>\n                </div>\n            </div></div>");
            $('body').append(modal);
            $('#li-cancel').on('click', function(){ modal.remove(); });
            $('#li-submit').on('click', function(){
                const u = $('#li-user').val(); const p = $('#li-pass').val();
                $.post('api.php?action=login', {username:u,password:p}, function(res){
                    if(res.success) { modal.remove(); location.reload(); }
                    else alert(res.message||'Lỗi');
                }, 'json');
            });
        }

        $(document).ready(function(){
            applyPdaSidebarDefault();
            loadAuth();
        });

        window.addEventListener('resize', function() {
            applyPdaSidebarDefault();
        });
    </script>
</body>
</html>