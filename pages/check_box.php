<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'], true)) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="pda-pickup-wrap pb-4">
    <div class="pda-card p-3 mb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title">Check Box</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Sẵn sàng quét</div>
        </div>
        <!-- <div class="text-xs text-slate-500 mt-2 check-help-text">
            Có thể quét Tem 1 hoặc Tem 2 trước. Hệ thống tự nhận diện tem và đối chiếu mã hàng.
        </div> -->
    </div>

    <div class="pda-card p-3 check-scan-card">
        <div class="pda-subtitle">Vùng quét</div>
        <!-- <div class="text-xs text-slate-500 mt-1">Tự nhận dữ liệu quét liên tục, không cần bấm xác nhận.</div> -->

        <div class="relative mt-3">
            <input type="text" id="check-scan-input" class="pda-input" placeholder="Quét Tem 1 hoặc Tem 2" autocomplete="off" maxlength="255">
            <button type="button" onclick="openQRScannerModal('check-scan-input', 'Quét Tem Check Box')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        

        <div id="scan-error" class="pda-msg-error mt-2 hidden"></div>

        <div id="result-card" class="hidden mt-2 rounded-lg border p-2 text-center text-xs font-bold"></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
            <div class="rounded border border-sky-200 bg-sky-50 p-2">
                <div class="text-xs font-bold text-sky-800">Tem định danh</div>
                <!-- <div class="text-xs text-slate-600 mt-1 check-raw-row">Nội dung: <span id="tem1-raw" class="font-semibold text-slate-800">-</span></div> -->
                <div class="text-xs text-slate-600 mt-1">Mã hàng: <span id="tem1-product" class="font-semibold text-slate-800">-</span></div>
                <!-- <div class="text-xs text-slate-600 mt-1">Số lượng: <span id="tem1-qty" class="font-semibold text-slate-800">-</span></div> -->
            </div>

            <div class="rounded border border-emerald-200 bg-emerald-50 p-2">
                <div class="text-xs font-bold text-emerald-800">Tem SATO</div>
                <!-- <div class="text-xs text-slate-600 mt-1 check-raw-row">Nội dung: <span id="tem2-raw" class="font-semibold text-slate-800">-</span></div> -->
                <div class="text-xs text-slate-600 mt-1">Mã hàng: <span id="tem2-product" class="font-semibold text-slate-800">-</span></div>
                <!-- <div class="text-xs text-slate-600 mt-1">Số lượng: <span id="tem2-qty" class="font-semibold text-slate-800">-</span></div> -->
            </div>
        </div>
        <button type="button" class="pda-btn mt-2" onclick="resetScanCycle(true)">Làm mới lượt quét</button>
    </div>

    <div class="pda-card p-3 mt-3">
        <div class="pda-subtitle">Lịch sử gần nhất</div>
        <div class="pda-table-wrap">
            <table class="w-full pda-table">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Tem Đ.danh</th>
                        <th>Tem SATO</th>
                        <th>Kết quả</th>
                    </tr>
                </thead>
                <tbody id="check-history-body">
                    <tr><td colspan="4" class="text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>
</div>
</div>

<style>
    /* Styles for PDA Check Box page */
    .check-result-ok {
        border-color: #16a34a;
        background: #dcfce7;
        color: #166534;
    }

    .check-result-warn {
        border-color: #f59e0b;
        background: #fef3c7;
        color: #92400e;
    }

    .check-raw-row {
        line-height: 1.15;
        max-height: 2.3em;
        overflow: hidden;
        word-break: break-all;
    }

    @media (max-width: 480px) {
        .pda-pickup-wrap {
            padding-bottom: 0.5rem;
        }

        .pda-pickup-wrap .pda-card {
            padding: 0.6rem !important;
            margin-bottom: 0.5rem !important;
        }

        .check-help-text {
            display: none;
        }

        .check-scan-card .pda-subtitle {
            font-size: 11px;
            margin-bottom: 0;
        }

        #check-scan-input {
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
            font-size: 12px;
        }

        #result-card {
            margin-top: 0.35rem;
            padding: 0.4rem;
        }

        #check-history-body td,
        .pda-table th,
        .pda-table td {
            font-size: 10px;
        }
    }
</style>

<!-- Check Box Result - Fullscreen Overlay Card -->
<div id="check-result-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); z-index: 10100; padding: 20px; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; padding: 40px 30px; text-align: center; max-width: 520px; width: 95%; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);">
        <h1 id="check-result-status-title" style="font-size: 48px; margin: 0 0 16px 0; font-weight: bold;">✓</h1>
        <h2 id="check-result-status-message" style="font-size: 28px; margin: 0 0 24px 0; font-weight: bold; color: #059669;">Khớp mã hàng</h2>
        <div id="check-result-details" style="font-size: 16px; color: #475569; margin-bottom: 24px; line-height: 1.8; white-space: pre-wrap;">Thông tin chi tiết</div>
        <button onclick="closeCheckResultOverlay()" style="background: #059669; color: white; padding: 16px 40px; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; width: 100%; min-height: 50px;">OK - Tiếp tục</button>
    </div>
</div>

<script>
let checkState = {
    tem1: null,
    tem2: null,
    history: [],
    blockedByModal: false
};

let checkAutoScanTimer = null;
let checkLastScannerValue = '';
let checkLastScannerAt = 0;

function normalizeText(text) {
    return (text || '')
        .replace(/＄/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
}

function normalizeProductId(value) {
    return (value || '').replace(/\s+/g, ' ').trim().toUpperCase();
}

function parseInteger(value) {
    const cleaned = String(value || '').replace(/[^0-9\-]/g, '');
    if (cleaned === '' || cleaned === '-') return null;
    const n = parseInt(cleaned, 10);
    return Number.isNaN(n) ? null : n;
}

function parseQuantity(value) {
    const cleaned = String(value || '')
        .replace(/,/g, '.')
        .replace(/\s+/g, '')
        .match(/-?\d+(?:\.\d+)?/);

    if (!cleaned) return null;

    const n = parseFloat(cleaned[0]);
    if (Number.isNaN(n)) return null;

    return Math.abs(n - Math.round(n)) < 0.000001 ? Math.round(n) : n;
}

function formatQuantity(value) {
    if (value === null || value === undefined || value === '') return '-';
    const numeric = Number(value);
    if (Number.isNaN(numeric)) return String(value);
    return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(5).replace(/0+$/, '').replace(/\.$/, '');
}

function parseTem1(raw) {
    const text = normalizeText(raw);
    // Format Tem 1: B$$[TEXT]$$[ma hang]
    const match = text.match(/^B\$\$([^$]*)\$\$([^$]+)$/i);
    if (!match) return null;

    const product = normalizeProductId(match[2]);

    // Tem 1 khong co du lieu so luong, mac dinh bang 0.
    const qty = 0;

    if (!product) return null;

    return {
        type: 'tem1',
        raw: text,
        product: product,
        qty: qty
    };
}

function parseTem2(raw) {
    const text = normalizeText(raw);
    const parts = text.split('$');
    if (parts.length < 2) return null;

    // Format Tem 2:
    // SMC001$trim([ma hang])$   $int([so luong])$   $   $
    const prefix = normalizeProductId(parts[0]);
    if (prefix !== 'SMC001') return null;

    const product = normalizeProductId(parts[1] || parts[0]);
    const qty = 0;

    if (!product) return null;

    return {
        type: 'tem2',
        raw: text,
        product: product,
        qty: qty
    };
}

function detectAndParse(raw) {
    const text = normalizeText(raw);
    if (!text) return null;

    if (text.indexOf('$$') !== -1) {
        return parseTem1(text);
    }

    return parseTem2(text);
}

function setWorkflowStatus(text, colorClass) {
    const el = $('#workflow-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-green-700 text-amber-700 text-red-700');
    el.addClass(colorClass || 'text-sky-700');
}

function showScanError(msg) {
    $('#scan-error').removeClass('hidden').text(msg || 'Có lỗi xảy ra');
}

function hideScanError() {
    $('#scan-error').addClass('hidden').text('');
}

function updateCaptureView() {
    $('#tem1-raw').text(checkState.tem1 ? checkState.tem1.raw : '-');
    $('#tem1-product').text(checkState.tem1 ? checkState.tem1.product : '-');
    $('#tem1-qty').text(checkState.tem1 ? formatQuantity(checkState.tem1.qty) : '-');

    $('#tem2-raw').text(checkState.tem2 ? checkState.tem2.raw : '-');
    $('#tem2-product').text(checkState.tem2 ? checkState.tem2.product : '-');
    $('#tem2-qty').text(checkState.tem2 ? formatQuantity(checkState.tem2.qty) : '-');
}

function showResultCard(mode, message) {
    const card = $('#result-card');
    card.removeClass('hidden check-result-ok check-result-warn');

    if (mode === 'ok') {
        card.addClass('check-result-ok');
    } else if (mode === 'warn') {
        card.addClass('check-result-warn');
    }

    card.text(message || '');
}

function hideResultCard() {
    $('#result-card').addClass('hidden').removeClass('check-result-ok check-result-warn').text('');
}

function showCheckResultOverlay(message, isMatch = false) {
    const overlay = $('#check-result-overlay');
    const title = isMatch ? '✓' : '✗';
    const statusMsg = isMatch ? 'Khớp mã hàng' : 'Không khớp mã hàng';
    const bgColor = isMatch ? '#059669' : '#dc2626';

    $('#check-result-status-title').text(title).css({
        'color': bgColor,
        'font-size': '60px'
    });
    
    $('#check-result-status-message').text(statusMsg).css({
        'color': bgColor,
        'font-size': '32px'
    });
    
    $('#check-result-details').text(message || statusMsg).css({
        'color': '#475569',
        'font-size': '16px'
    });

    overlay.find('button').css({
        'background-color': bgColor,
        'font-size': '18px',
        'padding': '16px 40px'
    });

    // Set display: flex and ensure flexbox properties are applied
    overlay.css({
        'display': 'flex',
        'align-items': 'center',
        'justify-content': 'center'
    });
    checkState.blockedByModal = true;

    // Store result in sessionStorage for reload persistence
    sessionStorage.setItem('checkBoxResult', JSON.stringify({
        message: message,
        isMatch: isMatch,
        timestamp: Date.now()
    }));

    // Fallback: If overlay still not visible after 500ms, reload page
    setTimeout(function() {
        if (overlay.css('display') === 'none') {
            console.warn('Overlay failed to display, reloading page...');
            location.reload();
        }
    }, 500);
}

function closeCheckResultOverlay() {
    // Simply hide overlay and reset state, no reload needed
    $('#check-result-overlay').css('display', 'none');
    checkState.blockedByModal = false;
    resetScanCycle(false);
    
    // Clear sessionStorage to prevent stale data
    sessionStorage.removeItem('checkBoxResult');
    sessionStorage.removeItem('checkBoxResultProcessed');
}

function showCheckResultModal(message, type = 'error', title = null) {
    // Redirect to new overlay system
    const isMatch = (type === 'success');
    showCheckResultOverlay(message, isMatch);
}

function closeCheckResultModal() {
    closeCheckResultOverlay();
}

function queueFocusScanInput() {
    setTimeout(function() {
        const input = $('#check-scan-input');
        input.focus();
        input.select();
    }, 80);
}

function resetScanCycle(showNotice) {
    checkState.tem1 = null;
    checkState.tem2 = null;
    checkState.blockedByModal = false;

    hideScanError();
    hideResultCard();
    updateCaptureView();

    $('#check-scan-input').val('');

    if (showNotice) {
        setWorkflowStatus('Đã làm mới lượt quét', 'text-sky-700');
    } else {
        setWorkflowStatus('Sẵn sàng quét', 'text-sky-700');
    }

    queueFocusScanInput();
}

function pushHistoryRow(resultText) {
    const now = new Date();
    const time = now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0') + ' ' +
        String(now.getHours()).padStart(2, '0') + ':' +
        String(now.getMinutes()).padStart(2, '0') + ':' +
        String(now.getSeconds()).padStart(2, '0');

    checkState.history.unshift({
        scanned_at: time,
        tem1: checkState.tem1 ? checkState.tem1.raw : '-',
        tem2: checkState.tem2 ? checkState.tem2.raw : '-',
        result: resultText
    });

    if (checkState.history.length > 20) {
        checkState.history = checkState.history.slice(0, 20);
    }

    renderHistory();
}

function renderHistory() {
    const body = $('#check-history-body');
    if (!checkState.history.length) {
        body.html('<tr><td colspan="4" class="text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>');
        return;
    }

    let html = '';
    checkState.history.forEach(function(item) {
        html += '<tr>' +
            '<td class="text-xs">' + item.scanned_at + '</td>' +
            '<td class="text-xs">' + item.tem1 + '</td>' +
            '<td class="text-xs">' + item.tem2 + '</td>' +
            '<td class="text-xs font-bold">' + item.result + '</td>' +
            '</tr>';
    });

    body.html(html);
}

function logCheckResult(resultCode, resultMessage, productMatch, qtyMatch) {
    if (!checkState.tem1 || !checkState.tem2) return;

    $.post('api.php?action=check_box_log', {
        type: 'check box',
        tem1_raw: checkState.tem1.raw,
        tem2_raw: checkState.tem2.raw,
        tem1_product: checkState.tem1.product,
        tem1_qty: checkState.tem1.qty,
        tem2_product: checkState.tem2.product,
        tem2_qty: checkState.tem2.qty,
        is_product_match: productMatch ? 1 : 0,
        is_qty_match: qtyMatch ? 1 : 0,
        result_code: resultCode,
        result_message: resultMessage
    }, function() {}, 'json');
}

function evaluateCurrentPair() {
    if (!checkState.tem1 || !checkState.tem2) return;

    const productMatch = normalizeProductId(checkState.tem1.product) === normalizeProductId(checkState.tem2.product);

    if (productMatch) {
        const message = `Tem Định danh: ${checkState.tem1.product}\nTem SATO: ${checkState.tem2.product}`;
        setWorkflowStatus('Khớp mã hàng ✓', 'text-green-500');
        pushHistoryRow('OK');
        logCheckResult('ok', 'Khớp mã hàng', true, true);
        hideResultCard();
        showCheckResultOverlay(message, true);
        return;
    }

    const detailMsg = `Tem Định danh: ${checkState.tem1.product}\nTem SATO: ${checkState.tem2.product}`;
    setWorkflowStatus('Không khớp mã hàng ✗', 'text-red-500');
    pushHistoryRow('NG');
    logCheckResult('product_mismatch', 'Không khớp mã hàng', false, true);
    hideResultCard();
    showCheckResultOverlay(detailMsg, false);
}

function handleScanSubmit(scannedRaw) {
    if (checkState.blockedByModal || $('#check-result-overlay').css('display') !== 'none') {
        return;
    }

    hideScanError();

    const raw = normalizeText(scannedRaw !== undefined ? scannedRaw : $('#check-scan-input').val());
    if (!raw) {
        queueFocusScanInput();
        return;
    }

    if (checkState.tem1 && checkState.tem2) {
        resetScanCycle(false);
    }

    const parsed = detectAndParse(raw);
    if (!parsed) {
        const invalidMsg = 'Không nhận diện được tem. Vui lòng kiểm tra đúng định dạng Tem Định danh hoặc Tem SATO.';
        showScanError(invalidMsg);
        setWorkflowStatus('Tem không hợp lệ', 'text-red-500');
        hideResultCard();
        showCheckResultOverlay(invalidMsg, false);
        $('#check-scan-input').val('');
        return;
    }

    if (parsed.type === 'tem1') {
        checkState.tem1 = parsed;
        setWorkflowStatus('Đã nhận Tem ĐD, quét Tem SATO', 'text-sky-700');
    } else {
        checkState.tem2 = parsed;
        setWorkflowStatus('Đã nhận Tem SATO, quét Tem ĐD', 'text-sky-700');
    }

    $('#check-scan-input').val('');
    updateCaptureView();

    if (checkState.tem1 && checkState.tem2) {
        evaluateCurrentPair();
    } else {
        queueFocusScanInput();
    }
}

function scheduleAutoScanSubmit() {
    if (checkAutoScanTimer) {
        clearTimeout(checkAutoScanTimer);
    }

    checkAutoScanTimer = setTimeout(function() {
        if (checkState.blockedByModal || $('#check-result-overlay').css('display') !== 'none') {
            return;
        }

        const raw = $('#check-scan-input').val();
        if (!raw || !raw.trim()) {
            return;
        }

        handleScanSubmit(raw);
    }, 120);
}

window.handleQRScannerScan = function(targetId, scannedValue) {
    if (targetId !== 'check-scan-input') return false;

    const normalizedValue = normalizeText(scannedValue);
    const now = Date.now();

    // Chan scan lap nhanh tren PDA camera (cung 1 ma trong thoi gian rat ngan).
    if (normalizedValue && normalizedValue === checkLastScannerValue && (now - checkLastScannerAt) < 800) {
        return true;
    }

    checkLastScannerValue = normalizedValue;
    checkLastScannerAt = now;

    if (typeof closeQRScannerModal === 'function') {
        closeQRScannerModal();
    }

    $('#check-scan-input').val(normalizedValue);
    setTimeout(function() {
        handleScanSubmit(normalizedValue);
    }, 0);

    return true;
};

$('#check-scan-input').on('input', function() {
    scheduleAutoScanSubmit();
});

$('#check-scan-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        e.stopPropagation();
        handleScanSubmit($('#check-scan-input').val());
    }
});

$(document).ready(function() {
    renderHistory();
    queueFocusScanInput();

    // Restore overlay from sessionStorage only if not already processed
    const savedResult = sessionStorage.getItem('checkBoxResult');
    const isAlreadyProcessed = sessionStorage.getItem('checkBoxResultProcessed');
    
    if (savedResult && !isAlreadyProcessed) {
        try {
            const result = JSON.parse(savedResult);
            // Only show if within last 5 seconds (prevent stale data)
            if (Date.now() - result.timestamp < 5000) {
                // Mark as processed to prevent re-display on reload
                sessionStorage.setItem('checkBoxResultProcessed', 'true');
                
                setTimeout(function() {
                    showCheckResultOverlay(result.message, result.isMatch);
                }, 100);
            }
        } catch (e) {
            console.error('Failed to restore result from sessionStorage', e);
        }
    }

    $(document).on('keydown', function(e) {
        // Ignore if event is from input field (already handled there)
        if (e.target.id === 'check-scan-input') {
            return;
        }
        
        if ((e.key === 'Escape' || e.key === 'Enter') && $('#check-result-overlay').css('display') !== 'none') {
            e.preventDefault();
            e.stopPropagation();
            closeCheckResultOverlay();
        }
    });
});
</script>
