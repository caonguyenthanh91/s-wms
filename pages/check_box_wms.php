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
            <div class="pda-title">Check Box - Đối chiếu 2 tem</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Sẵn sàng quét</div>
        </div>
        <div class="text-xs text-slate-500 mt-2 check-help-text">
            Có thể quét Tem 1 hoặc Tem 2 trước. Hệ thống tự nhận diện tem và đối chiếu mã hàng, số lượng.
        </div>
    </div>

    <div class="pda-card p-3 check-scan-card">
        <div class="pda-subtitle">Vùng quét</div>
        <div class="text-xs text-slate-500 mt-1">Tự nhận dữ liệu quét liên tục, không cần bấm xác nhận.</div>

        <div class="relative mt-3">
            <input type="text" id="check-scan-input" class="pda-input" placeholder="Quét Tem 1 hoặc Tem 2" autocomplete="off" maxlength="255">
            <button type="button" onclick="openQRScannerModal('check-scan-input', 'Quét Tem Check Box')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <button type="button" class="pda-btn mt-2" onclick="resetScanCycle(true)">Làm mới lượt quét</button>

        <div id="scan-error" class="pda-msg-error mt-2 hidden"></div>

        <div id="result-card" class="hidden mt-2 rounded-lg border p-2 text-center text-xs font-bold"></div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
            <div class="rounded border border-sky-200 bg-sky-50 p-2">
                <div class="text-xs font-bold text-sky-800">Tem 1</div>
                <div class="text-xs text-slate-600 mt-1 check-raw-row">Nội dung: <span id="tem1-raw" class="font-semibold text-slate-800">-</span></div>
                <div class="text-xs text-slate-600 mt-1">Mã hàng: <span id="tem1-product" class="font-semibold text-slate-800">-</span></div>
                <div class="text-xs text-slate-600 mt-1">Số lượng: <span id="tem1-qty" class="font-semibold text-slate-800">-</span></div>
            </div>

            <div class="rounded border border-emerald-200 bg-emerald-50 p-2">
                <div class="text-xs font-bold text-emerald-800">Tem 2</div>
                <div class="text-xs text-slate-600 mt-1 check-raw-row">Nội dung: <span id="tem2-raw" class="font-semibold text-slate-800">-</span></div>
                <div class="text-xs text-slate-600 mt-1">Mã hàng: <span id="tem2-product" class="font-semibold text-slate-800">-</span></div>
                <div class="text-xs text-slate-600 mt-1">Số lượng: <span id="tem2-qty" class="font-semibold text-slate-800">-</span></div>
            </div>
        </div>
    </div>

    <div class="pda-card p-3 mt-3">
        <div class="pda-subtitle">Lịch sử gần nhất</div>
        <div class="pda-table-wrap">
            <table class="w-full pda-table">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Tem 1</th>
                        <th>Tem 2</th>
                        <th>Kết quả</th>
                    </tr>
                </thead>
                <tbody id="check-history-body">
                    <tr><td colspan="4" class="text-center text-slate-500 text-xs">Chưa có dữ liệu</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .check-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(127, 29, 29, 0.82);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .check-modal-content {
        background-color: #b91c1c;
        border-radius: 12px;
        padding: 24px;
        max-width: 500px;
        width: 92%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
        text-align: center;
        color: #ffffff;
    }

    .check-modal-content h2 {
        font-size: 24px;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .check-modal-content p {
        font-size: 16px;
        line-height: 1.5;
        margin-bottom: 18px;
        white-space: pre-wrap;
    }

    .check-modal-btn {
        background-color: #ffffff;
        color: #991b1b;
        font-weight: 700;
        border: none;
        border-radius: 8px;
        padding: 10px 22px;
        cursor: pointer;
    }

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

<div id="check-error-modal" class="check-modal-overlay" style="display:none;">
    <div class="check-modal-content">
        <h2>Không khớp</h2>
        <p id="check-error-modal-message">Không khớp mã hàng và số lượng</p>
        <button class="check-modal-btn" onclick="closeErrorModalAndReset()">OK</button>
    </div>
</div>

<script>
let checkState = {
    tem1: null,
    tem2: null,
    history: [],
    blockedByError: false,
    pendingTem1Lookup: false
};

let checkAutoScanTimer = null;

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
    const match = text.match(/^B\$\$(.+)$/i);
    if (!match || match[1] === undefined) return null;

    const boxCode = normalizeProductId(match[1]);
    if (!boxCode) return null;

    return {
        type: 'tem1',
        raw: text,
        boxCode: boxCode,
        product: null,
        qty: null
    };
}

function parseTem2(raw) {
    const text = normalizeText(raw);
    const parts = text.split('$');
    if (parts.length < 4) return null;

    // Format Tem 2:
    // SMC001$trim([ma hang])$   $int([so luong])$   $   $
    const prefix = normalizeProductId(parts[0]);
    if (prefix !== 'SMC001') return null;

    const product = normalizeProductId(parts[1] || parts[0]);
    const qty = parseQuantity(parts[3]);

    if (!product || qty === null) return null;

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
    const tem1RawText = checkState.tem1
        ? (checkState.tem1.boxCode ? checkState.tem1.raw + ' [' + checkState.tem1.boxCode + ']' : checkState.tem1.raw)
        : '-';
    const tem1ProductText = checkState.tem1
        ? (checkState.tem1.product || (checkState.pendingTem1Lookup ? 'Đang tải...' : '-'))
        : '-';
    const tem1QtyText = checkState.tem1
        ? (checkState.tem1.qty !== null && checkState.tem1.qty !== undefined ? formatQuantity(checkState.tem1.qty) : (checkState.pendingTem1Lookup ? '...' : '-'))
        : '-';

    $('#tem1-raw').text(tem1RawText);
    $('#tem1-product').text(tem1ProductText);
    $('#tem1-qty').text(tem1QtyText);

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

function showErrorModal(message) {
    $('#check-error-modal-message').text(message || 'Không khớp mã hàng và số lượng');
    $('#check-error-modal').css('display', 'flex');
    checkState.blockedByError = true;
}

function closeErrorModalAndReset() {
    $('#check-error-modal').hide();
    checkState.blockedByError = false;
    queueFocusScanInput();
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
    checkState.blockedByError = false;
    checkState.pendingTem1Lookup = false;

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
    if (!checkState.tem1 || !checkState.tem2 || checkState.pendingTem1Lookup) return;

    const productMatch = normalizeProductId(checkState.tem1.product) === normalizeProductId(checkState.tem2.product);
    const qtyMatch = Number(checkState.tem1.qty) === Number(checkState.tem2.qty);

    if (productMatch && qtyMatch) {
        const message = 'OK';
        showResultCard('ok', message);
        setWorkflowStatus('Khớp mã hàng và số lượng', 'text-green-700');
        pushHistoryRow(message);
        logCheckResult('ok', message, true, true);
        return;
    }

    if (productMatch && !qtyMatch) {
        const message = 'Số lượng 2 tem lần lượt là ' + formatQuantity(checkState.tem1.qty) + ' và ' + formatQuantity(checkState.tem2.qty);
        showResultCard('warn', message);
        setWorkflowStatus('Khớp mã hàng, lệch số lượng', 'text-amber-700');
        pushHistoryRow(message);
        logCheckResult('qty_mismatch', message, true, false);
        return;
    }

    const modalMessage = 'Không khớp mã hàng và số lượng';
    setWorkflowStatus('Không khớp mã hàng', 'text-red-700');
    pushHistoryRow(modalMessage);
    logCheckResult('product_mismatch', modalMessage, false, qtyMatch);
    showErrorModal(modalMessage);
}

function resolveTem1Inventory(parsedTem1) {
    checkState.pendingTem1Lookup = true;
    updateCaptureView();
    setWorkflowStatus('Đang tra mã thùng Tem 1', 'text-amber-700');

    $.getJSON('api.php?action=get_check_box_tem1_inventory', { box_code: parsedTem1.boxCode })
        .done(function(res) {
            checkState.pendingTem1Lookup = false;

            if (!res || !res.success) {
                checkState.tem1 = null;
                showScanError((res && res.message) ? res.message : 'Không tra được dữ liệu Tem 1 từ wms_inventory');
                setWorkflowStatus('Tem 1 không hợp lệ', 'text-red-700');
                updateCaptureView();
                queueFocusScanInput();
                return;
            }

            checkState.tem1 = {
                type: 'tem1',
                raw: parsedTem1.raw,
                boxCode: parsedTem1.boxCode,
                product: normalizeProductId(res.product_id),
                qty: parseQuantity(res.quantity)
            };

            updateCaptureView();

            if (checkState.tem2) {
                evaluateCurrentPair();
            } else {
                setWorkflowStatus('Đã nhận Tem 1, quét Tem 2', 'text-sky-700');
                queueFocusScanInput();
            }
        })
        .fail(function() {
            checkState.pendingTem1Lookup = false;
            checkState.tem1 = null;
            showScanError('Không kết nối được API tra mã thùng Tem 1');
            setWorkflowStatus('Lỗi tra cứu Tem 1', 'text-red-700');
            updateCaptureView();
            queueFocusScanInput();
        });
}

function handleScanSubmit(scannedRaw) {
    if (checkState.blockedByError || $('#check-error-modal').css('display') !== 'none') {
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
        showScanError('Không nhận diện được tem. Vui lòng kiểm tra đúng định dạng Tem 1 hoặc Tem 2.');
        setWorkflowStatus('Tem không hợp lệ', 'text-red-700');
        $('#check-scan-input').val('');
        queueFocusScanInput();
        return;
    }

    if (checkState.pendingTem1Lookup && parsed.type !== 'tem2') {
        showScanError('Đang tra dữ liệu Tem 1, vui lòng quét Tem 2 hoặc chờ trong giây lát');
        $('#check-scan-input').val('');
        queueFocusScanInput();
        return;
    }

    if (parsed.type === 'tem1') {
        checkState.tem1 = parsed;
        $('#check-scan-input').val('');
        updateCaptureView();
        resolveTem1Inventory(parsed);
        return;
    } else {
        checkState.tem2 = parsed;
        setWorkflowStatus('Đã nhận Tem 2, quét Tem 1', 'text-sky-700');
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
        if (checkState.blockedByError || $('#check-error-modal').css('display') !== 'none') {
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
    $('#check-scan-input').val(scannedValue);
    handleScanSubmit(scannedValue);
    return true;
};

$('#check-scan-input').on('input', function() {
    scheduleAutoScanSubmit();
});

$('#check-scan-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handleScanSubmit($('#check-scan-input').val());
    }
});

$(document).ready(function() {
    renderHistory();
    queueFocusScanInput();

    $(document).on('keydown', function(e) {
        if ((e.key === 'Escape' || e.key === 'Enter') && $('#check-error-modal').css('display') !== 'none') {
            closeErrorModalAndReset();
        }
    });
});
</script>
