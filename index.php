<?php 
// Start output buffering to allow header redirects
ob_start();

require_once 'config/db.php';
require_once 'config/session_init.php';
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';
$page = $_GET['page'] ?? 'inventory';

$pageShortLabels = [
    'import' => 'Nhập Pallet',
    'pallet_receive' => 'Nhận Pallet',
    'inbound' => 'Nhập kho',
    'outbound' => 'Xuất kho',
    'packing' => 'Packing',
    'pickup' => 'Pickup',
    'check_box' => 'Check Box',
    'check_inventory' => 'Kiểm kê',
    'check_dashboard' => 'DB Kiểm kê',
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
    'data_import' => 'Nhập file',
    'wms_import' => 'WMS Import',
    'system_check' => 'Kiểm tra Hệ Thống',
    'admin' => 'Quản trị',
    'packing_ver_2' => 'Test_ORC',
];

$normalizedRole = $role === '' ? 'Guest' : $role;
$roleHierarchy = [
    'Guest' => 1,
    'Staff' => 2,
    'Leader' => 3,
    'Manager' => 4,
    'Admin' => 5,
];
$currentRoleRank = $roleHierarchy[$normalizedRole] ?? $roleHierarchy['Guest'];

$sidebarMenuItems = [
    ['page' => 'inventory', 'label' => 'Tra tồn', 'icon' => '📦', 'min_role' => 'Guest'],
    ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => '🛫', 'min_role' => 'Guest'],
    ['page' => 'import', 'label' => 'Nhận hàng (Pallet)', 'icon' => '🚚', 'min_role' => 'Staff'],
    ['page' => 'pallet_receive', 'label' => 'Nhận Pallet (Kho Tổng)', 'icon' => '🏭', 'min_role' => 'Staff'],
    ['page' => 'picking', 'label' => 'Picking', 'icon' => '🧭', 'min_role' => 'Staff'],
    ['page' => 'packing', 'label' => 'Packing', 'icon' => '📫', 'min_role' => 'Staff'],
    ['page' => 'pickup', 'label' => 'Pickup', 'icon' => '🚛', 'min_role' => 'Staff'],
    ['page' => 'check_box', 'label' => 'Check Box', 'icon' => '✅', 'min_role' => 'Staff'],
    ['page' => 'check_inventory', 'label' => 'Kiểm kê', 'icon' => '📋', 'min_role' => 'Staff'],
    ['page' => 'check_dashboard', 'label' => 'Dashboard Kiểm kê', 'icon' => '📊', 'min_role' => 'Staff'],
    ['page' => 'transfer', 'label' => 'Pallet >>> Kệ', 'icon' => '🔄', 'min_role' => 'Leader'],
    ['page' => 'inbound', 'label' => 'Nhập Kho', 'icon' => '📥', 'min_role' => 'Leader'],
    ['page' => 'outbound', 'label' => 'Xuất Kho', 'icon' => '📤', 'min_role' => 'Leader'],
    ['page' => 'change', 'label' => 'Đổi kệ', 'icon' => '↔️', 'min_role' => 'Leader'],
    ['page' => 'packing_ver_2', 'label' => 'Test_ORC', 'icon' => '🧪', 'min_role' => 'Staff'],
    ['page' => 'print', 'label' => 'In phiếu [Picking]', 'icon' => '🖨️', 'min_role' => 'Leader'],
    ['page' => 'print_case', 'label' => 'In tem [Packing]', 'icon' => '📦', 'min_role' => 'Leader'],
    ['page' => 'print_pallet', 'label' => 'In tem [Pallet]', 'icon' => '📮', 'min_role' => 'Leader'],
    ['page' => 'layout', 'label' => 'Layout', 'icon' => '📅', 'min_role' => 'Manager'],
    ['page' => 'shelves', 'label' => 'Kệ hàng', 'icon' => '🛒', 'min_role' => 'Manager'],
    ['page' => 'products', 'label' => 'Sản phẩm', 'icon' => '🏷️', 'min_role' => 'Manager'],
    ['page' => 'data_export', 'label' => 'Data Export', 'icon' => '📄', 'min_role' => 'Manager'],
    ['page' => 'wms_import', 'label' => 'WMS Import', 'icon' => '📥', 'min_role' => 'Manager'],
    ['page' => 'data_import', 'label' => 'Data Import', 'icon' => '📥', 'min_role' => 'Admin'],
    ['page' => 'admin', 'label' => 'Quản trị', 'icon' => '🔧', 'min_role' => 'Manager'],
];

$allowedPages = [];
foreach ($sidebarMenuItems as $menuItem) {
    if ($currentRoleRank >= ($roleHierarchy[$menuItem['min_role']] ?? PHP_INT_MAX)) {
        $allowedPages[] = $menuItem['page'];
    }
}

if ($currentRoleRank >= $roleHierarchy['Admin']) {
    $allowedPages[] = 'system_check';
}

$allowedPages = array_values(array_unique($allowedPages));

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
            <div id="mobile-header-bar" class="md:hidden px-3 py-2 border-b border-slate-700 flex items-center justify-between">
                <button id="mobile-menu-toggle" type="button" onclick="openMobileMenuOverlay()" class="font-bold text-base tracking-wide flex items-center gap-2">
                    <i id="mobile-menu-icon" class="fas fa-bars text-lg"></i>
                    <span id="mobile-menu-label"><?php echo htmlspecialchars($mobileHeaderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
                <div id="auth-block-mobile" class="text-xs text-gray-300">Đang tải...</div>
            </div>
            <div class="hidden md:flex p-6 text-2xl font-bold border-b border-slate-700 whitespace-nowrap items-center">
                <span class="sidebar-title-full">S-WMS</span>
                <span class="sidebar-title-collapsed hidden"><small>S-WMS</small></span>
            </div>
            <nav id="sidebar-nav" class="hidden md:block p-4 space-y-2">
                <?php foreach ($sidebarMenuItems as $menuItem): ?>
                <?php if ($currentRoleRank >= ($roleHierarchy[$menuItem['min_role']] ?? PHP_INT_MAX)): ?>
                <a href="?page=<?php echo urlencode($menuItem['page']); ?>" class="sidebar-nav-link block p-3 hover:bg-slate-700 rounded transition <?php echo $page === $menuItem['page'] ? 'bg-blue-600' : ''; ?> flex items-center justify-start">
                    <span class="sidebar-nav-icon"><?php echo $menuItem['icon']; ?></span>
                    <span class="ml-3 sidebar-nav-text"><?php echo htmlspecialchars($menuItem['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php endif; ?>
                <?php endforeach; ?>
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
                    if (in_array($page, $allowedPages, true)) {
                        include "pages/$page.php";
                    } else {
                        include "pages/inventory.php";
                    }
                ?>
            </div>
        </main>
    </div>

    <!-- Menu chức năng dạng lưới (menu.php) - chỉ hiển thị trên màn hình nhỏ khi bấm hamburger -->
    <div id="mobile-menu-overlay" class="fixed inset-0 z-[9998] bg-gray-100 overflow-y-auto hidden md:hidden">
        <div class="sticky top-0 z-10 flex items-center justify-between px-4 py-3 bg-slate-800 text-white shadow">
            <span class="font-bold text-lg flex items-center gap-2"><i class="fas fa-th-large"></i> Menu chức năng</span>
            <button type="button" onclick="closeMobileMenuOverlay()" aria-label="Đóng" class="text-3xl leading-none px-2 -my-1">&times;</button>
        </div>
        <div class="p-4">
            <?php include __DIR__ . '/menu.php'; ?>
        </div>
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
            return ['import', 'inbound', 'outbound', 'transfer', 'change', 'inventory', 'packing', 'pickup', 'check_box', 'check_inventory', 'packing_ver_2'];
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

        // Chan nhap tay tren cac o quet pallet_id / shelf_id: may quet (keyboard-wedge)
        // go ky tu rat nhanh (thuong < ~40-50ms/ky tu), nguoi go tay se co khoang cach lon hon.
        // Neu phat hien khoang cach vuot nguong -> coi la nhap tay -> xoa va canh bao.
        function attachScanOnlyGuard(selector, options) {
            options = options || {};
            const maxGap = options.maxGap || 50;

            document.querySelectorAll(selector).forEach(function(el) {
                if (el.dataset.scanOnlyGuard === '1') return;
                el.dataset.scanOnlyGuard = '1';

                let lastTime = 0;
                let lastWarnTime = 0;
                const warnCooldown = 1200; // tranh mo modal lien tuc khi nguoi dung go tay nhieu ky tu lien tiep

                function warnOnce() {
                    const now = Date.now();
                    if (now - lastWarnTime < warnCooldown) return;
                    lastWarnTime = now;
                    // Bo focus de cac ky tu go tay tiep theo khong tiep tuc kich hoat guard lien tuc
                    el.blur();
                    scanOnlyWarn(options.warnMessage);
                }

                el.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey || e.altKey) return;
                    if (e.key.length !== 1) return; // bo qua Enter, Backspace, Tab, phim dieu huong...

                    const now = Date.now();
                    const currentLen = el.value.length;

                    if (currentLen === 0 || el.selectionStart === 0 && el.selectionEnd === currentLen) {
                        lastTime = now;
                        return;
                    }

                    const gap = now - lastTime;
                    lastTime = now;

                    if (gap > maxGap) {
                        e.preventDefault();
                        el.value = '';
                        lastTime = 0;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        warnOnce();
                    }
                });

                el.addEventListener('paste', function(e) {
                    e.preventDefault();
                    warnOnce();
                });
            });
        }

        function scanOnlyWarn(message) {
            const text = message || 'Vui lòng quét mã bằng máy quét, không nhập tay!';
            if (typeof window.showModal === 'function') {
                window.showModal(text, 'warning');
            } else if (typeof window.showErrorModal === 'function') {
                window.showErrorModal(text);
            } else {
                alert(text);
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('qr-scanner-modal');
                if (modal && !modal.classList.contains('hidden')) {
                    closeQRScannerModal();
                    return;
                }
                const menuOverlay = document.getElementById('mobile-menu-overlay');
                if (menuOverlay && !menuOverlay.classList.contains('hidden')) {
                    closeMobileMenuOverlay();
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
                // PDA portrait: giu nguyen trang thai mo/doi cua menu, khong tu dong dong khi viewport thay doi.
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

        function openMobileMenuOverlay() {
            const ov = document.getElementById('mobile-menu-overlay');
            if (!ov) return;
            ov.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenuOverlay() {
            const ov = document.getElementById('mobile-menu-overlay');
            if (!ov) return;
            ov.classList.add('hidden');
            document.body.style.overflow = '';
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
            const labels = <?php echo json_encode($pageShortLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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

            // Hamburger trên màn hình nhỏ -> mở menu chức năng dạng lưới (menu.php)
            $('#mobile-menu-toggle').on('click', function(e) {
                e.stopPropagation();
                openMobileMenuOverlay();
            });

            // Bấm 1 chức năng trong overlay -> đóng overlay (link tự điều hướng)
            $('#mobile-menu-overlay').on('click', 'a.menu-card:not(.restricted)', function() {
                closeMobileMenuOverlay();
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
                closeMobileMenuOverlay();
            }
        });
    </script>
<?php ob_end_flush(); ?>
</body>
</html>