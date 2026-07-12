<div id="transfer-container" class="max-w-4xl mx-auto">
    <style>
        @media (max-width: 900px) {
            #transfer-container .pda-hide {
                display: none !important;
            }

            #transfer-container .pda-transfer-btn {
                display: block;
                width: 100%;
                margin-top: 0.35rem;
                text-align: center;
                padding-top: 0.55rem;
                padding-bottom: 0.55rem;
                font-size: 0.85rem;
            }

            #transfer-container #pallet-list td {
                padding-top: 0.6rem;
                padding-bottom: 0.6rem;
            }
        }
    </style>

    <div class="bg-white p-4 rounded-lg shadow-md mb-4 border border-slate-200 pda-hide">
        <h4 class="text-sm font-bold uppercase tracking-wide text-slate-600 mb-3">Thống kê tiến độ nhập kho Pallet</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                <div class="text-[11px] uppercase font-bold text-blue-700">Tổng số</div>
                <div id="summary-total" class="text-2xl font-black text-blue-800 leading-tight">0</div>
                <div class="text-[11px] text-blue-700">pallet</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                <div class="text-[11px] uppercase font-bold text-emerald-700">Đã lên kệ</div>
                <div id="summary-transferred" class="text-2xl font-black text-emerald-800 leading-tight">0</div>
                <div class="text-[11px] text-emerald-700">pallet</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                <div class="text-[11px] uppercase font-bold text-amber-700">Chưa lên kệ</div>
                <div id="summary-pending" class="text-2xl font-black text-amber-800 leading-tight">0</div>
                <div class="text-[11px] text-amber-700">pallet</div>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <span>📦</span> Danh sách Pallet cần lên kệ
        </h3>
        
        <!-- Ô tìm kiếm nhanh -->
        <div class="mb-4 flex gap-2">
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">🔍</span>
                <input type="text" id="pallet-search" placeholder="Nhập / Quét mã Pallet" 
                       class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm uppercase">
            </div>
            <button type="button" id="pallet-search-btn" class="px-3 py-2 rounded bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">Tìm</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 text-left">Mã Pallet</th>
                        <th class="p-3 text-left pda-hide">Thời điểm đăng ký</th>
                        <th class="p-3 text-left pda-hide">Người thao tác</th>
                        <th class="p-3 text-center pda-hide">Số thùng/ pallet</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody id="pallet-list">
                    <!-- Dữ liệu sẽ được load qua AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Confirm Gộp -->
<div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center" style="z-index: 9999;">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md mx-4 text-center">
        <h3 class="text-xl font-bold text-amber-600 mb-4">⚠️ Xác nhận</h3>
        <p id="confirm-message" class="text-gray-700 mb-6 leading-relaxed"></p>
        <div class="flex justify-center gap-4">
            <button onclick="closeConfirmModal()" class="px-6 py-2 bg-gray-300 text-gray-700 font-bold rounded hover:bg-gray-400 transition">Không</button>
            <button onclick="confirmAction()" class="px-6 py-2 bg-amber-600 text-white font-bold rounded hover:bg-amber-700 transition">Có</button>
        </div>
    </div>
</div>

<!-- Modal Nhập Kệ -->
<div id="shelf-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md mx-4">
        <h4 class="text-lg font-bold mb-4 text-gray-800">Chọn mã kệ để nhập hàng cho pallet</h4>
        <p class="text-sm text-gray-600 mb-4"><span id="modal-pallet-id-display" class="font-bold text-blue-600"></span></p>
        
        <input type="hidden" id="modal-pallet-id">
        <input type="text" id="target-shelf-id" placeholder="Nhập / Quét mã kệ" 
               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg text-center text-xl uppercase font-mono mb-6 focus:border-blue-600 outline-none">
        
        <div class="flex justify-end gap-3">
            <button onclick="closeModal()" class="px-6 py-2 text-gray-500 font-medium hover:bg-gray-100 rounded transition">Hủy</button>
            <button onclick="confirmTransfer()" class="px-6 py-2 bg-blue-600 text-white font-bold rounded hover:bg-blue-700 shadow-lg transition">✓ Đồng ý</button>
        </div>
    </div>
</div>

<!-- Modal Xem Chi Tiet Pallet -->
<div id="pallet-detail-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h4 class="text-lg font-bold text-gray-800">Chi tiết Pallet</h4>
                <p class="text-sm text-gray-600">Pallet: <span id="detail-pallet-id-display" class="font-bold text-blue-600"></span></p>
            </div>
            <button type="button" onclick="closePalletDetailModal()" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="p-3 text-left">Mã Hàng</th>
                        <th class="p-3 text-left">Tên Sản Phẩm</th>
                        <th class="p-3 text-right">Số Lượng</th>
                    </tr>
                </thead>
                <tbody id="pallet-detail-list">
                    <tr>
                        <td colspan="3" class="p-6 text-center text-gray-400">Chưa có dữ liệu.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mt-5">
            <button type="button" onclick="closePalletDetailModal()" class="px-6 py-2 bg-gray-200 text-gray-700 font-medium rounded hover:bg-gray-300 transition">Đóng</button>
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
let allPallets = []; // Lưu trữ dữ liệu gốc để search
let filteredPallets = [];
let currentSearchTerm = '';

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
    $('#error-modal').css('display', 'none');
}

let confirmCallback = null;

function showConfirmModal(message, onConfirm, onCancel) {
    $('#confirm-message').text(message);
    confirmCallback = { onConfirm, onCancel };
    $('#confirm-modal').removeClass('hidden');
}

function confirmAction() {
    $('#confirm-modal').addClass('hidden');
    if (confirmCallback && typeof confirmCallback.onConfirm === 'function') {
        confirmCallback.onConfirm();
    }
}

function closeConfirmModal() {
    $('#confirm-modal').addClass('hidden');
    if (confirmCallback && typeof confirmCallback.onCancel === 'function') {
        confirmCallback.onCancel();
    }
}

function updateTransferSummary(summary) {
    $('#summary-total').text(summary.total_pallets || 0);
    $('#summary-transferred').text(summary.transferred_pallets || 0);
    $('#summary-pending').text(summary.pending_pallets || 0);
}

function loadTransferSummary(keyword) {
    $.getJSON('api.php?action=get_pallet_transfer_summary', { keyword: keyword || '' }, function(res) {
        if (res && res.success) {
            updateTransferSummary(res);
            return;
        }
        updateTransferSummary({ total_pallets: 0, transferred_pallets: 0, pending_pallets: 0 });
    }).fail(function() {
        updateTransferSummary({ total_pallets: 0, transferred_pallets: 0, pending_pallets: 0 });
    });
}

function loadPendingPallets() {
    $.getJSON('api.php?action=get_pending_pallets', function(data) {
        allPallets = data;
        applyTransferFilter(currentSearchTerm);
    });
}

function applyTransferFilter(term) {
    const normalized = (term || '').trim().toUpperCase();
    currentSearchTerm = normalized;

    filteredPallets = normalized
        ? allPallets.filter(p => (p.pallet_id || '').toUpperCase().includes(normalized))
        : allPallets.slice();

    renderPalletTable(filteredPallets);
    loadTransferSummary(normalized);
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
                    <td class="p-3 text-gray-500 pda-hide">${p.created_at}</td>
                    <td class="p-3 text-gray-500 pda-hide">${p.created_by}</td>
                    <td class="p-3 text-center pda-hide"><span class="bg-gray-200 px-2 py-0.5 rounded text-xs font-semibold">${p.sku_count} SKUs</span></td>
                    <td class="p-3 text-right">
                        <button onclick="openModal('${p.pallet_id}')" class="pda-transfer-btn bg-blue-600 text-white px-4 py-1.5 rounded text-xs font-bold hover:bg-blue-700 transition uppercase tracking-wider shadow-sm">Transfer</button>
                    </td>
                </tr>
            `);
        });
}

function triggerTransferSearch() {
    applyTransferFilter($('#pallet-search').val());
}

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

    if (!shelfId) {
        showModal('Vui lòng nhập mã kệ!', 'error');
        return;
    }

    try {
        // 1. Kiểm tra kệ tồn tại
        const res = await $.post('api.php?action=check_shelf', { shelf_id: shelfId });
        if (!res.success) {
            showModal('Mã vị trí kệ không tồn tại!', 'error', '❌ Lỗi');
            return;
        }

        // 2. Kiểm tra hàng hóa hiện có trên kệ (để cảnh báo nhẹ)
        const items = await $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: shelfId });
        if (items && items.length > 0) {
            return showConfirmModal(
                `Cảnh báo: Vị trí ${shelfId} đang có hàng. Bạn muốn gộp Pallet ${palletId} vào vị trí này?`,
                function() {
                    // Người dùng nhấn "Có"
                    window.location.href = `index.php?page=inbound&shelf_id=${shelfId}&pallet_id=${palletId}`;
                },
                function() {
                    // Người dùng nhấn "Không" - không làm gì cả
                }
            );
        }

        // 3. Chuyển hướng sang inbound.php
        window.location.href = `index.php?page=inbound&shelf_id=${shelfId}&pallet_id=${palletId}`;
    } catch (e) {
        showModal('Lỗi hệ thống!', 'error');
    }
}

$(document).ready(function() {
    loadPendingPallets();
    loadTransferSummary('');

    $('#pallet-search-btn').on('click', function() {
        triggerTransferSearch();
    });

    $('#pallet-search').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerTransferSearch();
        }
    });

    $('#pallet-detail-modal').on('click', function(e) {
        if (e.target === this) {
            closePalletDetailModal();
        }
    });

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