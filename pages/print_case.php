<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<div class="container-main">
    <div class="panel">
        <h3><i class="fas fa-list"></i> In tem packing</h3>
        <div class="form-group import-box">
            <label>Import dữ liệu từ Excel:</label>
            <div class="import-note"><a href="../s-wms/assets/packing_list.xlsx">Tải file Excel mẫu tại đây.</a></div>
            <div class="import-actions">
                <input type="file" id="export-file" class="form-control" accept=".xlsx,.csv">
                <button type="button" class="btn btn-primary import-btn" id="import-export-btn" onclick="importExportTemp()">
                    <i class="fas fa-file-import"></i> Import Excel
                </button>
            </div>
            <div id="import-result" class="import-result"></div>
        </div>

        <div class="form-group">
            <label>Chọn ngày:</label>
            <div class="flight-filter-row" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input id="print-date" class="flight-date-input" type="date" style="flex: 1; min-width: 120px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="button" class="flight-btn" data-day-offset="-1" style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">Hôm qua</button>
                <button type="button" class="flight-btn active" data-day-offset="0" style="padding: 6px 12px; background: #007bff; color: white; border: 1px solid #0056b3; border-radius: 4px; cursor: pointer;">Hôm nay</button>
                <button type="button" class="flight-btn" data-day-offset="1" style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;">Ngày mai</button>
            </div>
        </div>

        <div class="form-group">
            <label>Danh sách Invoice:</label>
            <div id="invoices-list" style="border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9f9f9;">
                <div class="text-muted" style="padding: 12px;">Chọn ngày để xem danh sách invoice</div>
            </div>
        </div>

        <div class="form-group">
            <label>Danh sách kiện (Case) của Invoice:</label>
            <div id="cases-list" style="border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9f9f9;">
                <div class="text-muted" style="padding: 12px;">Chọn một invoice để xem danh sách kiện</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <h3><i class="fas fa-print"></i> Xem trước tem packing (115x80mm)</h3>

        <div class="pages-container" id="pages-preview">
            <div class="preview-placeholder">
                Chọn một invoice để xem trước tem packing
            </div>
        </div>

        <button onclick="printTickets()" class="btn btn-print" id="print-btn">
            <i class="fas fa-print"></i> In Tem Packing (115x80mm)
        </button>

        <div class="mt-3 text-sm text-gray-600" id="print-options-wrap">
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-test-mode">
                Chế độ test (không in thật)
            </label>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-split-mode" checked>
                Chế độ cho máy in nhiệt (tự cắt từng phiếu, nhấn xác nhận mỗi phiếu)
            </label>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-single-mode">
                Chế độ in gộp 1 phiếu dài (không tự cắt phiếu, không phải nhấn xác nhận)
            </label>
            <label style="display:block; margin-bottom:6px;">
                Vị trí QR Code (lên/xuống): 
                <input type="range" id="qr-vertical-offset" min="-15" max="15" value="0" step="1" style="width: 150px; vertical-align: middle;">
                <span id="qr-offset-display" style="margin-left: 8px; font-weight: bold;">0</span> mm
            </label>
            <div id="print-mode-note" class="text-xs text-gray-500"></div>
        </div>
    </div>
</div>

<div id="print-pages"></div>

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
    let currentCommand = '';
    let currentDate = '';
    let currentCases = [];
    let selectedCases = [];
    const printApiBase = 'api.php';

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

    function pad2(v) {
        return String(v).padStart(2, '0');
    }

    function toYmd(dateObj) {
        return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
    }

    function setDateByOffset(dayOffset) {
        const dt = new Date();
        dt.setHours(0, 0, 0, 0);
        dt.setDate(dt.getDate() + dayOffset);
        const dateStr = toYmd(dt);
        $('#print-date').val(dateStr);
        currentDate = dateStr;
        loadInvoicesByDate(dateStr);
    }

    function markActiveQuickButton(offset) {
        $('[data-day-offset]').removeClass('active');
        $('[data-day-offset="' + offset + '"]').addClass('active');
    }

    function loadInvoicesByDate(dateStr) {
        currentDate = dateStr;
        const invoicesList = $('#invoices-list');
        invoicesList.html('<div class="text-muted">Đang tải...</div>');

        $.ajax({
            type: 'GET',
            url: `${printApiBase}?action=get_invoices_by_date`,
            data: { date: dateStr },
            dataType: 'json',
            success: function(res) {
                if (!res.success || !res.invoices || res.invoices.length === 0) {
                    invoicesList.html('<div class="text-muted">Không có invoice nào trong ngày này</div>');
                    return;
                }

                let html = '';
                res.invoices.forEach(function(invoice) {
                    html += `
                        <button type="button" class="invoice-item-btn" style="display: block; width: 100%; text-align: left; padding: 10px; border: none; background: #f9f9f9; border-bottom: 1px solid #ddd; cursor: pointer;" data-command="${escapeHtml(invoice.command)}">
                            <div style="font-weight: bold;">${escapeHtml(invoice.command)}</div>
                            <small>${invoice.item_count} mã hàng | ${invoice.case_count} kiện</small>
                        </button>
                    `;
                });
                invoicesList.html(html);
            },
            error: function() {
                invoicesList.html('<div class="text-muted">Lỗi kết nối</div>');
            }
        });
    }

    function loadCasesByInvoice(command) {
        currentCommand = command;
        const casesList = $('#cases-list');
        casesList.html('<div class="text-muted">Đang tải...</div>');

        $.ajax({
            type: 'GET',
            url: `${printApiBase}?action=get_cases_by_invoice`,
            data: { command: command },
            dataType: 'json',
            success: function(res) {
                if (!res.success || !res.cases || res.cases.length === 0) {
                    casesList.html('<div class="text-muted">Không có kiện nào</div>');
                    currentCases = [];
                    selectedCases = [];
                    resetPreview();
                    return;
                }

                currentCases = res.cases || [];
                selectedCases = currentCases.slice();

                let html = '';
                currentCases.forEach(function(caseItem) {
                    html += `
                        <div style="padding: 10px; border-bottom: 1px solid #ddd; background: white; margin: 0; cursor: default;">
                            <div><strong>Kiện: ${escapeHtml(caseItem.case_no)}</strong> - ${caseItem.item_count} mã hàng</div>
                            <small>FOR: ${escapeHtml(caseItem.for_product || '-')} | VT: ${escapeHtml(caseItem.transport_type || '-')}</small>
                        </div>
                    `;
                });
                casesList.html(html);
                generatePrintPreview();
            },
            error: function() {
                casesList.html('<div class="text-muted">Lỗi kết nối</div>');
            }
        });
    }

    function resetPreview() {
        $('#pages-preview').html('<div class="preview-placeholder">Chọn một invoice để xem trước tem packing</div>');
        $('#print-pages').empty();
        $('#print-btn').hide();
    }


    async function importExportTemp() {
        const input = document.getElementById('export-file');
        const file = input?.files?.[0];
        if (!file) {
            showModal('Vui lòng chọn file Excel để import', 'error');
            return;
        }

        const button = $('#import-export-btn');
        const result = $('#import-result');
        const formData = new FormData();
        formData.append('excel_file', file);

        button.prop('disabled', true).text('Đang import...');
        result.removeClass('error success').text('Đang tải và xử lý file...');

        try {
            const res = await $.ajax({
                type: 'POST',
                url: `${printApiBase}?action=import_export_temp`,
                data: formData,
                dataType: 'json',
                processData: false,
                contentType: false
            });

            if (!res.success) {
                const errors = Array.isArray(res.errors) && res.errors.length
                    ? ` ${res.errors.slice(0, 3).join(' | ')}`
                    : '';
                throw new Error((res.message || 'Import thất bại') + errors);
            }

            const warningText = res.warning_count
                ? ` Có ${res.warning_count} dòng bỏ qua.`
                : '';
            result.removeClass('error').addClass('success').text(`Import thành công ${res.imported_count} dòng.${warningText}`);
            input.value = '';
            resetPreview();
            loadInvoicesByDate(currentDate);
        } catch (error) {
            result.removeClass('success').addClass('error').text(error.message || 'Import thất bại');
        } finally {
            button.prop('disabled', false).html('<i class="fas fa-file-import"></i> Import Excel');
        }
    }

    function syncPrintModeToggles(changedId) {
        const split = document.getElementById('print-split-mode');
        const single = document.getElementById('print-single-mode');
        const note = document.getElementById('print-mode-note');

        if (!split || !single || !note) return;

        if (changedId === 'print-split-mode' && split.checked) {
            single.checked = false;
        }
        if (changedId === 'print-single-mode' && single.checked) {
            split.checked = false;
        }

        if (!split.checked && !single.checked) {
            split.checked = true;
        }

        const modeText = split.checked
            ? 'Đang chọn: In tách từng phiếu.'
            : 'Đang chọn: In gộp 1 lần.';
        note.textContent = modeText;

        localStorage.setItem('print_test_mode', document.getElementById('print-test-mode')?.checked ? '1' : '0');
        localStorage.setItem('print_split_mode', split.checked ? '1' : '0');
        localStorage.setItem('print_single_mode', single.checked ? '1' : '0');
    }

    function initPrintOptions() {
        const test = document.getElementById('print-test-mode');
        const split = document.getElementById('print-split-mode');
        const single = document.getElementById('print-single-mode');

        if (!test || !split || !single) return;

        test.checked = localStorage.getItem('print_test_mode') === '1';
        split.checked = localStorage.getItem('print_split_mode') !== '0';
        single.checked = localStorage.getItem('print_single_mode') === '1';

        if (split.checked && single.checked) {
            single.checked = false;
        }
        if (!split.checked && !single.checked) {
            split.checked = true;
        }

        test.addEventListener('change', function() {
            syncPrintModeToggles('print-test-mode');
        });
        split.addEventListener('change', function() {
            syncPrintModeToggles('print-split-mode');
        });
        single.addEventListener('change', function() {
            syncPrintModeToggles('print-single-mode');
        });

        syncPrintModeToggles('init');
        initQROffset();
    }

    function initQROffset() {
        const qrInput = document.getElementById('qr-vertical-offset');
        const display = document.getElementById('qr-offset-display');
        
        if (!qrInput || !display) return;
        
        const savedOffset = localStorage.getItem('qr_vertical_offset') || '0';
        qrInput.value = savedOffset;
        display.textContent = savedOffset;
        
        qrInput.addEventListener('change', function() {
            localStorage.setItem('qr_vertical_offset', this.value);
            display.textContent = this.value;
            generatePrintPreview();
        });
        
        qrInput.addEventListener('input', function() {
            display.textContent = this.value;
        });
    }


    function generatePrintPreview() {
        const preview = $('#pages-preview');
        const printPages = $('#print-pages');

        if (!selectedCases || selectedCases.length === 0) {
            resetPreview();
            return;
        }

        let previewHtml = '';
        let printHtml = '';
        const totalCases = selectedCases.length;
        const qrVerticalOffset = localStorage.getItem('qr_vertical_offset') || '0';

        selectedCases.forEach((caseItem, idx) => {
            const command = currentCommand || '-';
            const caseNo = caseItem.case_no || '-';
            const forProduct = caseItem.for_product || '-';
            const transportType = caseItem.transport_type || '-';
            const itemCount = caseItem.item_count || 0;
            const createdAt = caseItem.created_at ? new Date(String(caseItem.created_at).replace(' ', 'T')).toLocaleDateString('vi-VN') : '-';

            const qrContent = `${command}$${caseNo}$${forProduct}$${itemCount}$${transportType}$${caseItem.created_at || ''}`;
            const qrDataUrl = generateQRCodeDataUrl(qrContent);

            const ticketHtml = `
                <div class="packing-label" style="width: 115mm; height: 80mm; margin: 0; padding: 0; display: flex; flex-direction: column; border: 1px solid #000; font-family: Arial, sans-serif; box-sizing: border-box;">

                    <!-- Top 55mm Section -->
                    <div style="height: 55mm; display: flex; gap: 2mm; padding: 2mm; box-sizing: border-box; border-bottom: 1px dashed #999;">
                        <!-- Left: QR Code 40x40mm -->
                        <div style="width: 40mm; height: 40mm; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: ${qrVerticalOffset}mm;">
                            ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">` : '<div style="width: 100%; height: 100%; border: 2px solid #000; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;">QR</div>'}
                        </div>

                        <!-- Right: Product Info -->
                        <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 2mm; padding: 2mm; box-sizing: border-box; justify-items: start; align-content: space-between; font-size: 11px; padding: 2mm 0;">
                            <!-- Invoice Label and Case Label -->
                            <div style="font-weight: bold; font-size: 9px; color: #666; text-align: left;">INVOICE<br>CASE:</div>
                            <div style="font-size: 24px; font-weight: bold; text-align: center; width: 100%;">${escapeHtml(command)}${escapeHtml(caseNo)}</div>

                            <!-- Customer Label and Transport Type Label -->
                            <div style="font-weight: bold; font-size: 9px; color: #666; text-align: left;">CUSTOMER<br>TRANSPORT TYPE:</div>
                            <div style="font-size: 20px; text-align: center; width: 100%;">${escapeHtml(forProduct)} / ${escapeHtml(transportType)}</div>

                            <!-- Item Count Label -->
                            <div style="font-weight: bold; font-size: 9px; color: #666; text-align: left;">NUM OF ITEM:</div>
                            <!-- Item Count Value -->
                            <div style="font-size: 20px; text-align: center; width: 100%;">${itemCount} items</div>

                            <!-- Created At Label -->
                            <div style="font-weight: bold; font-size: 9px; color: #666; text-align: left;">PICKING DATE:</div>
                            <!-- Created At Value -->
                            <div style="font-size: 20px; text-align: center; width: 100%;">${caseItem.created_at}</div>
                        </div>
                    </div>

                    <!-- Bottom 25mm Section -->
                    <div style="height: 25mm; display: flex; align-items: flex-end; padding: 2mm; box-sizing: border-box; background: linear-gradient(to bottom, #fff, #f9f9f9);">
                        <!-- Left: Print Time (small text) -->
                        <div style="font-size: 8px; color: #666; flex-shrink: 0;">
                            In: ${new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: '2-digit' })} ${pad2(new Date().getHours())}:${pad2(new Date().getMinutes())}
                        </div>

                        <!-- Right: Space for signature/notes -->
                        <div style="flex: 1; border-left: 1px dashed #ccc; margin-left: 3mm; padding-left: 3mm; font-size: 9px; color: #999;">
                            Sign / Note
                        </div>
                    </div>
                </div>
            `;

            previewHtml += ticketHtml;
            printHtml += ticketHtml;
        });

        preview.html(previewHtml || '<div class="preview-placeholder">Không có dữ liệu</div>');
        printPages.html(printHtml);

        if (printHtml) {
            $('#print-btn').show();
        } else {
            $('#print-btn').hide();
        }
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
                width: 140,
                height: 140,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
            });

            const canvas = holder.querySelector('canvas');
            const img = holder.querySelector('img');
            const dataUrl = canvas ? canvas.toDataURL('image/png') : (img ? img.src : '');

            document.body.removeChild(holder);
            return dataUrl;
        } catch (err) {
            console.log('QR generate error', err);
            return '';
        }
    }

    function logPrintCase() {
        if (!currentCommand) return;

        $.ajax({
            type: 'POST',
            url: `${printApiBase}?action=insert_export_log_print_case`,
            data: { command: currentCommand },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    console.log('Logged print_case action');
                }
            },
            error: function() {
                console.log('Error logging print_case');
            }
        });
    }

    function printTickets() {
        const isTestMode = document.getElementById('print-test-mode')?.checked;
        const splitMode = document.getElementById('print-split-mode')?.checked;
        const tickets = Array.from(document.querySelectorAll('#print-pages .packing-label'));
        if (!tickets.length) {
            return;
        }

        if (isTestMode) {
            showModal(`Đã bỏ qua lệnh in thật.\n\nSố tem: ${tickets.length}`, 'warning', 'Chế độ test');
            logPrintCase();
            return;
        }

        logPrintCase();

        if (!splitMode) {
            window.print();
            return;
        }

        const cssHref = new URL('assets/css/customs.css', window.location.href).href;
        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        let index = 0;

        const printNextTicket = function() {
            if (index >= tickets.length) {
                document.body.removeChild(iframe);
                return;
            }

            const ticketHtml = tickets[index].outerHTML;
            const doc = iframe.contentWindow.document;

            let progressed = false;
            const moveNext = function() {
                if (progressed) {
                    return;
                }
                progressed = true;
                index += 1;
                setTimeout(printNextTicket, 150);
            };

            iframe.onload = function() {
                try {
                    const win = iframe.contentWindow;
                    win.onafterprint = moveNext;
                    win.focus();
                    win.print();
                    setTimeout(moveNext, 2000);
                } catch (err) {
                    console.log('Print error', err);
                    moveNext();
                }
            };

            doc.open();
            doc.write(`<!doctype html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                    <title>Print label ${index + 1}</title>
                    <style>
                        body { margin: 0; padding: 0; background: #fff; }
                        .packing-label { margin: 0; }
                        @page { size: 115mm 80mm; margin: 0; }
                    </style>
                </head>
                <body>${ticketHtml}</body>
                </html>`);
            doc.close();
        };

        printNextTicket();
    }

    $(document).ready(function() {
        initPrintOptions();
        setDateByOffset(0);
        markActiveQuickButton(0);

        $('[data-day-offset]').on('click', function() {
            const offset = parseInt($(this).attr('data-day-offset') || '0', 10) || 0;
            setDateByOffset(offset);
            markActiveQuickButton(offset);
        });

        $('#print-date').on('change', function() {
            const dateStr = $(this).val();
            if (dateStr) {
                currentDate = dateStr;
                loadInvoicesByDate(dateStr);
                $('[data-day-offset]').removeClass('active');
            }
        });

        $(document).on('click', '.invoice-item-btn', function() {
            const command = $(this).attr('data-command');
            loadCasesByInvoice(command);
        });

        $('input[id="print-split-mode"], input[id="print-single-mode"], input[id="print-test-mode"]').on('change', function() {
            syncPrintModeToggles($(this).attr('id'));
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
