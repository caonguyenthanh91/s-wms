<div id="outbound-container" class="max-w-4xl mx-auto">
    <style>
        @media (max-width: 900px) {
            #outbound-container .pda-compact-scroll {
                max-height: 35vh;
                overflow-y: auto;
            }
        }
    </style>
    <!-- Bước 1: Quét Mã Kệ -->
    <div id="step-1" class="bg-white p-2 rounded-lg shadow-md text-center">
        <h3 class="text-lg font-bold mb-2 text-gray-800">Nhập mã Kệ Xuất</h3>
        <div class="relative w-full max-w-sm mx-auto mb-2">
            <input type="text" id="shelf-input" placeholder="Nhập mã kệ (VD: A-01-01)"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-red-600 outline-none">
            <button type="button" onclick="openQRScannerModal('shelf-input', 'Mã Kệ Xuất')" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800">
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
let shelfInventory = {}; // Lưu trữ tồn kho thực tế của kệ để validate
let outboundQrScanTimer = null;
let outboundLastHandledQRRaw = '';

function normalizeOutboundQRRaw(rawValue) {
    return (rawValue || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
}

function parseOutboundQRPayload(rawValue) {
    const normalizedValue = normalizeOutboundQRRaw(rawValue);
    if (!normalizedValue || normalizedValue.indexOf('$') === -1) return null;

    const parts = normalizedValue.split('$').map(part => part.trim());
    if (parts.length < 3) return null;

    const productId = (parts[1] || '').toUpperCase();
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

function showOutboundProductError(message) {
    $('#product-error').text(message).removeClass('hidden');
    $('#product_id').addClass('border-red-500').removeClass('border-green-500');
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

    validateOutboundProductInShelf(parsed.productId, function() {
        $('#product_id').val(parsed.productId);
        $('#qty-input').val(parsed.quantity);
        updateOutboundCounters();
        $('#qty-input').focus().select();
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
                shelfInventory = {};
                clearOutboundProductError();
                $('#product_id').removeClass('border-green-500').val('');
                $('#qty-input').val('');
                outboundLastHandledQRRaw = '';

                if (items && items.length > 0) {
                    items.forEach(item => {
                        const normalizedProductId = (item.product_id || '').toUpperCase();
                        shelfInventory[normalizedProductId] = item.quantity;
                        list.append(`<div class="flex justify-between border-b pb-1">
                            <span>${item.product_id} - ${item.product_name}</span>
                            <span class="font-bold">Tồn: ${item.quantity}</span>
                        </div>`);
                    });
                } else {
                    list.append('<p class="text-red-500 italic">Kệ này hiện không có hàng hóa.</p>');
                }
                updateOutboundCounters();
                $('#product_id').focus();
            });
        } else {
            $('#shelf-error').text('Mã kệ không tồn tại!').removeClass('hidden');
        }
    }, 'json');
}

function resetOutbound() {
    $('#step-2').addClass('hidden');
    $('#step-1').removeClass('hidden');
    $('#shelf-input').val('').focus();
    outboundItems = [];
    shelfInventory = {};
    outboundLastHandledQRRaw = '';
    $('#item-list').empty();
    $('#qty-input').val('');
    $('#product_id').val('').removeClass('border-green-500 border-red-500');
    clearOutboundProductError();
    updateOutboundCounters();
}

function addItemOutbound() {
    const sku = ($('#product_id').val() || '').trim().toUpperCase();
    const qty = parseInt($('#qty-input').val());
    const maxQty = shelfInventory[sku] || 0;

    if (!sku || isNaN(qty) || qty <= 0) {
        alert('Vui lòng quét/nhập mã sản phẩm và nhập số lượng.');
        return;
    }

    if (!Object.prototype.hasOwnProperty.call(shelfInventory, sku)) {
        showOutboundProductError('❌ Mã sản phẩm không thuộc kệ đang chọn!');
        $('#product_id').focus().select();
        return;
    }

    if (qty > maxQty) {
        alert(`Không đủ tồn kho! Số lượng tối đa có thể xuất là ${maxQty}`);
        return;
    }

    outboundItems.push({ product_id: sku, quantity: qty });
    renderOutboundList();
    $('#product_id').val('').removeClass('border-green-500 border-red-500').focus();
    $('#qty-input').val('');
    clearOutboundProductError();
    outboundLastHandledQRRaw = '';
    updateOutboundCounters();
}

function renderOutboundList() {
    const list = $('#item-list');
    list.empty();
    outboundItems.forEach((item, index) => {
        list.append(`<tr class="border-b">
            <td class="p-2 font-mono">${item.product_id}</td>
            <td class="p-2 text-right">${item.quantity}</td>
            <td class="p-2 text-center">
                <button onclick="outboundItems.splice(${index}, 1); renderOutboundList();" class="text-red-500">✕</button>
            </td>
        </tr>`);
    });
    updateOutboundCounters();
}

async function submitOutbound() {
    if (outboundItems.length === 0) return alert('Danh sách xuất trống!');
    const shelfId = $('#display-shelf').text();

    for (const item of outboundItems) {
        const res = await $.post('api.php?action=outbound_submit', {
            shelf_id: shelfId,
            product_id: item.product_id,
            quantity: item.quantity
        });
        if (!res.success) {
            alert(`Lỗi: ${res.message}`);
            return;
        }
    }
    alert('Xuất kho thành công!');
    resetOutbound();
}

$(document).ready(function() {
    updateOutboundCounters();

    $('#product_id').on('input', function() {
        updateOutboundCounters();
        const rawValue = normalizeOutboundQRRaw($(this).val());
        if (!rawValue || rawValue.indexOf('$') === -1) return;

        clearTimeout(outboundQrScanTimer);
        outboundQrScanTimer = setTimeout(function() {
            const finalRaw = normalizeOutboundQRRaw($('#product_id').val());
            const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 7;
            if (isLikelyComplete) handleOutboundQRProductPayload(finalRaw);
        }, 120);
    });

    $('#product_id').on('change', function() {
        updateOutboundCounters();
        const rawValue = normalizeOutboundQRRaw($(this).val());
        if (!rawValue) return;

        outboundLastHandledQRRaw = '';
        if (handleOutboundQRProductPayload(rawValue)) return;

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