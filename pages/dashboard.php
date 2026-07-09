<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Leader trở lên.</div>';
    return;
}
?>

<style>
    .flight-board-wrap {
        max-width: 1280px;
        margin: 0 auto;
    }

    .flight-board-card {
        background: linear-gradient(180deg, #0e1726 0%, #090f1a 100%);
        border: 1px solid #1f2937;
        border-radius: 16px;
        box-shadow: 0 20px 45px rgba(2, 6, 23, 0.35);
        color: #f8fafc;
    }

    .flight-title {
        font-size: 1.25rem;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .flight-subtitle {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        font-weight: 700;
    }

    .flight-filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .flight-date-input {
        height: 40px;
        border-radius: 10px;
        border: 1px solid #334155;
        background: #0b1220;
        color: #e2e8f0;
        font-weight: 700;
        padding: 0 12px;
    }

    .flight-btn {
        height: 38px;
        border: 1px solid #334155;
        border-radius: 999px;
        background: #0f172a;
        color: #cbd5e1;
        padding: 0 13px;
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .flight-btn:hover {
        border-color: #475569;
        color: #f8fafc;
    }

    .flight-btn.active {
        background: #f59e0b;
        border-color: #f59e0b;
        color: #111827;
    }

    .flight-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .flight-kpi {
        background: rgba(15, 23, 42, 0.68);
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 10px;
    }

    .flight-kpi-label {
        color: #94a3b8;
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .flight-kpi-value {
        margin-top: 5px;
        font-size: 1.1rem;
        font-weight: 900;
        color: #f8fafc;
    }

    .flight-table-wrap {
        margin-top: 14px;
        border: 1px solid #1f2937;
        border-radius: 12px;
        overflow: auto;
        background: #050a13;
    }

    .flight-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
        font-family: "Consolas", "Courier New", monospace;
        color: #f8fafc;
    }

    .flight-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #111827;
        color: #fbbf24;
        font-size: 0.78rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 800;
        padding: 12px 10px;
        border-bottom: 1px solid #374151;
    }

    .flight-table td {
        padding: 11px 10px;
        border-bottom: 1px solid rgba(51, 65, 85, 0.45);
        font-size: 0.86rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .flight-table tbody tr:nth-child(odd) {
        background: rgba(15, 23, 42, 0.36);
    }

    .flight-table tbody tr:hover {
        background: rgba(30, 41, 59, 0.68);
    }

    .flight-col-command {
        font-size: 1rem;
        color: #f8fafc;
        letter-spacing: 0.03em;
    }

    .flight-state {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .flight-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        box-shadow: 0 0 14px rgba(148, 163, 184, 0.35);
    }

    .dot-green {
        background: #22c55e;
        box-shadow: 0 0 14px rgba(34, 197, 94, 0.6);
    }

    .dot-amber {
        background: #f59e0b;
        box-shadow: 0 0 14px rgba(245, 158, 11, 0.6);
    }

    .dot-red {
        background: #ef4444;
        box-shadow: 0 0 14px rgba(239, 68, 68, 0.6);
    }

    .flight-muted {
        color: #94a3b8;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .flight-empty {
        padding: 24px;
        text-align: center;
        color: #94a3b8;
        font-weight: 700;
        font-size: 0.92rem;
    }

    @media (max-width: 900px) {
        .flight-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>

<div class="flight-board-wrap pb-4">
    <div class="flight-board-card p-4 md:p-5">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
            <div>
                <div class="flight-subtitle">S-WMS Export Monitor</div>
                <h3 class="flight-title">Bảng tiến độ xuất hàng</h3>
                <div class="flight-muted mt-1">Theo dõi tiến độ picking, packing và pickup theo ngày tạo lệnh.</div>
            </div>
            <div id="flight-status" class="flight-muted">Đang tải dữ liệu...</div>
        </div>

        <div class="flight-filter-row mt-4">
            <input id="flight-date" class="flight-date-input" type="date">
            <button type="button" class="flight-btn" data-day-offset="-1">Hôm qua</button>
            <button type="button" class="flight-btn active" data-day-offset="0">Hôm nay</button>
            <button type="button" class="flight-btn" data-day-offset="1">Ngày mai</button>
            <button type="button" id="btn-refresh-flight" class="flight-btn">Tải lại</button>
        </div>

        <div class="flight-kpi-grid mt-4">
            <div class="flight-kpi">
                <div class="flight-kpi-label">Total Invoice</div>
                <div id="kpi-commands" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Picking complete</div>
                <div id="kpi-picking" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Packing complete</div>
                <div id="kpi-packing" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Pickup complete</div>
                <div id="kpi-pickup" class="flight-kpi-value">0</div>
            </div>
        </div>

        <div class="flight-table-wrap">
            <table class="flight-table">
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
                    <tr><td colspan="6" class="flight-empty">Đang tải dữ liệu...</td></tr>
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
    const el = $('#flight-status');
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
        body.html('<tr><td colspan="6" class="flight-empty">Không có invoice nào trong ngày đã chọn.</td></tr>');
        return;
    }

    rows.forEach(function(row) {
        const pickDone = parseInt(row.picking_done_products || 0, 10) || 0;
        const packDone = parseInt(row.packing_done_products || 0, 10) || 0;
        const totalProducts = parseInt(row.total_products || 0, 10) || 0;
        const pickedCases = parseInt(row.picked_cases || 0, 10) || 0;
        const totalCases = parseInt(row.total_cases || 0, 10) || 0;

        const pickDot = inferDotClass(pickDone, totalProducts);
        const packDot = inferDotClass(packDone, totalProducts);
        const pickupDot = inferDotClass(pickedCases, totalCases);
        // '<td>' + escHtml(row.type) + '</td>' +
        body.append(
            '<tr>' +
                '<td class="flight-col-command">' + escHtml(row.command) + '</td>' +
                '<td>SEA/AIR</td>' +
                '<td><span class="flight-state"><span class="flight-dot ' + pickDot + '"></span>' + pickDone + ' / ' + totalProducts + '</span></td>' +
                '<td><span class="flight-state"><span class="flight-dot ' + packDot + '"></span>' + packDone + ' / ' + totalProducts + '</span></td>' +
                '<td><span class="flight-state"><span class="flight-dot ' + pickupDot + '"></span>' + pickedCases + ' / ' + totalCases + '</span></td>' +
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
        const totalProducts = parseInt(row.total_products || 0, 10) || 0;
        const pickDone = parseInt(row.picking_done_products || 0, 10) || 0;
        const packDone = parseInt(row.packing_done_products || 0, 10) || 0;
        const totalCases = parseInt(row.total_cases || 0, 10) || 0;
        const pickedCases = parseInt(row.picked_cases || 0, 10) || 0;

        if (totalProducts > 0 && pickDone >= totalProducts) pickingComplete++;
        if (totalProducts > 0 && packDone >= totalProducts) packingComplete++;
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
        setFlightStatus('Chua chon ngay loc.', true);
        return;
    }

    setFlightStatus('Dang tai danh sach command...');

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
        setFlightStatus('Ngay ' + selectedDate + ' - Co ' + flightBoardRows.length + ' command.');
    }).fail(function(xhr) {
        let message = 'Loi tai du lieu dashboard.';
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
});
</script>
