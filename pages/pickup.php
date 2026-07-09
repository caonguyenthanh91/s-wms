<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<style>
    .pda-pickup-wrap {
        max-width: 540px;
        margin: 0 auto;
    }

    .pda-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .pda-title {
        font-size: 1.1rem;
        font-weight: 900;
        letter-spacing: 0.01em;
        color: #0f172a;
    }

    .pda-subtitle {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
    }

    .pda-input {
        width: 100%;
        border: 2px solid #cbd5e1;
        border-radius: 12px;
        padding: 12px 44px 12px 14px;
        font-size: 1.05rem;
        font-weight: 800;
        text-align: center;
        text-transform: uppercase;
        outline: none;
        background: #fff;
    }

    .pda-input:focus {
        border-color: #0ea5e9;
    }

    .pda-btn {
        width: 100%;
        border: 0;
        border-radius: 12px;
        padding: 12px;
        font-size: 1rem;
        font-weight: 800;
        transition: all 0.18s ease;
    }

    .pda-btn-primary {
        background: #0284c7;
        color: #fff;
    }

    .pda-btn-primary:hover {
        background: #0369a1;
    }

    .pda-btn-muted {
        background: #e2e8f0;
        color: #0f172a;
    }

    .pda-kpi-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
    }

    .pda-kpi {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        padding: 8px;
        text-align: center;
    }

    .pda-kpi-label {
        font-size: 0.62rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .pda-kpi-value {
        margin-top: 4px;
        font-size: 0.9rem;
        font-weight: 900;
        color: #0f172a;
        word-break: break-all;
    }

    .pda-msg-error {
        color: #b91c1c;
        font-size: 0.86rem;
        font-weight: 700;
    }

    .pda-msg-info {
        color: #0369a1;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .pda-table-wrap {
        margin-top: 10px;
        max-height: 330px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }

    .pda-table th,
    .pda-table td {
        padding: 7px 8px;
        font-size: 0.8rem;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
        text-align: left;
    }

    .pda-table th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        z-index: 1;
    }

    .pda-status-ok {
        font-weight: 900;
        color: #166534;
    }

    .pda-status-wait {
        font-weight: 900;
        color: #b45309;
    }

    .pda-row-ok {
        background: #f0fdf4;
    }

    @media (max-width: 900px) {
        .pda-kpi-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 768px) {
        .pda-pickup-wrap {
            max-width: 780px;
        }
    }
</style>

<div class="pda-pickup-wrap pb-4">
    <div class="pda-card p-3 mb-3">
        <div class="flex items-center justify-between gap-2">
            <div class="pda-title">Pickup Case PDA</div>
            <div id="workflow-status" class="text-xs font-bold text-sky-700">Cho quet QR case</div>
        </div>

        <div class="pda-kpi-row mt-3">
            <div class="pda-kpi">
                <div class="pda-kpi-label">Command</div>
                <div id="summary-command" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Case vừa quét</div>
                <div id="summary-case" class="pda-kpi-value">-</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">OK / Tổng case</div>
                <div id="summary-ok" class="pda-kpi-value">0 / 0</div>
            </div>
            <div class="pda-kpi">
                <div class="pda-kpi-label">Lượt quét</div>
                <div id="summary-scan-count" class="pda-kpi-value">0</div>
            </div>
        </div>
    </div>

    <div class="pda-card p-3">
        <div class="pda-subtitle">Quét Pickup</div>
        <div class="pda-title mt-1">Quét QR [command6][case3]</div>
        <div class="text-xs text-slate-500 mt-1">Ví dụ: ABCDEF001. Khi case_no đúng, hệ thống ghi log status=pickup, quantity=1 cho tất cả dòng khớp command + case_no.</div>

        <div class="relative mt-3">
            <input type="text" id="pickup-qr-input" class="pda-input" placeholder="Quét QR pickup" maxlength="32">
            <button type="button" onclick="openQRScannerModal('pickup-qr-input', 'QR Pickup Case')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600">
                <i class="fas fa-qrcode text-lg"></i>
            </button>
        </div>

        <div class="mt-3 flex gap-2">
            <button type="button" class="pda-btn pda-btn-primary" onclick="handlePickupScanFromInput()">Xác nhận QR</button>
            <button type="button" class="pda-btn pda-btn-muted" onclick="resetPickupState(true)">Đổi command</button>
        </div>
    
        <div id="pickup-error" class="pda-msg-error mt-2 hidden"></div>
        <div id="pickup-info" class="pda-msg-info mt-2 hidden"></div>

        <div class="mt-3">
            <div class="text-xs font-bold text-slate-600 uppercase tracking-wide">Danh sách case theo command</div>
            <div class="pda-table-wrap">
                <table class="w-full pda-table">
                    <thead>
                        <tr>
                            <th>Case No</th>
                            <th>Trạng thái</th>
                            <th>Số lượt pickup</th>
                        </tr>
                    </thead>
                    <tbody id="pickup-case-list"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let pickupState = {
    command: '',
    lastCaseNo: '',
    totalCases: 0,
    okCases: 0,
    waitCases: 0,
    scanCount: 0,
    cases: [],
    busy: false,
    qrScanTimer: null,
    lastHandledQRRaw: ''
};

function normalizeQrText(text) {
    return (text || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim()
        .toUpperCase();
}

function parsePickupCode(rawValue) {
    const text = normalizeQrText(rawValue).replace(/\s+/g, '');
    const matched = text.match(/^([A-Z0-9]{6})([A-Z0-9]{3})$/);
    if (!matched) return null;
    return {
        command: matched[1],
        caseNo: matched[2],
        fullCode: text
    };
}

function showError(message) {
    $('#pickup-error').removeClass('hidden').text(message || 'Có lỗi xảy ra.');
}

function hideError() {
    $('#pickup-error').addClass('hidden').text('');
}

function showInfo(message) {
    if (!message) {
        $('#pickup-info').addClass('hidden').text('');
        return;
    }
    $('#pickup-info').removeClass('hidden').text(message);
}

function setWorkflowStatus(text, toneClass) {
    const el = $('#workflow-status');
    el.text(text || '');
    el.removeClass('text-sky-700 text-red-700 text-green-700 text-amber-700');
    el.addClass(toneClass || 'text-sky-700');
}

function updateSummary() {
    $('#summary-command').text(pickupState.command || '-');
    $('#summary-case').text(pickupState.lastCaseNo || '-');
    $('#summary-ok').text(`${pickupState.okCases || 0} / ${pickupState.totalCases || 0}`);
    $('#summary-scan-count').text(pickupState.scanCount || 0);
}

function applyCaseListFromResponse(res) {
    pickupState.command = normalizeQrText(res.command || pickupState.command);
    pickupState.cases = (res.cases || []).map(function(row) {
        return {
            case_no: normalizeQrText(row.case_no),
            pickup_logs: parseInt(row.pickup_logs || 0, 10) || 0,
            is_ok: !!row.is_ok
        };
    }).sort(function(a, b) {
        return a.case_no.localeCompare(b.case_no, undefined, { numeric: true });
    });

    pickupState.totalCases = parseInt(res.total_cases || pickupState.cases.length || 0, 10) || 0;
    pickupState.okCases = parseInt(res.ok_cases || 0, 10) || 0;
    pickupState.waitCases = parseInt(res.wait_cases || Math.max(0, pickupState.totalCases - pickupState.okCases), 10) || 0;

    renderCaseList();
    updateSummary();
}

function renderCaseList() {
    const body = $('#pickup-case-list');
    body.empty();

    if (!pickupState.command || !pickupState.cases.length) {
        body.append('<tr><td colspan="3" class="text-center text-slate-500">Chưa có dữ liệu command.</td></tr>');
        return;
    }

    pickupState.cases.forEach(function(item) {
        const statusText = item.is_ok ? 'OK' : 'WAIT';
        const statusClass = item.is_ok ? 'pda-status-ok' : 'pda-status-wait';
        const rowClass = item.is_ok ? 'pda-row-ok' : '';

        body.append(`
            <tr class="${rowClass}">
                <td>${item.case_no}</td>
                <td class="${statusClass}">${statusText}</td>
                <td>${item.pickup_logs}</td>
            </tr>
        `);
    });
}

function resetPickupState(clearInput) {
    pickupState = {
        command: '',
        lastCaseNo: '',
        totalCases: 0,
        okCases: 0,
        waitCases: 0,
        scanCount: 0,
        cases: [],
        busy: false,
        qrScanTimer: null,
        lastHandledQRRaw: ''
    };

    hideError();
    showInfo('');
    setWorkflowStatus('Cho quét QR case', 'text-sky-700');
    updateSummary();
    renderCaseList();

    if (clearInput) {
        $('#pickup-qr-input').val('').focus();
    }
}

function loadCommandCases(command, onDone) {
    $.getJSON('api.php?action=get_pickup_cases_by_command', { command: command }, function(res) {
        if (!res.success) {
            showError(res.message || 'Không tải được danh sách case theo command.');
            setWorkflowStatus('Không tìm thấy command', 'text-red-700');
            if (typeof onDone === 'function') onDone(false);
            return;
        }

        applyCaseListFromResponse(res);
        setWorkflowStatus(`Đã nạp danh sách case cho command ${command}.`, 'text-sky-700');
        if (typeof onDone === 'function') onDone(true);
    }).fail(function() {
        showError('Lỗi kết nối khi tải danh sách case.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        if (typeof onDone === 'function') onDone(false);
    });
}

function submitPickupCase(parsed, fromScanner) {
    const currentCase = pickupState.cases.find(function(item) {
        return item.case_no === parsed.caseNo;
    });

    if (!currentCase) {
        const message = `Case ${parsed.caseNo} không thuộc command ${pickupState.command}.`;
        showError(message);
        setWorkflowStatus('Sai case_no', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return;
    }

    const wasOk = currentCase.is_ok;

    $.post('api.php?action=pickup_scan_case', {
        command: pickupState.command,
        case_no: parsed.caseNo
    }, function(res) {
        if (!res.success) {
            showError(res.message || 'Không thể ghi nhận pickup.');
            setWorkflowStatus('Ghi nhận thất bại', 'text-red-700');
            pickupState.busy = false;
            return;
        }

        pickupState.scanCount += 1;
        pickupState.lastCaseNo = parsed.caseNo;
        applyCaseListFromResponse(res);

        if (wasOk) {
            showInfo(`Case ${parsed.caseNo} đã OK từ trước, tiếp tục ghi nhận lượt quét.`);
            setWorkflowStatus('Case đã OK (quét lặp vẫn hợp lệ)', 'text-amber-700');
        } else {
            showInfo(`Case ${parsed.caseNo} đã chuyển trạng thái OK.`);
            setWorkflowStatus('Ghi nhận pickup thành công', 'text-green-700');
        }

        $('#pickup-qr-input').val('').focus();
        pickupState.lastHandledQRRaw = '';
        pickupState.busy = false;

        if (fromScanner && typeof window.closeQRScannerModal === 'function') {
            window.closeQRScannerModal();
        }
    }, 'json').fail(function() {
        showError('Lỗi kết nối khi ghi nhận pickup.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-700');
        pickupState.busy = false;
    });
}

function processPickupScan(parsed, fromScanner) {
    if (pickupState.busy) return false;

    if (!parsed) {
        showError('QR không hợp lệ. Cần dùng [command6][case3], ví dụ ABCDEF001.');
        setWorkflowStatus('Sai định dạng QR', 'text-red-700');
        if (fromScanner && typeof window.resetQRScannerModalState === 'function') {
            window.resetQRScannerModalState();
        }
        return false;
    }

    hideError();
    pickupState.busy = true;
    setWorkflowStatus('Đang xử lý QR pickup...', 'text-sky-700');

    const continueAfterLoad = function(ok) {
        if (!ok) {
            pickupState.busy = false;
            return;
        }
        submitPickupCase(parsed, fromScanner);
    };

    if (!pickupState.command || pickupState.command !== parsed.command) {
        pickupState.command = parsed.command;
        loadCommandCases(parsed.command, continueAfterLoad);
    } else {
        continueAfterLoad(true);
    }

    return true;
}

function handlePickupScanFromInput() {
    const parsed = parsePickupCode($('#pickup-qr-input').val());
    return processPickupScan(parsed, false);
}

$('#pickup-qr-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        handlePickupScanFromInput();
    }
});

$('#pickup-qr-input').on('input', function() {
    const raw = normalizeQrText($(this).val()).replace(/\s+/g, '');
    if (!raw || raw.length < 9) return;

    clearTimeout(pickupState.qrScanTimer);
    pickupState.qrScanTimer = setTimeout(function() {
        const finalRaw = normalizeQrText($('#pickup-qr-input').val()).replace(/\s+/g, '');
        if (!finalRaw || finalRaw === pickupState.lastHandledQRRaw) return;

        const parsed = parsePickupCode(finalRaw);
        if (!parsed) return;

        pickupState.lastHandledQRRaw = finalRaw;
        processPickupScan(parsed, false);
    }, 100);
});

window.handleQRScannerScan = function(targetId, scannedValue) {
    const normalized = normalizeQrText(scannedValue).replace(/\s+/g, '');
    if (targetId !== 'pickup-qr-input') return false;

    $('#pickup-qr-input').val(normalized);
    pickupState.lastHandledQRRaw = normalized;
    return processPickupScan(parsePickupCode(normalized), true);
};

$(document).ready(function() {
    resetPickupState(false);
    $('#pickup-qr-input').focus();
});
</script>
