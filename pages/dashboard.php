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
                    <tr><td colspan="7" class="flight-empty">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for incomplete cases -->
<div id="incomplete-cases-modal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div class="p-4 border-b flex justify-between items-center">
            <div>
                <h4 id="modal-title" class="text-lg font-bold text-gray-800">Chi tiết chưa hoàn thành</h4>
                <p class="text-sm text-gray-600">Invoice: <span id="modal-invoice-id" class="font-bold"></span></p>
            </div>
            <button onclick="closeIncompleteCasesModal()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>
        <div id="modal-body" class="p-4 overflow-y-auto">
            <p id="modal-loading-text" class="text-center text-gray-500">Đang tải dữ liệu...</p>
            <div id="modal-cases-list" class="space-y-3">
                <!-- Case details will be loaded here -->
            </div>
        </div>
        <div class="p-3 bg-gray-50 border-t flex justify-end">
            <button onclick="closeIncompleteCasesModal()" class="px-5 py-2 bg-gray-200 text-gray-700 font-medium rounded-md hover:bg-gray-300 transition">
                Đóng
            </button>
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
    // Sử dụng ?? để đảm bảo số 0 không bị chuyển thành chuỗi rỗng
    return String(text ?? '')
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
                '<td class="cursor-pointer hover:opacity-70 transition" onclick="showIncompleteCases(\'' + escHtml(row.command) + '\', \'picking\')" style="position:relative;">' + progCell(pickItems, totalItems, 'SP') + '</td>' +
                '<td class="cursor-pointer hover:opacity-70 transition" onclick="showIncompleteCases(\'' + escHtml(row.command) + '\', \'packing\')" style="position:relative;">' + progCell(packItems, totalCases, 'kiện') + '</td>' +
                '<td class="cursor-pointer hover:opacity-70 transition" onclick="showIncompleteCases(\'' + escHtml(row.command) + '\', \'pickup\')" style="position:relative;">' + progCell(pickedCases, totalCases, 'kiện') + '</td>' +
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
    let totalCasesInDay = 0;
    let pickingCompleteCases = 0;
    let packingCompleteCases = 0;
    let pickupCompleteCases = 0;

    rows.forEach(function(row) {
        const ti = parseInt(row.total_items, 10) || 0;
        const tc = parseInt(row.total_cases, 10) || 0;
        const pickingItems = parseInt(row.picking_items, 10) || 0;
        const packingItems = parseInt(row.packing_items, 10) || 0;
        const pickedCases = parseInt(row.picked_cases, 10) || 0;

        // Tính tổng số kiện phải xuất trong ngày
        totalCasesInDay += tc;

        // Picking: nếu tất cả sản phẩm đã pick xong
        if (ti > 0 && pickingItems >= ti) {
            pickingComplete++;
            pickingCompleteCases += tc;
        }

        // Packing: nếu tất cả kiện đã pack xong
        if (tc > 0 && packingItems >= tc) {
            packingComplete++;
            packingCompleteCases += tc;
        }

        // Pickup: nếu tất cả kiện đã pickup xong
        if (tc > 0 && pickedCases >= tc) {
            pickupComplete++;
            pickupCompleteCases += tc;
        }
    });

    $('#kpi-commands').text(totalCommands + ' (' + totalCasesInDay + ' case)');
    $('#kpi-picking').text(pickingComplete + ' / ' + totalCommands + ' (' + pickingCompleteCases + ' case done)');
    $('#kpi-packing').text(packingComplete + ' / ' + totalCommands + ' (' + packingCompleteCases + ' case done)');
    $('#kpi-pickup').text(pickupComplete + ' / ' + totalCommands + ' (' + pickupCompleteCases + ' case done)');
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

    // Close modal on clicking outside
    $('#incomplete-cases-modal').on('click', function(e) {
        if (e.target === this) {
            closeIncompleteCasesModal();
        }
    });
});

function showIncompleteCases(command, type) {
    type = type || 'all';

    const titleMap = {
        'picking': 'SP chưa hoàn thành Picking',
        'packing': 'Kiện chưa hoàn thành Packing',
        'pickup': 'Kiện chưa thực hiện Pickup'
    };

    $('#modal-title').text(titleMap[type] || 'Chi tiết chưa hoàn thành');
    $('#modal-invoice-id').text(command);
    $('#modal-cases-list').empty();
    $('#modal-loading-text').show();
    $('#incomplete-cases-modal').removeClass('hidden');

    $.getJSON('api.php?action=get_incomplete_cases_by_command', { command: command, type: type }, function(res) {
        // console.log('API Response:', res);
        // console.log('Command:', command, 'Type:', type);
        // console.log('res.success:', res.success);
        // console.log('res.message:', res.message);
        // console.log('res.data:', res.data);
        // console.log('res.data length:', res.data ? res.data.length : 'undefined');

        $('#modal-loading-text').hide();
        const listDiv = $('#modal-cases-list');
        let html = '';

        if (!res.success) {
            listDiv.html(`<div class="p-4 text-center text-red-600 bg-red-50 rounded-lg"><strong>Lỗi:</strong> ${escHtml(res.message || 'Lỗi không xác định từ API')}</div>`);
            return;
        }

        if (!res.data || res.data.length === 0) {
            const completeMsg = {
                'picking': 'Tuyệt vời! Tất cả sản phẩm của invoice này đã được picking đầy đủ.',
                'packing': 'Tuyệt vời! Tất cả kiện của invoice này đã được packing đầy đủ.',
                'pickup': 'Tuyệt vời! Tất cả kiện của invoice này đã được pickup đầy đủ.'
            };
            listDiv.html(`<div class="p-4 text-center text-gray-600 bg-green-50 rounded-lg">${escHtml(completeMsg[type] || 'Dữ liệu đầy đủ')}</div>`);
            return;
        }

        if (type === 'picking') {
            // console.log('Rendering picking type, data count:', res.data.length);
            html += '';
            html += `
                <div class="p-3 border rounded-lg bg-red-50 mb-3">
                    <ul class="mt-2 pl-5 list-disc text-sm text-gray-700 space-y-1">`;
                    res.data.forEach(item => {
                        // console.log('Picking item:', item);
                        html += `
                            <li><strong>${escHtml(item.product_id)}</strong> (Mã đơn: ${escHtml(item.order_code || 'N/A')}) - Đã pick ${escHtml(item.picked_qty)} / ${escHtml(item.required_qty)}</li>
                        `;
                    });
            html += `</ul></div>`;
        } else if (type === 'packing') {
            console.log('Rendering packing type, data count:', res.data.length);
            console.log('Full packing data:', res.data);
            html += '<h5 class="text-md font-bold text-red-700 mb-3">Kiện chưa packing hoàn thành</h5>';
            res.data.forEach(caseItem => {
                console.log('Packing item:', caseItem);
                console.log('Case NO value:', caseItem.case_no);
                html += `
                    <div class="p-3 border rounded-lg bg-red-50 mb-3">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-gray-800">Kiện: ${escHtml(caseItem.case_no || 'N/A')}</span>
                            <span class="text-sm font-semibold text-red-600">Còn thiếu ${escHtml(caseItem.incomplete_items_count)} mã SP</span>
                        </div>
                        <ul class="mt-2 pl-5 list-disc text-sm text-gray-700 space-y-1">`;

                if (caseItem.items && Array.isArray(caseItem.items)) {
                    caseItem.items.forEach(product => {
                        console.log('Product in case:', product);
                        html += `<li><strong>${escHtml(product.product_id)}</strong> (Mã đơn: ${escHtml(product.order_code || 'N/A')}) - Đã pack ${escHtml(product.packed_qty)} / ${escHtml(product.required_qty)}</li>`;
                    });
                }
                html += `</ul></div>`;
            });
        } else if (type === 'pickup') {
            // console.log('Rendering pickup type, data count:', res.data.length);
            html += '';
            html += '<div class="space-y-2">';
            res.data.forEach(caseNo => {
                // console.log('Pickup case:', caseNo);
                html += `<div class="p-3 border rounded-lg bg-amber-50"><strong>Kiện:</strong> ${escHtml(caseNo)}</div>`;
            });
            html += '</div>';
        }

        listDiv.html(html);

    }).fail(function(xhr, status, error) {
        console.error('API Error:', error);
        console.error('XHR Status:', xhr.status);
        console.error('Response Text:', xhr.responseText);
        $('#modal-loading-text').hide();
        $('#modal-cases-list').html('<p class="text-center text-red-500">Lỗi khi tải dữ liệu chi tiết. ' + error + '</p>');
    });
}

function closeIncompleteCasesModal() {
    $('#incomplete-cases-modal').addClass('hidden');
}
</script>
