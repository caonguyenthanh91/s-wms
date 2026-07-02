<div id="inbound-container" class="max-w-4xl mx-auto">
    <!-- Step 1: Scan Shelf -->
    <div id="step-1" class="bg-white p-8 rounded-lg shadow-md text-center">
        <h3 class="text-2xl font-bold mb-4 text-gray-800">Bước 1: Quét Mã Kệ</h3>
        <div class="mb-6 text-6xl">📱</div>
        <div class="relative w-full max-w-sm mx-auto mb-4">
            <input type="text" id="shelf-input" placeholder="Nhập mã kệ (VD: A-01-01)"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-blue-600 outline-none">
            <button type="button" onclick="openQRScannerModal('shelf-input', 'Mã Kệ')" class="absolute right-2 top-1/2 -translate-y-1/2 text-blue-600 hover:text-blue-800">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>
        <button onclick="checkShelf()" class="w-full max-w-sm bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition">Xác Nhận Kệ</button>
        <p id="shelf-error" class="mt-4 text-red-600 hidden"></p>
    </div>

    <!-- Step 2: Input Items (Hidden by default) -->
    <div id="step-2" class="bg-white p-8 rounded-lg shadow-md hidden">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Bước 2: Nhập Hàng Vào Kệ <span id="display-shelf" class="text-blue-600"></span></h3>
            <button onclick="resetInbound()" class="text-gray-500 text-sm underline">Đổi kệ</button>
        </div>

        <div class="mb-5 flex flex-col gap-3 bg-gray-50 border border-gray-200 rounded-lg p-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase font-bold tracking-wide text-gray-500">Chế độ quét</p>
                <p class="text-sm text-gray-700" id="scan-mode-label">Gián đoạn: dừng để xác nhận số lượng</p>
            </div>
            <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden self-start sm:self-auto">
                <button type="button" id="scan-mode-interrupt" onclick="setInboundScanMode(false)" class="px-3 py-2 text-sm font-semibold bg-blue-600 text-white">Gián đoạn</button>
                <button type="button" id="scan-mode-continuous" onclick="setInboundScanMode(true)" class="px-3 py-2 text-sm font-semibold bg-white text-gray-700 hover:bg-gray-100">Liên tục</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="relative">
                <input type="text" id="product_id" name="product_id" placeholder="Mã Sản phẩm (Quét/Nhập)" class="w-full px-4 py-2 pr-11 border rounded uppercase font-mono">
                <button type="button" onclick="openQRScannerModal('product_id', 'Mã Sản Phẩm')" class="absolute right-2 top-1/2 -translate-y-1/2 text-blue-600 hover:text-blue-800">
                    <i class="fas fa-qrcode"></i>
                </button>
                <p id="product-error" class="absolute -bottom-5 left-0 text-[10px] text-red-600 hidden"></p>
            </div>
            <input type="number" id="qty-input" placeholder="Số lượng" class="px-4 py-2 border rounded" min="1">
            <button id="btn-add-item" onclick="addItem()" class="bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700">+ Thêm (Enter)</button>
        </div>

        <table class="w-full mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 text-left">SKU</th>
                    <th class="p-2 text-right">Số Lượng</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody id="item-list">
                <!-- Danh sách hàng sẽ hiện ở đây -->
            </tbody>
        </table>

        <button onclick="submitInbound()" class="w-full bg-blue-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-blue-700 shadow-lg">✓ Hoàn Tất Nhập Kho</button>

        <!-- Danh sách hàng hiện có trên kệ được chuyển xuống dưới cùng -->
        <div id="current-stock" class="mt-8 p-4 bg-blue-50 rounded-lg hidden border-t-2 border-blue-200">
            <h4 class="text-sm font-bold text-blue-800 mb-2 uppercase">Hàng đang có sẵn trên kệ này:</h4>
            <div id="shelf-stock-list" class="text-xs space-y-1">
                <!-- Danh sách hàng hiện có -->
            </div>
        </div>
    </div>
</div>

<script>
let inboundItems = [];
let qrScanTimer = null;
let lastHandledQRRaw = '';
let isContinuousInboundScan = false;

function setInboundScanMode(isContinuous) {
    isContinuousInboundScan = !!isContinuous;
    updateInboundScanModeUI();
    $('#product_id').focus();
}

function updateInboundScanModeUI() {
    const interruptBtn = $('#scan-mode-interrupt');
    const continuousBtn = $('#scan-mode-continuous');

    if (isContinuousInboundScan) {
        interruptBtn.removeClass('bg-blue-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        continuousBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-blue-600 text-white');
        $('#scan-mode-label').text('Liên tục: tự thêm ngay khi QR hợp lệ');
    } else {
        continuousBtn.removeClass('bg-blue-600 text-white').addClass('bg-white text-gray-700 hover:bg-gray-100');
        interruptBtn.removeClass('bg-white text-gray-700 hover:bg-gray-100').addClass('bg-blue-600 text-white');
        $('#scan-mode-label').text('Gián đoạn: dừng để xác nhận số lượng');
    }
}

function normalizeInboundQRRaw(rawValue) {
    return (rawValue || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
}

function showProductError(message) {
    $('#product-error').text(message).removeClass('hidden');
    $('#product_id').addClass('border-red-500').removeClass('border-green-500');
}

function clearProductError() {
    $('#product-error').addClass('hidden');
    $('#product_id').removeClass('border-red-500');
}

function parseInboundQRPayload(rawValue) {
    const normalizedValue = normalizeInboundQRRaw(rawValue);
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
            showProductError('❌ Mã sản phẩm không tồn tại!');
            if (typeof onFail === 'function') onFail();
        }
    });
}

function handleQRProductPayload(rawValue) {
    const normalizedRaw = normalizeInboundQRRaw(rawValue);
    if (!normalizedRaw || normalizedRaw === lastHandledQRRaw) return false;

    const parsed = parseInboundQRPayload(rawValue);
    if (!parsed) return false;

    lastHandledQRRaw = normalizedRaw;

    validateProduct(parsed.productId, function() {
        $('#product_id').val(parsed.productId);
        $('#qty-input').val(parsed.quantity);

        if (isContinuousInboundScan) {
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

function checkShelf() {
    const shelfId = $('#shelf-input').val().toUpperCase();
    $.post('api.php?action=check_shelf', { shelf_id: shelfId }, function(res) {
        if(res.success) {
            $('#display-shelf').text(res.data.shelf_id);
            $('#step-1').addClass('hidden');
            $('#step-2').removeClass('hidden');
            $('#product_id').focus();
            $('#shelf-error').addClass('hidden');

            // Tải danh sách hàng hiện có trên kệ
            $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: shelfId }, function(items) {
                const stockDiv = $('#current-stock');
                const list = $('#shelf-stock-list');
                list.empty();
                if (items && items.length > 0) {
                    items.forEach(item => {
                        list.append(`<div class="flex justify-between border-b border-blue-200 pb-1">
                            <span>${item.product_id} - ${item.product_name}</span>
                            <span class="font-bold">SL: ${item.quantity}</span>
                        </div>`);
                    });
                    stockDiv.removeClass('hidden');
                } else {
                    stockDiv.addClass('hidden');
                }
            });
        } else {
            $('#shelf-error').text('Mã kệ không tồn tại!').removeClass('hidden');
        }
    }, 'json');
}

function resetInbound() {
    $('#step-2').addClass('hidden');
    $('#step-1').removeClass('hidden');
    $('#shelf-input').val('').focus();
    inboundItems = [];
    lastHandledQRRaw = '';
    setInboundScanMode(false);
    renderItemList();
}

$('#product_id').on('input', function() {
    const rawValue = normalizeInboundQRRaw($(this).val());
    if (!rawValue || rawValue.indexOf('$') === -1) return;

    // Cho scanner nhap xong toan bo chuoi roi moi parse de tranh ky tu duoi QR chay vao o so luong.
    clearTimeout(qrScanTimer);
    qrScanTimer = setTimeout(function() {
        const finalRaw = normalizeInboundQRRaw($('#product_id').val());
        const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 7;
        if (isLikelyComplete) handleQRProductPayload(finalRaw);
    }, 120);
});

// Xử lý sự kiện khi rời khỏi ô nhập hoặc nhấn Enter (focus out) cho mã sản phẩm
$('#product_id').on('change', function() {
    const rawValue = normalizeInboundQRRaw($(this).val());
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

// Xử lý sự kiện phím Enter cho số lượng
$('#qty-input').on('keypress', function(e) {
    if (e.which == 13) {
        addItem();
    }
});

function addItem() {
    const productId = $('#product_id').val().trim().toUpperCase();
    const qty = parseInt($('#qty-input').val());

    if (!productId || isNaN(qty) || qty <= 0) {
        return;
    }

    inboundItems.push({ product_id: productId, quantity: qty });
    renderItemList();
    
    // Tối ưu: Reset và quay lại ô nhập mã sản phẩm ngay lập tức
    $('#product_id').val('').removeClass('border-green-500 border-red-500').focus();
    $('#qty-input').val('');
    $('#product-error').addClass('hidden');
    lastHandledQRRaw = '';
}

function renderItemList() {
    const list = $('#item-list');
    list.empty();
    inboundItems.forEach((item, index) => {
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
}

function removeItem(index) {
    inboundItems.splice(index, 1);
    renderItemList();
}

async function submitInbound() {
    if (inboundItems.length === 0) {
        alert('Danh sách hàng trống!');
        return;
    }

    const shelfId = $('#display-shelf').text();
    let hasError = false;

    for (const item of inboundItems) {
        try {
            const res = await $.post('api.php?action=inbound_submit', {
                shelf_id: shelfId,
                product_id: item.product_id,
                quantity: item.quantity
            });
            
            if (!res.success) {
                alert(`Lỗi khi nhập SP ${item.product_id}: ${res.message}`);
                hasError = true;
                break;
            }
        } catch (e) {
            alert('Lỗi kết nối máy chủ!');
            hasError = true;
            break;
        }
    }

    if (!hasError) {
        // Nếu là chuyển từ Pallet, đánh dấu pallet đã hoàn tất
        const urlParams = new URLSearchParams(window.location.search);
        const palletId = urlParams.get('pallet_id');
        if (palletId) {
            await $.post('api.php?action=mark_pallet_transferred', {
                pallet_id: palletId,
                shelf_id: shelfId
            });
        }

        alert('Nhập kho hoàn tất thành công!');
        resetInbound();
        
        // Nếu đến từ trang transfer, quay lại trang transfer
        if (palletId) {
            window.location.href = 'index.php?page=transfer';
        }
    }
}

$(document).ready(function() {
    setInboundScanMode(false);

    // Tự động kiểm tra kệ nếu có tham số từ Dashboard truyền sang
    const urlParams = new URLSearchParams(window.location.search);
    const shelfIdFromUrl = urlParams.get('shelf_id');
    if (shelfIdFromUrl) {
        $('#shelf-input').val(shelfIdFromUrl);
        checkShelf();
    }

    // Tự động tải hàng từ pallet nếu có tham số pallet_id
    const palletIdFromUrl = urlParams.get('pallet_id');
    if (palletIdFromUrl) {
        $.getJSON('api.php?action=get_import_temp_by_pallet', { pallet_id: palletIdFromUrl }, function(items) {
            if (items && items.length > 0) {
                inboundItems = items.map(i => ({ 
                    product_id: i.product_id, 
                    quantity: parseInt(i.quantity) 
                }));
                renderItemList();
            }
        });
    }
});
</script>