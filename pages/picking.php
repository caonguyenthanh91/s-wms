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
            <div id="step-pill-1" class="pda-step-pill current">1. QR lệnh</div>
            <div id="step-pill-2" class="pda-step-pill">2. Vị trí</div>
            <div id="step-pill-3" class="pda-step-pill">3. Quét thùng</div>
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
        <div class="text-xs text-slate-500 mt-1">Định dạng bắt buộc: [MÃ HÀNG]$[SỐ LƯỢNG]</div>

        <div class="relative mt-3">
            <input type="text" id="pick-order-input" class="pda-input" placeholder="VD: ABC123$24">
            <button type="button" onclick="openQRScannerModal('pick-order-input', 'QR Lệnh Picking')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-primary" onclick="handleStep1OrderScan()">Xác nhận QR lệnh</button>
        </div>

        <div id="step1-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="step1-info" class="pda-msg-info mt-2 hidden"></div>
    </div>

    <div id="step-2" class="pda-step pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 2</div>
        <div class="pda-title mt-1">Chọn đúng vị trí và quét QR kệ</div>
        <div class="text-xs text-slate-500 mt-1">Chỉ cho phép chọn vị trí đầu tiên trong danh sách (tồn thấp nhất).</div>

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
        <div class="text-xs text-slate-500 mt-1">Định dạng quét thùng: [TEXT]$[MÃ HÀNG]$[TEXT]$[SỐ LƯỢNG]$...</div>

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
            Tối đa mỗi lần: <span id="step3-max-qty" class="font-bold text-slate-900">0</span>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-success" id="btn-submit-pick" onclick="submitPickFromCurrentShelf()" disabled>OK - Cộng dồn và trừ tồn</button>
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

<script>
let pickingState = {
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
        const isSelected = pickingState.selectedShelf && pickingState.selectedShelf.shelf_id === shelf.shelf_id;
        const cls = [
            'pda-shelf-item',
            isSelected ? 'selected' : '',
            !isTop ? 'locked' : ''
        ].join(' ').trim();

        const btnLabel = isTop ? 'Chọn vị trí này' : 'Chỉ chọn khi lên đầu danh sách';
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
                <div class="text-xs mt-1 ${isTop ? 'text-sky-700' : 'text-slate-500'}">${btnLabel}</div>
            </button>
        `);
    });
}

function resetStep2And3Inputs() {
    pickingState.shelfConfirmed = false;
    pickingState.productConfirmed = false;

    $('#shelf-qr-input').val('');
    $('#box-qr-input').val('');
    $('#pick-qty-input').val('');

    $('#box-qr-input').prop('disabled', true);
    $('#pick-qty-input').prop('disabled', true);
    $('#btn-parse-box').prop('disabled', true);
    $('#btn-submit-pick').prop('disabled', true);
    $('#step3-max-qty').text('0');

    hideError('#step2-error');
    hideError('#step3-error');
    updateTopSummary();
}

function selectShelf(shelfId) {
    if (!pickingState.shelves.length) return;

    const target = normalizeQrText(shelfId);
    const topShelf = pickingState.shelves[0];

    if (!topShelf || target !== topShelf.shelf_id) {
        showError('#step2-error', 'Chỉ được chọn vị trí đầu tiên (tồn thấp nhất).');
        return;
    }

    pickingState.selectedShelf = { ...topShelf };
    $('#shelf-qr-input').prop('disabled', false).focus();
    $('#btn-confirm-shelf').prop('disabled', false);
    hideError('#step2-error');
    setWorkflowStatus('Đã chọn vị trí đầu danh sách, quét QR kệ để xác nhận.', 'text-amber-700');

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
    if (parts.length !== 2) return null;

    const productId = parts[0];
    const requiredQty = parseInt(parts[1], 10);
    if (!productId || isNaN(requiredQty) || requiredQty <= 0) return null;

    return { productId, requiredQty };
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
        showError('#step1-error', 'QR lệnh không hợp lệ. Cần đúng định dạng [MÃ HÀNG]$[SỐ LƯỢNG].');
        return;
    }

    pickingState.busy = true;
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
            data: { product_id: parsedOrder.productId },
            success: function(stockRes) {
                const shelves = sortShelvesByQtyAndCode(stockRes.shelves || []);
                const totalMain = parseFloat(stockRes.total_stock || 0);

                if (!shelves.length || totalMain <= 0) {
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
                pickingState.requiredQty = parsedOrder.requiredQty;
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
                setWorkflowStatus('Chọn vị trí đầu danh sách và quét QR kệ.', 'text-amber-700');
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
        showError('#step2-error', 'Hãy chọn vị trí đầu tiên trong danh sách trước.');
        return;
    }

    const scanned = normalizeQrText($('#shelf-qr-input').val());
    return processShelfQrScan(scanned, false);
}

function processShelfQrScan(scanned, fromScanner) {
    if (!pickingState.selectedShelf) {
        showError('#step2-error', 'Hãy chọn vị trí đầu tiên trong danh sách trước.');
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
        alert(`Sai vị trí. Cần ${expected} nhưng quét ${scanned}.`);
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

    $('#box-qr-input').prop('disabled', false).val('').focus();
    $('#btn-parse-box').prop('disabled', false);
    $('#pick-qty-input').prop('disabled', true).val('');
    $('#btn-submit-pick').prop('disabled', true);
    $('#shelf-qr-input').val('');

    setStepState(3);
    setWorkflowStatus(`Đã xác nhận đúng vị trí ${expected}.`, 'text-green-700');

    if (fromScanner && typeof window.closeQRScannerModal === 'function') {
        window.closeQRScannerModal();
    }

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
        showError('#step3-error', 'QR thùng không đúng định dạng yêu cầu.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    if (parsed.productId !== pickingState.productId) {
        alert(`Sai mã hàng. Cần ${pickingState.productId} nhưng quét ${parsed.productId}.`);
        showError('#step3-error', `Sai mã hàng. Cần ${pickingState.productId} nhưng quét ${parsed.productId}.`);
        $('#box-qr-input').val('').focus();
        setWorkflowStatus('Sai QR mã hàng thùng', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const maxAllowed = getMaxPickAllowedNow();
    if (maxAllowed <= 0) {
        showError('#step3-error', 'Không còn số lượng hợp lệ để pick tại vị trí hiện tại.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const suggestedQty = Math.min(parsed.qty, maxAllowed);

    pickingState.productConfirmed = true;
    hideError('#step3-error');
    $('#step3-max-qty').text(maxAllowed);
    $('#pick-qty-input').prop('disabled', false).val(suggestedQty).focus().select();
    $('#btn-submit-pick').prop('disabled', false);
    $('#box-qr-input').val('');

    setWorkflowStatus('Đã xác nhận mã hàng trên thùng, nhập/điều chỉnh số lượng rồi nhấn OK.', 'text-green-700');

    if (fromScanner && typeof window.closeQRScannerModal === 'function') {
        window.closeQRScannerModal();
    }

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
        pickingState.selectedShelf = null;
        pickingState.shelfConfirmed = false;
        pickingState.productConfirmed = false;

        if (!pickingState.shelves.length) {
            setWorkflowStatus('Không còn tồn kho chính ở các vị trí. Chưa đủ số lượng cần pick.', 'text-red-700');
            return;
        }

        setStepState(2);
        renderShelfList();
        $('#shelf-qr-input').prop('disabled', true).val('');
        $('#btn-confirm-shelf').prop('disabled', true);
        $('#box-qr-input').prop('disabled', true).val('');
        $('#pick-qty-input').prop('disabled', true).val('');
        $('#btn-parse-box').prop('disabled', true);
        $('#btn-submit-pick').prop('disabled', true);
        setWorkflowStatus('Vị trí đã hết tồn. Quay lại bước 2 để chọn vị trí đầu danh sách tiếp theo.', 'text-amber-700');
    } else {
        const maxAllowed = getMaxPickAllowedNow();
        $('#step3-max-qty').text(maxAllowed);
        $('#box-qr-input').val('').focus();
        $('#pick-qty-input').val('');
        $('#btn-submit-pick').prop('disabled', true);
        pickingState.productConfirmed = false;
        setWorkflowStatus('Tiếp tục quét thùng tại vị trí hiện tại.', 'text-sky-700');
    }
}

function submitPickFromCurrentShelf() {
    if (pickingState.busy) return;

    if (!pickingState.selectedShelf || !pickingState.shelfConfirmed || !pickingState.productConfirmed) {
        showError('#step3-error', 'Cần hoàn thành quét vị trí và quét thùng trước khi nhấn OK.');
        return;
    }

    const qty = parseInt($('#pick-qty-input').val(), 10);
    const maxAllowed = getMaxPickAllowedNow();

    if (isNaN(qty) || qty <= 0) {
        showError('#step3-error', 'Số lượng pick không hợp lệ.');
        return;
    }

    if (qty > maxAllowed) {
        showError('#step3-error', `Số lượng nhập vượt giới hạn. Tối đa hiện tại là ${maxAllowed}.`);
        return;
    }

    pickingState.busy = true;
    $('#btn-submit-pick').prop('disabled', true);
    hideError('#step3-error');
    setWorkflowStatus('Đang trừ tồn và ghi giao dịch...', 'text-sky-700');

    $.post('api.php?action=outbound_submit', {
        shelf_id: pickingState.selectedShelf.shelf_id,
        product_id: pickingState.productId,
        quantity: qty
    }, function(res) {
        if (!res.success) {
            showError('#step3-error', res.message || 'Không thể trừ tồn.');
            setWorkflowStatus('Trừ tồn thất bại', 'text-red-700');
            $('#btn-submit-pick').prop('disabled', false);
            pickingState.busy = false;
            return;
        }

        const selectedShelfId = pickingState.selectedShelf.shelf_id;
        pickingState.pickedQty += qty;

        pickingState.shelves = pickingState.shelves
            .map(function(shelf) {
                if (shelf.shelf_id === selectedShelfId) {
                    return {
                        ...shelf,
                        qty: Math.max(0, parseFloat(shelf.qty || 0) - qty)
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

        const now = res.transaction_time || new Date().toISOString().slice(0, 19).replace('T', ' ');
        pickingState.history.unshift({
            shelf_id: selectedShelfId,
            product_id: pickingState.productId,
            qty: qty,
            time: now
        });

        renderShelfList();
        renderHistory();
        updateTopSummary();
        moveToNextShelfIfNeeded();
        pickingState.busy = false;
    }, 'json').fail(function() {
        showError('#step3-error', 'Lỗi kết nối khi gửi lệnh trừ tồn.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        $('#btn-submit-pick').prop('disabled', false);
        pickingState.busy = false;
    });
}

function finishCurrentPickingOrder() {
    if (pickingState.pickedQty !== pickingState.requiredQty || !pickingState.requiredQty) {
        return;
    }

    setWorkflowStatus(`Đã hoàn tất lệnh ${pickingState.productId} (${pickingState.pickedQty}/${pickingState.requiredQty}).`, 'text-green-700');
    resetPickingJob(true);
}

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
        submitPickFromCurrentShelf();
    }
});

$(document).ready(function() {
    resetPickingJob(false);
    renderHistory();
});
</script>
