<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<style>
    .pallet-print-wrap {
        max-width: 1280px;
        margin: 0 auto;
    }

    .pallet-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .pallet-card {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 18px;
    }

    .pallet-card h3 {
        margin: 0 0 14px;
        color: #1e293b;
        font-size: 18px;
        font-weight: 700;
    }

    .pallet-label-hint {
        margin-bottom: 10px;
        font-size: 13px;
        color: #334155;
    }

    .pallet-form-label {
        display: block;
        margin-bottom: 8px;
        color: #334155;
        font-weight: 600;
        font-size: 13px;
    }

    #pallet-input {
        width: 100%;
        min-height: 210px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 12px;
        font-size: 14px;
        line-height: 1.5;
        resize: vertical;
        outline: none;
    }

    #pallet-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
    }

    .pallet-form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .pallet-btn {
        border: 0;
        border-radius: 6px;
        padding: 9px 14px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }

    .pallet-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .pallet-btn-primary {
        background: #2563eb;
        color: #ffffff;
    }

    .pallet-btn-secondary {
        background: #e2e8f0;
        color: #0f172a;
    }

    .pallet-btn-print {
        background: #16a34a;
        color: #ffffff;
        width: 100%;
        margin-top: 14px;
    }

    .pallet-alert {
        margin-top: 12px;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 13px;
        display: none;
    }

    .pallet-alert.show {
        display: block;
    }

    .pallet-alert.ok {
        background: #ecfdf3;
        border: 1px solid #86efac;
        color: #166534;
    }

    .pallet-alert.warn {
        background: #fff7ed;
        border: 1px solid #fdba74;
        color: #9a3412;
    }

    .pallet-result-list {
        margin-top: 12px;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }

    .pallet-result-item {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
        padding: 8px 10px;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
    }

    .pallet-result-item:last-child {
        border-bottom: 0;
    }

    .pallet-result-code {
        color: #0f172a;
        font-weight: 600;
        word-break: break-all;
    }

    .pallet-result-badge {
        border-radius: 999px;
        padding: 2px 8px;
        font-weight: 700;
        font-size: 11px;
        white-space: nowrap;
    }

    .pallet-result-badge.ok {
        background: #dcfce7;
        color: #166534;
    }

    .pallet-result-badge.error {
        background: #fee2e2;
        color: #b91c1c;
    }

    .preview-stage {
        min-height: 360px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 12px;
        background: #f8fafc;
        overflow: auto;
    }

    .preview-empty {
        text-align: center;
        color: #64748b;
        font-size: 14px;
        padding: 40px 10px;
    }

    .label-preview-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .pallet-label {
        width: 65mm;
        height: 30mm;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 2mm;
        box-sizing: border-box;
        display: grid;
        grid-template-columns: 20mm 1fr;
        align-items: center;
        padding: 2mm;
        column-gap: 2mm;
    }

    .pallet-label-qr {
        width: 20mm;
        height: 20mm;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 0.2mm solid #e5e7eb;
    }

    .pallet-label-qr img {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: contain;
    }

    .pallet-label-text {
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        overflow: hidden;
        color: #000000;
        font-weight: 700;
        letter-spacing: 0.01em;
        line-height: 1.06;
    }

    .pallet-label-line {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: clip;
    }

    #print-sheet {
        display: none;
    }

    @media (max-width: 980px) {
        .pallet-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #print-sheet,
        #print-sheet * {
            visibility: visible;
        }

        #print-sheet {
            display: block;
            position: fixed;
            inset: 0;
            margin: 0;
            padding: 0;
        }

        .print-item {
            page-break-after: always;
            width: 65mm;
            height: 30mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .print-item:last-child {
            page-break-after: auto;
        }

        .print-item .pallet-label {
            border: none;
            border-radius: 0;
            width: 65mm;
            height: 30mm;
            margin: 0;
        }

        @page {
            size: 65mm 30mm;
            margin: 0;
        }
    }
</style>

<div class="pallet-print-wrap">
    <div class="pallet-grid">
        <section class="pallet-card">
            <h3><i class="fas fa-tags"></i> In tem pallet 65x30 mm</h3>
            <div class="pallet-label-hint">
                Nhập mỗi dòng là 1 nội dung tem. Hệ thống kiểm tra trùng với cột <b>pallet_id</b> trong bảng <b>import_temp</b> trước khi cho in.
            </div>

            <label class="pallet-form-label" for="pallet-input">Danh sách nội dung tem (mỗi dòng 1 tem):</label>
            <textarea id="pallet-input" placeholder="Ví dụ:\nPALLET-001-A\nPALLET-001-B\nABC-123-XYZ"></textarea>

            <div class="pallet-form-actions">
                <button class="pallet-btn pallet-btn-primary" id="btn-check" type="button">
                    <i class="fas fa-check-circle"></i> Kiểm tra trùng và tạo tem
                </button>
                <button class="pallet-btn pallet-btn-secondary" id="btn-clear" type="button">
                    <i class="fas fa-eraser"></i> Xóa dữ liệu
                </button>
            </div>

            <div id="validation-msg" class="pallet-alert"></div>
            <div id="validation-list" class="pallet-result-list" style="display:none;"></div>
        </section>

        <section class="pallet-card">
            <h3><i class="fas fa-print"></i> Xem trước tem</h3>
            <div class="preview-stage" id="preview-stage">
                <div class="preview-empty">Chưa có dữ liệu tem để xem trước.</div>
            </div>

            <button class="pallet-btn pallet-btn-print" id="btn-print" type="button" style="display:none;">
                <i class="fas fa-print"></i> In tem 65x30 mm
            </button>
        </section>
    </div>
</div>

<div id="print-sheet"></div>

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
    const printApiBase = 'api.php';
    let currentLabels = [];

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

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(character) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return map[character] || character;
        });
    }

    function normalizePalletId(raw) {
        return String(raw || '').trim().toUpperCase();
    }

    function splitTextByDelimiter(text) {
        const parts = String(text || '').split('-').map(function(part) {
            return part.trim();
        }).filter(function(part) {
            return part.length > 0;
        });

        return parts.length ? parts : [String(text || '').trim()];
    }

    function calcFontSizePx(linesCount) {
        if (linesCount <= 1) return 27;
        if (linesCount === 2) return 20;
        if (linesCount === 3) return 15;
        if (linesCount === 4) return 12;
        if (linesCount === 5) return 10;
        return 9;
    }

    function generateQRCodeDataUrl(text) {
        try {
            const holder = document.createElement('div');
            holder.style.position = 'absolute';
            holder.style.left = '-9999px';
            holder.style.top = '-9999px';
            document.body.appendChild(holder);

            new QRCode(holder, {
                text: String(text || ''),
                width: 240,
                height: 240,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });

            const canvas = holder.querySelector('canvas');
            const img = holder.querySelector('img');
            const dataUrl = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');
            document.body.removeChild(holder);
            return dataUrl;
        } catch (error) {
            console.log('QR generation error', error);
            return '';
        }
    }

    function renderOneLabel(label) {
        const lines = label.lines || [label.palletId];
        const lineHtml = lines.map(function(line) {
            return '<span class="pallet-label-line">' + escapeHtml(line) + '</span>';
        }).join('');

        return `
            <div class="pallet-label">
                <div class="pallet-label-qr">
                    ${label.qrDataUrl ? `<img src="${label.qrDataUrl}" alt="QR ${escapeHtml(label.palletId)}">` : ''}
                </div>
                <div class="pallet-label-text" style="font-size:${label.fontSizePx}px;">
                    ${lineHtml}
                </div>
            </div>
        `;
    }

    function renderValidationList(items) {
        const box = $('#validation-list');
        if (!items.length) {
            box.hide().empty();
            return;
        }

        const html = items.map(function(item) {
            const isOk = !item.hasError;
            const badgeClass = isOk ? 'ok' : 'error';
            const badgeText = isOk ? 'Hợp lệ' : 'Trùng/Không hợp lệ';
            return `
                <div class="pallet-result-item">
                    <div class="pallet-result-code">${escapeHtml(item.palletId)}${item.message ? ` - ${escapeHtml(item.message)}` : ''}</div>
                    <span class="pallet-result-badge ${badgeClass}">${badgeText}</span>
                </div>
            `;
        }).join('');

        box.html(html).show();
    }

    function setValidationMessage(type, message) {
        const box = $('#validation-msg');
        box.removeClass('ok warn show').addClass(type === 'ok' ? 'ok' : 'warn').text(message).addClass('show');
    }

    function clearValidationMessage() {
        $('#validation-msg').removeClass('ok warn show').text('');
    }

    function renderPreview(labels) {
        const stage = $('#preview-stage');
        const printSheet = $('#print-sheet');

        if (!labels.length) {
            stage.html('<div class="preview-empty">Không có tem hợp lệ để xem trước.</div>');
            printSheet.empty();
            $('#btn-print').hide();
            return;
        }

        const previewHtml = labels.map(function(label) {
            return renderOneLabel(label);
        }).join('');

        const printHtml = labels.map(function(label) {
            return `<div class="print-item">${renderOneLabel(label)}</div>`;
        }).join('');

        stage.html(`<div class="label-preview-grid">${previewHtml}</div>`);
        printSheet.html(printHtml);
        $('#btn-print').show();
    }

    async function checkPalletUniqueOnServer(palletId) {
        const response = await $.ajax({
            type: 'POST',
            url: `${printApiBase}?action=check_pallet_unique`,
            data: { pallet_id: palletId },
            dataType: 'json'
        });

        if (response && response.success === true) {
            return { unique: true, message: '' };
        }

        return {
            unique: false,
            message: response && response.message ? response.message : 'Mã pallet đã tồn tại'
        };
    }

    async function buildLabelsWithValidation(rawLines) {
        const normalized = rawLines.map(function(line) {
            return normalizePalletId(line);
        }).filter(function(line) {
            return line.length > 0;
        });

        if (!normalized.length) {
            return {
                labels: [],
                validationRows: [],
                hasAnyError: true,
                message: 'Vui lòng nhập ít nhất 1 nội dung tem.'
            };
        }

        const countMap = {};
        normalized.forEach(function(id) {
            countMap[id] = (countMap[id] || 0) + 1;
        });

        const uniqueIds = Array.from(new Set(normalized));
        const serverChecks = await Promise.all(uniqueIds.map(async function(id) {
            if (countMap[id] > 1) {
                return { palletId: id, unique: false, message: 'Trùng trong danh sách vừa nhập' };
            }
            try {
                const result = await checkPalletUniqueOnServer(id);
                return { palletId: id, unique: !!result.unique, message: result.message || '' };
            } catch (error) {
                return { palletId: id, unique: false, message: 'Không kiểm tra được với server' };
            }
        }));

        const checkMap = {};
        serverChecks.forEach(function(item) {
            checkMap[item.palletId] = item;
        });

        const validationRows = [];
        const labels = [];

        uniqueIds.forEach(function(id) {
            const check = checkMap[id];
            const hasError = !check || !check.unique;
            const message = hasError ? (check ? check.message : 'Dữ liệu không hợp lệ') : '';

            validationRows.push({
                palletId: id,
                hasError: hasError,
                message: message
            });

            if (!hasError) {
                const lines = splitTextByDelimiter(id);
                labels.push({
                    palletId: id,
                    lines: lines,
                    qrDataUrl: generateQRCodeDataUrl(id),
                    fontSizePx: calcFontSizePx(lines.length)
                });
            }
        });

        const hasAnyError = validationRows.some(function(item) {
            return item.hasError;
        });

        return {
            labels: labels,
            validationRows: validationRows,
            hasAnyError: hasAnyError,
            message: hasAnyError
                ? 'Có pallet bị trùng/không hợp lệ. Chỉ các tem hợp lệ mới được tạo để in.'
                : `Tất cả ${labels.length} tem đều hợp lệ và sẵn sàng in.`
        };
    }

    async function handleCheckAndBuild() {
        const input = document.getElementById('pallet-input');
        const btn = $('#btn-check');
        const lines = String(input.value || '').split(/\r?\n/);

        btn.prop('disabled', true).text('Đang kiểm tra...');
        clearValidationMessage();
        $('#validation-list').hide().empty();

        try {
            const result = await buildLabelsWithValidation(lines);
            currentLabels = result.labels;

            renderValidationList(result.validationRows);
            setValidationMessage(result.hasAnyError ? 'warn' : 'ok', result.message);
            renderPreview(currentLabels);
        } finally {
            btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Kiểm tra trùng và tạo tem');
        }
    }

    function clearAll() {
        currentLabels = [];
        $('#pallet-input').val('');
        clearValidationMessage();
        $('#validation-list').hide().empty();
        renderPreview([]);
    }

    function printLabels() {
        if (!currentLabels.length) {
            showModal('Không có tem hợp lệ để in.', 'error');
            return;
        }
        window.print();
    }

    $(document).ready(function() {
        renderPreview([]);
        $('#btn-check').on('click', handleCheckAndBuild);
        $('#btn-clear').on('click', clearAll);
        $('#btn-print').on('click', printLabels);

        $('#pallet-input').on('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                handleCheckAndBuild();
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
