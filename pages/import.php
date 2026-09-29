<div id="import-container" class="max-w-4xl mx-auto">
    <style>
        @media (max-width: 900px) {
            #import-container .pda-compact-scroll {
                max-height: 35vh;
                overflow-y: auto;
            }
        }
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

    <!-- Step 1: Input Pallet ID -->
    <div id="step-1" class="bg-white p-2 rounded-lg shadow-md text-center">
        <h3 class="text-lg font-bold mb-2 text-gray-800">Nhập mã Pallet</h3>
        <!-- <div class="mb-6 text-6xl">📦</div> -->
        <div class="relative w-full max-w-sm mx-auto mb-2">
            <input type="text" id="pallet-input" placeholder="Nhập / Quét QR"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-orange-600 outline-none">
            <button type="button" onclick="openQRScannerModal('pallet-input', 'Mã Pallet')" class="absolute right-2 top-1/2 -translate-y-1/2 text-orange-600 hover:text-orange-800">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>
        <button onclick="checkPallet()" class="w-full max-w-sm bg-orange-600 text-white py-3 rounded-lg font-bold hover:bg-orange-700 transition">Xác nhận Pallet</button>
        <p id="pallet-error" class="mt-2 text-red-600 hidden text-sm"></p>
    </div>

    <!-- Step 2: Input Items -->
    <div id="step-2" class="bg-white p-3 sm:p-4 rounded-lg shadow-md hidden">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base sm:text-lg font-bold text-gray-800">Pallet ID: <span id="display-pallet" class="text-orange-600"></span></h3>
            <button onclick="resetImport()" class="text-gray-500 text-xs sm:text-sm underline">Đóng</button>
        </div>

        <div class="mb-2 inline-flex rounded-lg border border-gray-300 overflow-hidden self-start">
                <button type="button" id="scan-mode-interrupt" onclick="setImportScanMode(false)" class="px-1 py-1 text-sm font-semibold bg-orange-600 text-white">Gián đoạn</button>
                <button type="button" id="scan-mode-continuous" onclick="setImportScanMode(true)" class="px-1 py-1 text-sm font-semibold bg-white text-gray-700 hover:bg-gray-100">Liên tục</button>
        </div>

        <div class="grid grid-cols-12 gap-2 mb-2">
            <div class="relative col-span-8">
                <input type="text" id="product_id" name="product_id" placeholder="QR thùng hàng" class="w-full px-3 py-2 pr-10 border rounded uppercase font-mono text-sm">
                <button type="button" onclick="openQRScannerModal('product_id', 'QR thùng hàng')" class="absolute right-2 top-1/2 -translate-y-1/2 text-orange-600 hover:text-orange-800">
                    <i class="fas fa-qrcode"></i>
                </button>
                <p id="product-error" class="absolute -bottom-4 left-0 text-[10px] text-red-600 hidden"></p>
            </div>
            <input type="number" id="qty-input" placeholder="SL" class="col-span-4 px-3 py-2 border rounded text-sm text-center font-semibold" min="1">
        </div>

        <div id="import-live-summary" class="mb-2 grid grid-cols-2 gap-2 text-xs sm:text-sm">
            <div class="rounded border border-blue-200 bg-blue-50 px-2 py-1 font-semibold text-blue-700">Số thùng: <span id="scan-count">0</span></div>
            <div class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">Tổng SL: <span id="total-qty">0</span></div>
        </div>

        <button id="btn-add-item" onclick="addItem()" class="w-full mb-3 bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700">+ Thêm (Enter)</button>

        <div class="pda-compact-scroll mb-3 border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="sticky top-0 bg-gray-100">
                <tr class="bg-gray-100">
                    <th class="p-2 text-left text-xs sm:text-sm">QR thùng hàng</th>
                    <th class="p-2 text-right text-xs sm:text-sm">SL</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody id="item-list">
                <!-- Danh sách hàng sẽ hiện ở đây -->
            </tbody>
        </table>
        </div>

        <button id="btn-submit-import" onclick="submitImport()" class="w-full bg-orange-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-orange-700 shadow-lg">✓ Hoàn tất Pallet</button>

        <!-- Danh sách hàng hiện có trên Pallet này (Giống inbound) -->
        <div id="current-pallet-content" class="mt-8 p-4 bg-orange-50 rounded-lg hidden border-t-2 border-orange-200">
            <h4 class="text-sm font-bold text-orange-800 mb-2 uppercase">Hàng đã có trên Pallet này:</h4>
            <div id="pallet-items-list" class="text-xs space-y-1">
                <!-- Danh sách hàng trong import_temp -->
            </div>
        </div>
    </div>
</div>

<script>
let importItems = [];
let qrScanTimer = null;
let lastHandledQRRaw = '';
let isContinuousImportScan = false;
let isSubmitting = false;

function setImportScanMode(isContinuous) {
    isContinuousImportScan = !!isContinuous;
    updateImportScanModeUI();
    $('#product_id').focus();
}

function updateImportScanModeUI() {
    const interruptBtn = $('#scan-mode-interrupt');
    const continuousBtn = $('#scan-mode-continuous');

    if (isContinuousImportScan) {
        interruptBtn.removeClass('bg-orange-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        continuousBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-orange-600 text-white');
        $('#scan-mode-label').text('Liên tục: tự thêm ngay khi QR hợp lệ');
    } else {
        continuousBtn.removeClass('bg-orange-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        interruptBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-orange-600 text-white');
        $('#scan-mode-label').text('Gián đoạn: dừng lại để xác nhận số lượng');
    }
}

function normalizeImportQRRaw(rawValue) {
    return (rawValue || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
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

function showErrorModal(message) {
    showModal(message, 'error');
}

function closeErrorModal() {
    const modalContent = $('#error-modal-content');
    const isSuccess = modalContent.hasClass('success');

    $('#error-modal').css('display', 'none');

    if (isSuccess) {
        resetImport();
    } else {
        $('#product_id').val('').focus();
        clearProductError();
    }
}

function clearProductError() {
    $('#product-error').addClass('hidden');
    $('#product_id').removeClass('border-red-500');
}

function updateImportCounters() {
    const pendingProduct = $('#product_id').val().trim();
    const pendingQty = parseInt($('#qty-input').val(), 10);
    const pendingCount = pendingProduct && !isNaN(pendingQty) && pendingQty > 0 ? 1 : 0;

    const scanCount = importItems.length + pendingCount;
    const totalQtySaved = importItems.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
    const totalQty = totalQtySaved + (pendingCount ? pendingQty : 0);

    $('#scan-count').text(scanCount);
    $('#total-qty').text(totalQty);
}

function parseImportQRPayload(rawValue) {
    const normalizedValue = normalizeImportQRRaw(rawValue);
    if (!normalizedValue || normalizedValue.indexOf('$') === -1) return null;

    const parts = normalizedValue.split('$').map(part => part.trim());
    if (parts.length < 3) return null;

    const productId = (parts[1] || '').toUpperCase();

    // Tim quantity theo token toan so gan cuoi chuoi de tranh lech cot khi QR co them field.
    let qtyToken = '';
    for (let i = parts.length - 1; i >= 2; i--) {
        if (/^\d+$/.test(parts[i])) {
            qtyToken = parts[i];
            break;
        }
    }

    const quantity = parseInt(qtyToken, 10);

    if (!productId || isNaN(quantity) || quantity <= 0) return null;
    return { productId, quantity };
}

function validateProduct(productId, onSuccess, onFail) {
    $.getJSON('api.php?action=check_product', { product_id: productId }, function(res) {
        if (res.success) {
            clearProductError();
            $('#product_id').addClass('border-green-500');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            showErrorModal('Mã sản phẩm không tồn tại!\n\nVui lòng kiểm tra lại QR mã thùng hàng.');
            if (typeof onFail === 'function') onFail();
        }
    });
}

function handleQRProductPayload(rawValue) {
    const normalizedRaw = normalizeImportQRRaw(rawValue);
    if (!normalizedRaw || normalizedRaw === lastHandledQRRaw) return false;

    const parsed = parseImportQRPayload(rawValue);
    if (!parsed) return false;

    lastHandledQRRaw = normalizedRaw;

    validateProduct(parsed.productId, function() {
        $('#product_id').val(parsed.productId);
        $('#qty-input').val(parsed.quantity);

        if (isContinuousImportScan) {
            addItem();
            // Cho phep quet lai cung 1 ma o lan tiep theo.
            lastHandledQRRaw = '';
        } else {
            $('#qty-input').focus().select();
        }
    }, function() {
        $('#product_id').val(parsed.productId).select();
        $('#qty-input').val('');
    });

    return true;
}

function checkPallet() {
    const palletId = $('#pallet-input').val().trim().toUpperCase();
    if (!palletId) return;

    // Pallet đã được nhận tại kho tổng (RECEIVED) hoặc đã lên kệ (IMPORTED) thì không cho dùng lại ở bước Import.
    $.getJSON('api.php?action=check_pallet_import_status', { pallet_id: palletId }, function(res) {
        if (!res.success) {
            showErrorModal(res.message || 'Pallet này không thể sử dụng để nhận hàng.');
            return;
        }

        $('#display-pallet').text(palletId);
        $('#step-1').addClass('hidden');
        $('#step-2').removeClass('hidden');
        $('#product_id').focus();
        $('#pallet-error').addClass('hidden');

        loadCurrentPalletItems(palletId);
    }).fail(function() {
        showErrorModal('Lỗi kết nối máy chủ, vui lòng thử lại.');
    });
}

function loadCurrentPalletItems(palletId) {
    $.getJSON('api.php?action=get_import_temp_by_pallet', { pallet_id: palletId }, function(items) {
        const stockDiv = $('#current-pallet-content');
        const list = $('#pallet-items-list');
        list.empty();
        if (items && items.length > 0) {
            items.forEach(item => {
                list.append(`<div class="flex justify-between border-b border-orange-200 pb-1">
                    <span>${item.product_id}</span>
                    <span class="font-bold">SL: ${item.quantity}</span>
                </div>`);
            });
            stockDiv.removeClass('hidden');
        } else {
            stockDiv.addClass('hidden');
        }
    });
}

function resetImport() {
    if (importItems.length > 0 && !confirm('Danh sách hàng đang nhập sẽ bị xóa, bạn chắc chắn muốn đổi pallet?')) return;
    $('#step-2').addClass('hidden');
    $('#step-1').removeClass('hidden');
    $('#pallet-input').val('').focus();
    importItems = [];
    lastHandledQRRaw = '';
    setImportScanMode(false);
    renderItemList();
    updateImportCounters();
}

$('#product_id').on('input', function() {
    updateImportCounters();
    const rawValue = normalizeImportQRRaw($(this).val());
    if (!rawValue || rawValue.indexOf('$') === -1) return;

    // Cho scanner nhap xong toan bo chuoi roi moi parse de tranh ky tu duoi QR chay vao o so luong.
    clearTimeout(qrScanTimer);
    qrScanTimer = setTimeout(function() {
        const finalRaw = normalizeImportQRRaw($('#product_id').val());
        const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 7;
        if (isLikelyComplete) handleQRProductPayload(finalRaw);
    }, 120);
});

// Kiểm tra mã hàng khi rời khỏi ô nhập
$('#product_id').on('change', function() {
    updateImportCounters();
    const rawValue = normalizeImportQRRaw($(this).val());
    if (!rawValue) return;

    lastHandledQRRaw = '';
    if (handleQRProductPayload(rawValue)) return;

    const pid = rawValue.toUpperCase();
    validateProduct(pid, function() {
        $('#product_id').val(pid);
        $('#qty-input').focus();
    }, function() {
        $('#product_id').val(pid).select();
    });
});

$('#qty-input').on('keypress', function(e) { 
    if (e.which == 13) addItem();
});

$('#qty-input').on('input', function() {
    updateImportCounters();
});

function addItem() {
    const productId = $('#product_id').val().trim().toUpperCase();
    const qty = parseInt($('#qty-input').val());

    if (!productId || isNaN(qty) || qty <= 0) return;

    // Thêm vào danh sách tạm trên giao diện
    importItems.push({ product_id: productId, quantity: qty });

    renderItemList();
    $('#product_id').val('').removeClass('border-green-500 border-red-500').focus();
    $('#qty-input').val('');
    $('#product-error').addClass('hidden');
    updateImportCounters();
}

function renderItemList() {
    const list = $('#item-list');
    list.empty();
    importItems.forEach((item, index) => {
        list.append(`
            <tr class="border-b">
                <td class="p-2 text-left font-mono">${item.product_id}</td>
                <td class="p-2 text-right">${item.quantity}</td>
                <td class="p-2 text-center">
                    <button onclick="removeItem(${index})" class="text-red-500 hover:text-red-700">✕</button>
                </td>
            </tr>
        `);
    });
    updateImportCounters();
}

function removeItem(index) {
    importItems.splice(index, 1);
    renderItemList();
}

async function submitImport() {
    if (isSubmitting) {
        showModal('Đang xử lý, vui lòng chờ...', 'warning');
        return;
    }

    if (importItems.length === 0) return showModal('Danh sách hàng trống!', 'warning');

    const palletId = $('#display-pallet').text();
    const submitBtn = $('#btn-submit-import');

    isSubmitting = true;
    submitBtn.prop('disabled', true).css('opacity', '0.5');
    const originalText = submitBtn.html();
    submitBtn.html('⏳ Đang xử lý...');

    try {
        for (const item of importItems) {
            const res = await $.post('api.php?action=import_temp_submit', {
                pallet_id: palletId,
                product_id: item.product_id,
                quantity: item.quantity
            });
            if (!res.success) throw new Error(res.message);
        }
        showModal('Đã lưu dữ liệu pallet thành công!', 'success');
        importItems = [];
        checkPallet(); // Tải lại danh sách hiện có bên dưới
    } catch (e) {
        showModal(e.message, 'error');
    } finally {
        isSubmitting = false;
        submitBtn.prop('disabled', false).css('opacity', '1').html(originalText);
    }
}

$(document).ready(function() {
    attachScanOnlyGuard('#pallet-input');
    updateImportScanModeUI();
    updateImportCounters();

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