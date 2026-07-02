<div id="outbound-container" class="max-w-4xl mx-auto">
    <!-- Bước 1: Quét Mã Kệ -->
    <div id="step-1" class="bg-white p-8 rounded-lg shadow-md text-center">
        <h3 class="text-2xl font-bold mb-4 text-gray-800">Bước 1: Quét Mã Kệ Xuất</h3>
        <div class="mb-6 text-6xl">📱</div>
        <div class="relative w-full max-w-sm mx-auto mb-4">
            <input type="text" id="shelf-input" placeholder="Nhập mã kệ (VD: A-01-01)"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-red-600 outline-none">
            <button type="button" onclick="openQRScannerModal('shelf-input', 'Mã Kệ Xuất')" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>
        <button onclick="checkShelfOutbound()" class="w-full max-w-sm bg-red-600 text-white py-3 rounded-lg font-bold hover:bg-red-700 transition">Xác Nhận Kệ</button>
        <p id="shelf-error" class="mt-4 text-red-600 hidden"></p>
    </div>

    <!-- Bước 2: Nhập Hàng Xuất -->
    <div id="step-2" class="bg-white p-8 rounded-lg shadow-md hidden">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Bước 2: Xuất Hàng Từ Kệ <span id="display-shelf" class="text-red-600"></span></h3>
            <button onclick="resetOutbound()" class="text-gray-500 text-sm underline">Đổi kệ</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="relative">
                <select id="product_id" class="w-full px-4 py-2 pr-10 border rounded bg-white">
                    <option value="">-- Chọn sản phẩm trên kệ --</option>
                </select>
                <button type="button" onclick="openQRScannerModal('product_id', 'Mã Sản Phẩm')" class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800">
                    <i class="fas fa-qrcode"></i>
                </button>
            </div>
            <input type="number" id="qty-input" placeholder="Số lượng xuất" class="px-4 py-2 border rounded">
            <button onclick="addItemOutbound()" class="bg-red-600 text-white py-2 rounded font-bold hover:bg-red-700">+ Thêm</button>
        </div>

        <table class="w-full mb-6">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2 text-left">SKU</th>
                    <th class="p-2 text-right">Số Lượng Xuất</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody id="item-list">
                <!-- Danh sách hàng chờ xuất -->
            </tbody>
        </table>

        <button onclick="submitOutbound()" class="w-full bg-red-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-red-700 shadow-lg">✓ Xác Nhận Xuất Kho</button>

        <!-- Tồn kho thực tế trên kệ -->
        <div id="current-stock-info" class="mt-8 p-4 bg-gray-50 rounded-lg border-t-2 border-red-200">
            <h4 class="text-sm font-bold text-gray-700 mb-2 uppercase">Danh sách hàng đang có trên kệ:</h4>
            <div id="shelf-stock-list" class="text-xs space-y-1">
                <!-- Danh sách hàng hiện có -->
            </div>
        </div>
    </div>
</div>

<script>
let outboundItems = [];
let shelfInventory = {}; // Lưu trữ tồn kho thực tế của kệ để validate

function checkShelfOutbound() {
    const shelfId = $('#shelf-input').val().toUpperCase();
    $.post('api.php?action=check_shelf', { shelf_id: shelfId }, function(res) {
        if(res.success) {
            $('#display-shelf').text(res.data.shelf_id);
            $('#step-1').addClass('hidden');
            $('#step-2').removeClass('hidden');
            $('#shelf-error').addClass('hidden');

            // Tải tồn kho hiện tại của kệ
            $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: shelfId }, function(items) {
                const list = $('#shelf-stock-list');
                const select = $('#product_id');
                list.empty();
                select.empty().append('<option value="">-- Chọn sản phẩm trên kệ --</option>');
                shelfInventory = {};

                if (items && items.length > 0) {
                    items.forEach(item => {
                        shelfInventory[item.product_id] = item.quantity;
                        list.append(`<div class="flex justify-between border-b pb-1">
                            <span>${item.product_id} - ${item.product_name}</span>
                            <span class="font-bold">Tồn: ${item.quantity}</span>
                        </div>`);
                        select.append(`<option value="${item.product_id}">${item.product_id} (Tồn: ${item.quantity})</option>`);
                    });
                } else {
                    list.append('<p class="text-red-500 italic">Kệ này hiện không có hàng hóa.</p>');
                }
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
    $('#item-list').empty();
}

function addItemOutbound() {
    const sku = $('#product_id').val();
    const qty = parseInt($('#qty-input').val());
    const maxQty = shelfInventory[sku] || 0;

    if (!sku || isNaN(qty) || qty <= 0) {
        alert('Vui lòng chọn sản phẩm và nhập số lượng.');
        return;
    }

    if (qty > maxQty) {
        alert(`Không đủ tồn kho! Số lượng tối đa có thể xuất là ${maxQty}`);
        return;
    }

    outboundItems.push({ product_id: sku, quantity: qty });
    renderOutboundList();
    $('#qty-input').val('');
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
    const urlParams = new URLSearchParams(window.location.search);
    const shelfId = urlParams.get('shelf_id');
    if (shelfId) { $('#shelf-input').val(shelfId); checkShelfOutbound(); }
});
</script>