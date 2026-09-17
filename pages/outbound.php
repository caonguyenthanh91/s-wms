<div id="outbound-container" class="max-w-4xl mx-auto">
    <style>
        @media (max-width: 900px) {
            #outbound-container .pda-compact-scroll {
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

    <!-- Bước 1: Quét Mã Kệ -->
    <div id="step-1" class="bg-white p-2 rounded-lg shadow-md text-center">
        <h3 class="text-lg font-bold mb-2 text-gray-800">Nhập mã Kệ</h3>
        <div class="relative w-full max-w-sm mx-auto mb-2">
            <input type="text" id="shelf-input" placeholder="Nhập / Quét mã kệ"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-red-600 outline-none">
            <button type="button" onclick="openQRScannerModal('shelf-input', 'Mã Kệ')" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>
        <button onclick="checkShelfOutbound()" class="w-full max-w-sm bg-red-600 text-white py-3 rounded-lg font-bold hover:bg-red-700 transition">Xác nhận Kệ</button>
        <p id="shelf-error" class="mt-2 text-red-600 hidden text-sm"></p>
    </div>

    <!-- Bước 2: Nhập Hàng Xuất -->
    <div id="step-2" class="bg-white p-3 sm:p-4 rounded-lg shadow-md hidden">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base sm:text-lg font-bold text-gray-800">Kệ: <span id="display-shelf" class="text-red-600"></span></h3>
            <button onclick="resetOutbound()" class="text-gray-500 text-xs sm:text-sm underline">Đổi</button>
        </div>

        <div class="mb-2 inline-flex rounded-lg border border-gray-300 overflow-hidden self-start">
            <button type="button" id="scan-mode-interrupt" onclick="setOutboundScanMode(false)" class="px-1 py-1 text-sm font-semibold bg-red-600 text-white">Gián đoạn</button>
            <button type="button" id="scan-mode-continuous" onclick="setOutboundScanMode(true)" class="px-1 py-1 text-sm font-semibold bg-white text-gray-700 hover:bg-gray-100">Liên tục</button>
        </div>

        <div class="grid grid-cols-12 gap-2 mb-2">
            <div class="relative col-span-8">
                <input type="text" id="product_id" name="product_id" placeholder="Product ID"
                       class="w-full px-3 py-2 pr-10 border rounded uppercase font-mono text-sm">
                <button type="button" onclick="openQRScannerModal('product_id', 'Mã Sản Phẩm')" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800">
                    <i class="fas fa-qrcode"></i>
                </button>
                <p id="product-error" class="absolute -bottom-4 left-0 text-[10px] text-red-600 hidden"></p>
            </div>
            <input type="number" id="qty-input" placeholder="Qty" class="col-span-4 px-3 py-2 border rounded text-sm text-center font-semibold" min="1">
        </div>

        <div id="outbound-live-summary" class="mb-2 grid grid-cols-2 gap-2 text-xs sm:text-sm">
            <div class="rounded border border-red-200 bg-red-50 px-2 py-1 font-semibold text-red-700">Lượt xuất: <span id="scan-count">0</span></div>
            <div class="rounded border border-amber-200 bg-amber-50 px-2 py-1 font-semibold text-amber-700">Tổng SL: <span id="total-qty">0</span></div>
        </div>

        <button id="btn-add-item" onclick="addItemOutbound()" class="w-full mb-3 bg-red-600 text-white py-2 rounded font-bold hover:bg-red-700">+ Thêm (Enter)</button>

        <div class="pda-compact-scroll mb-3 border border-gray-200 rounded-lg">
        <table class="w-full">
            <thead class="sticky top-0 bg-gray-100">
                <tr class="bg-gray-100">
                    <th class="p-2 text-left text-xs sm:text-sm">Product</th>
                    <th class="p-2 text-right text-xs sm:text-sm">SL xuất</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody id="item-list">
                <!-- Danh sách hàng chờ xuất -->
            </tbody>
        </table>
        </div>

        <button id="btn-submit-outbound" onclick="submitOutbound()" class="w-full bg-red-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-red-700 shadow-lg hidden">✓ Xác Nhận Xuất Kho</button>

        <!-- Tồn kho thực tế trên kệ -->
        <div id="current-stock-info" class="mt-4 p-3 bg-gray-50 rounded-lg border-t-2 border-red-200">
            <h4 class="text-xs font-bold text-gray-700 mb-2 uppercase">Danh sách hàng đang có trên kệ:</h4>
            <div id="shelf-stock-list" class="text-xs space-y-1">
                <!-- Danh sách hàng hiện có -->
            </div>
        </div>
    </div>
</div>

<script>
let outboundItems = [];
let shelfInventory = {}; // Tồn kho thực tế của kệ theo mã hàng (gộp cả box) - dùng hiển thị
let shelfNullInventory = {}; // Tồn kho luồng cũ: chỉ các dòng box_id = NULL, key = product_id
let shelfBoxInventory = {}; // Tồn kho luồng mới theo thùng: key = box_id -> { productId, quantity }
let outboundCurrentBoxId = null; // box_id vừa quét được từ QR tem thùng (null = luồng cũ)
let outboundQrScanTimer = null;
let outboundLastHandledQRRaw = '';
let isContinuousOutboundScan = false;

function isInventorySourceItem(item) {
    const source = (item && item.source ? String(item.source) : 'INVENTORY').toUpperCase();
    return source === 'INVENTORY';
}

function setOutboundScanMode(isContinuous) {
    isContinuousOutboundScan = !!isContinuous;
    updateOutboundScanModeUI();
    $('#product_id').focus();
}

function updateOutboundScanModeUI() {
    const interruptBtn = $('#scan-mode-interrupt');
    const continuousBtn = $('#scan-mode-continuous');

    if (isContinuousOutboundScan) {
        interruptBtn.removeClass('bg-red-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        continuousBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-red-600 text-white');
    } else {
        continuousBtn.removeClass('bg-red-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        interruptBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-red-600 text-white');
    }
}

function normalizeOutboundQRRaw(rawValue) {
    return (rawValue || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
}

// box_id dạng [TEXT]-[yymmdd]-[num], TEXT có thể chứa . _ - (vd LOT-260828-1, AL.EXT-211228-001)
const BOX_ID_PATTERN = /^[A-Za-z0-9._-]+-\d{6}-\d+$/;

function extractBoxIdFromParts(parts) {
    // Chuẩn hoá hoa (khớp với picking.php - normalizeQrText đã uppercase mọi nội dung QR).
    // box_no trong QR tem thùng có thể ở index 7 hoặc 8 tùy số ký tự '$' ngăn cách -> quét toàn bộ.
    for (let i = 0; i < parts.length; i++) {
        const token = (parts[i] || '').trim().toUpperCase();
        if (BOX_ID_PATTERN.test(token)) return token;
    }
    // Fallback theo vị trí cố định (chỉ nhận nếu trông giống mã định danh: có gạch nối + chữ số).
    for (const idx of [7, 8]) {
        const fixed = (parts[idx] || '').trim().toUpperCase();
        if (fixed && /-/.test(fixed) && /\d/.test(fixed)) return fixed;
    }
    return null;
}

function parseOutboundQRPayload(rawValue) {
    const normalizedValue = normalizeOutboundQRRaw(rawValue);
    if (!normalizedValue || normalizedValue.indexOf('$') === -1) return null;

    const parts = normalizedValue.split('$').map(part => part.trim());
    if (parts.length < 3) return null;

    // Vị trí cố định: mã hàng = index 1, số lượng = index 3.
    // Không quét số từ cuối chuỗi (QR tem thùng có field số phía sau: ...$-$1$0$<ngày>$<mã>$0).
    const productId = (parts[1] || '').toUpperCase();
    let quantity = NaN;
    if (/^\d+$/.test(parts[3] || '')) {
        quantity = parseInt(parts[3], 10);
    } else {
        for (let i = 2; i < parts.length; i++) {
            if (/^\d+$/.test(parts[i]) && parseInt(parts[i], 10) > 0) {
                quantity = parseInt(parts[i], 10);
                break;
            }
        }
    }

    if (!productId || isNaN(quantity) || quantity <= 0) return null;
    return { productId, quantity, boxId: extractBoxIdFromParts(parts) };
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
        resetOutbound();
    } else {
        // Nếu ở step 1, focus shelf-input; nếu ở step 2, focus product_id
        if ($('#step-1').hasClass('hidden')) {
            // Step 2 is visible
            $('#product_id').val('').focus();
            clearOutboundProductError();
        } else {
            // Step 1 is visible
            $('#shelf-input').val('').focus();
        }
    }
}

function showOutboundProductError(message) {
    showErrorModal(message);
}

function clearOutboundProductError() {
    $('#product-error').addClass('hidden');
    $('#product_id').removeClass('border-red-500');
}

function validateOutboundProductInShelf(productId, onSuccess, onFail) {
    const normalizedProductId = (productId || '').trim().toUpperCase();
    if (!normalizedProductId) {
        if (typeof onFail === 'function') onFail('Vui lòng nhập mã sản phẩm.');
        return;
    }

    if (!Object.prototype.hasOwnProperty.call(shelfInventory, normalizedProductId)) {
        const message = '❌ Mã sản phẩm không thuộc kệ đang chọn!';
        showOutboundProductError(message);
        if (typeof onFail === 'function') onFail(message);
        return;
    }

    clearOutboundProductError();
    $('#product_id').addClass('border-green-500');
    if (typeof onSuccess === 'function') onSuccess();
}

function handleOutboundQRProductPayload(rawValue) {
    const normalizedRaw = normalizeOutboundQRRaw(rawValue);
    if (!normalizedRaw || normalizedRaw === outboundLastHandledQRRaw) return false;

    const parsed = parseOutboundQRPayload(rawValue);
    if (!parsed) return false;

    outboundLastHandledQRRaw = normalizedRaw;
    outboundCurrentBoxId = parsed.boxId || null;

    // Luồng mới: QR tem thùng có box_no -> kiểm tra thùng thuộc kệ và đúng mã hàng.
    if (outboundCurrentBoxId) {
        const boxInfo = shelfBoxInventory[outboundCurrentBoxId];
        if (!boxInfo) {
            showOutboundProductError(`❌ Thùng ${outboundCurrentBoxId} không có trên kệ đang chọn!`);
            $('#product_id').val(parsed.productId).select();
            $('#qty-input').val('');
            updateOutboundCounters();
            return true;
        }
        if (boxInfo.productId !== parsed.productId.toUpperCase()) {
            showOutboundProductError(`❌ Thùng ${outboundCurrentBoxId} chứa mã ${boxInfo.productId}, không phải ${parsed.productId}!`);
            $('#product_id').val(parsed.productId).select();
            $('#qty-input').val('');
            updateOutboundCounters();
            return true;
        }
    }

    validateOutboundProductInShelf(parsed.productId, function() {
        $('#product_id').val(parsed.productId);
        $('#qty-input').val(parsed.quantity);

        if (isContinuousOutboundScan) {
            addItemOutbound();
            // Cho phep quet lai cung 1 ma o lan tiep theo.
            outboundLastHandledQRRaw = '';
        } else {
            updateOutboundCounters();
            $('#qty-input').focus().select();
        }
    }, function() {
        $('#product_id').val(parsed.productId).select();
        $('#qty-input').val('');
        updateOutboundCounters();
    });

    return true;
}

function updateOutboundSubmitButton() {
    const totalQtySaved = outboundItems.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
    const shouldShow = outboundItems.length > 0 && totalQtySaved > 0;
    $('#btn-submit-outbound').toggleClass('hidden', !shouldShow);
}

function updateOutboundCounters() {
    const pendingProduct = ($('#product_id').val() || '').trim();
    const pendingQty = parseInt($('#qty-input').val(), 10);
    const pendingCount = pendingProduct && !isNaN(pendingQty) && pendingQty > 0 ? 1 : 0;

    const scanCount = outboundItems.length + pendingCount;
    const totalQtySaved = outboundItems.reduce((sum, item) => sum + (parseInt(item.quantity, 10) || 0), 0);
    const totalQty = totalQtySaved + (pendingCount ? pendingQty : 0);

    $('#scan-count').text(scanCount);
    $('#total-qty').text(totalQty);
    updateOutboundSubmitButton();
}

// Tổng SL đã đưa vào danh sách cho 1 mã hàng.
// boxId === undefined: tính tất cả. boxId === null/'' : chỉ item không thùng. boxId có giá trị: chỉ đúng thùng đó.
function getOutboundAllocatedQty(productId, boxId) {
    const normalizedProductId = (productId || '').trim().toUpperCase();
    if (!normalizedProductId) return 0;
    const filterByBox = arguments.length > 1;
    const normalizedBoxId = (boxId || '') === '' ? '' : String(boxId).trim();

    return outboundItems.reduce((sum, item) => {
        if ((item.product_id || '').toUpperCase() !== normalizedProductId) return sum;
        if (filterByBox && (item.box_id || '') !== normalizedBoxId) return sum;
        return sum + (parseInt(item.quantity, 10) || 0);
    }, 0);
}

function buildOutboundTotalsByProduct(items) {
    const totals = {};
    (items || []).forEach(item => {
        const productId = ((item && item.product_id) || '').toUpperCase();
        const qty = parseInt(item && item.quantity, 10) || 0;
        if (!productId || qty <= 0) return;
        totals[productId] = (totals[productId] || 0) + qty;
    });
    return totals;
}

function parseRealtimeInventoryRows(items) {
    const realtime = {};
    (items || []).forEach(item => {
        if (!isInventorySourceItem(item)) return;
        const productId = ((item && item.product_id) || '').toUpperCase();
        const qty = parseInt(item && item.quantity, 10) || 0;
        if (!productId || qty <= 0) return;
        realtime[productId] = (realtime[productId] || 0) + qty;
    });
    return realtime;
}

function updateShelfInventoryMap(items) {
    shelfInventory = parseRealtimeInventoryRows(items);
}

function fetchBoxInventoryByShelf(shelfId) {
    return $.getJSON('api.php?action=get_inventory_boxes_by_shelf', { shelf_id: shelfId });
}

// Dựng 2 map từ get_inventory_boxes_by_shelf:
//  - shelfNullInventory[product_id] = tồn các dòng box_id rỗng (luồng cũ)
//  - shelfBoxInventory[box_id] = { productId, quantity } (luồng mới)
function updateBoxInventoryMaps(rows) {
    shelfNullInventory = {};
    shelfBoxInventory = {};
    (rows || []).forEach(row => {
        const productId = ((row && row.product_id) || '').toUpperCase();
        const qty = parseInt(row && row.quantity, 10) || 0;
        if (!productId || qty <= 0) return;
        const boxId = (row && row.box_id ? String(row.box_id) : '').trim();
        if (boxId === '') {
            shelfNullInventory[productId] = (shelfNullInventory[productId] || 0) + qty;
        } else {
            shelfBoxInventory[boxId] = { productId, quantity: qty };
        }
    });
}

function buildOutboundSubmitErrorMessage(response) {
    const fallbackMessage = 'Không thể xuất kho. Vui lòng kiểm tra lại.';
    const baseMessage = (response && response.message) ? response.message : fallbackMessage;

    if (!response || !response.debug) {
        return baseMessage;
    }

    return `${baseMessage}\n\nDEBUG CONTEXT:\n${JSON.stringify(response.debug, null, 2)}`;
}

function fetchCurrentInventoryByShelf(shelfId) {
    return $.getJSON('api.php?action=get_inventory_current_by_shelf', { shelf_id: shelfId });
}

function checkShelfOutbound() {
    const shelfId = $('#shelf-input').val().toUpperCase();
    $.post('api.php?action=check_shelf', { shelf_id: shelfId }, function(res) {
        if(res.success) {
            $('#display-shelf').text(res.data.shelf_id);
            $('#step-1').addClass('hidden');
            $('#step-2').removeClass('hidden');
            $('#shelf-error').addClass('hidden');
            updateOutboundCounters();

            // Tải tồn kho hiện tại của kệ
            $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: shelfId }, function(items) {
                const list = $('#shelf-stock-list');
                list.empty();
                clearOutboundProductError();
                $('#product_id').removeClass('border-green-500').val('');
                $('#qty-input').val('');
                outboundLastHandledQRRaw = '';

                if (items && items.length > 0) {
                    items.forEach(item => {
                        const normalizedProductId = (item.product_id || '').toUpperCase();
                        const sourceLabel = isInventorySourceItem(item) ? 'INVENTORY' : String(item.source || 'OTHER');
                        const sourceBadgeClass = isInventorySourceItem(item)
                            ? 'text-emerald-700 bg-emerald-100'
                            : 'text-amber-700 bg-amber-100';

                        list.append(`<div class="flex justify-between border-b pb-1">
                            <span>${item.product_id} - ${item.product_name} <span class="ml-1 px-1 py-0.5 rounded text-[10px] font-semibold ${sourceBadgeClass}">${sourceLabel}</span></span>
                            <span class="font-bold">Tồn: ${item.quantity}</span>
                        </div>`);
                    });
                } else {
                    list.append('<p class="text-red-500 italic">Kệ này hiện không có hàng hóa.</p>');
                }

                $.when(
                    fetchCurrentInventoryByShelf(shelfId),
                    fetchBoxInventoryByShelf(shelfId)
                ).done(function(currentRes, boxRes) {
                    // $.when với 2 deferred -> mỗi tham số là [data, statusText, jqXHR].
                    const currentItems = (currentRes && currentRes[0]) || [];
                    const boxRows = (boxRes && boxRes[0]) || [];
                    updateShelfInventoryMap(currentItems);
                    updateBoxInventoryMaps(boxRows);
                    renderShelfBoxBreakdown();
                    updateOutboundCounters();
                    $('#product_id').focus();
                }).fail(function() {
                    shelfInventory = {};
                    shelfNullInventory = {};
                    shelfBoxInventory = {};
                    updateOutboundCounters();
                    showModal('Không thể tải tồn kho thực tế (inventory). Vui lòng thử lại.', 'error');
                });
            });
        } else {
            showErrorModal('Mã kệ không tồn tại!\n\nVui lòng kiểm tra lại mã kệ.');
        }
    }, 'json');
}

function resetOutbound() {
    $('#step-2').addClass('hidden');
    $('#step-1').removeClass('hidden');
    $('#shelf-input').val('').focus();
    outboundItems = [];
    shelfInventory = {};
    shelfNullInventory = {};
    shelfBoxInventory = {};
    outboundCurrentBoxId = null;
    outboundLastHandledQRRaw = '';
    $('#shelf-box-breakdown').remove();
    $('#item-list').empty();
    $('#qty-input').val('');
    $('#product_id').val('').removeClass('border-green-500 border-red-500');
    clearOutboundProductError();
    updateOutboundCounters();
}

function addItemOutbound() {
    const sku = ($('#product_id').val() || '').trim().toUpperCase();
    const qty = parseInt($('#qty-input').val());
    const boxId = outboundCurrentBoxId || null;

    if (!sku || isNaN(qty) || qty <= 0) {
        showModal('Vui lòng quét/nhập mã sản phẩm và nhập số lượng.', 'warning');
        return;
    }

    let maxQty;
    if (boxId) {
        // Luồng mới: trừ theo đúng thùng.
        const boxInfo = shelfBoxInventory[boxId];
        if (!boxInfo) {
            showErrorModal(`Thùng ${boxId} không có trên kệ đang chọn!\n\nVui lòng kiểm tra lại.`);
            $('#product_id').focus().select();
            return;
        }
        if (boxInfo.productId !== sku) {
            showErrorModal(`Thùng ${boxId} chứa mã ${boxInfo.productId}, không phải ${sku}!`);
            $('#product_id').focus().select();
            return;
        }
        maxQty = parseInt(boxInfo.quantity, 10) || 0;
    } else {
        // Luồng cũ: trừ theo cặp (mã hàng + mã vị trí), chỉ tính tồn box_id = NULL.
        if (!Object.prototype.hasOwnProperty.call(shelfNullInventory, sku)) {
            if (Object.prototype.hasOwnProperty.call(shelfInventory, sku)) {
                showErrorModal(`Mã ${sku} trên kệ này đang quản lý theo thùng.\n\nVui lòng quét QR trên tem thùng (có box_id).`);
            } else {
                showErrorModal('Mã sản phẩm không thuộc kệ đang chọn!\n\nVui lòng kiểm tra lại.');
            }
            $('#product_id').focus().select();
            return;
        }
        maxQty = parseInt(shelfNullInventory[sku], 10) || 0;
    }

    const allocatedQty = getOutboundAllocatedQty(sku, boxId || '');
    const remainingQty = Math.max(0, maxQty - allocatedQty);

    if (qty > remainingQty) {
        const scope = boxId ? `thùng ${boxId}` : `mã ${sku}`;
        showModal(`Không đủ tồn kho! Còn lại tối đa có thể xuất cho ${scope} là ${remainingQty}`, 'error');
        return;
    }

    outboundItems.push({ product_id: sku, quantity: qty, box_id: boxId });
    renderOutboundList();
    $('#product_id').val('').removeClass('border-green-500 border-red-500').focus();
    $('#qty-input').val('');
    clearOutboundProductError();
    outboundLastHandledQRRaw = '';
    outboundCurrentBoxId = null;
    updateOutboundCounters();
}

function renderOutboundList() {
    const list = $('#item-list');
    list.empty();
    outboundItems.forEach((item, index) => {
        const boxLabel = item.box_id
            ? `<span class="block text-[10px] text-sky-700 font-mono">📦 ${item.box_id}</span>`
            : '<span class="block text-[10px] text-gray-400">— không thùng —</span>';
        list.append(`<tr class="border-b">
            <td class="p-2 font-mono">${item.product_id}${boxLabel}</td>
            <td class="p-2 text-right align-top">${item.quantity}</td>
            <td class="p-2 text-center align-top">
                <button onclick="outboundItems.splice(${index}, 1); renderOutboundList();" class="text-red-500">✕</button>
            </td>
        </tr>`);
    });
    updateOutboundCounters();
}

// Hiển thị các thùng (box_id) đang có trên kệ, dưới danh sách hàng hiện có.
function renderShelfBoxBreakdown() {
    const list = $('#shelf-stock-list');
    if (!list.length) return;
    $('#shelf-box-breakdown').remove();

    const boxIds = Object.keys(shelfBoxInventory);
    if (boxIds.length === 0) return;

    const rows = boxIds
        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
        .map(boxId => {
            const info = shelfBoxInventory[boxId];
            return `<div class="flex justify-between">
                <span class="font-mono text-sky-700">📦 ${boxId} <span class="text-gray-500">(${info.productId})</span></span>
                <span class="font-bold">Tồn: ${info.quantity}</span>
            </div>`;
        }).join('');

    list.append(`<div id="shelf-box-breakdown" class="mt-2 pt-2 border-t border-dashed border-gray-300 space-y-1">
        <div class="text-[10px] font-bold text-gray-500 uppercase">Tồn theo thùng (box_id):</div>
        ${rows}
    </div>`);
}

async function submitOutbound() {
    if (outboundItems.length === 0) return showModal('Danh sách xuất trống!', 'warning');
    const shelfId = $('#display-shelf').text();

    // Lấy tồn realtime: tổng theo mã hàng (luồng cũ) + tách theo thùng (luồng mới).
    let latestStockRows = [];
    let latestBoxRows = [];
    try {
        [latestStockRows, latestBoxRows] = await Promise.all([
            fetchCurrentInventoryByShelf(shelfId),
            fetchBoxInventoryByShelf(shelfId)
        ]);
    } catch (e) {
        showModal('Không thể kiểm tra tồn kho realtime. Vui lòng thử lại.', 'error');
        return;
    }

    updateShelfInventoryMap(latestStockRows);
    updateBoxInventoryMaps(latestBoxRows);
    renderShelfBoxBreakdown();

    // Gom nhóm theo (mã hàng, box_id). Key: `${product_id}|${box_id||''}`.
    const groups = {};
    outboundItems.forEach(item => {
        const productId = (item.product_id || '').toUpperCase();
        const boxId = item.box_id || '';
        const qty = parseInt(item.quantity, 10) || 0;
        if (!productId || qty <= 0) return;
        const key = `${productId}|${boxId}`;
        if (!groups[key]) groups[key] = { product_id: productId, box_id: boxId, quantity: 0 };
        groups[key].quantity += qty;
    });

    const submitItems = Object.values(groups);

    // Kiểm tra đủ tồn tại thời điểm xác nhận.
    const insufficient = [];
    submitItems.forEach(item => {
        const available = item.box_id
            ? ((shelfBoxInventory[item.box_id] && shelfBoxInventory[item.box_id].quantity) || 0)
            : (shelfNullInventory[item.product_id] || 0);
        if (item.quantity > available) {
            insufficient.push({
                label: item.box_id ? `${item.product_id} / thùng ${item.box_id}` : item.product_id,
                requestedQty: item.quantity,
                availableQty: available
            });
        }
    });

    if (insufficient.length > 0) {
        const details = insufficient
            .map(item => `${item.label}: cần ${item.requestedQty}, còn ${item.availableQty}`)
            .join('\n');
        showModal(`Không đủ số lượng xuất tại thời điểm xác nhận:\n\n${details}\n\nVui lòng kiểm tra lại danh sách.`, 'error');
        return;
    }

    for (const item of submitItems) {
        const res = await $.post('api.php?action=outbound_basic_submit', {
            shelf_id: shelfId,
            product_id: item.product_id,
            quantity: item.quantity,
            box_id: item.box_id || ''
        });
        if (!res.success) {
            showModal(buildOutboundSubmitErrorMessage(res), 'error');
            return;
        }
    }
    showModal('Xuất kho thành công!', 'success');
}

$(document).ready(function() {
    attachScanOnlyGuard('#shelf-input');
    setOutboundScanMode(false);
    updateOutboundCounters();

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
        if (e.key === 'Enter' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
    });

    $('#product_id').on('input', function() {
        updateOutboundCounters();
        const rawValue = normalizeOutboundQRRaw($(this).val());
        // Gõ tay mã hàng (không phải payload QR) -> huỷ box_id đang giữ, coi như luồng cũ.
        if (!rawValue || rawValue.indexOf('$') === -1) {
            outboundCurrentBoxId = null;
            return;
        }

        clearTimeout(outboundQrScanTimer);
        outboundQrScanTimer = setTimeout(function() {
            const finalRaw = normalizeOutboundQRRaw($('#product_id').val());
            // Ngưỡng >=10 đảm bảo đã gõ QUA KHỎI field box_id (index 7/8) trước khi coi là "xong"
            // (giống fix đã áp dụng ở inbound.php) - tránh chốt sớm giữa chừng khi máy quét
            // (đặc biệt Bluetooth) có khoảng dừng ký tự ngay tại đó, gây mất box_id hoặc phát
            // sinh 1 dòng ảo (tem cũ, ~8 field, không box_id, không tự-chốt qua ngưỡng này -
            // vẫn xử lý được nhờ Enter/sự kiện 'change' khi rời ô nhập).
            const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 10;
            if (isLikelyComplete) handleOutboundQRProductPayload(finalRaw);
        }, 150);
    });

    $('#product_id').on('change', function() {
        updateOutboundCounters();
        const rawValue = normalizeOutboundQRRaw($(this).val());
        if (!rawValue) return;

        outboundLastHandledQRRaw = '';
        if (handleOutboundQRProductPayload(rawValue)) return;

        outboundCurrentBoxId = null;
        const pid = rawValue.toUpperCase();
        validateOutboundProductInShelf(pid, function() {
            $('#product_id').val(pid);
            $('#qty-input').focus();
        }, function() {
            $('#product_id').val(pid).select();
            $('#qty-input').val('');
        });
    });

    $('#qty-input').on('keypress', function(e) {
        if (e.which == 13) addItemOutbound();
    });

    $('#qty-input').on('input', function() {
        updateOutboundCounters();
    });

    const urlParams = new URLSearchParams(window.location.search);
    const shelfId = urlParams.get('shelf_id');
    if (shelfId) { $('#shelf-input').val(shelfId); checkShelfOutbound(); }
});
</script>