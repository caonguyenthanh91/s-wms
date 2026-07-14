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
                <div class="flight-kpi-label">Tổng Invoice</div>
                <div id="kpi-commands" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Picking xong</div>
                <div id="kpi-picking" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Packing xong</div>
                <div id="kpi-packing" class="flight-kpi-value">0</div>
            </div>
            <div class="flight-kpi">
                <div class="flight-kpi-label">Pickup xong</div>
                <div id="kpi-pickup" class="flight-kpi-value">0</div>
            </div>
        </div>

        <div class="flight-table-wrap">
            <table class="flight-table">
                <thead>
                    <tr>
                        <th>Invoice / Khách hàng</th>
                        <th>Ngày xuất</th>
                        <th>Vận chuyển</th>
                        <th>Picking (SP)</th>
                        <th>Packing (kiện)</th>
                        <th>Pickup (kiện)</th>
                        <th>Giờ bốc</th>
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

function fmtDate(value) {
    if (!value) return '--';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return escHtml(value);
    return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1);
}

function fmtPickupTime(value) {
    if (!value) return '--';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '--';
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
}

function progCell(done, total, remainLabel) {
    const cls = (total <= 0) ? 'is-none' : (done >= total) ? 'is-done' : (done > 0) ? 'is-partial' : 'is-none';
    const pct = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0;
    const remain = Math.max(0, total - done);
    return '<div style="display:flex; flex-direction:column; gap:0.35rem;">' +
        '<div style="display:flex; align-items:baseline; gap:0.15rem; font-weight:800;">' +
        '<span style="font-size:1.05rem; color:' +
        (cls === 'is-done' ? '#10b981' : cls === 'is-partial' ? '#f59e0b' : '#ef4444') + '">' + done + '</span>' +
        '<span style="font-size:0.85rem; color:#94a3b8;">/</span>' +
        '<span style="font-size:0.85rem; color:#94a3b8;">' + total + '</span>' +
        '</div>' +
        '<div style="height:8px; border-radius:999px; background:#1e2c47; overflow:hidden;">' +
        '<div style="height:100%; border-radius:999px; background:' +
        (cls === 'is-done' ? '#10b981' : cls === 'is-partial' ? '#f59e0b' : '#ef4444') +
        '; width:' + pct + '%; transition:width 0.6s ease;"></div>' +
        '</div>' +
        '<div style="font-size:0.64rem; font-weight:600; letter-spacing:0.02em; color:' +
        (cls === 'is-done' ? '#10b981' : cls === 'is-partial' ? '#f59e0b' : '#ef4444') + '">' +
        (remain > 0 ? 'Còn ' + remain + ' ' + remainLabel : '✓ Đủ') +
        '</div>' +
        '</div>';
}

function renderFlightRows(rows) {
    const body = $('#flight-board-body');
    body.empty();

    if (!rows || !rows.length) {
        body.html('<tr><td colspan="7" class="flight-empty">Không có invoice nào trong ngày đã chọn.</td></tr>');
        return;
    }

    rows.forEach(function(row) {
        const totalItems = parseInt(row.total_items, 10) || 0;      // count distinct product_id
        const pickItems = parseInt(row.picking_items, 10) || 0;     // count distinct product_id done picking
        const packItems = parseInt(row.packing_items, 10) || 0;     // count distinct case_no done packing
        const totalCases = parseInt(row.total_cases, 10) || 0;      // count distinct case_no
        const pickedCases = parseInt(row.picked_cases, 10) || 0;    // count distinct case_no done pickup

        const pickDone = totalItems > 0 && pickItems >= totalItems;
        const packDone = totalCases > 0 && packItems >= totalCases;
        const pickupDone = totalCases > 0 && pickedCases >= totalCases;

        const type = String(row.transport_type || 'SEA').toUpperCase();
        const chipCls = (type.indexOf('AIR') >= 0) ? 'chip-air' : 'chip-sea';
        const chipIcon = (type.indexOf('AIR') >= 0) ? 'fa-plane' : 'fa-ship';
        const cust = String(row.for_product || '').trim();

        const pickupTime = fmtPickupTime(row.last_pickup_at);

        body.append(
            '<tr>' +
                '<td class="flight-col-command">' + escHtml(row.command) +
                    (cust ? '<div style="font-size:0.7rem; color:#94a3b8; margin-top:0.25rem;"><i class="fa-solid fa-user-tag"></i> ' + escHtml(cust) + '</div>' : '') +
                '</td>' +
                '<td style="font-size:0.9rem; color:#94a3b8; font-weight:600;">' + fmtDate(row.export_date) + '</td>' +
                '<td><span style="display:inline-flex; align-items:center; gap:0.4em; padding:0.25em 0.7em; border-radius:999px; font-size:0.8rem; font-weight:700; background:' +
                (chipCls === 'chip-air' ? 'rgba(99,102,241,0.18)' : 'rgba(56,189,248,0.16)') + '; color:' +
                (chipCls === 'chip-air' ? '#818cf8' : '#38bdf8') + ';">' +
                '<i class="fa-solid ' + chipIcon + '"></i> ' + escHtml(type) + '</span></td>' +
                '<td>' + progCell(pickItems, totalItems, 'SP') + '</td>' +
                '<td>' + progCell(packItems, totalCases, 'kiện') + '</td>' +
                '<td>' + progCell(pickedCases, totalCases, 'kiện') + '</td>' +
                '<td style="text-align:center; font-size:0.9rem; font-weight:700; color:' +
                (pickupDone ? '#10b981' : '#94a3b8') + ';">' + escHtml(pickupTime) + '</td>' +
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
        const ti = parseInt(row.total_items, 10) || 0;
        const tc = parseInt(row.total_cases, 10) || 0;
        if (ti > 0 && (parseInt(row.picking_items, 10) || 0) >= ti) pickingComplete++;        // picking: product_id vs product_id
        if (tc > 0 && (parseInt(row.packing_items, 10) || 0) >= tc) packingComplete++;        // packing: case_no vs case_no
        if (tc > 0 && (parseInt(row.picked_cases, 10) || 0) >= tc) pickupComplete++;         // pickup: case_no vs case_no
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

    $.getJSON('api.php?action=get_monitor_board', { date: selectedDate }, function(res) {
        if (!res || !res.success) {
            setFlightStatus((res && res.message) ? res.message : 'Không thể tải dữ liệu dashboard.', true);
            renderFlightRows([]);
            renderFlightKpi([]);
            return;
        }

        flightBoardRows = res.rows || [];
        renderFlightRows(flightBoardRows);
        renderFlightKpi(flightBoardRows);
        setFlightStatus('Ngày ' + selectedDate + ' - Có ' + flightBoardRows.length + ' invoice.');
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
});
</script>
