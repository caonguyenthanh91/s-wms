<?php
// Dashboard hiển thị full screen trên monitor, không cần login
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

$bootstrapCssPath = __DIR__ . '/../assets/css/bootstrap.min.css';
$fontAwesomeCssPath = __DIR__ . '/../assets/css/all.min.css';
$customCssPath = __DIR__ . '/../assets/css/customs.css';

$bootstrapCssVersion = file_exists($bootstrapCssPath) ? (string) filemtime($bootstrapCssPath) : '1';
$fontAwesomeCssVersion = file_exists($fontAwesomeCssPath) ? (string) filemtime($fontAwesomeCssPath) : '1';
$customCssVersion = file_exists($customCssPath) ? (string) filemtime($customCssPath) : '1';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S-WMS Export Monitor - Bảng tiến độ xuất hàng</title>
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $assetBaseUrl; ?>assets/img/icon.png">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/bootstrap.min.css?v=<?php echo $bootstrapCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/all.min.css?v=<?php echo $fontAwesomeCssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $assetBaseUrl; ?>assets/css/customs.css?v=<?php echo $customCssVersion; ?>">
    <script src="<?php echo $assetBaseUrl; ?>assets/js/jquery.min.js"></script>
    <script src="<?php echo $assetBaseUrl; ?>assets/js/tailwindcss.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
        }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        body {
            background: #f3f4f6;
            font-family: system-ui, -apple-system, sans-serif;
        }
        #dashboard-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .dashboard-header {
            flex-shrink: 0;
            padding: 1.5rem;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .dashboard-content {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .dashboard-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .dashboard-kpi {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .dashboard-kpi-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .dashboard-kpi-value {
            font-size: 1.875rem;
            font-weight: bold;
            color: #1f2937;
        }
        .dashboard-table-wrap {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .dashboard-table thead {
            background: #f3f4f6;
            border-bottom: 2px solid #e5e7eb;
        }
        .dashboard-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        .dashboard-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .dashboard-table tbody tr:hover {
            background: #f9fafb;
        }
        .dashboard-table-col-command {
            font-weight: 600;
            color: #1f2937;
            font-family: monospace;
        }
        .dashboard-state {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dashboard-dot {
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .dot-red { background: #ef4444; }
        .dot-amber { background: #f59e0b; }
        .dot-green { background: #10b981; }
        .dashboard-empty {
            text-align: center;
            padding: 3rem 1rem;
            color: #9ca3af;
        }
        .dashboard-title {
            font-size: 1.875rem;
            font-weight: bold;
            color: #1f2937;
            margin: 0;
        }
        .dashboard-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .dashboard-muted {
            font-size: 0.875rem;
            color: #9ca3af;
        }
        .dashboard-filter-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .dashboard-date-input,
        .dashboard-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: white;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .dashboard-date-input {
            font-family: monospace;
        }
        .dashboard-btn:hover {
            background: #f3f4f6;
        }
        .dashboard-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        #dashboard-status {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .dashboard-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .dashboard-header-left div:first-child {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div id="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-header-top">
                <div>
                    <div class="dashboard-subtitle">S-WMS Export Monitor</div>
                    <h3 class="dashboard-title">Bảng tiến độ xuất hàng</h3>
                    <div class="dashboard-muted" style="margin-top: 0.25rem;">Theo dõi tiến độ picking, packing và pickup theo ngày tạo lệnh.</div>
                </div>
                <div id="dashboard-status" class="dashboard-muted">Đang tải dữ liệu...</div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="dashboard-filter-row">
                <input id="flight-date" class="dashboard-date-input" type="date">
                <button type="button" class="dashboard-btn" data-day-offset="-1">Hôm qua</button>
                <button type="button" class="dashboard-btn active" data-day-offset="0">Hôm nay</button>
                <button type="button" class="dashboard-btn" data-day-offset="1">Ngày mai</button>
                <button type="button" id="btn-refresh-flight" class="dashboard-btn">Tải lại</button>
            </div>

            <div class="dashboard-kpi-grid">
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi-label">Total Invoice</div>
                    <div id="kpi-commands" class="dashboard-kpi-value">0</div>
                </div>
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi-label">Picking complete</div>
                    <div id="kpi-picking" class="dashboard-kpi-value">0</div>
                </div>
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi-label">Packing complete</div>
                    <div id="kpi-packing" class="dashboard-kpi-value">0</div>
                </div>
                <div class="dashboard-kpi">
                    <div class="dashboard-kpi-label">Pickup complete</div>
                    <div id="kpi-pickup" class="dashboard-kpi-value">0</div>
                </div>
            </div>

            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Type</th>
                            <th>Picking</th>
                            <th>Packing</th>
                            <th>Pickup</th>
                            <th>Pickup Time</th>
                        </tr>
                    </thead>
                    <tbody id="flight-board-body">
                        <tr><td colspan="6" class="dashboard-empty">Đang tải dữ liệu...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let flightBoardRows = [];

        function pad2(v) {
            return String(v).padStart(2, '0');
        }

        function toYmd(dateObj) {
            return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
        }

        function escHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setFlightStatus(text, isError) {
            const el = $('#dashboard-status');
            el.text(text || '');
            el.toggleClass('text-red-400', !!isError);
        }

        function setDateByOffset(dayOffset) {
            const dt = new Date();
            dt.setHours(0, 0, 0, 0);
            dt.setDate(dt.getDate() + dayOffset);
            $('#flight-date').val(toYmd(dt));
        }

        function markActiveQuickButton(offset) {
            $('[data-day-offset]').removeClass('active');
            $('[data-day-offset="' + offset + '"]').addClass('active');
        }

        function inferDotClass(done, total) {
            if (total <= 0) return 'dot-red';
            if (done >= total) return 'dot-green';
            if (done > 0) return 'dot-amber';
            return 'dot-red';
        }

        function formatPickupTime(value) {
            if (!value) return '--';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(dt.getTime())) return '--';
            return pad2(dt.getHours()) + ':' + pad2(dt.getMinutes()) + ' ' + pad2(dt.getDate()) + '/' + pad2(dt.getMonth() + 1);
        }

        function renderFlightRows(rows) {
            const body = $('#flight-board-body');
            body.empty();

            if (!rows || !rows.length) {
                body.html('<tr><td colspan="6" class="dashboard-empty">Không có invoice nào trong ngày đã chọn.</td></tr>');
                return;
            }

            rows.forEach(function(row) {
                // Items: picking, packing
                const pickingItems = parseInt(row.picking_items || 0, 10) || 0;
                const packingItems = parseInt(row.packing_items || 0, 10) || 0;
                const totalItems = parseInt(row.total_items || 0, 10) || 0;

                // Cases: pickup
                const pickedCases = parseInt(row.picked_cases || 0, 10) || 0;
                const totalCases = parseInt(row.total_cases || 0, 10) || 0;

                const pickDot = inferDotClass(pickingItems, totalItems);
                const packDot = inferDotClass(packingItems, totalItems);
                const pickupDot = inferDotClass(pickedCases, totalCases);

                body.append(
                    '<tr>' +
                        '<td class="dashboard-table-col-command">' + escHtml(row.command) + '</td>' +
                        '<td>SEA/AIR</td>' +
                        '<td><span class="dashboard-state"><span class="dashboard-dot ' + pickDot + '"></span>' + pickingItems + ' / ' + totalItems + '</span></td>' +
                        '<td><span class="dashboard-state"><span class="dashboard-dot ' + packDot + '"></span>' + packingItems + ' / ' + totalItems + '</span></td>' +
                        '<td><span class="dashboard-state"><span class="dashboard-dot ' + pickupDot + '"></span>' + pickedCases + ' / ' + totalCases + '</span></td>' +
                        '<td>' + escHtml(formatPickupTime(row.last_pickup_at)) + '</td>' +
                    '</tr>'
                );
            });
        }

        function renderFlightKpi(rows) {
            const totalCommands = rows.length;
            let pickingComplete = 0;
            let packingComplete = 0;
            let pickupComplete = 0;

            rows.forEach(function(row) {
                // Items
                const totalItems = parseInt(row.total_items || 0, 10) || 0;
                const pickingItems = parseInt(row.picking_items || 0, 10) || 0;
                const packingItems = parseInt(row.packing_items || 0, 10) || 0;
                // Cases
                const totalCases = parseInt(row.total_cases || 0, 10) || 0;
                const pickedCases = parseInt(row.picked_cases || 0, 10) || 0;

                if (totalItems > 0 && pickingItems >= totalItems) pickingComplete++;
                if (totalItems > 0 && packingItems >= totalItems) packingComplete++;
                if (totalCases > 0 && pickedCases >= totalCases) pickupComplete++;
            });

            $('#kpi-commands').text(totalCommands);
            $('#kpi-picking').text(pickingComplete + ' / ' + totalCommands);
            $('#kpi-packing').text(packingComplete + ' / ' + totalCommands);
            $('#kpi-pickup').text(pickupComplete + ' / ' + totalCommands);
        }

        function loadCommandFlightBoard() {
            const selectedDate = $('#flight-date').val();
            if (!selectedDate) {
                setFlightStatus('Chưa chọn ngày lọc.', true);
                return;
            }

            setFlightStatus('Đang tải danh sách command...');

            $.getJSON('api.php?action=get_command_flight_board', { date: selectedDate }, function(res) {
                if (!res || !res.success) {
                    setFlightStatus((res && res.message) ? res.message : 'Không thể tải dữ liệu dashboard.', true);
                    renderFlightRows([]);
                    renderFlightKpi([]);
                    return;
                }

                flightBoardRows = res.rows || [];
                renderFlightRows(flightBoardRows);
                renderFlightKpi(flightBoardRows);
                setFlightStatus('Ngày ' + selectedDate + ' - Có ' + flightBoardRows.length + ' command.');
            }).fail(function(xhr) {
                let message = 'Lỗi tải dữ liệu dashboard.';
                try {
                    const response = JSON.parse(xhr.responseText || '{}');
                    if (response.message) message = response.message;
                } catch (e) {}
                setFlightStatus(message, true);
                renderFlightRows([]);
                renderFlightKpi([]);
            });
        }

        $(document).ready(function() {
            setDateByOffset(0);
            markActiveQuickButton(0);
            loadCommandFlightBoard();

            $('[data-day-offset]').on('click', function() {
                const offset = parseInt($(this).attr('data-day-offset') || '0', 10) || 0;
                setDateByOffset(offset);
                markActiveQuickButton(offset);
                loadCommandFlightBoard();
            });

            $('#flight-date').on('change', function() {
                $('[data-day-offset]').removeClass('active');
                loadCommandFlightBoard();
            });

            $('#btn-refresh-flight').on('click', function() {
                loadCommandFlightBoard();
            });

            // Auto reload mỗi 5 phút (300000ms)
            setInterval(function() {
                loadCommandFlightBoard();
            }, 300000);
        });
    </script>
</body>
</html>
