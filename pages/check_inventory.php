<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'], true)) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="pda-checkinv-wrap pb-4">
    <div class="pda-card p-2 mb-2">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title text-sm">Kiểm kê vị trí</div>
            <div id="ci-status" class="ci-status-text">Bước 1: quét mã vị trí</div>
        </div>
    </div>

    <!-- Bước 1: quét mã vị trí -->
    <div id="ci-step-1" class="pda-card p-2 mb-2">
        <div class="flex items-center justify-between gap-1">
            <div class="pda-title mt-0 text-sm">Quét QR mã vị trí</div>
            <button type="button" class="ci-help-toggle" onclick="ciToggleHelp('ci-step1-help')" title="Trợ giúp"><i class="fas fa-info-circle"></i></button>
        </div>
        <div id="ci-step1-help" class="ci-help-box hidden">Có thể lưu nhiều đợt. Lần sau quét lại vị trí, hệ thống hiện các mã hàng chưa kiểm đủ để kiểm tiếp.</div>

        <div class="relative mt-2">
            <input type="text" id="ci-loc-input" class="pda-input" placeholder="Quét QR mã vị trí" autocomplete="off" maxlength="120">
            <button type="button" onclick="openQRScannerModal('ci-loc-input', 'QR Mã Vị Trí')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-2">
            <button type="button" class="pda-btn pda-btn-primary w-full" id="ci-btn-confirm-loc" onclick="ciConfirmLocation()">Xác nhận vị trí</button>
        </div>

        <div id="ci-step1-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="ci-step1-info" class="pda-msg-info mt-2 hidden"></div>
    </div>

    <!-- Bước 2: quét từng thùng hàng -->
    <div id="ci-step-2" class="pda-card p-2 mb-2" style="display:none;">
        <!-- Thanh ghim vị trí đang kiểm -->
        <div class="ci-pinned-bar">
            <div class="ci-pinned-loc">
                <span class="ci-pin-icon">📍</span>
                <span id="ci-pinned-shelf" class="ci-pinned-shelf-text">-</span>
            </div>
            <div class="ci-pinned-actions">
                <span id="ci-progress-chip" class="ci-chip">0/0</span>
                <button type="button" class="ci-small-btn" onclick="ciBackToStep1()">Đổi vị trí</button>
            </div>
        </div>

        <!-- Vùng quét chính: luôn gọn trong 1 màn hình -->
        <div class="ci-scan-zone">
            <div class="flex items-center justify-between gap-2">
                <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-xs">
                    <button type="button" id="ci-scan-mode-interrupt" onclick="ciSetScanMode(false)" class="px-2 py-1 font-semibold bg-green-600 text-white">Gián đoạn</button>
                    <button type="button" id="ci-scan-mode-continuous" onclick="ciSetScanMode(true)" class="px-2 py-1 font-semibold bg-white text-gray-700 hover:bg-gray-100">Liên tục</button>
                </div>
                <button type="button" class="ci-help-toggle" onclick="ciToggleHelp('ci-step2-help')" title="Trợ giúp định dạng QR"><i class="fas fa-info-circle"></i></button>
            </div>
            <div id="ci-step2-help" class="ci-help-box hidden">QR chuẩn: mã hàng ở vị trí 1, số lượng ở vị trí 2 (VD: TEXT$MÃ HÀNG$SỐ LƯỢNG$...)</div>

            <div class="relative mt-2">
                <input type="text" id="ci-box-input" class="pda-input" placeholder="Quét QR trên thùng" autocomplete="off">
                <button type="button" onclick="openQRScannerModal('ci-box-input', 'QR Thùng Hàng')" class="absolute right-3 top-1/2 -translate-y-1/2 text-green-600">
                    <i class="fas fa-qrcode text-lg"></i>
                </button>
            </div>

            <div class="ci-scan-row mt-2">
                <div id="ci-current-product" class="ci-scan-product-value">-</div>
                <input type="number" id="ci-qty-input" class="pda-input" min="1" placeholder="SL" disabled>
            </div>

            <div class="mt-2">
                <button type="button" class="ci-add-btn" id="ci-btn-add" onclick="ciAddItemFromInput()" disabled>
                    <i class="fas fa-plus"></i> Thêm vào danh sách
                </button>
            </div>

            <div id="ci-step2-error" class="pda-msg-error mt-2 hidden"></div>
        </div>

        <div class="ci-scan-actions mt-3">
            <button type="button" id="ci-btn-bulk" onclick="ciBulkConfirm()" class="ci-bulk-btn"
                title="Xác nhận hàng loạt: dùng khi hàng ở trên cao, khó quét từng thùng. Chấp nhận kết quả kiểm kê = toàn bộ tồn hệ thống của vị trí. Cần mật khẩu.">
                <i class="fas fa-layer-group"></i>
            </button>
            <button type="button" class="pda-btn pda-btn-success flex-1" id="ci-btn-save" onclick="ciSave()" disabled>
                Lưu lịch sử quét (<span id="ci-save-count">0</span>)
            </button>
        </div>

        <!-- ============ Bên dưới: danh sách đã quét & tiến độ ============ -->
        <div class="ci-below-fold">
            <div class="pda-subtitle">Danh sách đã quét (chờ lưu)</div>
            <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left text-xs font-bold text-gray-700">STT</th>
                            <th class="p-2 text-left text-xs font-bold text-gray-700">MÃ HÀNG</th>
                            <th class="p-2 text-right text-xs font-bold text-gray-700">SL</th>
                            <th class="p-2 text-center text-xs font-bold text-gray-700">XÓA</th>
                        </tr>
                    </thead>
                    <tbody id="ci-box-list">
                        <tr><td colspan="4" class="p-2 text-center text-slate-500 text-xs">Chưa quét thùng nào</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pda-subtitle mt-3">Tiến độ theo mã hàng tại vị trí</div>
            <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-2 text-left text-xs font-bold text-gray-700">MÃ HÀNG</th>
                            <th class="p-2 text-right text-xs font-bold text-gray-700">HỆ THỐNG</th>
                            <th class="p-2 text-right text-xs font-bold text-gray-700">ĐÃ KIỂM</th>
                            <th class="p-2 text-right text-xs font-bold text-gray-700">CÒN LẠI</th>
                            <th class="p-2 text-center text-xs font-bold text-gray-700">TT</th>
                        </tr>
                    </thead>
                    <tbody id="ci-progress-body">
                        <tr><td colspan="5" class="p-2 text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Lịch sử -->
    <div class="pda-card p-2 mt-2">
        <div class="pda-subtitle">Lịch sử kiểm kê gần nhất</div>
        <div class="pda-table-wrap mt-2">
            <table class="w-full pda-table">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Vị trí</th>
                        <th>Người kiểm</th>
                        <th>Lần quét</th>
                        <th>Tổng SL</th>
                        <th>Lệch</th>
                    </tr>
                </thead>
                <tbody id="ci-history-body">
                    <tr><td colspan="6" class="text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    /* ---------- Bố cục gọn cho màn hình PDA 4 inch ---------- */
    .pda-checkinv-wrap .pda-card { padding: 0.6rem; }
    .pda-checkinv-wrap .pda-subtitle { font-size: 11px; }

    .ci-status-text {
        font-size: 11px;
        font-weight: 700;
        text-align: right;
        max-width: 60%;
        line-height: 1.3;
    }

    .ci-help-toggle {
        width: 24px;
        height: 24px;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
    }
    .ci-help-box {
        font-size: 11px;
        color: #64748b;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 6px 8px;
        margin-top: 6px;
        line-height: 1.4;
    }

    /* Thanh ghim mã vị trí đang kiểm */
    .ci-pinned-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        background: #0f172a;
        color: #fff;
        border-radius: 10px;
        padding: 8px 10px;
        margin-bottom: 8px;
    }
    .ci-pinned-loc { display: flex; align-items: center; gap: 6px; min-width: 0; }
    .ci-pin-icon { font-size: 14px; flex-shrink: 0; }
    .ci-pinned-shelf-text {
        font-weight: 800;
        font-size: 15px;
        font-family: ui-monospace, monospace;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ci-pinned-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .ci-chip {
        background: rgba(255, 255, 255, 0.16);
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        white-space: nowrap;
    }
    .ci-small-btn {
        background: rgba(255, 255, 255, 0.16);
        border: none;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        border-radius: 6px;
        padding: 5px 8px;
        white-space: nowrap;
        cursor: pointer;
    }

    /* Vùng quét mã hàng + số lượng - cùng 1 dòng, tỷ lệ 7:5, cùng chiều cao & cỡ chữ */
    .ci-scan-row {
        display: flex;
        gap: 6px;
        align-items: stretch;
    }
    .ci-scan-product-value {
        flex: 7 1 0%;
        min-width: 0;
        display: flex;
        align-items: center;
        height: 42px;
        box-sizing: border-box;
        font-weight: 800;
        font-size: 15px;
        color: #0f172a;
        font-family: ui-monospace, monospace;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0 8px;
        background: #f8fafc;
    }
    #ci-qty-input {
        flex: 5 1 0%;
        min-width: 0;
        height: 42px;
        box-sizing: border-box;
        padding: 0 8px;
        text-align: center;
        font-size: 15px;
        font-weight: 800;
        font-family: ui-monospace, monospace;
    }
    .ci-add-btn {
        width: 100%;
        height: 42px;
        border-radius: 8px;
        border: none;
        background: #0284c7;
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .ci-add-btn:disabled { background: #94a3b8; }

    .ci-scan-actions { display: flex; gap: 8px; align-items: stretch; }
    .ci-bulk-btn {
        flex-shrink: 0;
        width: 42px;
        border-radius: 8px;
        border: none;
        background: #f59e0b;
        color: #fff;
        font-size: 15px;
        cursor: pointer;
    }

    .ci-below-fold {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px dashed #cbd5e1;
    }

    @media (max-width: 480px) {
        .pda-checkinv-wrap { padding-bottom: 0.5rem; }
        .pda-checkinv-wrap .pda-card { padding: 0.5rem !important; margin-bottom: 0.4rem !important; }
        .ci-pinned-shelf-text { font-size: 13px; }
        .ci-status-text { font-size: 10px; }
        #ci-loc-input, #ci-box-input { padding-top: 0.5rem; padding-bottom: 0.5rem; font-size: 12px; }
    }

    /* ---------- Modal ---------- */
    .ci-modal-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100% !important;
        height: 100% !important;
        margin: 0 !important;
        background-color: rgba(15, 23, 42, 0.55) !important;
        z-index: 2147483000 !important;
    }
    .ci-modal-box {
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        -webkit-transform: translate(-50%, -50%) !important;
        transform: translate(-50%, -50%) !important;
        width: calc(100% - 28px) !important;
        max-width: 560px !important;
        max-height: 86vh !important;
        overflow-y: auto !important;
        background: #ffffff !important;
        color: #1e293b !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 20px !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35) !important;
        -webkit-box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35) !important;
        display: block !important;
        box-sizing: border-box !important;
    }
    .ci-modal-box h2 {
        font-size: 18px;
        font-weight: 800;
        margin: 0 0 12px;
        color: #1e293b;
        line-height: 1.3;
    }
    .ci-modal-box.warning h2 { color: #b45309; }
    .ci-modal-box.success h2 { color: #047857; }
    .ci-modal-box.error h2 { color: #b91c1c; }
    .ci-modal-box p { margin: 0 0 14px; }
    .ci-recon-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 8px 0 14px; }
    .ci-recon-table th, .ci-recon-table td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: center; }
    .ci-recon-table th { background: #f1f5f9; font-weight: 700; }
    .ci-recon-table td.ci-cell-pid { text-align: left; font-family: ui-monospace, monospace; }
    .ci-row-match   { background: #f0fdf4; color: #166534; }
    .ci-row-pending { background: #f8fafc; color: #475569; }
    .ci-row-short   { background: #fffbeb; color: #92400e; font-weight: 700; }
    .ci-row-over    { background: #fef2f2; color: #991b1b; font-weight: 700; }
    .ci-row-extra   { background: #fef2f2; color: #991b1b; font-weight: 700; }
    .ci-modal-actions { text-align: right; margin-top: 4px; }
    .ci-modal-btn { display: inline-block; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; margin-left: 8px; }
    .ci-modal-btn-primary { background: #0284c7; color: #fff; }
    .ci-badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 10px; font-weight: 700; }
    .ci-badge-match { background: #dcfce7; color: #166534; }
    .ci-badge-pending { background: #e2e8f0; color: #475569; }
    .ci-badge-short { background: #fef3c7; color: #92400e; }
    .ci-badge-over { background: #fee2e2; color: #991b1b; }
</style>

<div id="ci-alert-modal" class="ci-modal-overlay" style="display:none;">
    <div id="ci-alert-content" class="ci-modal-box">
        <h2 id="ci-alert-title">Thông báo</h2>
        <p id="ci-alert-message" style="white-space:pre-wrap; font-size:14px; line-height:1.5;"></p>
        <div class="ci-modal-actions">
            <button type="button" class="ci-modal-btn ci-modal-btn-primary" onclick="ciCloseAlert()">OK</button>
        </div>
    </div>
</div>

<div id="ci-recon-modal" class="ci-modal-overlay" style="display:none;">
    <div id="ci-recon-content" class="ci-modal-box">
        <h2 id="ci-recon-title">Kết quả đối chiếu tồn kho</h2>
        <p class="text-xs" style="color:#64748b; margin-bottom:6px;">Vị trí: <span id="ci-recon-loc" class="font-bold"></span></p>
        <div id="ci-recon-body"></div>
        <div class="ci-modal-actions">
            <button type="button" class="ci-modal-btn ci-modal-btn-primary" onclick="ciCloseRecon()">Đóng</button>
        </div>
    </div>
</div>

<script>
let ciState = {
    sessionId: '',
    shelfId: '',
    shelfName: '',
    systemInventory: [],   // [{product_id, product_name, quantity}]
    priorCounted: {},      // {product_id: counted_qty}  cộng dồn các lượt đã lưu trước đó
    list: [],              // [{productId, qty, raw}]  các lần quét CHƯA lưu
    currentParsed: null,
    busy: false,
    history: []
};
let ciContinuousScan = false;
let ciBoxScanTimer = null;

function ciGenSessionId() {
    return 'CKI-' + Date.now() + '-' + Math.random().toString(36).substr(2, 8).toUpperCase();
}

function ciNormalize(text) {
    return (text || '')
        .replace(/＄/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim()
        .toUpperCase();
}

function ciSetStatus(text, tone) {
    const el = $('#ci-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-green-700 text-amber-700 text-red-700');
    el.addClass(tone || 'text-sky-700');
}

function ciToggleHelp(targetId) {
    $('#' + targetId).toggleClass('hidden');
}

function ciShowError(sel, msg) { $(sel).removeClass('hidden').text(msg || 'Có lỗi xảy ra.'); }
function ciHideError(sel) { $(sel).addClass('hidden').text(''); }
function ciShowInfo(msg) {
    if (!msg) { $('#ci-step1-info').addClass('hidden').text(''); return; }
    $('#ci-step1-info').removeClass('hidden').text(msg);
}

function ciShowAlert(message, type) {
    $('#ci-alert-content').removeClass('warning success error').addClass(type || '');
    const titles = { success: '✅ Thành công', error: '❌ Lỗi', warning: '⚠️ Cảnh báo' };
    $('#ci-alert-title').text(titles[type] || 'Thông báo');
    $('#ci-alert-message').text(message || '');
    $('#ci-alert-modal').css('display', 'block');
}
function ciCloseAlert() { $('#ci-alert-modal').css('display', 'none'); }
function ciCloseRecon() {
    $('#ci-recon-modal').css('display', 'none');
    setTimeout(function() { $('#ci-box-input').focus(); }, 60);
}

/* ---------- Tiến độ / đối chiếu ---------- */

// Gộp SL theo mã hàng trong danh sách CHƯA lưu
function ciCurrentListMap() {
    const m = {};
    ciState.list.forEach(function(it) { m[it.productId] = (m[it.productId] || 0) + it.qty; });
    return m;
}

// Trả về [{productId, system, counted, remaining, status}]  (counted = đã lưu + đang chờ lưu)
function ciComputeProgress() {
    const listMap = ciCurrentListMap();
    const sysMap = {};
    ciState.systemInventory.forEach(function(r) {
        sysMap[String(r.product_id).toUpperCase()] = Math.round(Number(r.quantity) || 0);
    });

    const pids = Object.keys(sysMap)
        .concat(Object.keys(ciState.priorCounted))
        .concat(Object.keys(listMap))
        .filter(function(v, i, a) { return a.indexOf(v) === i; })
        .sort();

    return pids.map(function(pid) {
        const system = Object.prototype.hasOwnProperty.call(sysMap, pid) ? sysMap[pid] : null;
        const counted = (ciState.priorCounted[pid] || 0) + (listMap[pid] || 0);
        let status;
        if (system === null) status = 'extra';
        else if (counted === system) status = 'match';
        else if (counted === 0) status = 'pending';
        else if (counted < system) status = 'short';
        else status = 'over';
        return {
            productId: pid,
            system: system,
            counted: counted,
            remaining: system === null ? null : Math.max(0, system - counted),
            status: status
        };
    });
}

const CI_STATUS_LABEL = {
    match: '<span class="ci-badge ci-badge-match">Đủ</span>',
    pending: '<span class="ci-badge ci-badge-pending">Chưa kiểm</span>',
    short: '<span class="ci-badge ci-badge-short">Thiếu</span>',
    over: '<span class="ci-badge ci-badge-over">Dư</span>',
    extra: '<span class="ci-badge ci-badge-over">Ngoài HT</span>'
};

function ciRenderProgress() {
    const rows = ciComputeProgress();
    const body = $('#ci-progress-body');
    if (!rows.length) {
        body.html('<tr><td colspan="5" class="p-2 text-center text-slate-500 text-xs">Vị trí không có tồn hệ thống</td></tr>');
        return rows;
    }
    let html = '';
    rows.forEach(function(r) {
        html += '<tr class="ci-row-' + r.status + '">' +
            '<td class="p-2 text-xs font-mono font-bold">' + r.productId + '</td>' +
            '<td class="p-2 text-right text-xs">' + (r.system === null ? '—' : r.system) + '</td>' +
            '<td class="p-2 text-right text-xs font-semibold">' + r.counted + '</td>' +
            '<td class="p-2 text-right text-xs">' + (r.remaining === null ? '—' : r.remaining) + '</td>' +
            '<td class="p-2 text-center">' + (CI_STATUS_LABEL[r.status] || '') + '</td>' +
            '</tr>';
    });
    body.html(html);
    return rows;
}

function ciUpdateSummary() {
    const rows = ciRenderProgress();
    const done = rows.filter(function(r) { return r.status === 'match'; }).length;
    const totalSys = rows.filter(function(r) { return r.system !== null; }).length;
    $('#ci-pinned-shelf').text(ciState.shelfId || '-');
    $('#ci-progress-chip').text(done + '/' + totalSys);
    $('#ci-save-count').text(ciState.list.length);
    $('#ci-btn-save').prop('disabled', ciState.list.length === 0 || ciState.busy);
}

/* ---------- Bước 1: quét vị trí ---------- */

function ciConfirmLocation() {
    if (ciState.busy) return;
    ciHideError('#ci-step1-error');
    ciShowInfo('');

    const loc = ciNormalize($('#ci-loc-input').val());
    if (!loc) { ciShowError('#ci-step1-error', 'Vui lòng quét mã vị trí.'); return; }

    ciState.busy = true;
    ciSetStatus('Đang kiểm tra vị trí...', 'text-sky-700');

    $.getJSON('api.php?action=check_inventory_start', { shelf_id: loc }, function(res) {
        ciState.busy = false;
        if (!res || !res.success) {
            ciShowError('#ci-step1-error', (res && res.message) ? res.message : 'Không xác thực được mã vị trí.');
            ciSetStatus('Vị trí không hợp lệ', 'text-red-700');
            return;
        }

        ciState.sessionId = ciGenSessionId();
        ciState.shelfId = res.shelf_id;
        ciState.shelfName = res.shelf_name || '';
        ciState.systemInventory = Array.isArray(res.system_inventory) ? res.system_inventory : [];
        ciState.priorCounted = {};
        (res.counted || []).forEach(function(c) {
            ciState.priorCounted[String(c.product_id).toUpperCase()] = parseInt(c.counted_qty || 0, 10);
        });
        ciState.list = [];
        ciState.currentParsed = null;

        $('#ci-step-1').hide();
        $('#ci-step-2').show();
        ciRenderBoxList();
        ciUpdateSummary();
        ciSetScanMode(false);

        const prog = ciComputeProgress();
        const pending = prog.filter(function(r) { return r.status === 'pending' || r.status === 'short'; });
        if (res.checked_before) {
            const pnames = pending.map(function(r) { return r.productId + ' (còn ' + r.remaining + ')'; });
            ciSetStatus('Đã kiểm 1 phần — còn ' + pending.length + ' mã.', 'text-amber-700');
            ciShowAlert(
                'Vị trí ' + res.shelf_id + ' đã được kiểm kê trước đó' +
                (res.last_checked_at ? ' (gần nhất ' + res.last_checked_at + ' bởi ' + (res.last_checked_by || '?') + ')' : '') + '.\n\n' +
                (pnames.length
                    ? 'Các mã hàng CHƯA kiểm đủ:\n- ' + pnames.join('\n- ')
                    : 'Tất cả mã hàng đã kiểm đủ. Bạn có thể kiểm lại nếu cần.'),
                'warning'
            );
        } else {
            ciSetStatus('Đang kiểm tại ' + ciState.shelfId, 'text-sky-700');
        }
        setTimeout(function() { $('#ci-box-input').focus(); }, 60);
        console.log('[kiểm kê] bắt đầu', ciState.shelfId, 'prior=', ciState.priorCounted);
    }).fail(function() {
        ciState.busy = false;
        ciShowError('#ci-step1-error', 'Không kết nối được API kiểm kê.');
        ciSetStatus('Lỗi kết nối', 'text-red-700');
    });
}

function ciBackToStep1() {
    if (ciState.list.length > 0) {
        if (!window.confirm('Còn ' + ciState.list.length + ' lần quét CHƯA lưu. Đổi vị trí sẽ mất các quét này. Tiếp tục?')) {
            return;
        }
    }
    ciResetToStep1(true);
}

/* ---------- Bước 2: quét thùng ---------- */

function ciSetScanMode(isContinuous) {
    ciContinuousScan = !!isContinuous;
    const a = $('#ci-scan-mode-interrupt');
    const b = $('#ci-scan-mode-continuous');
    if (ciContinuousScan) {
        a.removeClass('bg-green-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        b.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-green-600 text-white');
    } else {
        b.removeClass('bg-green-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        a.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-green-600 text-white');
    }
    $('#ci-box-input').focus();
}

// QR chuẩn: mã hàng ở index 1, số lượng ở index 2.
function ciParseBoxQr(rawValue) {
    const raw = ciNormalize(rawValue);
    if (!raw || raw.indexOf('$') === -1) return null;

    const parts = raw.split('$').map(function(p) { return p.trim(); });
    if (parts.length < 3) return null;

    const productId = (parts[1] || '').toUpperCase();
    if (!productId) return null;

    let qty = NaN;
    if (/^\d+$/.test(parts[2] || '')) {
        qty = parseInt(parts[2], 10);
    } else {
        for (let i = 2; i < parts.length; i++) {
            if (/^\d+$/.test(parts[i]) && parseInt(parts[i], 10) > 0) { qty = parseInt(parts[i], 10); break; }
        }
    }
    if (isNaN(qty) || qty <= 0) return null;
    return { productId: productId, qty: qty, raw: raw };
}

function ciProcessBoxScan(parsed, fromScanner) {
    if (!parsed) {
        const msg = 'QR thùng không đúng định dạng.\nCần: [TEXT]$[MÃ HÀNG]$[SỐ LƯỢNG]$...';
        ciShowAlert(msg, 'error');
        ciShowError('#ci-step2-error', msg.replace(/\n/g, ' '));
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') window.resetQRScannerModalState();
        return false;
    }

    ciHideError('#ci-step2-error');
    ciState.currentParsed = parsed;
    $('#ci-current-product').text(parsed.productId);
    $('#ci-qty-input').prop('disabled', false).val(parsed.qty);
    $('#ci-btn-add').prop('disabled', false);
    $('#ci-box-input').val('');

    if (fromScanner && typeof window.closeQRScannerModal === 'function') window.closeQRScannerModal();

    if (ciContinuousScan) {
        ciAddItem(parsed.productId, parsed.qty, parsed.raw);
        ciState.currentParsed = null;
        $('#ci-qty-input').val('').prop('disabled', true);
        $('#ci-btn-add').prop('disabled', true);
        $('#ci-current-product').text('-');
        setTimeout(function() { $('#ci-box-input').focus(); }, 60);
        ciSetStatus('Đã thêm ' + parsed.productId + ' x' + parsed.qty + '. Quét tiếp.', 'text-green-700');
    } else {
        ciSetStatus('Đã đọc: ' + parsed.productId + '. Kiểm tra SL rồi nhấn +.', 'text-sky-700');
        setTimeout(function() { $('#ci-qty-input').focus().select(); }, 60);
    }
    return true;
}

function ciParseBoxFromInput() {
    ciProcessBoxScan(ciParseBoxQr($('#ci-box-input').val()), false);
}

function ciAddItem(productId, qty, raw) {
    ciState.list.push({ productId: productId, qty: qty, raw: raw || '' });
    ciRenderBoxList();
    ciUpdateSummary();
}

function ciAddItemFromInput() {
    if (!ciState.currentParsed) { ciShowError('#ci-step2-error', 'Cần đọc QR thùng trước.'); return; }
    const qty = parseInt($('#ci-qty-input').val(), 10);
    if (isNaN(qty) || qty <= 0) { ciShowError('#ci-step2-error', 'Số lượng không hợp lệ.'); return; }
    ciAddItem(ciState.currentParsed.productId, qty, ciState.currentParsed.raw);
    ciState.currentParsed = null;
    $('#ci-qty-input').val('').prop('disabled', true);
    $('#ci-btn-add').prop('disabled', true);
    $('#ci-current-product').text('-');
    ciHideError('#ci-step2-error');
    setTimeout(function() { $('#ci-box-input').focus(); }, 60);
    ciSetStatus('Đã thêm. Quét tiếp hoặc nhấn "Lưu lịch sử quét".', 'text-green-700');
}

function ciRemoveItem(index) {
    if (index >= 0 && index < ciState.list.length) {
        ciState.list.splice(index, 1);
        ciRenderBoxList();
        ciUpdateSummary();
    }
}

/* ---------- Xác nhận hàng loạt (hàng trên cao, khó quét) ---------- */
const CI_BULK_PASSWORD = '0398802109';

function ciBulkConfirm() {
    if (ciState.busy) return;
    if (!ciState.shelfId) { ciShowError('#ci-step2-error', 'Chưa chọn vị trí.'); return; }

    if (!ciState.systemInventory.length) {
        ciShowAlert('Vị trí ' + ciState.shelfId + ' không có tồn kho hệ thống nên không thể xác nhận hàng loạt.', 'warning');
        return;
    }

    const pw = window.prompt('Nhập mật khẩu xác nhận hàng loạt cho vị trí ' + ciState.shelfId + ':', '');
    if (pw === null) return; // người dùng bấm huỷ
    if (pw.trim() !== CI_BULK_PASSWORD) {
        ciShowAlert('Mật khẩu không đúng. Không thể xác nhận hàng loạt.', 'error');
        return;
    }

    // Đưa toàn bộ mã hàng có tồn vào danh sách theo đúng SL tồn hệ thống,
    // chỉ bù phần còn thiếu so với số đã kiểm (đã lưu + đang chờ lưu).
    const listMap = ciCurrentListMap();
    let addedProducts = 0;
    let addedQty = 0;
    ciState.systemInventory.forEach(function(r) {
        const pid = String(r.product_id).toUpperCase();
        const system = Math.round(Number(r.quantity) || 0);
        const counted = (ciState.priorCounted[pid] || 0) + (listMap[pid] || 0);
        const remaining = system - counted;
        if (remaining > 0) {
            ciState.list.push({ productId: pid, qty: remaining, raw: 'BULK-CONFIRM' });
            addedProducts++;
            addedQty += remaining;
        }
    });

    if (!addedProducts) {
        ciShowAlert('Tất cả mã hàng tại ' + ciState.shelfId + ' đã đủ số lượng, không có gì để thêm.', 'warning');
        return;
    }

    // Dọn ô quét dở nếu có
    ciState.currentParsed = null;
    $('#ci-qty-input').val('').prop('disabled', true);
    $('#ci-btn-add').prop('disabled', true);
    $('#ci-current-product').text('-');
    ciHideError('#ci-step2-error');

    ciRenderBoxList();
    ciUpdateSummary();
    ciSetStatus('Đã xác nhận hàng loạt ' + addedProducts + ' mã (' + addedQty + ' đơn vị). Nhấn Lưu.', 'text-amber-700');
    ciShowAlert(
        'Đã đưa toàn bộ tồn của vị trí ' + ciState.shelfId + ' vào danh sách:\n' +
        addedProducts + ' mã hàng, tổng ' + addedQty + ' đơn vị.\n\n' +
        'Kiểm tra lại danh sách rồi nhấn "Lưu lịch sử quét" để hoàn tất.',
        'success'
    );
}

function ciRenderBoxList() {
    const tbody = $('#ci-box-list');
    tbody.empty();
    if (!ciState.list.length) {
        tbody.html('<tr><td colspan="4" class="p-2 text-center text-slate-500 text-xs">Chưa quét thùng nào</td></tr>');
        return;
    }
    ciState.list.forEach(function(it, idx) {
        tbody.append(
            '<tr class="border-b hover:bg-gray-50">' +
            '<td class="p-2 text-xs text-slate-600">' + (idx + 1) + '</td>' +
            '<td class="p-2 text-xs font-mono font-bold">' + it.productId + '</td>' +
            '<td class="p-2 text-right text-xs font-semibold">' + it.qty + '</td>' +
            '<td class="p-2 text-center"><button type="button" onclick="ciRemoveItem(' + idx + ')" class="text-red-500 text-sm font-bold hover:text-red-700">✕</button></td>' +
            '</tr>'
        );
    });
}

/* ---------- Lưu (bất cứ lúc nào) ---------- */

function ciSave() {
    if (ciState.busy) return;
    if (!ciState.list.length) { ciShowError('#ci-step2-error', 'Chưa có lần quét nào để lưu.'); return; }

    ciState.busy = true;
    ciUpdateSummary();
    ciSetStatus('Đang lưu lịch sử quét...', 'text-sky-700');

    $.post('api.php?action=check_inventory_submit', {
        shelf_id: ciState.shelfId,
        session_id: ciState.sessionId,
        items: JSON.stringify(ciState.list.map(function(it) {
            return { product_id: it.productId, qty: it.qty, raw: it.raw };
        }))
    }, function(res) {
        ciState.busy = false;
        if (!res || !res.success) {
            ciShowError('#ci-step2-error', (res && res.message) ? res.message : 'Lưu thất bại.');
            ciSetStatus('Lưu thất bại', 'text-red-700');
            ciUpdateSummary();
            return;
        }

        // Cập nhật số đã kiểm cộng dồn từ kết quả server, xóa danh sách chờ lưu
        const recon = res.reconciliation || [];
        ciState.priorCounted = {};
        recon.forEach(function(r) {
            ciState.priorCounted[String(r.product_id).toUpperCase()] = parseInt(r.counted_qty || 0, 10);
        });
        ciState.list = [];
        ciState.currentParsed = null;
        $('#ci-qty-input').val('').prop('disabled', true);
        $('#ci-btn-add').prop('disabled', true);
        $('#ci-current-product').text('-');
        ciRenderBoxList();
        ciUpdateSummary();
        ciLoadHistory();

        // Chỉ hiển thị cảnh báo - không chặn
        ciShowReconModal(res);
        const pending = res.pending_count || 0;
        if (pending > 0) {
            ciSetStatus('Đã lưu. Còn ' + pending + ' mã chưa kiểm đủ tại ' + res.shelf_id + '.', 'text-amber-700');
        } else if ((res.mismatch_count || 0) > 0) {
            ciSetStatus('Đã lưu. Có mã lệch tồn - xem bảng đối chiếu.', 'text-amber-700');
        } else {
            ciSetStatus('Đã lưu. Toàn bộ mã hàng khớp tồn hệ thống.', 'text-green-700');
        }
    }, 'json').fail(function() {
        ciState.busy = false;
        ciShowError('#ci-step2-error', 'Lỗi kết nối khi lưu. Vui lòng thử lại.');
        ciSetStatus('Lỗi kết nối', 'text-red-700');
        ciUpdateSummary();
    });
}

function ciShowReconModal(res) {
    const rows = res.reconciliation || [];
    const mismatch = res.mismatch_count || 0;

    $('#ci-recon-loc').text(res.shelf_id || ciState.shelfId);
    $('#ci-recon-content').removeClass('warning success').addClass(mismatch > 0 ? 'warning' : 'success');
    $('#ci-recon-title').text(mismatch > 0
        ? '⚠️ Đã lưu — còn mã hàng chưa khớp tồn hệ thống'
        : '✅ Đã lưu — toàn bộ khớp tồn hệ thống');

    let html = '<table class="ci-recon-table"><thead><tr>' +
        '<th>Mã hàng</th><th>Đã kiểm</th><th>Hệ thống</th><th>Lệch</th><th>TT</th></tr></thead><tbody>';
    rows.forEach(function(r) {
        const sys = (r.system_qty === null || r.system_qty === undefined) ? '—' : r.system_qty;
        const diff = (r.diff === null || r.diff === undefined) ? '(ngoài HT)' : (r.diff > 0 ? '+' + r.diff : r.diff);
        html += '<tr class="ci-row-' + r.status + '">' +
            '<td class="ci-cell-pid">' + r.product_id + '</td>' +
            '<td>' + r.counted_qty + '</td>' +
            '<td>' + sys + '</td>' +
            '<td>' + diff + '</td>' +
            '<td>' + (CI_STATUS_LABEL[r.status] || '') + '</td>' +
            '</tr>';
    });
    html += '</tbody></table>';
    if (mismatch > 0) {
        html += '<p style="font-size:12px; color:#64748b;">Dữ liệu đã được lưu. Bạn có thể quét tiếp các mã còn thiếu rồi lưu lại, hoặc đổi vị trí.</p>';
    }
    $('#ci-recon-body').html(html);
    $('#ci-recon-modal').css('display', 'block');
}

function ciResetToStep1(focusInput) {
    ciState.sessionId = '';
    ciState.shelfId = '';
    ciState.shelfName = '';
    ciState.systemInventory = [];
    ciState.priorCounted = {};
    ciState.list = [];
    ciState.currentParsed = null;

    $('#ci-step-2').hide();
    $('#ci-step-1').show();
    ciRenderBoxList();
    $('#ci-progress-body').html('<tr><td colspan="5" class="p-2 text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>');
    $('#ci-pinned-shelf').text('-');
    $('#ci-progress-chip').text('0/0');
    $('#ci-save-count').text('0');
    $('#ci-btn-save').prop('disabled', true);

    $('#ci-loc-input').val('');
    $('#ci-box-input').val('');
    $('#ci-qty-input').val('').prop('disabled', true);
    $('#ci-btn-add').prop('disabled', true);
    $('#ci-current-product').text('-');
    ciHideError('#ci-step1-error');
    ciHideError('#ci-step2-error');
    ciShowInfo('');

    if (focusInput !== false) {
        ciSetStatus('Bước 1: quét mã vị trí', 'text-sky-700');
        setTimeout(function() { $('#ci-loc-input').focus(); }, 60);
    }
}

/* ---------- Lịch sử ---------- */

function ciLoadHistory() {
    $.getJSON('api.php?action=check_inventory_history', { limit: 15 }, function(res) {
        if (!res || !res.success) return;
        ciState.history = res.sessions || [];
        ciRenderHistory();
    });
}

function ciRenderHistory() {
    const body = $('#ci-history-body');
    if (!ciState.history.length) {
        body.html('<tr><td colspan="6" class="text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>');
        return;
    }
    let html = '';
    ciState.history.forEach(function(s) {
        const mismatch = parseInt(s.mismatch_rows || 0, 10);
        html += '<tr>' +
            '<td class="text-xs">' + (s.checked_at || '-') + '</td>' +
            '<td class="text-xs font-mono">' + (s.shelf_id || '-') + '</td>' +
            '<td class="text-xs">' + (s.checked_by || '-') + '</td>' +
            '<td class="text-xs text-center">' + (s.scan_rows || 0) + '</td>' +
            '<td class="text-xs text-center">' + (s.total_qty || 0) + '</td>' +
            '<td class="text-xs text-center font-bold ' + (mismatch > 0 ? 'text-red-600' : 'text-green-600') + '">' +
            (mismatch > 0 ? mismatch : 'OK') + '</td>' +
            '</tr>';
    });
    body.html(html);
}

/* ---------- Bindings ---------- */

$('#ci-loc-input').on('keydown', function(e) {
    if (e.which === 13) { e.preventDefault(); ciConfirmLocation(); }
});
$('#ci-box-input').on('keydown', function(e) {
    if (e.which === 13) { e.preventDefault(); ciParseBoxFromInput(); }
});
$('#ci-box-input').on('input', function() {
    const raw = $(this).val();
    if (!raw || raw.indexOf('$') === -1) return;
    clearTimeout(ciBoxScanTimer);
    ciBoxScanTimer = setTimeout(function() {
        const finalRaw = $('#ci-box-input').val();
        const looksComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 3;
        if (looksComplete) {
            const parsed = ciParseBoxQr(finalRaw);
            if (parsed) ciProcessBoxScan(parsed, false);
        }
    }, 120);
});
$('#ci-qty-input').on('keydown', function(e) {
    if (e.which === 13) { e.preventDefault(); ciAddItemFromInput(); }
});

window.handleQRScannerScan = function(targetId, scannedValue) {
    const val = ciNormalize(scannedValue);
    if (targetId === 'ci-loc-input') {
        $('#ci-loc-input').val(val);
        ciConfirmLocation();
        return true;
    }
    if (targetId === 'ci-box-input') {
        $('#ci-box-input').val(val);
        return ciProcessBoxScan(ciParseBoxQr(val), true);
    }
    return false;
};

$(document).ready(function() {
    // Đưa modal ra thẳng body để không bị cắt bởi layout/transform của trang
    $('#ci-alert-modal, #ci-recon-modal').appendTo('body');

    ciResetToStep1(true);
    ciLoadHistory();

    $(document).on('keydown', function(e) {
        if ((e.key === 'Escape' || e.key === 'Enter') && $('#ci-alert-modal').css('display') !== 'none') {
            ciCloseAlert();
        } else if (e.key === 'Escape' && $('#ci-recon-modal').css('display') !== 'none') {
            ciCloseRecon();
        }
    });
});
</script>
