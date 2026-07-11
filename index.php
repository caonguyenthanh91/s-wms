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

$pageShortLabels = [
    'import' => 'Nhập Pallet',
    'inbound' => 'Nhập kho',
    'outbound' => 'Xuất kho',
    'packing' => 'Packing',
    'pickup' => 'Pickup',
    'inventory' => 'Tra tồn',
    'picking' => 'Picking',
    'transfer' => 'Pallet >>> Kệ',
    'change' => 'Đổi kệ',
    'print' => 'In phiếu',
    'print_case' => 'In Case',
    'print_pallet' => 'In Pallet',
    'layout' => 'Layout',
    'dashboard' => 'Dashboard',
    'shelves' => 'ĐK Kệ',
    'products' => 'ĐK SP',
    'data_export' => 'Xuất file',
    'admin' => 'Quản trị',
];

$mobileHeaderLabel = 'S-WMS';
if (isset($pageShortLabels[$page])) {
    $mobileHeaderLabel .= '/' . $pageShortLabels[$page];
}

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
    <style>
        body.pda-compact-header #main-page-header {
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
        }

        body.pda-compact-header #page-title,
        body.pda-compact-header #auth-block {
            display: none;
        }

        body.pda-compact-header #main-content {
            padding: 0.55rem;
        }

        body.pda-compact-header #pda-logout-slot {
            display: inline-flex;
            margin-left: auto;
        }

        body.pda-compact-header #pda-logout-slot .pda-auth-btn {
            border: 1px solid rgba(148, 163, 184, 0.7);
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            border-radius: 0.45rem;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.28rem 0.55rem;
            line-height: 1.05;
        }

        body.pda-compact-header #pda-logout-slot .pda-auth-btn:active {
            transform: translateY(1px);
        }

        @media (min-width: 901px) {
            #pda-logout-slot {
                display: none !important;
            }
        }
    </style>

</head>
<body class="bg-gray-100 font-sans">
    <div id="app-container" class="min-h-screen flex flex-col md:flex-row sidebar-expanded">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-full md:w-64 bg-slate-800 text-white flex-shrink-0 transition-all duration-300 overflow-hidden">
            <div id="mobile-header-bar" class="md:hidden px-3 py-2 border-b border-slate-700 flex items-center justify-between cursor-pointer">
                <button id="mobile-menu-toggle" type="button" class="font-bold text-base tracking-wide flex items-center gap-2">
                    <span id="mobile-menu-label"><?php echo htmlspecialchars($mobileHeaderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <i id="mobile-menu-icon" class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="auth-block-mobile" class="text-xs text-gray-300">Đang tải...</div>
            </div>
            <div class="hidden md:flex p-6 text-2xl font-bold border-b border-slate-700 whitespace-nowrap items-center">
                <span class="sidebar-title-full">S-WMS</span>
                <span class="sidebar-title-collapsed hidden"><small>S-WMS</small></span>
            </div>
            <nav id="sidebar-nav" class="hidden md:block p-4 space-y-2">
                <?php if (in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])): ?>
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
                <a href="?page=packing" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'packing' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📫</span>
                    <span class="ml-3 sidebar-nav-text">Packing</span>
                </a>
                <a href="?page=pickup" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'pickup' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🚛</span>
                    <span class="ml-3 sidebar-nav-text">Pickup</span>
                </a>
                <a href="?page=picking" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'picking' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🧭</span>
                    <span class="ml-3 sidebar-nav-text">Picking</span>
                </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Leader', 'Manager', 'Admin'])): ?>
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
                <a href="?page=print_case" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'print_case' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📦</span>
                    <span class="ml-3 sidebar-nav-text">In Case</span>
                </a>
                <a href="?page=print_pallet" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'print_pallet' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📮</span>
                    <span class="ml-3 sidebar-nav-text">In Pallet</span>
                </a>
                <a href="?page=dashboard" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'dashboard' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🛫</span>
                    <span class="ml-3 sidebar-nav-text">Dashboard</span>
                </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Manager', 'Admin'])): ?>
                <a href="?page=layout" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'layout' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📅</span>
                    <span class="ml-3 sidebar-nav-text">Layout</span>
                </a>
                <a href="?page=shelves" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'shelves' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🛒</span>
                    <span class="ml-3 sidebar-nav-text">Kệ hàng</span>
                </a>
                <a href="?page=products" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'products' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🏷️</span>
                    <span class="ml-3 sidebar-nav-text">Sản Phẩm</span>
                </a>
                <a href="?page=data_export" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'data_export' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">📄</span>
                    <span class="ml-3 sidebar-nav-text">Data Export</span>
                </a>
                <?php endif; ?>

                <?php if ($role === 'Admin'): ?>
                <a href="?page=admin" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page == 'admin' ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon">🔧</span>
                    <span class="ml-3 sidebar-nav-text">Quản trị</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>
        <!-- Main Content -->
        <main class="flex-1 flex flex-col min-w-0">
            <header id="main-page-header" class="hidden md:flex bg-white shadow p-4 justify-between items-center">
                <button onclick="toggleSidebar()" class="p-2 rounded hover:bg-gray-100 hidden md:block">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h2 id="page-title" class="text-xl font-semibold text-gray-800 uppercase">
                    <?php 
                        echo $page; 
                    ?>
                </h2>
                <div class="flex items-center gap-4">
                        <div id="auth-block" class="hidden md:block text-sm text-gray-500">Đang tải...</div>
                    <span id="pda-logout-slot" class="hidden"></span>
                    </div>
            </header>

            <div id="main-content" class="p-6">
                <?php
                    // Cấu hình quyền truy cập trang (Access Control List)
                    $allowed_pages = [];

                    if (in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
                        $allowed_pages = array_merge($allowed_pages, ['import', 'inbound', 'outbound', 'inventory', 'packing', 'pickup', 'picking']);
                    }

                    if (in_array($role, ['Leader', 'Manager', 'Admin'])) {
                        $allowed_pages = array_merge($allowed_pages, ['transfer', 'change', 'print', 'print_case', 'print_pallet', 'dashboard']);
                    }

                    if (in_array($role, ['Manager', 'Admin'])) {
                        $allowed_pages = array_merge($allowed_pages, ['layout', 'shelves', 'products', 'data_export']);
                    }

                    if ($role === 'Admin') {
                        $allowed_pages[] = 'admin';
                    }

                    if (in_array($page, $allowed_pages)) {
                        include "pages/$page.php";
                    } else {
                        include "pages/inbound.php";
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
        let currentAuthUser = null;

        function pdaOptimizedPages() {
            return ['import', 'inbound', 'outbound', 'transfer', 'change', 'inventory', 'packing', 'pickup'];
        }

        function isPdaCompactMode() {
            const currentPage = '<?php echo addslashes($page); ?>';
            const isSmallViewport = window.matchMedia('(max-width: 900px)').matches;
            return isSmallViewport && pdaOptimizedPages().includes(currentPage);
        }

        function applyPdaHeaderMode() {
            document.body.classList.toggle('pda-compact-header', isPdaCompactMode());
        }

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

        function resetQRScannerModalState() {
            qrScannerValue = '';

            const result = document.getElementById('qr-scan-result');
            if (result) {
                result.classList.add('hidden');
                result.textContent = '';
            }
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

            if (typeof window.handleQRScannerScan === 'function') {
                const handled = window.handleQRScannerScan(qrScannerTargetId, qrScannerValue);
                if (handled) {
                    return;
                }
            }
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
            const isSmallViewport = window.matchMedia('(max-width: 900px)').matches;
            const appContainer = document.getElementById('app-container');
            const currentPage = '<?php echo addslashes($page); ?>';

            if (appContainer && isMobileSidebarMode()) {
                // PDA portrait: mac dinh thu gon menu, mo bang cach cham header S-WMS.
                appContainer.classList.remove('mobile-sidebar-open');
                appContainer.classList.remove('sidebar-collapsed');
                return;
            }

            if (isSmallViewport && pdaOptimizedPages().includes(currentPage)) {
                toggleSidebar(true);
            }
        }

        function bindLogoutButton(selector) {
            const btn = $(selector);
            if (!btn.length) return;
            btn.off('click').on('click', function(){
                $.post('api.php?action=logout', {}, function(){ location.reload(); });
            });
        }

        function renderAuthBlock(user) {
            const block = $('#auth-block');
            const compactSlot = $('#pda-logout-slot');
            const mobileBlock = $('#auth-block-mobile');
            compactSlot.empty();

            if (user && user.username) {
                mobileBlock.html(`<span class="font-medium mr-2">${user.username}</span><button id="btn-logout-mobile" class="text-blue-200 underline">Đăng xuất</button>`);
                bindLogoutButton('#btn-logout-mobile');
            } else {
                mobileBlock.html('<button id="btn-login-mobile" class="text-blue-200 underline" type="button">Đăng nhập</button>');
                $('#btn-login-mobile').off('click').on('click', function(){ showLogin(); });
            }

            if (user && user.username) {
                if (isPdaCompactMode()) {
                    block.empty();
                    compactSlot.html('<button id="btn-logout-compact" class="pda-auth-btn" type="button">Đăng xuất</button>');
                    bindLogoutButton('#btn-logout-compact');
                } else {
                    block.html(`<span class="font-medium">Xin chào, ${user.username}</span> <button id="btn-logout" class="ml-3 text-blue-600 underline text-sm">Đăng xuất</button>`);
                    bindLogoutButton('#btn-logout');
                }
            } else {
                if (isPdaCompactMode()) {
                    block.empty();
                    compactSlot.html('<button id="btn-login-compact" class="pda-auth-btn" type="button">Đăng nhập</button>');
                    $('#btn-login-compact').off('click').on('click', function(){ showLogin(); });
                } else {
                    block.html(`<button id="btn-login" class="text-white bg-blue-600 px-3 py-1 rounded">Đăng nhập</button>`);
                    $('#btn-login').off('click').on('click', function(){ showLogin(); });
                }
            }
        }

        // Auth: load current user and provide login/logout
        function loadAuth() {
            $.getJSON('api.php?action=get_current_user', function(res){
                const user = res.user;
                currentAuthUser = user || null;
                renderAuthBlock(currentAuthUser);
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

        function toggleMobileSidebarMenu() {
            const nav = document.getElementById('sidebar-nav');
            const icon = document.getElementById('mobile-menu-icon');
            const appContainer = document.getElementById('app-container');
            if (!nav) return;

            let willOpen = nav.classList.contains('hidden');
            if (isMobileSidebarMode() && appContainer) {
                appContainer.classList.toggle('mobile-sidebar-open');
                willOpen = appContainer.classList.contains('mobile-sidebar-open');
            } else {
                nav.classList.toggle('hidden');
                willOpen = !nav.classList.contains('hidden');
            }

            if (icon) {
                icon.classList.toggle('fa-chevron-down', !willOpen);
                icon.classList.toggle('fa-chevron-up', willOpen);
            }
        }

        function updateMobileMenuLabel(text) {
            const label = document.getElementById('mobile-menu-label');
            if (!label) return;
            label.textContent = text || 'S-WMS';
        }

        function getCurrentMobileLabelFromPage() {
            const pageKey = '<?php echo addslashes($page); ?>';
            const labels = {
                import: 'Nhập Pallet',
                inbound: 'Nhập kho',
                outbound: 'Xuất kho',
                packing: 'Packing',
                pickup: 'Pickup',
                inventory: 'Tra tồn',
                picking: 'Picking',
                transfer: 'Pallet >>> Kệ',
                change: 'Đổi kệ',
                print: 'In phiếu',
                layout: 'Layout',
                dashboard: 'Dashboard',
                shelves: 'ĐK Kệ',
                products: 'ĐK SP',
                data_export: 'Xuất file',
                admin: 'Quản trị'
            };

            if (!labels[pageKey]) {
                return 'S-WMS';
            }

            return 'S-WMS/' + labels[pageKey];
        }

        function closeMobileSidebarMenu() {
            if (window.innerWidth >= 768) return;
            const nav = document.getElementById('sidebar-nav');
            const icon = document.getElementById('mobile-menu-icon');
            const appContainer = document.getElementById('app-container');
            if (!nav) return;

            if (isMobileSidebarMode() && appContainer) {
                appContainer.classList.remove('mobile-sidebar-open');
            }
            nav.classList.add('hidden');
            if (icon) { icon.classList.add('fa-chevron-down'); icon.classList.remove('fa-chevron-up'); }
            updateMobileMenuLabel(getCurrentMobileLabelFromPage());
        }

        $(document).ready(function(){
            applyPdaHeaderMode();
            applyPdaSidebarDefault();
            loadAuth();
            updateMobileMenuLabel(getCurrentMobileLabelFromPage());

            $('#mobile-header-bar').on('click', function(e) {
                const interactive = e.target.closest('a, input, textarea, select, label');
                if (interactive) return;
                toggleMobileSidebarMenu();
            });

            $('#mobile-menu-toggle').on('click', function(e) {
                e.stopPropagation();
                toggleMobileSidebarMenu();
            });

            $('#sidebar-nav').on('click', 'a', function() {
                const shortText = $(this).find('.sidebar-nav-text').text().trim();
                if (shortText) {
                    updateMobileMenuLabel('S-WMS/' + shortText);
                }
                closeMobileSidebarMenu();
            });
        });

        window.addEventListener('resize', function() {
            applyPdaHeaderMode();
            applyPdaSidebarDefault();
            renderAuthBlock(currentAuthUser);
            if (window.innerWidth >= 768) {
                const nav = document.getElementById('sidebar-nav');
                if (nav) nav.classList.remove('hidden');
            } else {
                closeMobileSidebarMenu();
            }
        });
    </script>
</body>
</html>