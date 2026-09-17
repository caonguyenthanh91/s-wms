<?php
// S-WMS Checkin Monitor - Bảng tiến độ nhập hàng full-screen cho màn hình TV lớn.
// Không cần đăng nhập (Guest). Tự động tải lại dữ liệu mỗi 5 phút và cuộn danh sách tự động.
require_once __DIR__ . '/config/session_init.php';

$assetBaseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($assetBaseUrl === '') {
    $assetBaseUrl = '/';
}
if ($assetBaseUrl !== '/') {
    $assetBaseUrl .= '/';
}

$fontAwesomeCssPath = __DIR__ . '/assets/css/all.min.css';
$fontAwesomeCssVersion = file_exists($fontAwesomeCssPath) ? (string) filemtime($fontAwesomeCssPath) : '1';
?>
<!DOCTYPE html>
<html lang="vi" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S-WMS Checkin Monitor · Bảng tiến độ nhập hàng</title>
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $assetBaseUrl; ?>assets/img/icon.png">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/all.min.css?v=<?php echo $fontAwesomeCssVersion; ?>">
    <script src="<?php echo $assetBaseUrl; ?>assets/js/jquery.min.js"></script>
    <style>
        :root {
            --board-radius: 14px;
        }
        html[data-theme="dark"] {
            --bg: #0b1220;
            --bg-grad: radial-gradient(1200px 600px at 15% -10%, #17233c 0%, #0b1220 55%);
            --panel: #111c31;
            --panel-2: #0e1729;
            --row: #0f1a2e;
            --row-alt: #12203a;
            --row-hover: #16294a;
            --border: #1e2c47;
            --text: #f1f5f9;
            --text-dim: #94a3b8;
            --text-faint: #64748b;
            --accent: #38bdf8;
            --track: #1e2c47;
            --green: #22c55e;
            --green-soft: rgba(34,197,94,0.16);
            --amber: #f59e0b;
            --amber-soft: rgba(245,158,11,0.16);
            --red: #ef4444;
            --red-soft: rgba(239,68,68,0.14);
        }
        html[data-theme="light"] {
            --bg: #eef2f7;
            --bg-grad: radial-gradient(1200px 600px at 15% -10%, #ffffff 0%, #e6ecf4 55%);
            --panel: #ffffff;
            --panel-2: #f8fafc;
            --row: #ffffff;
            --row-alt: #f4f7fb;
            --row-hover: #eaf1fb;
            --border: #dce3ec;
            --text: #0f172a;
            --text-dim: #52606d;
            --text-faint: #94a3b8;
            --accent: #0284c7;
            --track: #e2e8f0;
            --green: #16a34a;
            --green-soft: rgba(22,163,74,0.14);
            --amber: #d97706;
            --amber-soft: rgba(217,119,6,0.14);
            --red: #dc2626;
            --red-soft: rgba(220,38,38,0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; }
        body {
            background: var(--bg);
            background-image: var(--bg-grad);
            color: var(--text);
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }
        #board {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: clamp(0.75rem, 1.4vw, 1.6rem);
            gap: clamp(0.6rem, 1vw, 1rem);
        }

        /* ===== Header ===== */
        .board-head {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .brand { display: flex; align-items: center; gap: 0.9rem; }
        .brand-logo {
            width: clamp(2.4rem, 3vw, 3.2rem);
            height: clamp(2.4rem, 3vw, 3.2rem);
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #6366f1);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: clamp(1.1rem, 1.6vw, 1.6rem);
            box-shadow: 0 6px 20px rgba(56,189,248,0.35);
        }
        .brand-title {
            font-size: clamp(1.15rem, 1.9vw, 2rem);
            font-weight: 800; letter-spacing: 0.02em; line-height: 1.1;
        }
        .brand-sub {
            font-size: clamp(0.7rem, 0.95vw, 0.95rem);
            color: var(--text-dim); font-weight: 500; letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .head-right { display: flex; align-items: center; gap: clamp(0.8rem, 1.6vw, 1.8rem); }
        .clock { text-align: right; }
        .clock-time {
            font-size: clamp(1.5rem, 2.8vw, 3rem);
            font-weight: 800; line-height: 1; letter-spacing: 0.02em;
        }
        .clock-time .sec { color: var(--accent); }
        .clock-date {
            font-size: clamp(0.72rem, 1vw, 1rem);
            color: var(--text-dim); font-weight: 600; margin-top: 0.2rem;
        }
        .theme-toggle {
            width: clamp(2.6rem, 3.2vw, 3.4rem);
            height: clamp(2.6rem, 3.2vw, 3.4rem);
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--panel);
            color: var(--text);
            font-size: clamp(1rem, 1.4vw, 1.4rem);
            cursor: pointer;
            transition: transform 0.15s, background 0.2s;
        }
        .theme-toggle:hover { transform: rotate(-18deg) scale(1.05); background: var(--row-hover); }

        /* ===== KPI strip ===== */
        .kpi-strip {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: clamp(0.6rem, 1vw, 1rem);
        }
        .kpi {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--board-radius);
            padding: clamp(0.7rem, 1.1vw, 1.1rem) clamp(0.9rem, 1.4vw, 1.4rem);
            display: flex; align-items: center; gap: clamp(0.7rem, 1.2vw, 1.1rem);
            position: relative; overflow: hidden;
        }
        .kpi::before {
            content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px;
            background: var(--accent);
        }
        .kpi.k-pending::before { background: var(--red); }
        .kpi.k-received::before { background: var(--amber); }
        .kpi.k-imported::before { background: var(--green); }
        .kpi.k-backlog::before { background: var(--accent); }
        .kpi-icon {
            font-size: clamp(1.2rem, 1.8vw, 1.9rem);
            color: var(--text-dim); width: 1.6em; text-align: center;
        }
        .kpi-body { display: flex; flex-direction: column; }
        .kpi-value { font-size: clamp(1.4rem, 2.6vw, 2.6rem); font-weight: 800; line-height: 1; }
        .kpi-value small { font-size: 0.5em; color: var(--text-faint); font-weight: 600; }
        .kpi-label {
            font-size: clamp(0.66rem, 0.9vw, 0.9rem); color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; margin-top: 0.25rem;
        }

        /* ===== Board table ===== */
        .board-panel {
            flex: 1; min-height: 0;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--board-radius);
            display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        .board-thead {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: var(--grid);
            background: var(--panel-2);
            border-bottom: 2px solid var(--border);
        }
        .board-thead > div {
            padding: clamp(0.6rem, 0.9vw, 1rem) clamp(0.5rem, 0.8vw, 0.9rem);
            font-size: clamp(0.66rem, 0.92vw, 0.98rem);
            font-weight: 700; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .board-thead .col-num { text-align: center; }
        .board-scroll { flex: 1; min-height: 0; overflow: hidden; position: relative; }
        .board-track { will-change: transform; }
        .brow {
            display: grid;
            grid-template-columns: var(--grid);
            align-items: center;
            border-bottom: 1px solid var(--border);
            background: var(--row);
            animation: fadein 0.4s ease;
        }
        .brow:nth-child(even) { background: var(--row-alt); }
        .brow > div { padding: clamp(0.55rem, 0.95vw, 1.05rem) clamp(0.5rem, 0.8vw, 0.9rem); }
        @keyframes fadein { from { opacity: 0; } to { opacity: 1; } }

        .cell-cmd { font-size: clamp(1rem, 1.5vw, 1.6rem); font-weight: 800; letter-spacing: 0.03em; font-family: "Consolas", "Menlo", monospace; }
        .cell-date { font-size: clamp(0.8rem, 1.1vw, 1.15rem); color: var(--text-dim); font-weight: 600; }

        /* stat cell: plain count of pallets for a given stage */
        .stat-num { font-size: clamp(1.15rem, 1.9vw, 2.1rem); font-weight: 800; line-height: 1; text-align: center; }
        .stat-num.pending { color: var(--red); }
        .stat-num.received { color: var(--amber); }
        .stat-num.imported { color: var(--green); }
        .stat-num.backlog { color: var(--accent); }

        .board-empty {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 100%; color: var(--text-faint); gap: 1rem;
            font-size: clamp(1rem, 1.6vw, 1.6rem);
        }
        .board-empty i { font-size: 3em; opacity: 0.4; }

        /* ===== Footer status ===== */
        .board-foot {
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between;
            font-size: clamp(0.68rem, 0.92vw, 0.95rem);
            color: var(--text-dim); padding: 0 0.4rem;
        }
        .foot-status { display: flex; align-items: center; gap: 0.5rem; }
        .live-dot {
            width: 0.6em; height: 0.6em; border-radius: 50%; background: var(--green);
            box-shadow: 0 0 0 0 rgba(34,197,94,0.6); animation: pulse 2s infinite;
        }
        .live-dot.err { background: var(--red); animation: none; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
            70% { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .foot-legend { display: flex; align-items: center; gap: 1.1rem; }
        .lg { display: inline-flex; align-items: center; gap: 0.4em; }
        .lg-dot { width: 0.7em; height: 0.7em; border-radius: 50%; }
        .lg-dot.green { background: var(--green); }
        .lg-dot.amber { background: var(--amber); }
        .lg-dot.red { background: var(--red); }
    </style>
</head>
<body>
    <!-- Grid template shared by header + rows: Ngày | Chủng loại | [Hôm nay] Đang đóng | [Hôm nay] Đã nhận | [Hôm nay] Đã cất kho | [Lũy kế] Nhận chưa cất -->
    <div id="board" style="--grid: 0.8fr 1.3fr 1.3fr 1.3fr 1.3fr 1.3fr;">
        <div class="board-head">
            <div class="brand">
                <div class="brand-logo"><i class="fa-solid fa-dolly"></i></div>
                <div>
                    <div class="brand-sub">S-WMS Checkin Monitor</div>
                    <div class="brand-title">Bảng tiến độ nhập hàng</div>
                </div>
            </div>
            <div class="head-right">
                <div class="clock">
                    <div id="clock-time" class="clock-time">--:--<span class="sec">:--</span></div>
                    <div id="clock-date" class="clock-date">--</div>
                </div>
                <button id="theme-toggle" class="theme-toggle" title="Đổi giao diện Sáng / Tối">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>

        <div class="kpi-strip">
            <div class="kpi k-pending">
                <div class="kpi-icon"><i class="fa-solid fa-box-open"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-pending">0</div>
                    <div class="kpi-label">[Hôm nay] Đang đóng Pallet</div>
                </div>
            </div>
            <div class="kpi k-received">
                <div class="kpi-icon"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-received">0</div>
                    <div class="kpi-label">[Hôm nay] Đã nhận Pallet</div>
                </div>
            </div>
            <div class="kpi k-imported">
                <div class="kpi-icon"><i class="fa-solid fa-warehouse"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-imported">0</div>
                    <div class="kpi-label">[Hôm nay] Đã cất kho</div>
                </div>
            </div>
            <div class="kpi k-backlog">
                <div class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-backlog">0</div>
                    <div class="kpi-label">[Lũy kế] Đã nhận chưa cất kho</div>
                </div>
            </div>
        </div>

        <div class="board-panel">
            <div class="board-thead">
                <div>Ngày</div>
                <div>Chủng loại</div>
                <div class="col-num">[Hôm nay] Đang đóng</div>
                <div class="col-num">[Hôm nay] Đã nhận</div>
                <div class="col-num">[Hôm nay] Đã cất kho</div>
                <div class="col-num">[Lũy kế] Nhận chưa cất</div>
            </div>
            <div class="board-scroll" id="board-scroll">
                <div class="board-track" id="board-track">
                    <div class="board-empty"><i class="fa-solid fa-spinner fa-spin"></i><span>Đang tải dữ liệu...</span></div>
                </div>
            </div>
        </div>

        <div class="board-foot">
            <div class="foot-status">
                <span class="live-dot" id="live-dot"></span>
                <span id="board-status">Đang khởi tạo...</span>
            </div>
            <div class="foot-legend">
                <span class="lg"><span class="lg-dot green"></span> Hoàn thành</span>
                <span class="lg"><span class="lg-dot amber"></span> Đang xử lý</span>
                <span class="lg"><span class="lg-dot red"></span> Chưa bắt đầu</span>
                <span id="foot-updated">Cập nhật: --</span>
            </div>
        </div>
    </div>

    <script>
        var RELOAD_MS = 300000;      // Tự động tải lại dữ liệu mỗi 5 phút
        var SCROLL_SPEED = 0.4;      // px mỗi frame khi cuộn tự động
        var SCROLL_PAUSE = 3500;     // dừng ở đầu/cuối danh sách (ms)

        function pad2(v) { return String(v).padStart(2, '0'); }

        function escHtml(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        var DOW = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

        function updateClock() {
            var now = new Date();
            $('#clock-time').html(pad2(now.getHours()) + ':' + pad2(now.getMinutes()) +
                '<span class="sec">:' + pad2(now.getSeconds()) + '</span>');
            $('#clock-date').text(DOW[now.getDay()] + ', ' + pad2(now.getDate()) + '/' +
                pad2(now.getMonth() + 1) + '/' + now.getFullYear());
        }

        function toYmd(d) {
            return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
        }

        function fmtDate(value) {
            if (!value) return '--';
            var d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return escHtml(value);
            return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1);
        }

        function statCell(count, cls) {
            return '<div class="stat-num ' + cls + '">' + count + '</div>';
        }

        function renderRows(rows) {
            var track = $('#board-track');
            track.empty();

            if (!rows || !rows.length) {
                track.html('<div class="board-empty"><i class="fa-solid fa-clipboard-check"></i>' +
                    '<span>Chưa có Pallet nào trong ngày.</span></div>');
                return;
            }

            var html = '';
            rows.forEach(function(row) {
                var pendingToday = parseInt(row.pending_today, 10) || 0;
                var receivedToday = parseInt(row.received_today, 10) || 0;
                var importedToday = parseInt(row.imported_today, 10) || 0;
                var backlogReceived = parseInt(row.backlog_received, 10) || 0;

                html += '<div class="brow">' +
                    '<div class="cell-date">' + fmtDate(row.date) + '</div>' +
                    '<div class="cell-cmd">' + escHtml(row.category) + '</div>' +
                    '<div class="col-num">' + statCell(pendingToday, 'pending') + '</div>' +
                    '<div class="col-num">' + statCell(receivedToday, 'received') + '</div>' +
                    '<div class="col-num">' + statCell(importedToday, 'imported') + '</div>' +
                    '<div class="col-num">' + statCell(backlogReceived, 'backlog') + '</div>' +
                '</div>';
            });
            track.html(html);
        }

        function renderKpi(kpi) {
            $('#kpi-pending').text(kpi.pending_today || 0);
            $('#kpi-received').text(kpi.received_today || 0);
            $('#kpi-imported').text(kpi.imported_today || 0);
            $('#kpi-backlog').text(kpi.backlog_received || 0);
        }

        function setStatus(text, isError) {
            $('#board-status').text(text || '');
            $('#live-dot').toggleClass('err', !!isError);
        }

        /* ===== Auto scroll (kiểu bảng thông tin sân bay) ===== */
        var scrollRAF = null, scrollState = 'wait-top', waitUntil = 0;

        function stopAutoScroll() {
            if (scrollRAF) { cancelAnimationFrame(scrollRAF); scrollRAF = null; }
        }

        function startAutoScroll() {
            stopAutoScroll();
            var el = document.getElementById('board-scroll');
            el.scrollTop = 0;
            scrollState = 'wait-top';
            waitUntil = performance.now() + SCROLL_PAUSE;

            function step(now) {
                var maxScroll = el.scrollHeight - el.clientHeight;
                if (maxScroll <= 2) { scrollRAF = requestAnimationFrame(step); return; }

                if (scrollState === 'wait-top') {
                    if (now >= waitUntil) scrollState = 'down';
                } else if (scrollState === 'down') {
                    el.scrollTop += SCROLL_SPEED;
                    if (el.scrollTop >= maxScroll - 1) {
                        el.scrollTop = maxScroll;
                        scrollState = 'wait-bottom';
                        waitUntil = now + SCROLL_PAUSE;
                    }
                } else if (scrollState === 'wait-bottom') {
                    if (now >= waitUntil) { el.scrollTop = 0; scrollState = 'wait-top'; waitUntil = now + SCROLL_PAUSE; }
                }
                scrollRAF = requestAnimationFrame(step);
            }
            scrollRAF = requestAnimationFrame(step);
        }

        /* ===== Load data ===== */
        function loadBoard() {
            var date = toYmd(new Date());
            $.getJSON('api.php?action=get_checkin_board', { date: date }, function(res) {
                if (!res || !res.success) {
                    setStatus((res && res.message) ? res.message : 'Không thể tải dữ liệu.', true);
                    return;
                }
                var rows = res.rows || [];
                renderRows(rows);
                renderKpi(res.kpi || {});
                var now = new Date();
                setStatus('Trực tiếp · ' + rows.length + ' chủng loại trong ngày', false);
                $('#foot-updated').text('Cập nhật: ' + pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds()));
                startAutoScroll();
            }).fail(function(xhr) {
                var msg = 'Lỗi kết nối máy chủ.';
                try { var r = JSON.parse(xhr.responseText || '{}'); if (r.message) msg = r.message; } catch (e) {}
                setStatus(msg, true);
            });
        }

        /* ===== Theme ===== */
        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            $('#theme-toggle i').attr('class', theme === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun');
            try { localStorage.setItem('swms-checkin-theme', theme); } catch (e) {}
        }

        $(document).ready(function() {
            var saved = 'dark';
            try { saved = localStorage.getItem('swms-checkin-theme') || 'dark'; } catch (e) {}
            applyTheme(saved);

            $('#theme-toggle').on('click', function() {
                var cur = document.documentElement.getAttribute('data-theme');
                applyTheme(cur === 'dark' ? 'light' : 'dark');
            });

            updateClock();
            setInterval(updateClock, 1000);

            loadBoard();
            setInterval(loadBoard, RELOAD_MS);

            // Nếu cửa sổ đổi kích thước thì tính lại vùng cuộn
            var rt;
            window.addEventListener('resize', function() {
                clearTimeout(rt);
                rt = setTimeout(startAutoScroll, 400);
            });
        });
    </script>
</body>
</html>
