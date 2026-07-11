<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="pda-pack-wrap pb-4">
    <div class="pda-card p-3 mb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title">Packing PDA 2 bước</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Chờ quét invoice</div>
        </div>

        <div class="pda-step-index mt-3">
            <div id="step-pill-1" class="pda-step-pill current">1. QR Invoice</div>
            <div id="step-pill-2" class="pda-step-pill">2. Quét thùng liên tục</div>
        </div>

        <div class="pda-top-index mt-3">
            <div class="pda-kpi">
                <div class="pda-kpi-label">Invoice</div>
                <div id="summary-invoice" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Đã packing / cần</div>
                <div id="summary-packing" class="pda-kpi-value">0 / 0</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Lượt quét</div>
                <div id="summary-scan-count" class="pda-kpi-value">0</div>
            </div>
        </div>
    </div>

    <div id="step-1" class="pda-step active pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 1</div>
        <div class="pda-title mt-1">Quét QR tem packing</div>
        <div class="text-xs text-slate-500 mt-1">Quét QR từ phiếu packing: [command]$[case_no]$[for_product]$... hoặc [command][case_no]. Ví dụ: VUN350$001 hoặc VUN350001</div>

        <div class="relative mt-3">
            <input type="text" id="invoice-input" class="pda-input" placeholder="VD: VUN350$001" maxlength="100">
            <button type="button" onclick="openQRScannerModal('invoice-input', 'QR Invoice')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-3">
            <button type="button" class="pda-btn pda-btn-primary" onclick="handleInvoiceScan()">Xác nhận invoice</button>
        </div>

        <div id="invoice-pending-wrap" class="pda-pending-wrap hidden">
            <div class="pda-pending-title">Mã hàng chưa đủ số lượng (theo command + case_no)</div>
            <div id="invoice-pending-summary" class="text-xs text-slate-600 mt-1"></div>
            <div id="invoice-pending-list" class="pda-pending-list"></div>
        </div>

        <div id="step1-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="step1-info" class="pda-msg-info mt-2 hidden"></div>
    </div>

    <div id="step-2" class="pda-step pda-card p-3 mb-3">
        <div class="pda-subtitle">Bước 2</div>
        <div class="pda-title mt-1">Quét mã thùng để ghi nhận packing</div>
        <div class="text-xs text-slate-500 mt-1">Định dạng: [TEXT]$[MÃ HÀNG]$[TEXT]$[SỐ LƯỢNG]$...</div>

        <div class="relative mt-3">
            <input type="text" id="box-qr-input" class="pda-input" placeholder="Quét QR trên thùng" disabled>
            <button type="button" onclick="openQRScannerModal('box-qr-input', 'QR Thùng Hàng')" class="absolute right-3 top-1/2 -translate-y-1/2 text-green-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-2 text-xs text-slate-600">
            Quét đúng mã thuộc invoice sẽ tự động ghi log trạng thái <span class="font-bold">packing</span>, không cần bấm xác nhận.
        </div>

        <div class="mt-3 flex gap-2">
            <button type="button" class="pda-btn pda-btn-success" onclick="focusBoxInput()">Sẵn sàng quét</button>
            <button type="button" class="pda-btn" style="background:#fbbf24;color:#0f172a;" onclick="completePackingJob()">Hoàn thành</button>
            <button type="button" class="pda-btn" style="background:#e2e8f0;color:#0f172a;" onclick="resetPackingJob(true)">Đổi invoice</button>
        </div>

        <div id="step2-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="step2-info" class="pda-msg-info mt-2 hidden"></div>

        <div class="pda-state-row mt-3">
            <div id="state-picking" class="pda-state-card">
                <div class="pda-state-label">Picking</div>
                <div id="state-picking-value" class="pda-state-value">0 / 0</div>
            </div>
            <div id="state-packing" class="pda-state-card">
                <div class="pda-state-label">Packing</div>
                <div id="state-packing-value" class="pda-state-value">0 / 0</div>
            </div>
            <div id="state-pickup" class="pda-state-card">
                <div class="pda-state-label">Pickup</div>
                <div id="state-pickup-value" class="pda-state-value">0 / 0</div>
            </div>
        </div>

        <div class="mt-3 pda-table-wrap">
            <table class="w-full pda-table">
                <thead>
                    <tr>
                        <th>Mã hàng</th>
                        <th class="num">Tổng xuất</th>
                        <th class="num">Đã đóng gói</th>
                        <th class="num">Còn lại</th>
                    </tr>
                </thead>
                <tbody id="packing-lines-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let packingState = {
    command: '',
    caseNo: '',
    invoiceCode: '',
    requiredTotal: 0,
    statusTotals: { picking: 0, packing: 0, pickup: 0 },
    items: [],
    scanCount: 0,
    busy: false,
    lastHandledQRRaw: '',
    qrScanTimer: null
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

function showInfo(selector, message) {
    if (!message) {
        $(selector).addClass('hidden').text('');
        return;
    }
    $(selector).removeClass('hidden').text(message);
}

function setWorkflowStatus(text, toneClass) {
    const el = $('#workflow-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-red-700 text-green-700 text-amber-700');
    el.addClass(toneClass || 'text-sky-700');
}

function setStepState(currentStep) {
    for (let i = 1; i <= 2; i++) {
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
}

function updateTopSummary() {
    $('#summary-invoice').text(packingState.invoiceCode || '-');
    $('#summary-packing').text(`${packingState.statusTotals.packing || 0} / ${packingState.requiredTotal || 0}`);
    $('#summary-scan-count').text(packingState.scanCount || 0);
}

function updateStatusCards() {
    // Picking: từ toàn bộ invoice (command) vì picking gộp mã hàng
    const pickingDone = packingState.pickingDoneProducts || 0;
    const invoiceTotalProducts = packingState.invoiceTotalProducts || 0;

    // Packing: từ case_no này
    const packingDone = packingState.packingDoneProducts || 0;
    const caseNoTotalProducts = packingState.totalProducts || 0;

    // Pickup: từ case_no này
    const pickupTotal = packingState.pickupCaseTotal || 0;

    // Hiển thị theo dashboard.php format
    $('#state-picking-value').text(`${pickingDone} / ${invoiceTotalProducts}`);
    $('#state-packing-value').text(`${packingDone} / ${caseNoTotalProducts}`);
    $('#state-pickup-value').text(`${pickupTotal > 0 ? 'Có' : 'Chưa'}`);

    // Đánh dấu hoàn tất khi đủ
    $('#state-picking').toggleClass('done', invoiceTotalProducts > 0 && pickingDone >= invoiceTotalProducts);
    $('#state-packing').toggleClass('done', caseNoTotalProducts > 0 && packingDone >= caseNoTotalProducts);
    $('#state-pickup').toggleClass('done', pickupTotal > 0);
}

function renderPackingLines() {
    const body = $('#packing-lines-body');
    body.empty();

    if (!packingState.items.length) {
        body.append('<tr><td colspan="4" class="text-center text-slate-500">Chưa có dữ liệu invoice.</td></tr>');
        return;
    }

    packingState.items.forEach(function(item) {
        const rowClass = (item.remain_qty || 0) <= 0 ? 'pda-row-done' : '';
        body.append(`
            <tr class="${rowClass}">
                <td>${item.product_id}</td>
                <td class="num">${item.required_qty}</td>
                <td class="num">${item.packed_qty}</td>
                <td class="num">${item.remain_qty}</td>
            </tr>
        `);
    });
}

function renderPendingItemsStep1() {
    const wrap = $('#invoice-pending-wrap');
    const list = $('#invoice-pending-list');
    const summary = $('#invoice-pending-summary');

    list.empty();

    if (!packingState.invoiceCode || !packingState.items.length) {
        wrap.addClass('hidden');
        summary.text('');
        return;
    }

    const pendingItems = packingState.items.filter(function(item) {
        return (item.remain_qty || 0) > 0;
    });

    if (!pendingItems.length) {
        wrap.removeClass('hidden');
        summary.text('Tất cả mã hàng trong invoice này đã đủ số lượng trên export_log.');
        list.html('<div class="p-2 text-xs text-emerald-700 font-bold">Không còn mã hàng cần hiển thị.</div>');
        return;
    }

    const totalRemain = pendingItems.reduce(function(sum, item) {
        return sum + (parseInt(item.remain_qty || 0, 10) || 0);
    }, 0);

    summary.text(`Còn ${pendingItems.length} mã hàng chưa đủ, tổng còn thiếu: ${totalRemain}.`);

    pendingItems.forEach(function(item) {
        list.append(`
            <div class="pda-pending-item">
                <span class="pda-pending-code">${item.product_id}</span>
                <span class="pda-pending-qty">Còn thiếu: ${item.remain_qty}</span>
            </div>
        `);
    });

    wrap.removeClass('hidden');
}

function parseInvoiceCode(raw) {
    const text = normalizeQrText(raw).replace(/\s+/g, '');

    // Format 1: Full QR [command]$[case_no]$[for_product]$[count distinct product_id]$[transport_type]$[created_at]
    // Chỉ cần lấy command (phần 1) và case_no (phần 2)
    if (text.indexOf('$') !== -1) {
        const parts = text.split('$');
        if (parts.length >= 2) {
            const command = (parts[0] || '').toUpperCase();
            const caseNo = (parts[1] || '').toUpperCase();
            if (/^[A-Z0-9]{6}$/.test(command) && /^[A-Z0-9]{3}$/.test(caseNo)) {
                return {
                    invoiceCode: command + caseNo,
                    command: command,
                    caseNo: caseNo
                };
            }
        }
        return null;
    }

    // Format 2: Short format [command 6][case_no 3] = 9 chars (legacy từ version cũ)
    const matched = text.match(/^([A-Z0-9]{6})([A-Z0-9]{3})$/);
    if (!matched) {
        return null;
    }

    return {
        invoiceCode: text,
        command: matched[1],
        caseNo: matched[2]
    };
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

    return { productId, qty, raw };
}

function applyInvoiceDetail(res) {
    packingState.requiredTotal = parseInt(res.required_total || 0, 10) || 0;
    packingState.statusTotals = {
        picking: parseInt((res.status_totals || {}).picking || 0, 10) || 0,
        packing: parseInt((res.status_totals || {}).packing || 0, 10) || 0,
        pickup: parseInt((res.status_totals || {}).pickup || 0, 10) || 0
    };

    // Thống kê theo dashboard.php format
    // Picking: từ toàn bộ invoice (command) vì picking gộp mã hàng
    packingState.pickingDoneProducts = parseInt(res.picking_done_products || 0, 10) || 0;
    packingState.invoiceTotalProducts = parseInt(res.invoice_total_products || 0, 10) || 0;

    // Packing: từ case_no này
    packingState.packingDoneProducts = parseInt(res.packing_done_products || 0, 10) || 0;
    packingState.totalProducts = parseInt(res.total_products || 0, 10) || 0;

    // Pickup: từ case_no này
    packingState.pickupCaseTotal = parseInt(res.pickup_case_total || 0, 10) || 0;

    packingState.items = (res.items || []).map(function(item) {
        const requiredQty = parseInt(item.required_qty || 0, 10) || 0;
        const packedQty = parseInt(item.packed_qty || 0, 10) || 0;
        return {
            product_id: normalizeQrText(item.product_id),
            required_qty: requiredQty,
            packed_qty: packedQty,
            remain_qty: Math.max(0, requiredQty - packedQty)
        };
    });

    updateTopSummary();
    updateStatusCards();
    renderPackingLines();
    renderPendingItemsStep1();
}

function handleInvoiceScan() {
    if (packingState.busy) return;

    hideError('#step1-error');
    showInfo('#step1-info', '');

    const parsed = parseInvoiceCode($('#invoice-input').val());
    if (!parsed) {
        showError('#step1-error', 'Invoice không hợp lệ. Format: [command 6]$[case_no 3]$... hoặc [command 6][case_no 3]');
        return;
    }

    packingState.busy = true;
    setWorkflowStatus('Đang tải dữ liệu invoice...', 'text-sky-700');

    $.getJSON('api.php?action=get_export_invoice_detail', {
        command: parsed.command,
        case_no: parsed.caseNo
    }, function(res) {
        if (!res.success) {
            showError('#step1-error', res.message || 'Không tìm thấy dữ liệu invoice.');
            setWorkflowStatus('Invoice không tồn tại', 'text-red-700');
            packingState.busy = false;
            return;
        }

        packingState.command = parsed.command;
        packingState.caseNo = parsed.caseNo;
        packingState.invoiceCode = parsed.invoiceCode;
        packingState.scanCount = 0;
        packingState.lastHandledQRRaw = '';

        applyInvoiceDetail(res);

        setStepState(2);
        $('#box-qr-input').prop('disabled', false).val('').focus();
        showInfo('#step1-info', '');
        showInfo('#step2-info', `Invoice ${parsed.invoiceCode} hợp lệ. Bắt đầu quét thùng.`);
        setWorkflowStatus('Sẵn sàng quét thùng liên tục.', 'text-green-700');
        packingState.busy = false;
    }).fail(function() {
        showError('#step1-error', 'Không thể tải dữ liệu invoice.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        packingState.busy = false;
    });
}

function focusBoxInput() {
    if (!packingState.invoiceCode) return;
    $('#box-qr-input').focus();
}

function completePackingJob() {
    if (!packingState.invoiceCode) {
        alert('Cần quét invoice ở bước 1 trước.');
        return;
    }

    if (packingState.requiredTotal <= 0) {
        alert('Không có dữ liệu để hoàn thành.');
        return;
    }

    const packedQty = packingState.statusTotals.packing || 0;
    const requiredQty = packingState.requiredTotal || 0;

    if (packedQty < requiredQty) {
        const shortfall = requiredQty - packedQty;
        alert(`Packing chưa đủ số lượng, vui lòng kiểm tra lại.\n\nCòn thiếu: ${shortfall}/${requiredQty}`);
        setWorkflowStatus('Packing chưa đủ', 'text-amber-700');
        return;
    }

    if (packedQty > requiredQty) {
        alert(`Packing đã vượt số lượng, cần xóa log không hợp lệ.\n\nVượt: ${packedQty - requiredQty}/${requiredQty}`);
        setWorkflowStatus('Packing vượt quá', 'text-amber-700');
        return;
    }

    alert(`Packing hoàn tất, vui lòng chuyển sang bước Pickup.\n\nĐã đóng gói: ${packedQty}/${requiredQty}`);
    setWorkflowStatus('Packing đã hoàn tất', 'text-green-700');
}

function resetPackingJob(clearInvoiceInput) {
    packingState = {
        command: '',
        caseNo: '',
        invoiceCode: '',
        requiredTotal: 0,
        statusTotals: { picking: 0, packing: 0, pickup: 0 },
        items: [],
        scanCount: 0,
        busy: false,
        lastHandledQRRaw: '',
        qrScanTimer: null
    };

    hideError('#step1-error');
    hideError('#step2-error');
    showInfo('#step1-info', '');
    showInfo('#step2-info', '');

    $('#box-qr-input').val('').prop('disabled', true);
    $('#invoice-pending-wrap').addClass('hidden');
    $('#invoice-pending-list').empty();
    $('#invoice-pending-summary').text('');

    setStepState(1);
    setWorkflowStatus('Chờ quét invoice', 'text-sky-700');
    updateTopSummary();
    updateStatusCards();
    renderPackingLines();

    if (clearInvoiceInput) {
        $('#invoice-input').val('').focus();
    }
}

function processBoxScan(parsed, fromScanner) {
    if (packingState.busy) {
        return false;
    }

    if (!packingState.invoiceCode) {
        showError('#step2-error', 'Cần quét invoice ở bước 1 trước.');
        return false;
    }

    if (!parsed) {
        showError('#step2-error', 'QR thùng không đúng định dạng [TEXT]$[MÃ HÀNG]$[TEXT]$[SỐ LƯỢNG]$.');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const line = packingState.items.find(function(item) {
        return item.product_id === parsed.productId;
    });

    if (!line) {
        const message = `Mã hàng ${parsed.productId} không thuộc invoice ${packingState.invoiceCode}.`;
        alert(message);
        showError('#step2-error', message);
        $('#box-qr-input').val('').focus();
        setWorkflowStatus('Sai mã hàng', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    const wouldPack = (line.packed_qty || 0) + parsed.qty;
    const required = line.required_qty || 0;
    if (wouldPack > required) {
        const message = `Mã hàng ${parsed.productId} sẽ vượt số lượng yêu cầu.\nHiện tại: ${line.packed_qty || 0}, thêm: ${parsed.qty}, yêu cầu: ${required}`;
        alert(message);
        showError('#step2-error', message);
        $('#box-qr-input').val('').focus();
        setWorkflowStatus('Vượt quá số lượng', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    packingState.busy = true;
    hideError('#step2-error');
    setWorkflowStatus('Đang ghi log packing...', 'text-sky-700');

    $.post('api.php?action=export_log_scan', {
        command: packingState.command,
        case_no: packingState.caseNo,
        product_id: parsed.productId,
        quantity: parsed.qty,
        status: 'packing'
    }, function(res) {
        if (!res.success) {
            showError('#step2-error', res.message || 'Không thể ghi log packing.');
            setWorkflowStatus('Ghi log thất bại', 'text-red-700');
            packingState.busy = false;
            return;
        }

        packingState.scanCount += 1;

        const targetLine = packingState.items.find(function(item) {
            return item.product_id === parsed.productId;
        });

        if (targetLine) {
            targetLine.packed_qty += parsed.qty;
            targetLine.remain_qty = Math.max(0, targetLine.required_qty - targetLine.packed_qty);
        }

        packingState.statusTotals = {
            picking: parseInt((res.status_totals || {}).picking || 0, 10) || 0,
            packing: parseInt((res.status_totals || {}).packing || 0, 10) || 0,
            pickup: parseInt((res.status_totals || {}).pickup || 0, 10) || 0
        };

        updateTopSummary();
        updateStatusCards();
        renderPackingLines();
        renderPendingItemsStep1();

        const overPacked = targetLine && targetLine.packed_qty > targetLine.required_qty;
        const done = packingState.requiredTotal > 0 && packingState.statusTotals.packing >= packingState.requiredTotal;

        if (overPacked) {
            showInfo('#step2-info', `Đã quét ${parsed.productId} +${parsed.qty}. Đã vượt số lượng yêu cầu của mã hàng này.`);
            setWorkflowStatus('Packing đã vượt số lượng ở một mã hàng', 'text-amber-700');
        } else if (done) {
            showInfo('#step2-info', `Đã quét ${parsed.productId} +${parsed.qty}. Toàn bộ invoice đã pack xong.`);
            setWorkflowStatus('Packing đã hoàn tất invoice', 'text-green-700');
        } else {
            showInfo('#step2-info', `Đã quét ${parsed.productId} +${parsed.qty}.`);
            setWorkflowStatus('Quét tiếp để packing', 'text-green-700');
        }

        $('#box-qr-input').val('').focus();
        packingState.lastHandledQRRaw = '';
        packingState.busy = false;

        if (fromScanner && typeof window.closeQRScannerModal === 'function') {
            window.closeQRScannerModal();
        }
    }, 'json').fail(function() {
        showError('#step2-error', 'Lỗi kết nối khi ghi log packing.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        packingState.busy = false;
    });

    return true;
}

$('#invoice-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handleInvoiceScan();
    }
});

$('#box-qr-input').on('input', function() {
    const raw = normalizeQrText($(this).val());
    if (!raw || raw.indexOf('$') === -1) return;

    clearTimeout(packingState.qrScanTimer);
    packingState.qrScanTimer = setTimeout(function() {
        const finalRaw = normalizeQrText($('#box-qr-input').val());
        if (!finalRaw || finalRaw === packingState.lastHandledQRRaw) return;

        const isLikelyComplete = finalRaw.endsWith('$') || finalRaw.split('$').length >= 7;
        if (!isLikelyComplete) return;

        const parsed = parseBoxQr(finalRaw);
        if (!parsed) return;

        packingState.lastHandledQRRaw = finalRaw;
        processBoxScan(parsed, false);
    }, 110);
});

$('#box-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        const raw = normalizeQrText($('#box-qr-input').val());
        const parsed = parseBoxQr(raw);
        if (raw) {
            packingState.lastHandledQRRaw = raw;
        }
        processBoxScan(parsed, false);
    }
});

window.handleQRScannerScan = function(targetId, scannedValue) {
    const normalizedValue = normalizeQrText(scannedValue);

    if (targetId === 'invoice-input') {
        $('#invoice-input').val(normalizedValue);
        handleInvoiceScan();
        return true;
    }

    if (targetId === 'box-qr-input') {
        $('#box-qr-input').val(normalizedValue);
        packingState.lastHandledQRRaw = normalizedValue;
        return processBoxScan(parseBoxQr(normalizedValue), true);
    }

    return false;
};

$(document).ready(function() {
    resetPackingJob(false);
    $('#invoice-input').focus();
});
</script>
