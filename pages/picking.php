<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="pda-picking-wrap pb-4">
    <div class="pda-card p-3 mb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title">Picking PDA 4 bước</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Chờ lệnh pick</div>
        </div>

        <div class="pda-step-index mt-3">
            <div id="step-pill-1" class="pda-step-pill current">1. QR Picking</div>
            <div id="step-pill-2" class="pda-step-pill">2. QR kệ</div>
            <div id="step-pill-3" class="pda-step-pill">3. QR thùng</div>
            <div id="step-pill-4" class="pda-step-pill">4. Hoàn tất</div>
        </div>

        <div class="pda-top-index mt-3">
            <div class="pda-kpi">
                <div class="pda-kpi-label">Mã hàng</div>
                <div id="summary-product" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Mã kệ</div>
                <div id="summary-shelf" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Đã pick / cần</div>
                <div id="summary-progress" class="pda-kpi-value">0 / 0</div>
            </div>
        </div>
    </div>

    <div id="step-1" class="pda-step active pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 1</div>
        <div class="pda-title mt-1">Quét QR lệnh picking</div>
        <!-- <div class="text-xs text-slate-500 mt-1">Định dạng: [INVOICE]$[MÃ HÀNG]$[SỐ LƯỢNG GỘP]</div> -->

        <div class="mt-3">
            <div class="relative">
                <input type="text" id="pick-order-input" class="pda-input" placeholder="[INVOICE]$[MÃ HÀNG]$[SỐ LƯỢNG]" maxlength="100">
                <button type="button" onclick="openQRScannerModal('pick-order-input', 'QR Lệnh Picking')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                    <i class="fas fa-qrcode text-lg"></i>
                </button>
            </div>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-primary" onclick="handleStep1OrderScan()">Xác nhận lệnh Picking</button>
        </div>

        <div id="step1-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="step1-info" class="pda-msg-info mt-2 hidden"></div>
    </div>

    <div id="step-2" class="pda-step pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 2</div>
        <div class="pda-title mt-1">Chọn đúng vị trí và quét QR kệ</div>
        <div class="text-xs text-slate-500 mt-1">Mặc định chỉ chọn vị trí đầu danh sách. Có thể bật chế độ chọn bất kỳ vị trí nếu cần.</div>

        <div class="mt-2 inline-flex rounded-lg border border-gray-300 overflow-hidden">
            <button type="button" id="shelf-mode-top" onclick="setShelfPickMode(false)" class="px-3 py-2 text-xs font-semibold bg-amber-600 text-white">Chỉ vị trí đầu</button>
            <button type="button" id="shelf-mode-any" onclick="setShelfPickMode(true)" class="px-3 py-2 text-xs font-semibold bg-white text-gray-700 hover:bg-gray-100">Chọn bất kỳ vị trí</button>
        </div>

        <div class="mt-3 pda-shelf-list" id="shelf-list"></div>

        <div class="relative mt-2">
            <input type="text" id="shelf-qr-input" class="pda-input" placeholder="Quét QR mã vị trí" disabled>
            <button type="button" onclick="openQRScannerModal('shelf-qr-input', 'QR Mã Vị Trí')" class="absolute right-3 top-1/2 -translate-y-1/2 text-amber-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-amber" id="btn-confirm-shelf" onclick="confirmShelfQr()" disabled>Xác nhận đúng vị trí</button>
        </div>

        <div id="step2-error" class="pda-msg-error mt-2 hidden"></div>
    </div>

    <div id="step-3" class="pda-step pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 3</div>
        <div class="pda-title mt-1">Quét thùng hàng và xác nhận số lượng</div>
        <div class="text-xs text-slate-500 mt-1">Định dạng quét thùng: [TEXT]$[MÃ HÀNG]$[TEXT]$[SỐ LƯỢNG]$ (Tự động điền số lượng khi quét đúng mã)</div>

        <!-- Scan Mode Buttons -->
        <div class="mb-3 inline-flex rounded-lg border border-gray-300 overflow-hidden gap-0">
            <button type="button" id="scan-mode-interrupt" onclick="setPickScanMode(false)" class="px-3 py-2 text-sm font-semibold bg-green-600 text-white rounded-l">Gián đoạn</button>
            <button type="button" id="scan-mode-continuous" onclick="setPickScanMode(true)" class="px-3 py-2 text-sm font-semibold bg-white text-gray-700 hover:bg-gray-100 rounded-r">Liên tục</button>
        </div>

        <div class="relative mt-3">
            <input type="text" id="box-qr-input" class="pda-input" placeholder="Quét QR trên thùng" disabled>
            <button type="button" onclick="openQRScannerModal('box-qr-input', 'QR Thùng Hàng')" class="absolute right-3 top-1/2 -translate-y-1/2 text-green-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-2">
            <button type="button" class="pda-btn pda-btn-primary" id="btn-parse-box" onclick="parseBoxQrAndSuggestQty()" disabled>Đọc QR thùng</button>
        </div>

        <div class="mt-3">
            <input type="number" id="pick-qty-input" class="pda-input" min="1" placeholder="Số lượng pick" disabled>
        </div>

        <div class="mt-2 text-xs text-slate-600">
            Tồn kho ban đầu: <span id="step3-max-qty" class="font-bold text-slate-900">0</span>
        </div>

        <!-- Box List Table -->
        <div class="mt-4 border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 sticky top-0">
                    <tr>
                        <th class="p-2 text-left text-xs font-bold text-gray-700">STT</th>
                        <th class="p-2 text-left text-xs font-bold text-gray-700">MÃ HÀNG</th>
                        <th class="p-2 text-right text-xs font-bold text-gray-700">SL</th>
                        <th class="p-2 text-center text-xs font-bold text-gray-700">XÓA</th>
                    </tr>
                </thead>
                <tbody id="pick-box-list">
                    <tr>
                        <td colspan="4" class="p-2 text-center text-slate-500 text-xs">Chưa quét thùng nào</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Counter -->
        <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs font-semibold text-blue-700">
            Đã pick / Sẽ pick / cần: <span id="pick-already">0</span> / <span id="pick-counter">0</span> / <span id="pick-target">0</span>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-success" id="btn-submit-pick" onclick="submitPickAndDeductStock()" disabled>OK - Cộng dồn và trừ tồn</button>
        </div>

        <div id="step3-error" class="pda-msg-error mt-2 hidden"></div>
    </div>

    <div id="step-4" class="pda-step active pda-card p-3">
        <div class="pda-subtitle">Bước 4</div>
        <div class="pda-title mt-1">Hoàn tất lệnh và theo dõi lịch sử</div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-success" id="btn-finish-order" disabled onclick="finishCurrentPickingOrder()">Xác nhận hoàn thành</button>
        </div>

        <div class="mt-3 pda-history-wrap">
            <table class="w-full pda-history-table">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 text-left">
                        <th>Vị trí</th>
                        <th>Mã hàng</th>
                        <th>SL pick</th>
                        <th>Thời điểm</th>
                    </tr>
                </thead>
                <tbody id="pick-history-body"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .error-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .error-modal-content {
        background-color: white;
        border-radius: 12px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        text-align: center;
        animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .error-modal-content h2 {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 16px;
    }
    .error-modal-content.error h2 {
        color: #dc2626;
    }
    .error-modal-content.success h2 {
        color: #059669;
    }
    .error-modal-content.warning h2 {
        color: #d97706;
    }
    .error-modal-content p {
        font-size: 16px;
        color: #374151;
        margin-bottom: 24px;
        line-height: 1.6;
        white-space: pre-wrap;
    }
    .error-modal-btn {
        color: white;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        border: none;
        transition: background-color 0.2s;
    }
    .error-modal-content.error .error-modal-btn {
        background-color: #dc2626;
    }
    .error-modal-content.error .error-modal-btn:hover {
        background-color: #b91c1c;
    }
    .error-modal-content.success .error-modal-btn {
        background-color: #059669;
    }
    .error-modal-content.success .error-modal-btn:hover {
        background-color: #047857;
    }
    .error-modal-content.warning .error-modal-btn {
        background-color: #d97706;
    }
    .error-modal-content.warning .error-modal-btn:hover {
        background-color: #b45309;
    }
</style>

<!-- Universal Modal -->
<div id="error-modal" class="error-modal-overlay" style="display: none;">
    <div id="error-modal-content" class="error-modal-content error">
        <h2 id="error-modal-title">⚠️ Cảnh báo</h2>
        <p id="error-modal-message"></p>
        <button onclick="closeErrorModal()" class="error-modal-btn">OK</button>
    </div>
</div>

<script>
let pickingState = {
    command: '',
    productId: '',
    productName: '',
    requiredQty: 0,
    pickedQty: 0,
    shelves: [],
    selectedShelf: null,
    shelfConfirmed: false,
    productConfirmed: false,
    busy: false,
    history: []
};

let boxQrScanTimer = null;
let isContinuousPickScan = false;
let pickBoxList = [];
let allowPickAnyShelf = false;
let currentPickBatchId = null;

function generatePickBatchId() {
    return 'PICK-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9).toUpperCase();
}

function showModal(message, type = 'error', title = null) {
    const titles = {
        error: '❌ Lỗi',
        success: '✅ Thành công',
        warning: '⚠️ Cảnh báo'
    };

    $('#error-modal-title').text(title || titles[type]);
    $('#error-modal-message').text(message);
    $('#error-modal-content').removeClass('error success warning').addClass(type);
    $('#error-modal').css('display', 'flex');
}

function submitOutboundWithRetry(shelf_id, product_id, quantity, command, case_no, is_picking, idempotency_key, maxRetries = 3) {
    return new Promise((resolve, reject) => {
        let retryCount = 0;

        function attemptSubmit() {
            $.post('api.php?action=outbound_submit', {
                shelf_id: shelf_id,
                product_id: product_id,
                quantity: quantity,
                command: command,
                case_no: case_no,
                is_picking: is_picking,
                idempotency_key: idempotency_key
            }, function(res) {
                if (res.success) {
                    resolve(res);
                } else {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        const delay = Math.pow(2, retryCount) * 500;
                        setTimeout(attemptSubmit, delay);
                    } else {
                        reject(new Error(res.message || 'Không thể trừ tồn item này.'));
                    }
                }
            }).fail(function(xhr, status, error) {
                if (retryCount < maxRetries) {
                    retryCount++;
                    const delay = Math.pow(2, retryCount) * 500;
                    setTimeout(attemptSubmit, delay);
                } else {
                    reject(new Error('Lỗi kết nối khi trừ tồn. Vui lòng kiểm tra kết nối mạng.'));
                }
            });
        }

        attemptSubmit();
    });
}

function showErrorModal(message) {
    showModal(message, 'error');
}

function closeErrorModal() {
    $('#error-modal').css('display', 'none');
}

function normalizeQrText(text) {
    return (text || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim()
        .toUpperCase();
}

function showError(selector, message) {
    $(selector).removeClass('hidden').text(message || 'Có lỗi xảy ra.');
}

function hideError(selector) {
    $(selector).addClass('hidden').text('');
}

function showInfoStep1(message) {
    if (!message) {
        $('#step1-info').addClass('hidden').text('');
        return;
    }
    $('#step1-info').removeClass('hidden').text(message);
}

function setWorkflowStatus(text, toneClass) {
    const el = $('#workflow-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-red-700 text-green-700 text-amber-700');
    el.addClass(toneClass || 'text-sky-700');
}

function updateTopSummary() {
    $('#summary-product').text(pickingState.productId || '-');
    $('#summary-shelf').text(pickingState.selectedShelf ? pickingState.selectedShelf.shelf_id : '-');
    $('#summary-progress').text(`${pickingState.pickedQty} / ${pickingState.requiredQty}`);
}

function setPickScanMode(isContinuous) {
    isContinuousPickScan = !!isContinuous;
    updatePickScanModeUI();
    $('#box-qr-input').focus();
}

function updatePickScanModeUI() {
    const interruptBtn = $('#scan-mode-interrupt');
    const continuousBtn = $('#scan-mode-continuous');

    if (isContinuousPickScan) {
        interruptBtn.removeClass('bg-green-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        continuousBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-green-600 text-white');
    } else {
        continuousBtn.removeClass('bg-green-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        interruptBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-green-600 text-white');
    }
}

function setShelfPickMode(allowAny) {
    allowPickAnyShelf = !!allowAny;
    updateShelfPickModeUI();
    renderShelfList();

    if (allowPickAnyShelf) {
        setWorkflowStatus('Đang bật chọn bất kỳ vị trí. Chọn vị trí rồi quét QR kệ.', 'text-amber-700');
    } else {
        setWorkflowStatus('Đang bật chế độ chỉ vị trí đầu danh sách.', 'text-amber-700');
    }
}

function updateShelfPickModeUI() {
    const topBtn = $('#shelf-mode-top');
    const anyBtn = $('#shelf-mode-any');

    if (allowPickAnyShelf) {
        topBtn.removeClass('bg-amber-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        anyBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-amber-600 text-white');
    } else {
        anyBtn.removeClass('bg-amber-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        topBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-amber-600 text-white');
    }
}

function updatePickBoxListDisplay() {
    const tbody = $('#pick-box-list');
    tbody.empty();

    if (pickBoxList.length === 0) {
        tbody.html('<tr><td colspan="4" class="p-2 text-center text-slate-500 text-xs">Chưa quét thùng nào</td></tr>');
        $('#btn-submit-pick').prop('disabled', true);
        return;
    }

    let totalQty = 0;
    pickBoxList.forEach(function(item, index) {
        totalQty += item.qty;
        tbody.append(`
            <tr class="border-b hover:bg-gray-50">
                <td class="p-2 text-xs text-slate-600">${index + 1}</td>
                <td class="p-2 text-xs font-mono font-bold">${item.productId}</td>
                <td class="p-2 text-right text-xs font-semibold">${item.qty}</td>
                <td class="p-2 text-center">
                    <button type="button" onclick="removePickBoxItem(${index})" class="text-red-500 text-sm font-bold hover:text-red-700">✕</button>
                </td>
            </tr>
        `);
    });

    updatePickBoxListCounter();
    updatePickSubmitButton();
}

function removePickBoxItem(index) {
    if (index >= 0 && index < pickBoxList.length) {
        pickBoxList.splice(index, 1);
        updatePickBoxListDisplay();
    }
}

function updatePickBoxListCounter() {
    const totalQtyInList = pickBoxList.reduce(function(sum, item) {
        return sum + item.qty;
    }, 0);
    const willPickTotal = pickingState.pickedQty + totalQtyInList;

    $('#pick-already').text(pickingState.pickedQty);
    $('#pick-counter').text(willPickTotal);
    $('#pick-target').text(pickingState.requiredQty);
}

function updatePickSubmitButton() {
    if (pickBoxList.length === 0) {
        $('#btn-submit-pick').prop('disabled', true);
        return;
    }

    const totalQty = pickBoxList.reduce(function(sum, item) {
        return sum + item.qty;
    }, 0);
    const totalWithPicked = pickingState.pickedQty + totalQty;

    // Cho phép xác nhận trừ tồn bất cứ khi nào đang có hàng trong danh sách,
    // không bắt buộc phải đủ 100% hoặc hết tồn vị trí mới được nhấn OK.
    // Lý do: với lot hàng lớn, nhân viên cần xác nhận trừ tồn theo nhiều đợt.
    // Chỉ chặn khi tổng sẽ vượt quá số lượng cần pick.
    $('#btn-submit-pick').prop('disabled', totalWithPicked > pickingState.requiredQty);
}

function setStepState(currentStep) {
    for (let i = 1; i <= 4; i++) {
        const pill = $(`#step-pill-${i}`);
        pill.removeClass('current done');
        if (i < currentStep) {
            pill.addClass('done');
        } else if (i === currentStep) {
            pill.addClass('current');
        }
    }

    $('#step-1').toggleClass('active', currentStep === 1);
    $('#step-2').toggleClass('active', currentStep === 2);
    $('#step-3').toggleClass('active', currentStep === 3);

    if (currentStep >= 4) {
        $('#step-pill-4').addClass('current');
    }
}

function sortShelvesByQtyAndCode(rows) {
    return (rows || [])
        .map(function(row) {
            return {
                shelf_id: normalizeQrText(row.shelf_id),
                shelf_name: row.shelf_name || '',
                qty: parseFloat(row.qty || 0)
            };
        })
        .filter(function(row) {
            return row.shelf_id && row.qty > 0;
        })
        .sort(function(a, b) {
            if (a.qty === b.qty) {
                return a.shelf_id.localeCompare(b.shelf_id, undefined, { numeric: true });
            }
            return a.qty - b.qty;
        });
}

function renderShelfList() {
    const list = $('#shelf-list');
    list.empty();

    if (!pickingState.shelves.length) {
        list.html('<div class="text-sm text-red-700 font-bold">Không còn vị trí tồn kho chính để pick.</div>');
        return;
    }

    const topShelfId = pickingState.shelves[0].shelf_id;

    pickingState.shelves.forEach(function(shelf) {
        const isTop = shelf.shelf_id === topShelfId;
        const canSelect = allowPickAnyShelf || isTop;
        const isSelected = pickingState.selectedShelf && pickingState.selectedShelf.shelf_id === shelf.shelf_id;
        const cls = [
            'pda-shelf-item',
            isSelected ? 'selected' : '',
            !canSelect ? 'locked' : ''
        ].join(' ').trim();

        const btnLabel = canSelect ? 'Chọn vị trí này' : 'Chỉ chọn khi lên đầu danh sách';
        const shelfName = shelf.shelf_name && shelf.shelf_name !== shelf.shelf_id ? ` - ${shelf.shelf_name}` : '';

        list.append(`
            <button type="button" class="${cls}" onclick="selectShelf('${shelf.shelf_id}')">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs text-slate-500">Mã vị trí${shelfName}</div>
                        <div class="font-black text-slate-900">${shelf.shelf_id}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-slate-500">Tồn</div>
                        <div class="text-xl font-black text-slate-900">${shelf.qty}</div>
                    </div>
                </div>
                <div class="text-xs mt-1 ${canSelect ? 'text-sky-700' : 'text-slate-500'}">${btnLabel}</div>
            </button>
        `);
    });
}

function resetStep2And3Inputs() {
    pickingState.shelfConfirmed = false;
    pickingState.productConfirmed = false;
    pickBoxList = [];

    $('#shelf-qr-input').val('');
    $('#box-qr-input').val('');
    $('#pick-qty-input').val('');

    $('#box-qr-input').prop('disabled', true);
    $('#pick-qty-input').prop('disabled', true);
    $('#btn-parse-box').prop('disabled', true);
    $('#btn-submit-pick').prop('disabled', true);
    $('#step3-max-qty').text('0');

    updatePickBoxListDisplay();
    updatePickBoxListCounter();

    hideError('#step2-error');
    hideError('#step3-error');
    updateTopSummary();
}

function selectShelf(shelfId) {
    if (!pickingState.shelves.length) return;

    const target = normalizeQrText(shelfId);
    const topShelf = pickingState.shelves[0];
    const selected = pickingState.shelves.find(function(shelf) {
        return shelf.shelf_id === target;
    });

    if (!selected) {
        showError('#step2-error', 'Vị trí không tồn tại trong danh sách hiện tại.');
        return;
    }

    if (!allowPickAnyShelf && (!topShelf || target !== topShelf.shelf_id)) {
        showError('#step2-error', 'Chỉ được chọn vị trí đầu tiên (tồn thấp nhất).');
        return;
    }

    pickingState.selectedShelf = { ...selected };
    $('#shelf-qr-input').prop('disabled', false).focus();
    $('#btn-confirm-shelf').prop('disabled', false);
    hideError('#step2-error');
    setWorkflowStatus(`Đã chọn vị trí ${selected.shelf_id}, quét QR kệ để xác nhận.`, 'text-amber-700');

    resetStep2And3Inputs();
    renderShelfList();
    updateTopSummary();
}

function parsePickOrderQr(raw) {
    const text = normalizeQrText(raw);
    if (!text || text.indexOf('$') === -1) return null;

    const parts = text.split('$').map(function(part) {
        return part.trim();
    });

    // Format mới: [command]$[product_id]$[quantity]
    if (parts.length >= 3) {
        const command = parts[0];
        const productId = parts[1];
        const requiredQty = parseInt(parts[2], 10);
        if (!command || !productId || isNaN(requiredQty) || requiredQty <= 0) return null;
        return { command, productId, requiredQty };
    }

    // Format cũ (legacy): [product_id]$[quantity]
    if (parts.length === 2) {
        const productId = parts[0];
        const requiredQty = parseInt(parts[1], 10);
        if (!productId || isNaN(requiredQty) || requiredQty <= 0) return null;
        return { command: '', productId, requiredQty };
    }

    return null;
}

function buildAuxStockMessage(auxRes) {
    if (!auxRes || !auxRes.success) {
        return 'Kho chính không có tồn. Không đọc được dữ liệu kho phụ.';
    }

    const pallets = Array.isArray(auxRes.pallets) ? auxRes.pallets : [];
    if (!pallets.length) {
        return 'Kho chính không có tồn. Kho phụ cũng không có tồn khả dụng trong import_temp.';
    }

    const firstPallets = pallets.slice(0, 6).map(function(row) {
        return `${row.pallet_id}: ${row.qty}`;
    }).join(' | ');

    return `Kho chính không có tồn. Kho phụ đang có ${auxRes.total_aux} tại pallet: ${firstPallets}`;
}

function resetPickingJob(clearInput) {
    pickingState = {
        command: '',
        caseNo: '',
        productId: '',
        productName: '',
        requiredQty: 0,
        pickedQty: 0,
        shelves: [],
        selectedShelf: null,
        shelfConfirmed: false,
        productConfirmed: false,
        busy: false,
        history: []
    };

    pickBoxList = [];

    $('#shelf-list').empty();
    $('#shelf-qr-input').val('').prop('disabled', true);
    $('#btn-confirm-shelf').prop('disabled', true);
    $('#box-qr-input').val('').prop('disabled', true);
    $('#pick-qty-input').val('').prop('disabled', true);
    $('#btn-parse-box').prop('disabled', true);
    $('#btn-submit-pick').prop('disabled', true);
    $('#btn-finish-order').prop('disabled', true);
    $('#step3-max-qty').text('0');
    $('#pick-history-body').empty();

    updatePickBoxListDisplay();
    updatePickBoxListCounter();

    hideError('#step1-error');
    hideError('#step2-error');
    hideError('#step3-error');
    showInfoStep1('');

    setWorkflowStatus('Chờ lệnh pick', 'text-sky-700');
    setStepState(1);
    updateTopSummary();

    if (clearInput) {
        $('#pick-order-input').val('').focus();
    }
}

function handleStep1OrderScan() {
    if (pickingState.busy) return;

    hideError('#step1-error');
    showInfoStep1('');

    const parsedOrder = parsePickOrderQr($('#pick-order-input').val());
    if (!parsedOrder) {
        showModal('QR lệnh không hợp lệ. Cần đúng định dạng [INVOICE]$[MÃ HÀNG]$[SỐ LƯỢNG].', 'error');
        showError('#step1-error', 'QR lệnh không hợp lệ. Cần đúng định dạng [INVOICE]$[MÃ HÀNG]$[SỐ LƯỢNG].');
        return;
    }

    pickingState.busy = true;
    pickingState.command = parsedOrder.command;
    setWorkflowStatus('Đang kiểm tra mã hàng và tồn kho chính...', 'text-sky-700');

    $.getJSON('api.php?action=check_product', { product_id: parsedOrder.productId }, function(productRes) {
        if (!productRes.success || !productRes.data) {
            showError('#step1-error', `Mã hàng ${parsedOrder.productId} chưa được đăng ký trong hệ thống.`);
            setWorkflowStatus('Sai mã hàng', 'text-red-700');
            pickingState.busy = false;
            return;
        }

        $.ajax({
            type: 'POST',
            url: 'api.php?action=get_shelf_inventory',
            dataType: 'json',
            data: {
                product_id: parsedOrder.productId,
                command: parsedOrder.command
            },
            success: function(stockRes) {
                const shelves = sortShelvesByQtyAndCode(stockRes.shelves || []);
                const totalMain = parseFloat(stockRes.total_stock || 0);

                // Kiểm tra nếu đã picking xong
                if (parsedOrder.command && stockRes.remaining_qty <= 0 && (stockRes.required_qty || 0) > 0) {
                    const msg = `Mã hàng: ${parsedOrder.productId}\nYêu cầu: ${stockRes.required_qty} items\nĐã picking: ${stockRes.picked_qty} items\nCòn lại: 0 items\n\nMã hàng này không cần picking lại.`;
                    showModal(msg, 'success', '✅ ĐÃ HOÀN TẤT PICKING!');
                    showError('#step1-error', msg.replace(/\n/g, ' '));
                    setWorkflowStatus('Picking mã hàng này đã hoàn tất', 'text-green-700');
                    pickingState.busy = false;
                    return;
                }

                if (!shelves.length || totalMain <= 0) {
                    const msg = `Mã hàng: ${parsedOrder.productId}\nSố lượng cần: ${parsedOrder.requiredQty} items\n\nKhông tìm thấy vị trí nào có tồn kho chính.\nVui lòng liên hệ Leader để kiểm tra!`;
                    showModal(msg, 'error', '❌ KHÔNG CÓ TỒN KHO!');
                    $.getJSON('api.php?action=get_aux_stock_by_product', { product_id: parsedOrder.productId }, function(auxRes) {
                        showError('#step1-error', 'Không có tồn kho chính để pick.');
                        showInfoStep1(buildAuxStockMessage(auxRes));
                        setWorkflowStatus('Dừng tại bước 1: không có tồn chính', 'text-red-700');
                        pickingState.busy = false;
                    }).fail(function() {
                        showError('#step1-error', 'Không có tồn kho chính để pick.');
                        showInfoStep1('Không đọc được thông tin kho phụ.');
                        setWorkflowStatus('Dừng tại bước 1: không có tồn chính', 'text-red-700');
                        pickingState.busy = false;
                    });
                    return;
                }

                pickingState.productId = parsedOrder.productId;
                pickingState.productName = productRes.data.product_name || '';
                // Sử dụng remaining_qty từ API (đã tính từ export_temp - export_log)
                pickingState.requiredQty = parsedOrder.command && stockRes.remaining_qty > 0
                    ? stockRes.remaining_qty
                    : parsedOrder.requiredQty;
                pickingState.pickedQty = 0;
                pickingState.shelves = shelves;
                pickingState.selectedShelf = null;
                pickingState.history = [];
                resetStep2And3Inputs();

                $('#pick-history-body').empty();
                $('#btn-finish-order').prop('disabled', true);
                renderShelfList();
                setStepState(2);
                updateTopSummary();

                // Hiển thị thông báo nếu có lịch sử picking
                let statusMsg = 'Chọn vị trí đầu danh sách và quét QR kệ.';
                let infoMsg = '';
                if (allowPickAnyShelf) {
                    statusMsg = 'Chọn vị trí bất kỳ trong danh sách và quét QR kệ.';
                }
                if (parsedOrder.command && stockRes.required_qty > 0) {
                    infoMsg = `📋 Yêu cầu: ${stockRes.required_qty} | Đã picking: ${stockRes.picked_qty} | Còn lại: ${stockRes.remaining_qty}`;
                    if (stockRes.picked_qty > 0) {
                        statusMsg += ` (Tiếp tục picking ${stockRes.remaining_qty} items còn lại)`;
                    }
                }
                if (infoMsg) {
                    showInfoStep1(infoMsg);
                }
                setWorkflowStatus(statusMsg, 'text-amber-700');
                pickingState.busy = false;
            },
            error: function() {
                showError('#step1-error', 'Không thể tải tồn kho chính.');
                setWorkflowStatus('Lỗi kết nối', 'text-red-700');
                pickingState.busy = false;
            }
        });
    }).fail(function() {
        showError('#step1-error', 'Không thể kiểm tra thông tin sản phẩm.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        pickingState.busy = false;
    });
}

function confirmShelfQr() {
    if (!pickingState.selectedShelf) {
        showError('#step2-error', 'Hãy chọn vị trí trong danh sách trước.');
        return;
    }

    const scanned = normalizeQrText($('#shelf-qr-input').val());
    return processShelfQrScan(scanned, false);
}

function processShelfQrScan(scanned, fromScanner) {
    if (!pickingState.selectedShelf) {
        showError('#step2-error', 'Hãy chọn vị trí trong danh sách trước.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const expected = normalizeQrText(pickingState.selectedShelf.shelf_id);

    if (!scanned) {
        showError('#step2-error', 'Hãy quét QR mã vị trí.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    if (scanned !== expected) {
        showModal(`Cần ${expected}\nNhưng quét ${scanned}.`, 'error', 'Sai vị trí');
        showError('#step2-error', `Sai vị trí. Cần ${expected} nhưng quét ${scanned}.`);
        $('#shelf-qr-input').val('').focus();
        setWorkflowStatus('Sai QR vị trí', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    pickingState.shelfConfirmed = true;
    hideError('#step2-error');

    $('#box-qr-input').prop('disabled', false).val('');
    $('#btn-parse-box').prop('disabled', false);
    $('#pick-qty-input').prop('disabled', true).val('');
    $('#shelf-qr-input').val('');

    // Initialize scan mode for step 3
    setPickScanMode(false);
    updatePickBoxListDisplay();
    updatePickBoxListCounter();

    // Display initial stock for this shelf/product
    const initialStock = Math.max(0, parseFloat(pickingState.selectedShelf.qty || 0));
    $('#step3-max-qty').text(initialStock);

    setStepState(3);
    setWorkflowStatus(`Đã xác nhận đúng vị trí ${expected}. Sẵn sàng quét mã thùng.`, 'text-green-700');

    if (fromScanner && typeof window.closeQRScannerModal === 'function') {
        window.closeQRScannerModal();
    }

    // Focus vào ô quét mã thùng sau khi modal đóng
    setTimeout(function() {
        $('#box-qr-input').focus();
    }, 100);

    return true;
}

function parseBoxQr(rawValue) {
    const raw = normalizeQrText(rawValue);
    if (!raw || raw.indexOf('$') === -1) return null;

    const parts = raw.split('$').map(function(part) {
        return part.trim();
    });

    if (parts.length < 4) return null;

    const productId = (parts[1] || '').toUpperCase();
    if (!productId) return null;

    let qtyToken = '';
    for (let i = parts.length - 1; i >= 3; i--) {
        if (/^\d+$/.test(parts[i])) {
            qtyToken = parts[i];
            break;
        }
    }

    const qty = parseInt(qtyToken, 10);
    if (isNaN(qty) || qty <= 0) return null;

    return { productId, qty };
}

function getMaxPickAllowedNow() {
    if (!pickingState.selectedShelf) return 0;
    const remainingNeed = Math.max(0, pickingState.requiredQty - pickingState.pickedQty);
    const shelfQty = Math.max(0, parseFloat(pickingState.selectedShelf.qty || 0));
    return Math.floor(Math.min(remainingNeed, shelfQty));
}

function parseBoxQrAndSuggestQty() {
    if (!pickingState.shelfConfirmed || !pickingState.selectedShelf) {
        showError('#step3-error', 'Cần xác nhận đúng vị trí ở bước 2 trước.');
        return;
    }

    const parsed = parseBoxQr($('#box-qr-input').val());
    return processBoxQrScan(parsed, false);
}

function processBoxQrScan(parsed, fromScanner) {
    if (!pickingState.shelfConfirmed || !pickingState.selectedShelf) {
        showError('#step3-error', 'Cần xác nhận đúng vị trí ở bước 2 trước.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    if (!parsed) {
        const msg = 'QR thùng không đúng định dạng yêu cầu.\n\nCần [TEXT]$[MÃ HÀNG]$[TEXT]$[SỐ LƯỢNG]';
        showModal(msg, 'error');
        showError('#step3-error', msg.replace(/\n/g, ' '));
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    if (parsed.productId !== pickingState.productId) {
        const msg = `✓ Cần: ${pickingState.productId}\n✗ Quét: ${parsed.productId}\n\nVui lòng quét lại đúng mã hàng!`;
        showModal(msg, 'error', '❌ SAI MÃ HÀNG!');
        showError('#step3-error', msg.replace(/\n/g, ' '));
        $('#box-qr-input').val('').focus();
        setWorkflowStatus('Sai QR mã hàng thùng', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const maxAllowed = getMaxPickAllowedNow();
    if (maxAllowed <= 0) {
        const msg = 'Không còn số lượng hợp lệ để pick tại vị trí hiện tại.';
        showModal(msg, 'error');
        showError('#step3-error', msg);
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const initialShelfQty = Math.max(0, parseFloat(pickingState.selectedShelf.qty || 0));
    const requiredQty = pickingState.requiredQty;
    const pickedQty = pickingState.pickedQty;
    const remainingNeed = requiredQty - pickedQty;

    // Calculate total qty already in list (to be picked from this shelf)
    const totalQtyInList = pickBoxList.reduce(function(sum, item) {
        return sum + item.qty;
    }, 0);

    // Remaining shelf stock after deducting items in the list
    const remainingShelfStock = initialShelfQty - totalQtyInList;

    // Check if we need to adjust quantity (auto-cap to remaining need)
    const remainingNeedAfterList = requiredQty - pickedQty - totalQtyInList;
    const qtyToTake = Math.min(parsed.qty, remainingNeedAfterList);

    // Validate 1: Nếu đã đủ số cần pick, thông báo nhưng vẫn cho phép lấy số lượng còn thiếu
    if (remainingNeedAfterList <= 0) {
        const msg = `⚠️ ĐÃ ĐỦ SỐ LƯỢNG CẦN PICK!\n\nĐã pick: ${pickedQty} items\nDanh sách: ${totalQtyInList} items\nCần: ${requiredQty} items\n\nBạn đã đủ số lượng cần pick. Nhấn OK để hoàn tất.`;
        showModal(msg, 'warning', '⚠️ ĐỦ LƯỢNG RỒI!');
        $('#box-qr-input').prop('disabled', true);
        $('#btn-parse-box').prop('disabled', true);
        setWorkflowStatus('Đủ số lượng cần pick rồi! Vui lòng xác nhận để hoàn tất.', 'text-orange-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    // Validate 1b: Nếu thùng quét có số lượng lớn hơn số cần, tự động điều chỉnh
    if (qtyToTake < parsed.qty) {
        const msg = `ℹ️ SỐ LƯỢNG ĐÃ ĐƯỢC ĐIỀU CHỈNH\n\nThùng này có: ${parsed.qty} items\nSố cần pick còn lại: ${remainingNeedAfterList} items\n\n✓ Hệ thống sẽ lấy ${qtyToTake} items để đủ đúng yêu cầu.`;
        showModal(msg, 'success', 'ℹ️ ĐIỀU CHỈNH SỐ LƯỢNG');
    }

    // Validate 2: Cảnh báo nếu vượt quá tồn kho tại vị trí này
    if (parsed.qty > remainingShelfStock) {
        const msg = `❌ HẾT TỒN KHO TẠI VỊ TRÍ NÀY!\n\nThùng này có: ${parsed.qty} items\nTồn kho ban đầu: ${initialShelfQty} items\nĐã lấy từ danh sách: ${totalQtyInList} items\nCòn lại: ${remainingShelfStock} items\n\nVị trí này không đủ hàng nữa. Vui lòng chuyển sang kệ tiếp theo!`;
        showModal(msg, 'error', '❌ HẾT TỒN KHO!');
        showError('#step3-error', `❌ Hết tồn kho! (${parsed.qty} > ${remainingShelfStock} còn lại)`);
        $('#box-qr-input').prop('disabled', true);
        $('#btn-parse-box').prop('disabled', true);
        setWorkflowStatus('Hết tồn kho tại vị trí này! Vui lòng chuyển sang vị trí tiếp theo.', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const suggestedQty = Math.min(qtyToTake, maxAllowed);

    pickingState.productConfirmed = true;
    hideError('#step3-error');
    $('#step3-max-qty').text(maxAllowed);
    $('#pick-qty-input').prop('disabled', false).val(suggestedQty).focus().select();
    $('#box-qr-input').val('');

    // Thông báo nếu đây là thùng cuối cùng
    if (suggestedQty + pickedQty + totalQtyInList === requiredQty) {
        setWorkflowStatus('✓ Thùng này sẽ hoàn tất lệnh picking!', 'text-green-700');
    }

    if (fromScanner && typeof window.closeQRScannerModal === 'function') {
        window.closeQRScannerModal();
    }

    // Mode liên tục: tự động thêm vào danh sách
    if (isContinuousPickScan) {
        // Thêm vào danh sách
        pickBoxList.push({ productId: parsed.productId, qty: suggestedQty });
        updatePickBoxListDisplay();
        updatePickBoxListCounter();

        // Reset input, focus lại để quét tiếp
        $('#pick-qty-input').val('');
        $('#box-qr-input').val('').focus();
        pickingState.productConfirmed = false;

        // Thông báo nếu đây là thùng cuối cùng
        if (suggestedQty + pickedQty + totalQtyInList + suggestedQty === requiredQty) {
            setWorkflowStatus('✓ Đã đủ số lượng! Nhấn OK để hoàn tất.', 'text-green-700');
            $('#box-qr-input').prop('disabled', true);
            $('#btn-parse-box').prop('disabled', true);
        } else {
            setWorkflowStatus('Đã thêm vào danh sách. Tiếp tục quét thùng tiếp theo.', 'text-green-700');
        }
        return true;
    }

    // Mode gián đoạn: chờ user nhập và nhấn OK
    setWorkflowStatus('Đã xác nhận mã hàng trên thùng, nhập/điều chỉnh số lượng rồi nhấn OK.', 'text-green-700');
    return true;
}

function renderHistory() {
    const body = $('#pick-history-body');
    body.empty();

    if (!pickingState.history.length) {
        body.append('<tr><td colspan="4" class="text-center text-slate-500">Chưa có giao dịch pick.</td></tr>');
        return;
    }

    pickingState.history.forEach(function(item) {
        body.append(`
            <tr>
                <td>${item.shelf_id}</td>
                <td>${item.product_id}</td>
                <td>${item.qty}</td>
                <td>${item.time}</td>
            </tr>
        `);
    });
}

function moveToNextShelfIfNeeded() {
    if (pickingState.pickedQty >= pickingState.requiredQty) {
        $('#btn-finish-order').prop('disabled', false);
        setStepState(4);
        setWorkflowStatus('Đã đủ số lượng cần pick. Nhấn "Xác nhận hoàn thành".', 'text-green-700');
        $('#box-qr-input').prop('disabled', true);
        $('#pick-qty-input').prop('disabled', true);
        $('#btn-parse-box').prop('disabled', true);
        $('#btn-submit-pick').prop('disabled', true);
        return;
    }

    if (pickingState.selectedShelf && parseFloat(pickingState.selectedShelf.qty || 0) <= 0) {
        const remainingNeed = pickingState.requiredQty - pickingState.pickedQty;
        const currentShelf = pickingState.selectedShelf.shelf_id || 'vị trí hiện tại';

        if (remainingNeed > 0) {
            if (!pickingState.shelves.length) {
                // Hết vị trí + chưa đủ số lượng → Cho phép hoàn tất
                const msg = `Đã pick: ${pickingState.pickedQty} items\nCần pick: ${pickingState.requiredQty} items\nCòn thiếu: ${remainingNeed} items\n\nKhông còn vị trí nào có hàng.\nBạn có thể hoàn tất picking và quay lại chờ phiếu tiếp theo.`;
                showModal(msg, 'warning', '⚠️ HẾT VỊ TRÍ CÓ TỒN!');

                // Cho phép hoàn tất
                $('#btn-finish-order').prop('disabled', false);
                setStepState(4);
                $('#box-qr-input').prop('disabled', true);
                $('#pick-qty-input').prop('disabled', true);
                $('#btn-parse-box').prop('disabled', true);
                $('#btn-submit-pick').prop('disabled', true);
                setWorkflowStatus('Hết vị trí có tồn. Nhấn "Xác nhận hoàn thành" để hoàn tất picking.', 'text-amber-700');
                return;
            }
            const msg = `Vị trí: ${currentShelf}\nTồn lại: 0 items\n\nĐã pick: ${pickingState.pickedQty}/${pickingState.requiredQty} items\n\nVui lòng chọn vị trí tiếp theo để tiếp tục!`;
            showModal(msg, 'warning', '⚠️ VỊ TRÍ HẾT TỒN!');
        }

        // Capture selectedShelfId before setting to null
        const selectedShelfId = pickingState.selectedShelf.shelf_id;
        pickingState.selectedShelf = null;
        pickingState.shelfConfirmed = false;
        pickingState.productConfirmed = false;
        pickBoxList = [];

        if (pickingState.shelves.length) {
            // Loại bỏ vị trí hết tồn khỏi danh sách
            pickingState.shelves = pickingState.shelves.filter(function(shelf) {
                return shelf.shelf_id !== selectedShelfId;
            });

            // Quay lại bước 2 với danh sách vị trí đã được refresh
            setStepState(2);
            renderShelfList();

            // Enable bước 2 để quét mã vị trí mới
            $('#shelf-qr-input').prop('disabled', false).val('').focus();
            $('#btn-confirm-shelf').prop('disabled', false);

            // Disable bước 3 và reset
            $('#box-qr-input').prop('disabled', true).val('');
            $('#pick-qty-input').prop('disabled', true).val('');
            $('#btn-parse-box').prop('disabled', true);
            $('#btn-submit-pick').prop('disabled', true);
            $('#step3-max-qty').text('0');

            updatePickBoxListDisplay();
            updatePickBoxListCounter();

            if (allowPickAnyShelf) {
                setWorkflowStatus('Vị trí đã hết tồn. Quay lại bước 2 để chọn vị trí tiếp theo.', 'text-amber-700');
            } else {
                setWorkflowStatus('Vị trí đã hết tồn. Quay lại bước 2 để chọn vị trí đầu danh sách tiếp theo.', 'text-amber-700');
            }
        }
    } else {
        const maxAllowed = getMaxPickAllowedNow();
        $('#step3-max-qty').text(maxAllowed);
        $('#box-qr-input').val('').focus();
        $('#pick-qty-input').val('');
        pickingState.productConfirmed = false;
        pickBoxList = [];
        updatePickBoxListDisplay();
        updatePickBoxListCounter();
        setWorkflowStatus('Tiếp tục quét thùng tại vị trí hiện tại.', 'text-sky-700');
    }
}

function addPickBoxItemFromInput() {
    if (!pickingState.productConfirmed) {
        showError('#step3-error', 'Cần quét thùng trước.');
        return false;
    }

    const qty = parseInt($('#pick-qty-input').val(), 10);
    const maxAllowed = getMaxPickAllowedNow();

    if (isNaN(qty) || qty <= 0) {
        showError('#step3-error', 'Số lượng pick không hợp lệ.');
        return false;
    }

    if (qty > maxAllowed) {
        const msg = `Số lượng nhập vượt giới hạn. Tối đa hiện tại là ${maxAllowed}.`;
        showModal(msg, 'warning', '⚠️ VƯỢT GIỚI HẠN!');
        return false;
    }

    // Thêm vào danh sách
    pickBoxList.push({ productId: pickingState.productId, qty: qty });
    updatePickBoxListDisplay();

    // Reset input
    $('#pick-qty-input').val('');
    $('#box-qr-input').val('').focus();
    pickingState.productConfirmed = false;
    hideError('#step3-error');

    setWorkflowStatus('Đã thêm vào danh sách. Tiếp tục quét thùng tiếp theo.', 'text-green-700');
    return true;
}

function submitPickAndDeductStock() {
    if (pickingState.busy) return;

    if (pickBoxList.length === 0) {
        showError('#step3-error', 'Danh sách thùng hàng trống.');
        return;
    }

    const totalQty = pickBoxList.reduce(function(sum, item) {
        return sum + item.qty;
    }, 0);
    const totalWithPicked = pickingState.pickedQty + totalQty;

    // Validate tổng số lượng
    if (totalWithPicked !== pickingState.requiredQty) {
        // Cho phép nếu là thùng cuối (tức là chưa đủ nhưng không có vị trí khác)
        if (totalWithPicked > pickingState.requiredQty) {
            showModal(
                `Tổng số lượng vượt quá yêu cầu!\n\nĐã pick: ${pickingState.pickedQty}\nDanh sách: ${totalQty}\nTổng: ${totalWithPicked}\nCần: ${pickingState.requiredQty}`,
                'error'
            );
            return;
        }
    }

    pickingState.busy = true;
    currentPickBatchId = generatePickBatchId();
    $('#btn-submit-pick').prop('disabled', true);
    hideError('#step3-error');
    setWorkflowStatus('Đang trừ tồn và ghi giao dịch...', 'text-sky-700');

    const selectedShelfId = pickingState.selectedShelf.shelf_id;
    let successCount = 0;
    let failedItems = [];

    // Trừ tồn lần lượt cho mỗi thùng với retry logic
    async function processItems(index) {
        if (index >= pickBoxList.length) {
            if (successCount === pickBoxList.length) {
                // Update state
                pickingState.pickedQty += totalQty;

                pickingState.shelves = pickingState.shelves
                    .map(function(shelf) {
                        if (shelf.shelf_id === selectedShelfId) {
                            return {
                                ...shelf,
                                qty: Math.max(0, parseFloat(shelf.qty || 0) - totalQty)
                            };
                        }
                        return shelf;
                    })
                    .filter(function(shelf) {
                        return parseFloat(shelf.qty || 0) > 0;
                    })
                    .sort(function(a, b) {
                        if (a.qty === b.qty) {
                            return a.shelf_id.localeCompare(b.shelf_id, undefined, { numeric: true });
                        }
                        return a.qty - b.qty;
                    });

                const selected = pickingState.shelves.find(function(shelf) {
                    return shelf.shelf_id === selectedShelfId;
                });
                pickingState.selectedShelf = selected ? { ...selected } : null;

                // Add to history
                const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                pickingState.history.unshift({
                    shelf_id: selectedShelfId,
                    product_id: pickingState.productId,
                    qty: totalQty,
                    time: now
                });

                renderShelfList();
                renderHistory();
                updateTopSummary();

                // Reset danh sách
                pickBoxList = [];
                updatePickBoxListDisplay();
                updatePickBoxListCounter();

                // Check if shelf is completely empty
                if (!pickingState.selectedShelf) {
                    // Vị trí hết tồn hoàn toàn → reload trang
                    showModal(`Vị trí ${selectedShelfId} đã hết tồn hoàn toàn.\n\nHệ thống sẽ tải lại để lấy vị trí tiếp theo.`, 'success', '✅ THÀNH CÔNG!');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                    return;
                }

                // Vị trí còn tồn → reset bước 3 để quét tiếp
                moveToNextShelfIfNeeded();
                pickingState.busy = false;
            } else {
                // Có lỗi xảy ra
                const msg = `Không thể trừ tồn ${failedItems.length} thùng hàng. Các thùng thất bại: ${failedItems.join(', ')}`;
                showError('#step3-error', msg);
                setWorkflowStatus('Trừ tồn thất bại - kiểm tra kết nối mạng', 'text-red-700');
                $('#btn-submit-pick').prop('disabled', false);
                pickingState.busy = false;
            }
            return;
        }

        const item = pickBoxList[index];
        const idempotencyKey = `${currentPickBatchId}-${index}`;

        try {
            const res = await submitOutboundWithRetry(
                selectedShelfId,
                item.productId,
                item.qty,
                pickingState.command,
                '001',
                pickingState.command ? 1 : 0,
                idempotencyKey,
                3
            );
            successCount++;
            await processItems(index + 1);
        } catch (error) {
            failedItems.push(`${item.productId}(${item.qty})`);
            await processItems(index + 1);
        }
    }

    processItems(0);
}

function verifyExportLogReconciliation(batchId) {
    return new Promise((resolve) => {
        $.getJSON('api.php?action=verify_export_log', {
            command: pickingState.command,
            product_id: pickingState.productId,
            batch_id: batchId
        }, function(res) {
            resolve(res);
        }).fail(function() {
            resolve({ success: false, log_recorded: false });
        });
    });
}

function finishCurrentPickingOrder() {
    if (!pickingState.requiredQty || pickingState.pickedQty <= 0) {
        return;
    }

    const isComplete = pickingState.pickedQty >= pickingState.requiredQty;
    const status = isComplete ? 'success' : 'warning';
    const title = isComplete ? '✅ HOÀN TẤT' : '⚠️ HOÀN TẤT (CHƯA ĐỦ)';

    let msg = `Mã hàng: ${pickingState.productId}\nĐã pick: ${pickingState.pickedQty} items\nCần pick: ${pickingState.requiredQty} items`;

    // Nếu là picking command, thêm verification message
    if (pickingState.command && currentPickBatchId) {
        msg += '\n\n⏳ Đang xác minh log...';
        showModal(msg, status, title);

        verifyExportLogReconciliation(currentPickBatchId).then(function(verifyRes) {
            if (verifyRes.success && verifyRes.log_recorded) {
                msg = `Mã hàng: ${pickingState.productId}\nĐã pick: ${pickingState.pickedQty} items\nCần pick: ${pickingState.requiredQty} items\n✅ Export log đã được ghi (${verifyRes.log_count} record)\n\nQuay lại màn hình chờ phiếu picking tiếp theo.`;
            } else {
                msg = `Mã hàng: ${pickingState.productId}\nĐã pick: ${pickingState.pickedQty} items\nCần pick: ${pickingState.requiredQty} items\n⚠️ Không thể xác minh export log. Vui lòng báo cáo cho Leader!\n\nQuay lại màn hình chờ phiếu picking tiếp theo.`;
            }
            showModal(msg, status, title);
            setWorkflowStatus(`Đã hoàn tất lệnh ${pickingState.productId} (${pickingState.pickedQty}/${pickingState.requiredQty}).`, 'text-green-700');
            resetPickingJob(true);
        });
    } else {
        msg += '\n\nQuay lại màn hình chờ phiếu picking tiếp theo.';
        showModal(msg, status, title);
        setWorkflowStatus(`Đã hoàn tất lệnh ${pickingState.productId} (${pickingState.pickedQty}/${pickingState.requiredQty}).`, 'text-green-700');
        resetPickingJob(true);
    }
}

$('#invoice-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        $('#pick-order-input').focus();
    }
});

$('#pick-order-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handleStep1OrderScan();
    }
});

$('#shelf-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        confirmShelfQr();
    }
});

$('#box-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        parseBoxQrAndSuggestQty();
    }
});

// Tự động parse và fill số lượng khi quét thùng hàng
$('#box-qr-input').on('input', function() {
    const rawValue = $(this).val();
    if (!rawValue || rawValue.indexOf('$') === -1) return;

    clearTimeout(boxQrScanTimer);
    boxQrScanTimer = setTimeout(function() {
        const finalRaw = $('#box-qr-input').val();
        const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 4;
        if (isLikelyComplete) {
            const parsed = parseBoxQr(finalRaw);
            if (parsed) {
                processBoxQrScan(parsed, false);
            }
        }
    }, 100);
});

window.handleQRScannerScan = function(targetId, scannedValue) {
    const normalizedValue = normalizeQrText(scannedValue);

    if (targetId === 'shelf-qr-input') {
        $('#shelf-qr-input').val(normalizedValue);
        return processShelfQrScan(normalizedValue, true);
    }

    if (targetId === 'box-qr-input') {
        $('#box-qr-input').val(normalizedValue);
        return processBoxQrScan(parseBoxQr(normalizedValue), true);
    }

    return false;
};

$('#pick-qty-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        if (isContinuousPickScan) {
            // Mode liên tục: chỉ cần Enter là thêm vào list
            addPickBoxItemFromInput();
        } else {
            // Mode gián đoạn: Enter để thêm
            addPickBoxItemFromInput();
        }
    }
});

$(document).ready(function() {
    resetPickingJob(false);
    renderHistory();
    setPickScanMode(false); // Initialize scan mode buttons
    updateShelfPickModeUI();
    updatePickBoxListDisplay();

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
        if (e.key === 'Enter' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
    });
});
</script>
