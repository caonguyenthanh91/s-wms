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
        <h3><i class="fas fa-list"></i> In phiếu picking</h3>
        <div class="form-group import-box">
            <label>Import dữ liệu từ Excel:</label>
            <div class="import-note"><a href="../s-wms/assets/packing_list.xlsx">Tải file Excel mẫu tại đây.</a></div>
            <div class="import-actions">
                <input type="file" id="export-file" class="form-control" accept=".xlsx,.csv">
                <button type="button" class="btn btn-primary import-btn" id="import-export-btn" onclick="importExportTemp()">
                    <i class="fas fa-file-import"></i> Import Excel
                </button>
            </div>
            <!-- <label class="inline-checkbox">
                <input type="checkbox" id="clear-existing-export" checked>
                Xóa dữ liệu export_temp cũ trước khi import
            </label> -->
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
            <label>Danh sách mã hàng của Invoice:</label>
            <div id="invoice-items-list" style="border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; background: #f9f9f9;">
                <div class="text-muted" style="padding: 12px;">Chọn một invoice để xem danh sách mã hàng</div>
            </div>
        </div>

        <div id="warning-container" class="warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="warning-text"></span>
        </div>

        <div id="items-container" class="items-scroll"></div>
    </div>

    <div class="panel">
        <h3><i class="fas fa-print"></i> Xem trước phiếu picking</h3>

        <div class="pages-container" id="pages-preview">
            <div class="preview-placeholder">
                Chọn một chỉ thị để xem trước phiếu in
            </div>
        </div>

        <button onclick="printTickets()" class="btn btn-print" id="print-btn">
            <i class="fas fa-print"></i> In Phiếu Picking (80mm)
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
            <div id="print-mode-note" class="text-xs text-gray-500"></div>
        </div>
    </div>
</div>

<div id="print-pages"></div>

<script>
    let currentCommand = '';
    let currentDate = '';
    let currentItems = [];
    let selectedItems = [];
    let shelvesData = {};
    const printApiBase = 'api.php';

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

    function loadInvoiceItems(command) {
        currentCommand = command;
        const itemsList = $('#invoice-items-list');
        itemsList.html('<div class="text-muted">Đang tải...</div>');

        $.ajax({
            type: 'GET',
            url: `${printApiBase}?action=get_invoice_items_by_date`,
            data: { command: command, date: currentDate },
            dataType: 'json',
            success: function(res) {
                if (!res.success || !res.items || res.items.length === 0) {
                    itemsList.html('<div class="text-muted">Không có mã hàng nào</div>');
                    currentItems = [];
                    selectedItems = [];
                    resetPreview();
                    return;
                }

                currentItems = res.items || [];
                selectedItems = currentItems.map(function(item) {
                    return {
                        product_id: item.product_id,
                        product_name: item.product_name,
                        unit: item.unit,
                        for_product: item.for_product,
                        total_qty: item.total_qty,
                        created_at: item.created_at
                    };
                });

                let html = '';
                currentItems.forEach(function(item, idx) {
                    html += `
                        <div style="padding: 10px; border-bottom: 1px solid #ddd; background: white; margin: 0; cursor: default;">
                            <div><strong>${escapeHtml(item.product_id)}</strong> - ${escapeHtml(item.product_name || 'N/A')} (${escapeHtml(item.unit || 'pcs')})</div>
                            <small>Số lượng: ${item.total_qty} | FOR: ${escapeHtml(item.for_product || '-')}</small>
                        </div>
                    `;
                });
                itemsList.html(html);
                generatePrintPreview();
            },
            error: function() {
                itemsList.html('<div class="text-muted">Lỗi kết nối</div>');
            }
        });
    }

    function resetPreview() {
        $('#pages-preview').html('<div class="preview-placeholder">Chọn một invoice để xem trước phiếu in</div>');
        $('#print-pages').empty();
        $('#print-btn').hide();
    }

    async function importExportTemp() {
        const input = document.getElementById('export-file');
        const file = input?.files?.[0];
        if (!file) {
            alert('Vui lòng chọn file Excel để import');
            return;
        }

        const button = $('#import-export-btn');
        const result = $('#import-result');
        const formData = new FormData();
        formData.append('excel_file', file);
        formData.append('clear_existing', $('#clear-existing-export').is(':checked') ? '1' : '0');

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
    }


    function generatePrintPreview() {
        const preview = $('#pages-preview');
        const printPages = $('#print-pages');

        if (!selectedItems || selectedItems.length === 0) {
            resetPreview();
            return;
        }

        let previewHtml = '';
        let printHtml = '';
        let pageNum = 0;

        selectedItems.forEach((item, idx) => {
            pageNum++;
            const qrDataUrl = generateQRCodeDataUrl(currentCommand + '$' + item.product_id + '$' + item.total_qty);
            const createdAt = item.created_at ? new Date(String(item.created_at).replace(' ', 'T')).toLocaleDateString('vi-VN') : '-';
            const totalPages = selectedItems.length;

            const ticketHtml = `
                <div class="picking-ticket" style="width: 80mm; padding: 4mm;">
                    <!-- Row 1: Date/Time | PHIẾU PICKING | Page No -->
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 9px; margin-bottom: 3mm; border-bottom: 1px solid #000;">
                        <span style="flex: 1; text-align: left;">${new Date().toLocaleDateString('vi-VN')} ${pad2(new Date().getHours())}:${pad2(new Date().getMinutes())}</span>
                        <span style="flex: 1; text-align: center; font-weight: bold; font-size: 16px;">PHIẾU PICKING</span>
                        <span style="flex: 1; text-align: right;">Phiếu: ${pageNum}/${totalPages}</span>
                    </div>

                    <!-- Row 2: Invoice | Customer | Created Date -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; font-size: 8px; margin-bottom: 3mm; gap: 2mm;">
                        <div style="flex: 1.5;">
                            <div style="font-weight: bold;">Invoice:</div>
                            <div style="font-size: 16px; font-weight: bold;">${escapeHtml(currentCommand || '-')}</div>
                        </div>
                        <div style="flex: 1.5;">
                            <div style="font-weight: bold;">Khách hàng:</div>
                            <div style="font-size: 16px;">${escapeHtml(item.for_product || '-')}</div>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: bold;">Ngày xuất:</div>
                            <div style="font-size: 16px;">${createdAt}</div>
                        </div>
                    </div>

                    <!-- Row 3: QR Code (30x30mm) | Quantity -->
                    <div style="display: flex; gap: 3mm; margin-bottom: 3mm;">
                        <div style="width: 30mm; height: 30mm; border: 1px solid #999;">
                            ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR" style="width: 100%; height: 100%; object-fit: contain;">` : '<div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 8px;">QR lỗi</div>'}
                        </div>
                        <div style="flex: 1; border: 1px solid #ffc107; background: #fff9e6; padding: 2mm; text-align: center;">
                            <div style="font-size: 16px; font-weight: bold;">SL CẦN PICK</div>
                            <div style="font-size: 30px; font-weight: bold;">${item.total_qty}</div>
                            <div style="font-size: 16px;">${escapeHtml(item.unit || 'pcs')}</div>
                        </div>
                    </div>

                    <!-- Row 4: Product Code | Product Name -->
                    <div style="margin-bottom: 3mm;">
                        <div style="font-weight: bold; font-size: 16px;">${escapeHtml(item.product_id)}</div>
                        <div style="font-size: 8px;">${escapeHtml(item.product_name || '-')}</div>
                    </div>

                    <!-- Row 5: Signature Line (20mm height) -->
                    <div style="margin-top: auto; padding-top: 3mm; border-top: 1px solid #000; font-size: 8px; text-align: center; height: 20mm; display: flex; flex-direction: column; justify-content: center;">
                        <div style="margin-bottom: 3mm;">Ngày hoàn thành: ________________Ký tên: ________________</div>
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

    function printTickets() {
        const isTestMode = document.getElementById('print-test-mode')?.checked;
        const splitMode = document.getElementById('print-split-mode')?.checked;
        const tickets = Array.from(document.querySelectorAll('#print-pages .picking-ticket'));
        if (!tickets.length) {
            return;
        }

        if (isTestMode) {
            alert(`Chế độ test: Đã bỏ qua lệnh in thật. Số phiếu: ${tickets.length}`);
            return;
        }

        if (!splitMode) {
            // In gop 1 lan de giam so popup print tren trinh duyet.
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
                    <title>Print ticket ${index + 1}</title>
                    <link rel="stylesheet" href="${cssHref}">
                    <style>
                        body { margin: 0; padding: 0; background: #fff; }
                        .picking-ticket { margin: 0; }
                        .qr-image { width: 100%; height: auto; aspect-ratio: 1 / 1; object-fit: contain; display: block; }
                        @page { size: 80mm auto; margin: 0; }
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
            loadInvoiceItems(command);
        });
    });
</script>
