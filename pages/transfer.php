<div id="transfer-container" class="max-w-4xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <span>📦</span> Chuyển Pallet Vào Kệ
        </h3>
        
        <!-- Ô tìm kiếm nhanh -->
        <div class="mb-4 relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">🔍</span>
            <input type="text" id="pallet-search" placeholder="Tìm nhanh mã pallet..." 
                   class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 text-left">Mã Pallet</th>
                        <th class="p-3 text-left">Ngày Nhập Tạm</th>
                        <th class="p-3 text-left">Người Tạo</th>
                        <th class="p-3 text-center">Số Loại Hàng</th>
                        <th class="p-3 text-right">Thao Tác</th>
                    </tr>
                </thead>
                <tbody id="pallet-list">
                    <!-- Dữ liệu sẽ được load qua AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nhập Kệ -->
<div id="shelf-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md mx-4">
        <h4 class="text-lg font-bold mb-4 text-gray-800">Chọn Vị Trí Kệ Để Nhập Hàng</h4>
        <p class="text-sm text-gray-600 mb-4">Nhập mã kệ cho Pallet: <span id="modal-pallet-id-display" class="font-bold text-blue-600"></span></p>
        
        <input type="hidden" id="modal-pallet-id">
        <input type="text" id="target-shelf-id" placeholder="VD: A-01-01" 
               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg text-center text-xl uppercase font-mono mb-6 focus:border-blue-600 outline-none">
        
        <div class="flex justify-end gap-3">
            <button onclick="closeModal()" class="px-6 py-2 text-gray-500 font-medium hover:bg-gray-100 rounded transition">Hủy</button>
            <button onclick="confirmTransfer()" class="px-6 py-2 bg-blue-600 text-white font-bold rounded hover:bg-blue-700 shadow-lg transition">Tiếp Tục ✓</button>
        </div>
    </div>
</div>

<!-- Modal Xem Chi Tiet Pallet -->
<div id="pallet-detail-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h4 class="text-lg font-bold text-gray-800">Chi Tiet Pallet</h4>
                <p class="text-sm text-gray-600">Pallet: <span id="detail-pallet-id-display" class="font-bold text-blue-600"></span></p>
            </div>
            <button type="button" onclick="closePalletDetailModal()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 text-left">Ma Hang</th>
                        <th class="p-3 text-left">Ten San Pham</th>
                        <th class="p-3 text-right">So Luong</th>
                    </tr>
                </thead>
                <tbody id="pallet-detail-list">
                    <tr>
                        <td colspan="3" class="p-6 text-center text-gray-400">Chua co du lieu.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mt-5">
            <button type="button" onclick="closePalletDetailModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-medium rounded hover:bg-gray-300 transition">Dong</button>
        </div>
    </div>
</div>

<script>
let allPallets = []; // Lưu trữ dữ liệu gốc để search
function loadPendingPallets() {
    $.getJSON('api.php?action=get_pending_pallets', function(data) {
        allPallets = data;
        renderPalletTable(data);
    });
}

function renderPalletTable(data) {
        const tbody = $('#pallet-list');
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="5" class="p-8 text-center text-gray-400">Không có pallet nào đang chờ chuyển kho.</td></tr>');
            return;
        }
        data.forEach(p => {
            tbody.append(`
                <tr class="pallet-row border-b hover:bg-gray-50 transition" data-id="${p.pallet_id}">
                    <td class="p-3 font-mono font-bold text-blue-600">
                        <button type="button" onclick="openPalletDetailModal('${p.pallet_id}')" class="hover:underline">${p.pallet_id}</button>
                    </td>
                    <td class="p-3 text-gray-500">${p.created_at}</td>
                    <td class="p-3 text-gray-500">${p.created_by}</td>
                    <td class="p-3 text-center"><span class="bg-gray-200 px-2 py-0.5 rounded text-xs font-semibold">${p.sku_count} SKUs</span></td>
                    <td class="p-3 text-right">
                        <button onclick="openModal('${p.pallet_id}')" class="bg-blue-600 text-white px-4 py-1.5 rounded text-xs font-bold hover:bg-blue-700 transition uppercase tracking-wider shadow-sm">Transfer</button>
                    </td>
                </tr>
            `);
        });
}

// Xử lý tìm kiếm nhanh
$('#pallet-search').on('input', function() {
    const term = $(this).val().trim().toUpperCase();
    const filtered = allPallets.filter(p => p.pallet_id.toUpperCase().includes(term));
    renderPalletTable(filtered);
});

function openModal(palletId) {
    $('#modal-pallet-id').val(palletId);
    $('#modal-pallet-id-display').text(palletId);
    $('#target-shelf-id').val('');
    $('#shelf-modal').removeClass('hidden');
    setTimeout(() => $('#target-shelf-id').focus(), 100);
}

function closeModal() {
    $('#shelf-modal').addClass('hidden');
}

function closePalletDetailModal() {
    $('#pallet-detail-modal').addClass('hidden');
}

function openPalletDetailModal(palletId) {
    $('#detail-pallet-id-display').text(palletId);
    $('#pallet-detail-list').html('<tr><td colspan="3" class="p-6 text-center text-gray-400">Dang tai du lieu...</td></tr>');
    $('#pallet-detail-modal').removeClass('hidden');

    $.getJSON('api.php?action=get_import_temp_by_pallet', { pallet_id: palletId }, function(data) {
        const tbody = $('#pallet-detail-list');
        tbody.empty();

        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="3" class="p-6 text-center text-gray-400">Pallet nay khong co du lieu.</td></tr>');
            return;
        }

        data.forEach(item => {
            tbody.append(`
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 font-mono font-bold text-gray-700">${item.product_id}</td>
                    <td class="p-3 text-gray-600">${item.product_name || '-'}</td>
                    <td class="p-3 text-right font-medium">${item.quantity}</td>
                </tr>
            `);
        });
    }).fail(function() {
        $('#pallet-detail-list').html('<tr><td colspan="3" class="p-6 text-center text-red-500">Khong the tai chi tiet pallet.</td></tr>');
    });
}

async function confirmTransfer() {
    const shelfId = $('#target-shelf-id').val().trim().toUpperCase();
    const palletId = $('#modal-pallet-id').val();
    
    if (!shelfId) return alert('Vui lòng nhập mã kệ!');

    try {
        // 1. Kiểm tra kệ tồn tại
        const res = await $.post('api.php?action=check_shelf', { shelf_id: shelfId });
        if (!res.success) {
            alert('❌ Lỗi: Mã vị trí kệ không tồn tại!');
            return;
        }

        // 2. Kiểm tra hàng hóa hiện có trên kệ (để cảnh báo nhẹ)
        const items = await $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: shelfId });
        if (items && items.length > 0) {
            if (!confirm(`⚠️ Cảnh báo: Vị trí ${shelfId} đang có hàng. Bạn muốn gộp Pallet ${palletId} vào vị trí này?`)) return;
        }

        // 3. Chuyển hướng sang inbound.php
        window.location.href = `index.php?page=inbound&shelf_id=${shelfId}&pallet_id=${palletId}`;
    } catch (e) { alert('Lỗi hệ thống!'); }
}

$(document).ready(function() {
    loadPendingPallets();

    $('#pallet-detail-modal').on('click', function(e) {
        if (e.target === this) {
            closePalletDetailModal();
        }
    });
});
</script>