<div id="inventory-container" class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-10 gap-4 lg:gap-6">
    <style>
        #inventory-container .lk-tab.active { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
        #inventory-container .lk-tab.active .lk-icon { background: #3b82f6; color: #fff; }
        @media (max-width: 1023px) {
            #inventory-container .lk-tabs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .35rem; }
            #inventory-container .lk-tab { flex-direction: column; padding: .45rem .25rem; text-align: center; font-size: .68rem; }
            #inventory-container .lk-tab .lk-hint { display: none; }
            #inventory-container .lk-results-scroll { max-height: 60vh; }
        }
    </style>

    <!-- BÊN TRÁI (30%): BỘ TÌM KIẾM -->
    <aside class="lg:col-span-3">
        <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden lg:sticky lg:top-4">
            <div class="px-5 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
                <h3 class="text-lg font-bold flex items-center gap-2"><span>🔍</span> Tra cứu tồn kho</h3>
                <p class="text-blue-100 text-xs mt-0.5">Chọn loại tìm kiếm, nhập mã và nhấn Enter.</p>
            </div>

            <div class="p-4 space-y-4">
                <div class="lk-tabs flex flex-col gap-1.5" id="lookup-tabs"></div>

                <div class="space-y-2">
                    <label for="lookup-q" id="lookup-label" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide"></label>
                    <div class="relative">
                        <input type="text" id="lookup-q" autocomplete="off"
                               class="w-full pl-4 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase font-mono text-sm transition">
                        <button type="button" id="lookup-qr" title="Quét QR" class="absolute right-3 top-1/2 -translate-y-1/2 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>
                    <div id="lookup-month-wrap" class="hidden">
                        <label for="lookup-month" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tháng</label>
                        <input type="month" id="lookup-month"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>
                    <button type="button" id="lookup-btn" class="w-full py-3 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md transition">
                        TÌM KIẾM
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <!-- BÊN PHẢI (70%): KẾT QUẢ -->
    <section class="lg:col-span-7">
        <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="font-bold text-gray-800" id="result-title">Kết quả</h4>
                    <p class="text-xs text-gray-500 mt-0.5" id="result-subtitle">Chưa có tìm kiếm nào.</p>
                </div>
                <div id="result-badge"></div>
            </div>
            <div id="result-note" class="hidden mx-5 mt-4 px-4 py-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs font-medium"></div>
            <div id="result-extra" class="hidden px-5 pt-4"></div>
            <div class="lk-results-scroll overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead id="result-head" class="bg-gray-50 text-gray-500 uppercase text-[11px] font-semibold tracking-wide sticky top-0"></thead>
                    <tbody id="result-body" class="divide-y divide-gray-100"></tbody>
                    <tfoot id="result-foot" class="bg-gray-50 font-bold border-t"></tfoot>
                </table>
            </div>
            <div id="result-empty" class="px-5 py-14 text-center text-gray-400 text-sm">
                <div class="text-4xl mb-2">📭</div>
                <div id="result-empty-text">Nhập từ khóa để bắt đầu tra cứu.</div>
            </div>
        </div>
    </section>
</div>

<script>
const LOOKUP_TYPES = {
    product: { icon: '🏷️', label: 'Mã hàng',     hint: 'Vị trí & tồn theo mã hàng', placeholder: 'Product ID' },
    shelf:   { icon: '📍', label: 'Mã vị trí',   hint: 'Hàng đang nằm trên kệ',     placeholder: 'Shelf ID' },
    box:     { icon: '📦', label: 'Mã thùng',    hint: 'Thông tin thùng & vị trí',  placeholder: 'Box ID' },
    pallet:  { icon: '🧱', label: 'Pallet',      hint: 'Hàng trên pallet',          placeholder: 'Pallet ID' },
    invoice: { icon: '🧾', label: 'Invoice',     hint: 'Chi tiết theo số invoice',  placeholder: 'Số invoice (command)' },
    order:   { icon: '📑', label: 'Order',       hint: 'Chi tiết theo số order',    placeholder: 'Số order' },
    history: { icon: '🕘', label: 'Lịch sử',     hint: 'Nhập / xuất của mã hàng',   placeholder: 'Product ID' }
};
const PALLET_STATUS = {
    PENDING:  { text: 'Đang đóng',   cls: 'bg-gray-100 text-gray-700' },
    RECEIVED: { text: 'Đã nhận',     cls: 'bg-amber-100 text-amber-800' },
    IMPORTED: { text: 'Đã cất kho',  cls: 'bg-green-100 text-green-800' }
};
let lookupType = 'product';

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function fmtQty(v) { return Number(v || 0).toLocaleString('vi-VN'); }
function badge(text, cls) { return `<span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold ${cls}">${esc(text)}</span>`; }
function productCell(r) {
    return `<div class="font-mono font-bold text-gray-800">${esc(r.product_id)}</div>`
         + (r.product_name ? `<div class="text-xs text-gray-500">${esc(r.product_name)}</div>` : '');
}
function actionCell(r) {
    if (r.source === 'PALLET') return '<span class="inline-block px-2 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">Hãy nhập kho</span>';
    const qs = `shelf_id=${encodeURIComponent(r.shelf_id)}&product_id=${encodeURIComponent(r.product_id)}`;
    return `<a href="?page=inbound&${qs}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded-lg text-xs font-semibold mr-1">Nhập</a>`
         + `<a href="?page=outbound&${qs}" class="inline-block bg-red-600 hover:bg-red-700 text-white px-2.5 py-1 rounded-lg text-xs font-semibold">Xuất</a>`;
}

// Mã QR dạng "...$PRODUCT_ID$..." -> lấy PRODUCT_ID
function resolveProductId(raw) {
    const v = (raw || '').replace(/＄/g, '$').replace(/\\\$/g, '$').replace(/&#36;/g, '$').trim();
    if (v.indexOf('$') !== -1) {
        const parts = v.split('$').map(p => p.trim());
        if (parts.length >= 3 && parts[1]) return parts[1].toUpperCase();
    }
    return v.toUpperCase();
}

function renderTabs() {
    const html = Object.entries(LOOKUP_TYPES).map(([key, t]) => `
        <button type="button" data-type="${key}" class="lk-tab ${key === lookupType ? 'active' : ''} flex items-center gap-3 w-full text-left px-3 py-2 rounded-xl border border-transparent hover:bg-gray-50 text-gray-700 transition">
            <span class="lk-icon w-8 h-8 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center text-base">${t.icon}</span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold">${t.label}</span>
                <span class="lk-hint block text-[11px] text-gray-400 truncate">${t.hint}</span>
            </span>
        </button>`).join('');
    $('#lookup-tabs').html(html);
}

function setType(type) {
    lookupType = type;
    const t = LOOKUP_TYPES[type];
    $('.lk-tab').removeClass('active').filter(`[data-type="${type}"]`).addClass('active');
    $('#lookup-label').text(t.label);
    $('#lookup-q').attr('placeholder', t.placeholder).val('').focus();
    $('#lookup-month-wrap').toggleClass('hidden', type !== 'history');
    clearResult('Nhập từ khóa để bắt đầu tra cứu.');
}

function clearResult(emptyText) {
    $('#result-title').text('Kết quả');
    $('#result-subtitle').text(LOOKUP_TYPES[lookupType].hint);
    $('#result-badge, #result-head, #result-body, #result-foot').empty();
    $('#result-note, #result-extra').addClass('hidden').empty();
    $('#result-empty-text').text(emptyText);
    $('#result-empty').removeClass('hidden');
}

function renderTable(headers, rowsHtml, footHtml) {
    $('#result-head').html('<tr>' + headers.map(h => `<th class="px-4 py-3 ${h.cls || ''}">${h.text}</th>`).join('') + '</tr>');
    $('#result-body').html(rowsHtml.join(''));
    $('#result-foot').html(footHtml || '');
}

const TD = 'px-4 py-3';

const RENDERERS = {
    product(res) {
        const rows = res.rows;
        const total = rows.reduce((s, r) => s + Number(r.quantity || 0), 0);
        renderTable(
            [{ text: 'Mã hàng' }, { text: 'Vị trí' }, { text: 'Số lượng', cls: 'text-right' }, { text: 'Nhập / Xuất', cls: 'text-center' }],
            rows.map(r => `<tr class="hover:bg-gray-50">
                <td class="${TD}">${productCell(r)}</td>
                <td class="${TD} font-mono font-semibold text-gray-700">${esc(r.shelf_id)}${r.source === 'PALLET' ? ' ' + badge('Pallet đã nhận', 'bg-amber-100 text-amber-800') : ''}</td>
                <td class="${TD} text-right font-semibold">${fmtQty(r.quantity)}</td>
                <td class="${TD} text-center whitespace-nowrap">${actionCell(r)}</td>
            </tr>`),
            `<tr><td class="${TD}" colspan="2">TỔNG TỒN</td><td class="${TD} text-right text-blue-700">${fmtQty(total)}</td><td></td></tr>`
        );
    },
    shelf(res) { RENDERERS.product(res); },
    box(res) {
        const b = res.meta.box;
        if (b) {
            const info = [
                ['Mã hàng', b.product_id], ['Tên hàng', b.product_name], ['Số lượng', fmtQty(b.qty)], ['Trọng lượng', b.weight],
                ['Lot no', b.lot_no], ['Invoice', b.invoice_no], ['Order', b.order_no], ['Bundle no', b.bundle_no],
                ['Nhà cung cấp', b.supplier], ['Ngày nhập', b.input_date], ['Người tạo', b.created_by], ['Thời điểm tạo', b.created_at]
            ];
            $('#result-extra').removeClass('hidden').html(
                `<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 rounded-xl bg-gray-50 border border-gray-200">` +
                info.map(([k, v]) => `<div><div class="text-[10px] uppercase text-gray-400">${k}</div><div class="text-sm font-semibold text-gray-800 break-words">${esc(v || '—')}</div></div>`).join('') +
                `</div><div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-4">Tồn kho & vị trí</div>`
            );
        }
        if (res.rows.length) {
            RENDERERS.product(res);
        } else {
            $('#result-extra').append('<p class="text-sm text-gray-400 py-3">Thùng hiện không còn tồn trên kệ nào.</p>');
        }
    },
    pallet(res) {
        const st = PALLET_STATUS[res.meta.pallet_status] || PALLET_STATUS.PENDING;
        $('#result-badge').html(badge(st.text, st.cls));
        $('#result-note').removeClass('hidden').text('⚠️ Dữ liệu pallet chỉ tham khảo, không phải là dữ liệu tồn kho hiện tại.');
        const total = res.rows.reduce((s, r) => s + Number(r.quantity || 0), 0);
        renderTable(
            [{ text: 'Mã hàng' }, { text: 'Vị trí' }, { text: 'Số lượng', cls: 'text-right' }, { text: 'Trạng thái', cls: 'text-center' }],
            res.rows.map(r => `<tr class="hover:bg-gray-50">
                <td class="${TD}">${productCell(r)}</td>
                <td class="${TD} font-mono font-semibold text-gray-700">${r.shelf_id ? esc(r.shelf_id) : '<span class="text-gray-300">—</span>'}</td>
                <td class="${TD} text-right font-semibold">${fmtQty(r.quantity)}</td>
                <td class="${TD} text-center">${badge(st.text, st.cls)}</td>
            </tr>`),
            `<tr><td class="${TD}" colspan="2">TỔNG</td><td class="${TD} text-right text-blue-700">${fmtQty(total)}</td><td></td></tr>`
        );
    },
    invoice(res) {
        const total = res.rows.reduce((s, r) => s + Number(r.quantity || 0), 0);
        const picked = res.rows.filter(r => r.status === 'picking').length;
        $('#result-badge').html(badge(`${picked}/${res.rows.length} đã picking`, picked === res.rows.length ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'));
        renderTable(
            [{ text: 'Case no' }, { text: 'Mã hàng' }, { text: 'Order' }, { text: 'Số lượng', cls: 'text-right' }, { text: 'Trạng thái', cls: 'text-center' }],
            res.rows.map(r => `<tr class="hover:bg-gray-50">
                <td class="${TD} font-mono font-semibold">${esc(r.case_no)}</td>
                <td class="${TD}">${productCell(r)}</td>
                <td class="${TD} font-mono text-gray-700">${esc(r.order_code || '—')}</td>
                <td class="${TD} text-right font-semibold">${fmtQty(r.quantity)}</td>
                <td class="${TD} text-center">${r.status === 'picking' ? badge('picking', 'bg-green-100 text-green-800') : '<span class="text-gray-400">-</span>'}</td>
            </tr>`),
            `<tr><td class="${TD}" colspan="3">TỔNG</td><td class="${TD} text-right text-blue-700">${fmtQty(total)}</td><td></td></tr>`
        );
    },
    order(res) { RENDERERS.invoice(res); },
    history(res) {
        let net = 0;
        const rows = res.rows.map(r => {
            const isIn = String(r.type).toUpperCase() === 'IN';
            const qty = Number(r.quantity || 0);
            net += isIn ? qty : -qty;
            return `<tr class="hover:bg-gray-50">
                <td class="${TD} whitespace-nowrap text-gray-600">${esc(r.created_at)}</td>
                <td class="${TD}">${badge(isIn ? 'Nhập' : 'Xuất', isIn ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')}</td>
                <td class="${TD} font-mono text-gray-700">${esc(r.shelf_id || '—')}</td>
                <td class="${TD} text-right font-bold ${isIn ? 'text-green-600' : 'text-red-600'}">${isIn ? '+' : '-'}${fmtQty(qty)}</td>
                <td class="${TD} text-gray-700">${esc(r.created_by)}</td>
            </tr>`;
        });
        if (res.rows[0]?.product_name) $('#result-subtitle').text(res.rows[0].product_name + ' · tháng ' + res.meta.month);
        renderTable(
            [{ text: 'Thời điểm' }, { text: 'Loại' }, { text: 'Vị trí' }, { text: 'Số lượng', cls: 'text-right' }, { text: 'Người thực hiện' }],
            rows,
            `<tr><td class="${TD}" colspan="3">TỔNG PHÁT SINH</td><td class="${TD} text-right ${net >= 0 ? 'text-green-700' : 'text-red-700'}">${net >= 0 ? '+' : '-'}${fmtQty(Math.abs(net))}</td><td></td></tr>`
        );
    }
};

function runLookup() {
    let q = $('#lookup-q').val();
    q = (lookupType === 'product' || lookupType === 'history') ? resolveProductId(q) : q.trim().toUpperCase();
    if (!q) return;
    $('#lookup-q').val(q);

    const params = { type: lookupType, q };
    if (lookupType === 'history') params.month = $('#lookup-month').val();

    clearResult('Đang tìm kiếm...');
    $.getJSON('api.php?action=inventory_lookup', params, function(res) {
        if (!res.success) { clearResult(res.message || 'Có lỗi xảy ra.'); return; }
        const t = LOOKUP_TYPES[res.type];
        $('#result-title').html(`${t.icon} ${t.label}: <span class="font-mono">${esc(res.q)}</span>`);
        const hasData = res.rows.length > 0 || (res.type === 'box' && res.meta.box);
        if (!hasData) { $('#result-empty-text').text('Không tìm thấy dữ liệu phù hợp.'); return; }
        $('#result-empty').addClass('hidden');
        RENDERERS[res.type](res);
    }).fail(function() { clearResult('Lỗi kết nối máy chủ.'); });
}

$(document).ready(function() {
    renderTabs();
    $('#lookup-month').val(new Date().toISOString().slice(0, 7));

    $('#lookup-tabs').on('click', '.lk-tab', function() { setType($(this).data('type')); });
    $('#lookup-btn').on('click', runLookup);
    $('#lookup-q').on('keydown', function(e) { if (e.which === 13) { e.preventDefault(); runLookup(); } });
    $('#lookup-month').on('change', function() { if ($('#lookup-q').val().trim()) runLookup(); });
    $('#lookup-qr').on('click', function() { openQRScannerModal('lookup-q', LOOKUP_TYPES[lookupType].label); });

    // Hỗ trợ link cũ: ?page=inventory&shelf_id=... / &pallet_id=... / &product_id=...
    const urlParams = new URLSearchParams(window.location.search);
    const preset = [['shelf_id', 'shelf'], ['pallet_id', 'pallet'], ['product_id', 'product'], ['box_id', 'box']]
        .find(([k]) => urlParams.get(k));
    setType(preset ? preset[1] : 'product');
    if (preset) { $('#lookup-q').val(urlParams.get(preset[0])); runLookup(); }
});
</script>
