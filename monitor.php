<?php
// S-WMS Export Monitor - Bảng tiến độ xuất hàng full-screen cho màn hình TV lớn.
// Không cần đăng nhập (Guest). Tự động tải lại dữ liệu mỗi 5 phút và cuộn danh sách tự động.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    <title>S-WMS Export Monitor · Bảng tiến độ xuất hàng</title>
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
        .kpi.k-picking::before { background: var(--amber); }
        .kpi.k-packing::before { background: var(--accent); }
        .kpi.k-pickup::before { background: var(--green); }
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
        .cell-cmd .cust { display:block; font-size: 0.62em; color: var(--text-dim); font-weight: 600; letter-spacing: 0.04em; margin-top: 0.15rem; }
        .cell-date { font-size: clamp(0.8rem, 1.1vw, 1.15rem); color: var(--text-dim); font-weight: 600; }

        .chip {
            display: inline-flex; align-items: center; gap: 0.4em;
            padding: 0.25em 0.7em; border-radius: 999px;
            font-size: clamp(0.72rem, 1vw, 1.05rem); font-weight: 700; letter-spacing: 0.05em;
        }
        .chip-sea { background: rgba(56,189,248,0.16); color: var(--accent); }
        .chip-air { background: rgba(99,102,241,0.18); color: #818cf8; }

        /* progress cell */
        .prog { display: flex; flex-direction: column; gap: 0.35rem; }
        .prog-head { display: flex; align-items: baseline; gap: 0.15rem; font-weight: 800; }
        .prog-done { font-size: clamp(1.05rem, 1.7vw, 1.9rem); }
        .prog-sep { font-size: clamp(0.85rem, 1.2vw, 1.3rem); color: var(--text-faint); }
        .prog-total { font-size: clamp(0.85rem, 1.2vw, 1.3rem); color: var(--text-dim); }
        .prog-bar { height: clamp(6px, 0.7vw, 10px); border-radius: 999px; background: var(--track); overflow: hidden; }
        .prog-fill { height: 100%; border-radius: 999px; transition: width 0.6s ease; }
        .prog-remain { font-size: clamp(0.64rem, 0.9vw, 0.92rem); font-weight: 600; letter-spacing: 0.02em; }

        .is-done .prog-done { color: var(--green); }
        .is-done .prog-fill { background: var(--green); }
        .is-done .prog-remain { color: var(--green); }
        .is-partial .prog-done { color: var(--amber); }
        .is-partial .prog-fill { background: var(--amber); }
        .is-partial .prog-remain { color: var(--amber); }
        .is-none .prog-done { color: var(--red); }
        .is-none .prog-fill { background: var(--red); }
        .is-none .prog-remain { color: var(--red); }

        .cell-pickup-time { font-size: clamp(0.85rem, 1.15vw, 1.25rem); font-weight: 700; color: var(--text-dim); }
        .cell-pickup-time.done { color: var(--green); }

        .status-pill {
            display: inline-flex; align-items: center; gap: 0.45em;
            font-size: clamp(0.7rem, 0.95vw, 1rem); font-weight: 700;
            padding: 0.3em 0.7em; border-radius: 999px;
        }
        .status-pill i { font-size: 0.85em; }
        .st-done { background: var(--green-soft); color: var(--green); }
        .st-progress { background: var(--amber-soft); color: var(--amber); }
        .st-wait { background: var(--red-soft); color: var(--red); }

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
    <!-- Grid template shared by header + rows: Invoice | Date | Type | Picking | Packing | Pickup | Status | Time -->
    <div id="board" style="--grid: 1.5fr 0.9fr 0.8fr 1.4fr 1.4fr 1.4fr 1fr 0.9fr;">
        <div class="board-head">
            <div class="brand">
                <div class="brand-logo"><i class="fa-solid fa-truck-fast"></i></div>
                <div>
                    <div class="brand-sub">S-WMS Export Monitor</div>
                    <div class="brand-title">Bảng tiến độ xuất hàng</div>
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
            <div class="kpi">
                <div class="kpi-icon"><i class="fa-solid fa-file-invoice"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-total">0</div>
                    <div class="kpi-label">Tổng Invoice</div>
                </div>
            </div>
            <div class="kpi k-picking">
                <div class="kpi-icon"><i class="fa-solid fa-hand"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-picking">0<small> /0</small></div>
                    <div class="kpi-label">Picking xong</div>
                </div>
            </div>
            <div class="kpi k-packing">
                <div class="kpi-icon"><i class="fa-solid fa-box"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-packing">0<small> /0</small></div>
                    <div class="kpi-label">Packing xong</div>
                </div>
            </div>
            <div class="kpi k-pickup">
                <div class="kpi-icon"><i class="fa-solid fa-truck-ramp-box"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value" id="kpi-pickup">0<small> /0</small></div>
                    <div class="kpi-label">Pickup xong</div>
                </div>
            </div>
        </div>

        <div class="board-panel">
            <div class="board-thead">
                <div>Invoice / Khách hàng</div>
                <div>Ngày xuất</div>
                <div>Vận chuyển</div>
                <div class="col-num">Picking (SP)</div>
                <div class="col-num">Packing (kiện)</div>
                <div class="col-num">Pickup (kiện)</div>
                <div class="col-num">Trạng thái</div>
                <div class="col-num">Giờ bốc</div>
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

        function fmtPickupTime(value) {
            if (!value) return '--';
            var d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '--';
            return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        }

        function progClass(done, total) {
            if (total <= 0) return 'is-none';
            if (done >= total) return 'is-done';
            if (done > 0) return 'is-partial';
            return 'is-none';
        }

        function progCell(done, total, remainLabel) {
            var cls = progClass(done, total);
            var pct = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0;
            var remain = Math.max(0, total - done);
            return '<div class="prog ' + cls + '">' +
                '<div class="prog-head"><span class="prog-done">' + done + '</span>' +
                '<span class="prog-sep">/</span><span class="prog-total">' + total + '</span></div>' +
                '<div class="prog-bar"><div class="prog-fill" style="width:' + pct + '%"></div></div>' +
                '<div class="prog-remain">' + (remain > 0 ? 'Còn ' + remain + ' ' + remainLabel : '✓ Đủ') + '</div>' +
                '</div>';
        }

        function statusPill(pickDone, packDone, pickupDone, totalItems, totalCases) {
            if (totalItems <= 0 && totalCases <= 0) {
                return '<span class="status-pill st-wait"><i class="fa-solid fa-hourglass-start"></i> Chờ</span>';
            }
            if (pickupDone) {
                return '<span class="status-pill st-done"><i class="fa-solid fa-circle-check"></i> Hoàn tất</span>';
            }
            if (pickDone && packDone) {
                return '<span class="status-pill st-progress"><i class="fa-solid fa-truck-ramp-box"></i> Chờ bốc</span>';
            }
            if (pickDone) {
                return '<span class="status-pill st-progress"><i class="fa-solid fa-box"></i> Packing</span>';
            }
            return '<span class="status-pill st-progress"><i class="fa-solid fa-hand"></i> Picking</span>';
        }

        function renderRows(rows) {
            var track = $('#board-track');
            track.empty();

            if (!rows || !rows.length) {
                track.html('<div class="board-empty"><i class="fa-solid fa-clipboard-check"></i>' +
                    '<span>Chưa có Invoice nào trong ngày.</span></div>');
                return;
            }

            var html = '';
            rows.forEach(function(row) {
                var totalItems = parseInt(row.total_items, 10) || 0;      // count distinct product_id
                var pickItems = parseInt(row.picking_items, 10) || 0;     // count distinct product_id done picking
                var packItems = parseInt(row.packing_items, 10) || 0;     // count distinct case_no done packing
                var totalCases = parseInt(row.total_cases, 10) || 0;      // count distinct case_no
                var pickedCases = parseInt(row.picked_cases, 10) || 0;    // count distinct case_no done pickup

                var pickDone = totalItems > 0 && pickItems >= totalItems;       // picking: product_id vs product_id
                var packDone = totalCases > 0 && packItems >= totalCases;       // packing: case_no vs case_no
                var pickupDone = totalCases > 0 && pickedCases >= totalCases;   // pickup: case_no vs case_no

                var type = String(row.transport_type || 'SEA').toUpperCase();
                var chipCls = (type.indexOf('AIR') >= 0) ? 'chip-air' : 'chip-sea';
                var chipIcon = (type.indexOf('AIR') >= 0) ? 'fa-plane' : 'fa-ship';
                var cust = String(row.for_product || '').trim();

                var pickupTime = fmtPickupTime(row.last_pickup_at);

                html += '<div class="brow">' +
                    '<div class="cell-cmd">' + escHtml(row.command) +
                        (cust ? '<span class="cust"><i class="fa-solid fa-user-tag"></i> ' + escHtml(cust) + '</span>' : '') +
                    '</div>' +
                    '<div class="cell-date">' + fmtDate(row.export_date) + '</div>' +
                    '<div><span class="chip ' + chipCls + '"><i class="fa-solid ' + chipIcon + '"></i> ' + escHtml(type) + '</span></div>' +
                    '<div>' + progCell(pickItems, totalItems, 'SP') + '</div>' +
                    '<div>' + progCell(packItems, totalCases, 'kiện') + '</div>' +
                    '<div>' + progCell(pickedCases, totalCases, 'kiện') + '</div>' +
                    '<div class="col-num" style="text-align:center;">' +
                        statusPill(pickDone, packDone, pickupDone, totalItems, totalCases) + '</div>' +
                    '<div class="cell-pickup-time' + (pickupDone ? ' done' : '') + '" style="text-align:center;">' +
                        escHtml(pickupTime) + '</div>' +
                '</div>';
            });
            track.html(html);
        }

        function renderKpi(rows) {
            var total = rows.length, pk = 0, pc = 0, pu = 0;
            rows.forEach(function(row) {
                var ti = parseInt(row.total_items, 10) || 0;
                var tc = parseInt(row.total_cases, 10) || 0;
                if (ti > 0 && (parseInt(row.picking_items, 10) || 0) >= ti) pk++;        // picking: product_id vs product_id
                if (tc > 0 && (parseInt(row.packing_items, 10) || 0) >= tc) pc++;        // packing: case_no vs case_no
                if (tc > 0 && (parseInt(row.picked_cases, 10) || 0) >= tc) pu++;         // pickup: case_no vs case_no
            });
            $('#kpi-total').text(total);
            $('#kpi-picking').html(pk + '<small> /' + total + '</small>');
            $('#kpi-packing').html(pc + '<small> /' + total + '</small>');
            $('#kpi-pickup').html(pu + '<small> /' + total + '</small>');
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
            $.getJSON('api.php?action=get_monitor_board', { date: date }, function(res) {
                if (!res || !res.success) {
                    setStatus((res && res.message) ? res.message : 'Không thể tải dữ liệu.', true);
                    return;
                }
                var rows = res.rows || [];
                renderRows(rows);
                renderKpi(rows);
                var now = new Date();
                setStatus('Trực tiếp · ' + rows.length + ' Invoice trong ngày', false);
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
            try { localStorage.setItem('swms-monitor-theme', theme); } catch (e) {}
        }

        $(document).ready(function() {
            var saved = 'dark';
            try { saved = localStorage.getItem('swms-monitor-theme') || 'dark'; } catch (e) {}
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
