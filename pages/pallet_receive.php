<div id="pallet-receive-container" class="max-w-4xl mx-auto">
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
        .error-modal-content.error h2 { color: #dc2626; }
        .error-modal-content.success h2 { color: #059669; }
        .error-modal-content.warning h2 { color: #d97706; }
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
        .error-modal-content.error .error-modal-btn { background-color: #dc2626; }
        .error-modal-content.error .error-modal-btn:hover { background-color: #b91c1c; }
        .error-modal-content.success .error-modal-btn { background-color: #059669; }
        .error-modal-content.success .error-modal-btn:hover { background-color: #047857; }
        .error-modal-content.warning .error-modal-btn { background-color: #d97706; }
        .error-modal-content.warning .error-modal-btn:hover { background-color: #b45309; }
    </style>

    <!-- Universal Modal -->
    <div id="error-modal" class="error-modal-overlay" style="display: none;">
        <div id="error-modal-content" class="error-modal-content error">
            <h2 id="error-modal-title">⚠️ Cảnh báo</h2>
            <p id="error-modal-message"></p>
            <button onclick="closeErrorModal()" class="error-modal-btn">OK</button>
        </div>
    </div>

    <!-- Step 1: Scan Pallet -->
    <div id="scan-step" class="bg-white p-4 rounded-lg shadow-md text-center">
        <h3 class="text-lg font-bold mb-2 text-gray-800">Nhận Pallet tại Kho Tổng</h3>
        <div class="relative w-full max-w-sm mx-auto mb-2">
            <input type="text" id="pallet-input" placeholder="Nhập / Quét mã Pallet"
                   class="w-full px-4 py-3 pr-11 border-2 border-gray-300 rounded-lg text-center text-xl uppercase font-mono focus:border-blue-600 outline-none">
            <button type="button" onclick="openQRScannerModal('pallet-input', 'Mã Pallet')" class="absolute right-2 top-1/2 -translate-y-1/2 text-blue-600 hover:text-blue-800">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>
        <button onclick="lookupPallet()" class="w-full max-w-sm bg-blue-600 text-white py-3 rounded-lg font-bold hover:bg-blue-700 transition">Kiểm tra Pallet</button>
    </div>

    <!-- Step 2: Confirm -->
    <div id="confirm-step" class="bg-white p-4 rounded-lg shadow-md mt-4 hidden">
        <div class="flex justify-between items-center mb-3">
            <h3 class="text-base sm:text-lg font-bold text-gray-800">Pallet: <span id="display-pallet" class="text-blue-600"></span></h3>
            <button onclick="resetReceive()" class="text-gray-500 text-xs sm:text-sm underline">Đóng</button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 text-sm">
            <div class="rounded border border-blue-200 bg-blue-50 px-2 py-2 font-semibold text-blue-700">Số SKU: <span id="pallet-sku-count">0</span></div>
            <div class="rounded border border-emerald-200 bg-emerald-50 px-2 py-2 font-semibold text-emerald-700">Tổng SL: <span id="pallet-total-qty">0</span></div>
        </div>

        <div class="mb-4 border border-gray-200 rounded-lg overflow-hidden">
            <div class="max-h-64 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-gray-100">
                        <tr class="bg-gray-100">
                            <th class="p-2 text-left text-xs sm:text-sm">Mã hàng</th>
                            <th class="p-2 text-right text-xs sm:text-sm">Số thùng</th>
                            <th class="p-2 text-right text-xs sm:text-sm">Số lượng</th>
                            <th class="p-2 text-left text-xs sm:text-sm">Nhập cuối</th>
                        </tr>
                    </thead>
                    <tbody id="pallet-item-list">
                        <!-- Danh sách hàng theo import_temp -->
                    </tbody>
                </table>
            </div>
        </div>

        <button id="btn-confirm-receive" onclick="confirmReceive()" class="w-full bg-blue-600 text-white py-4 rounded-lg font-bold text-lg hover:bg-blue-700 shadow-lg">✓ Xác nhận đã nhận hàng</button>
    </div>
</div>

<script>
let currentPalletId = '';
let isConfirming = false;

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
        resetReceive();
    } else {
        $('#pallet-input').focus();
    }
}

function renderPalletItems(items) {
    const tbody = $('#pallet-item-list');
    tbody.empty();

    if (!items || items.length === 0) {
        tbody.append('<tr><td colspan="4" class="p-4 text-center text-gray-400">Không có dữ liệu.</td></tr>');
        return;
    }

    items.forEach(item => {
        tbody.append(`
            <tr class="border-b">
                <td class="p-2 text-left font-mono">${item.product_id}</td>
                <td class="p-2 text-right">${item.box_count}</td>
                <td class="p-2 text-right">${item.quantity}</td>
                <td class="p-2 text-left text-xs text-gray-600">${item.last_import_at || ''}</td>
            </tr>
        `);
    });
}

function resetReceive() {
    currentPalletId = '';
    $('#confirm-step').addClass('hidden');
    $('#pallet-item-list').empty();
    $('#pallet-input').val('').focus();
}

function lookupPallet() {
    const palletId = $('#pallet-input').val().trim().toUpperCase();
    if (!palletId) return;

    $.getJSON('api.php?action=pallet_receive_lookup', { pallet_id: palletId }, function(res) {
        if (!res.success) {
            showErrorModal(res.message || 'Không thể nhận pallet này.');
            return;
        }

        currentPalletId = palletId;
        $('#display-pallet').text(palletId);
        $('#pallet-sku-count').text(res.sku_count || 0);
        $('#pallet-total-qty').text(res.total_qty || 0);
        renderPalletItems(res.items || []);
        $('#confirm-step').removeClass('hidden');
    }).fail(function() {
        showErrorModal('Lỗi kết nối máy chủ, vui lòng thử lại.');
    });
}

function confirmReceive() {
    if (isConfirming || !currentPalletId) return;
    isConfirming = true;

    const btn = $('#btn-confirm-receive');
    const originalText = btn.html();
    btn.prop('disabled', true).css('opacity', '0.5').html('⏳ Đang xử lý...');

    $.post('api.php?action=pallet_receive_confirm', { pallet_id: currentPalletId }, function(res) {
        if (res.success) {
            showModal(`Đã xác nhận nhận hàng cho Pallet ${currentPalletId}!`, 'success');
        } else {
            showErrorModal(res.message || 'Xác nhận thất bại.');
        }
    }, 'json').fail(function() {
        showErrorModal('Lỗi kết nối máy chủ, vui lòng thử lại.');
    }).always(function() {
        isConfirming = false;
        btn.prop('disabled', false).css('opacity', '1').html(originalText);
    });
}

$(document).ready(function() {
    attachScanOnlyGuard('#pallet-input');

    $('#pallet-input').on('keypress', function(e) {
        if (e.which === 13) lookupPallet();
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
