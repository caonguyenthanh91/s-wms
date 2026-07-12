<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="pda-pickup-wrap pb-4">
    <div class="pda-card p-3 mb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title">Pickup - Xác nhận bốc hàng</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Quét tem hàng</div>
        </div>

        <div class="pda-kpi-row mt-3">
            <div class="pda-kpi">
                <div class="pda-kpi-label">Invoice</div>
                <div id="summary-command" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Kiện</div>
                <div id="summary-case" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Khách hàng</div>
                <div id="summary-customer" class="pda-kpi-value text-sm">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Tổng kiện</div>
                <div id="summary-total-cases" class="pda-kpi-value">0</div>
            </div>
        </div>
    </div>

    <div class="pda-card p-3">
        <div class="pda-subtitle">Bước 1: Quét Tem Hàng</div>
        <div class="pda-title mt-1">QR trên Tem Kiện</div>
        <div class="text-xs text-slate-500 mt-1">Quét QR dạng [command]$[case_no]$[for_product]$[distinct_products]$[transport_type]$[created_at].</div>

        <div class="relative mt-3">
            <input type="text" id="pallet-qr-input" class="pda-input" placeholder="Quét QR tem kiện" maxlength="128" autocomplete="off">
            <button type="button" onclick="openQRScannerModal('pallet-qr-input', 'QR Tem Kiện')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <button type="button" class="pda-btn pda-btn-primary mt-3" onclick="handlePalletQrScan()">Xác nhận Tem Kiện</button>

        <div id="pallet-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="pallet-info" class="pda-msg-info mt-2 hidden"></div>

        <!-- Display pallet QR info -->
        <div id="pallet-info-box" class="hidden mt-3 p-3 bg-sky-50 border border-sky-200 rounded">
            <div class="text-xs font-bold text-sky-900 mb-2">✓ Thông tin tem kiện:</div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div><span class="text-slate-600">Invoice:</span> <span class="font-bold" id="pallet-info-command">-</span></div>
                <div><span class="text-slate-600">Kiện:</span> <span class="font-bold" id="pallet-info-case">-</span></div>
                <div><span class="text-slate-600">Khách hàng:</span> <span class="font-bold" id="pallet-info-customer">-</span></div>
                <div><span class="text-slate-600">Loại vận chuyển:</span> <span class="font-bold" id="pallet-info-transport">-</span></div>
                <div><span class="text-slate-600">Mã hàng:</span> <span class="font-bold" id="pallet-info-products">-</span></div>
                <div><span class="text-slate-600">Ngày xuất:</span> <span class="font-bold" id="pallet-info-date">-</span></div>
            </div>
        </div>
    </div>

    <div class="pda-card p-3 mt-3" id="shipping-mark-section" style="display:none;">
        <div class="pda-subtitle">Bước 2: Xác nhận Phiếu Shipping Mark</div>
        <div class="pda-title mt-1">QR Phiếu Shipping Mark</div>
        <div class="text-xs text-slate-500 mt-1">Quét QR dạng [command6][case3] từ phiếu shipping mark, ví dụ: ABCDEF001.</div>

        <div class="relative mt-3">
            <input type="text" id="shipping-qr-input" class="pda-input" placeholder="Quét QR phiếu shipping mark" maxlength="32" autocomplete="off">
            <button type="button" onclick="openQRScannerModal('shipping-qr-input', 'QR Phiếu Shipping Mark')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <button type="button" class="pda-btn pda-btn-primary mt-3" onclick="handleShippingMarkQrScan()">Xác nhận Phiếu</button>

        <div id="shipping-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="shipping-info" class="pda-msg-info mt-2 hidden"></div>

        <!-- Verification result -->
        <div id="shipping-match-box" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded">
            <div class="text-xs font-bold text-green-900">✓ Thông tin khớp! Đang ghi nhận pickup...</div>
        </div>
    </div>

    <!-- Session summary -->
    <div class="pda-card p-3 mt-3">
        <div class="pda-subtitle">Thống kê Pickup</div>
        <div class="pda-table-wrap">
            <table class="w-full pda-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Kiện</th>
                        <th>Khách hàng</th>
                        <th>Trạng thái</th>
                        <th>Giờ ghi nhận</th>
                    </tr>
                </thead>
                <tbody id="pickup-session-list">
                    <tr><td colspan="5" class="text-center text-slate-500 text-xs">Chưa ghi nhận pickup nào</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let pickupSession = {
    palletQR: null,  // {command, case_no, for_product, distinct_products, transport_type, created_at}
    sessionHistory: [],  // [{command, case_no, customer, timestamp}]
    busy: false
};

function normalizeQrText(text) {
    return (text || '')
        .replace(/＄/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim()
        .toUpperCase();
}

// Parse QR from pallet (tem kiện): [command]$[case_no]$[for_product]$[distinct_products]$[transport_type]$[created_at]
function parsePalletQR(rawValue) {
    const text = normalizeQrText(rawValue);
    const parts = text.split('$').map(p => p.trim());
    if (parts.length < 6) {
        return null;
    }
    return {
        command: parts[0],
        case_no: parts[1],
        for_product: parts[2],
        distinct_products: parts[3],
        transport_type: parts[4],
        created_at: parts[5]
    };
}

// Parse shipping mark QR: [command6][case3], e.g. ABCDEF001
function parseShippingMarkQR(rawValue) {
    const text = normalizeQrText(rawValue).replace(/\s+/g, '');
    const matched = text.match(/^([A-Z0-9]{6})([A-Z0-9]{3})$/);
    if (!matched) return null;
    return {
        command: matched[1],
        case_no: matched[2]
    };
}

function showPalletError(msg) {
    $('#pallet-error').removeClass('hidden').text(msg || 'Có lỗi xảy ra');
    $('#pallet-info').addClass('hidden');
}

function hidePalletError() {
    $('#pallet-error').addClass('hidden');
}

function showShippingError(msg) {
    $('#shipping-error').removeClass('hidden').text(msg || 'Có lỗi xảy ra');
    $('#shipping-info').addClass('hidden');
}

function hideShippingError() {
    $('#shipping-error').addClass('hidden');
}

function showShippingInfo(msg) {
    if (!msg) {
        $('#shipping-info').addClass('hidden');
        return;
    }
    $('#shipping-info').removeClass('hidden').text(msg);
}

function setWorkflowStatus(text, toneClass) {
    const el = $('#workflow-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-red-700 text-green-700 text-amber-700');
    el.addClass(toneClass || 'text-sky-700');
}

function updateSummary() {
    if (!pickupSession.palletQR) {
        $('#summary-command').text('-');
        $('#summary-case').text('-');
        $('#summary-customer').text('-');
        $('#summary-total-cases').text('0');
        return;
    }
    const qr = pickupSession.palletQR;
    $('#summary-command').text(qr.command || '-');
    $('#summary-case').text(qr.case_no || '-');
    $('#summary-customer').text(qr.for_product || '-');
    $('#summary-total-cases').text(qr.distinct_products || '-');
}

function displayPalletQRInfo(qr) {
    $('#pallet-info-command').text(qr.command);
    $('#pallet-info-case').text(qr.case_no);
    $('#pallet-info-customer').text(qr.for_product || '(không có)');
    $('#pallet-info-transport').text(qr.transport_type || '(không có)');
    $('#pallet-info-products').text(qr.distinct_products + ' SP');
    $('#pallet-info-date').text(qr.created_at || '(không có)');
    $('#pallet-info-box').removeClass('hidden');
}

function handlePalletQrScan() {
    const raw = $('#pallet-qr-input').val();
    if (!raw) {
        showPalletError('Vui lòng quét QR tem kiện');
        return;
    }

    const parsed = parsePalletQR(raw);
    if (!parsed) {
        showPalletError('QR không hợp lệ. Cần dùng format: [command]$[case_no]$[customer]$[products]$[type]$[date]');
        setWorkflowStatus('QR tem kiện không hợp lệ', 'text-red-700');
        return;
    }

    hidePalletError();
    pickupSession.palletQR = parsed;
    updateSummary();
    displayPalletQRInfo(parsed);

    setWorkflowStatus('Đã quét tem kiện, quét phiếu shipping mark tiếp', 'text-green-700');

    // Show step 2
    $('#shipping-mark-section').show();
    $('#shipping-qr-input').val('').focus();
    hideShippingError();
    showShippingInfo('');
    $('#shipping-match-box').addClass('hidden');
}

function handleShippingMarkQrScan() {
    if (!pickupSession.palletQR) {
        showShippingError('Vui lòng quét tem kiện trước (Bước 1)');
        return;
    }

    const raw = $('#shipping-qr-input').val();
    if (!raw) {
        showShippingError('Vui lòng quét QR phiếu shipping mark');
        return;
    }

    const parsed = parseShippingMarkQR(raw);
    if (!parsed) {
        showShippingError('QR phiếu không hợp lệ. Cần dùng format [command6][case3], ví dụ: ABCDEF001');
        setWorkflowStatus('QR phiếu shipping mark không hợp lệ', 'text-red-700');
        if (typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return;
    }

    // Verify match
    const pallet = pickupSession.palletQR;
    if (parsed.command !== pallet.command || parsed.case_no !== pallet.case_no) {
        showShippingError(`Không khớp! Tem kiện: ${pallet.command}/${pallet.case_no}, Phiếu: ${parsed.command}/${parsed.case_no}`);
        setWorkflowStatus('Phiếu không khớp với tem kiện', 'text-red-700');
        $('#shipping-match-box').addClass('hidden');
        if (typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return;
    }

    // Match confirmed
    hideShippingError();
    $('#shipping-match-box').removeClass('hidden');
    setWorkflowStatus('Thông tin khớp, đang ghi nhận pickup...', 'text-green-700');

    if (pickupSession.busy) return;
    pickupSession.busy = true;

    // Submit to API
    $.post('api.php?action=pickup_submit', {
        command: pallet.command,
        case_no: pallet.case_no
    }, function(res) {
        pickupSession.busy = false;

        if (!res.success) {
            showShippingError(res.message || 'Không thể ghi nhận pickup');
            setWorkflowStatus('Ghi nhận thất bại', 'text-red-700');
            return;
        }

        // Success: add to history and reset
        const now = new Date();
        const timeStr = now.getHours().toString().padStart(2, '0') + ':' +
                       now.getMinutes().toString().padStart(2, '0') + ':' +
                       now.getSeconds().toString().padStart(2, '0');

        pickupSession.sessionHistory.push({
            command: pallet.command,
            case_no: pallet.case_no,
            customer: pallet.for_product,
            timestamp: timeStr
        });

        showShippingInfo(`✓ Pickup ${pallet.command}/${pallet.case_no} đã ghi nhận thành công`);
        setWorkflowStatus('Ghi nhận thành công! Quét tem kiện tiếp theo', 'text-green-700');

        renderSessionHistory();

        // Reset for next
        setTimeout(function() {
            pickupSession.palletQR = null;
            $('#pallet-qr-input').val('').focus();
            $('#shipping-mark-section').hide();
            $('#pallet-info-box').addClass('hidden');
            $('#shipping-match-box').addClass('hidden');
            updateSummary();
            showShippingInfo('');
        }, 800);
    }, 'json').fail(function() {
        pickupSession.busy = false;
        showShippingError('Lỗi kết nối khi ghi nhận pickup');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
    });
}

function renderSessionHistory() {
    const body = $('#pickup-session-list');
    if (!pickupSession.sessionHistory.length) {
        body.html('<tr><td colspan="5" class="text-center text-slate-500 text-xs">Chưa ghi nhận pickup nào</td></tr>');
        return;
    }

    let html = '';
    pickupSession.sessionHistory.forEach(function(item) {
        html += '<tr>' +
            '<td>' + (item.command || '-') + '</td>' +
            '<td>' + (item.case_no || '-') + '</td>' +
            '<td>' + (item.customer || '(không có)') + '</td>' +
            '<td><span class="pda-status-ok">✓ OK</span></td>' +
            '<td class="text-xs">' + (item.timestamp || '-') + '</td>' +
        '</tr>';
    });
    body.html(html);
}

// Handle QR scanner events
window.handleQRScannerScan = function(targetId, scannedValue) {
    if (targetId === 'pallet-qr-input') {
        $('#pallet-qr-input').val(scannedValue);
        handlePalletQrScan();
        return true;
    }
    if (targetId === 'shipping-qr-input') {
        $('#shipping-qr-input').val(scannedValue);
        handleShippingMarkQrScan();
        return true;
    }
    return false;
};

// Auto-submit on Enter
$('#pallet-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handlePalletQrScan();
    }
});

$('#shipping-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handleShippingMarkQrScan();
    }
});

$(document).ready(function() {
    renderSessionHistory();
    $('#pallet-qr-input').focus();
});
</script>
