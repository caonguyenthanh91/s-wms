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
            <label>Invoice:</label>
            <div class="qr-inline-wrap">
                <input type="text" id="export-search-input" class="form-control" placeholder="Nhập hoặc quét mã Invoice">
                <button type="button" class="btn btn-outline-primary" id="search-qr-btn" onclick="openQRScannerModal('export-search-input', 'Mã CTSX')" title="Quét mã">
                    <i class="fas fa-qrcode"></i>
                </button>
            </div>
            <div id="export-search-suggestions" class="command-suggestions"></div>
        </div>

        <button onclick="loadExportItems()" class="btn btn-primary search-command-btn" id="search-export-btn">
            <i class="fas fa-search"></i> Tìm case theo Invoice
        </button>

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
                Nhập mã Invoice để xem trước phiếu in theo case
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
    let currentItems = [];
    let suggestionRequest = null;
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

    function showWarning(message) {
        if (!message) {
            $('#warning-container').removeClass('show');
            $('#warning-text').text('');
            return;
        }
        $('#warning-text').text(message);
        $('#warning-container').addClass('show');
    }

    function resetExportResult(placeholderText) {
        currentItems = [];
        $('#items-container').html('<div class="text-muted">Không có dữ liệu.</div>');
        $('#pages-preview').html(`<div class="preview-placeholder">${placeholderText || 'Không có dữ liệu'}</div>`);
        $('#print-pages').empty();
        $('#print-btn').hide();
        showWarning('');
    }

    function refreshWarningState() {
        if (!currentItems.length) {
            showWarning('');
            return;
        }
        showWarning(`Đã tải ${currentItems.length} case cho Invoice ${currentCommand}. Mỗi case in 1 phiếu.`);
    }

    function renderSuggestionItem(item) {
        const encodedValue = encodeURIComponent(item.value || '');
        let detail = item.command_date || '';
        if (item.row_count) {
            detail += `${detail ? ' | ' : ''}${item.row_count} dòng`;
        }

        return `
            <button type="button" class="suggestion-item suggestion-button" data-value="${encodedValue}">
                <span>${escapeHtml(item.value || '')}</span>
                <small>${escapeHtml(detail)}</small>
            </button>
        `;
    }

    function loadSearchSuggestions(keyword) {
        const suggestions = $('#export-search-suggestions');
        if (!keyword) {
            suggestions.empty();
            return;
        }

        if (suggestionRequest && typeof suggestionRequest.abort === 'function') {
            suggestionRequest.abort();
        }

        suggestionRequest = $.ajax({
            type: 'GET',
            url: `${printApiBase}?action=get_export_search_suggestions`,
            data: {
                search_type: 'command',
                keyword: keyword
            },
            dataType: 'json',
            success: function(res) {
                if (!res.success || !Array.isArray(res.items) || !res.items.length) {
                    suggestions.empty();
                    return;
                }

                const html = ['<div class="suggestion-label">Gợi ý:</div>'];
                res.items.forEach(function(item) {
                    html.push(renderSuggestionItem(item));
                });
                suggestions.html(html.join(''));
            },
            error: function(xhr, status) {
                if (status !== 'abort') {
                    suggestions.empty();
                }
            }
        });
    }

    function selectSearchKeyword(value) {
        $('#export-search-input').val(value);
        $('#export-search-suggestions').empty();
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
            resetExportResult('Dữ liệu đã thay đổi. Hãy tìm lại Invoice để xem phiếu in theo case.');
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

    function loadExportItems() {
        const keyword = $('#export-search-input').val().trim().toUpperCase();
        if (!keyword) {
            alert('Vui lòng nhập mã Invoice');
            return;
        }

        currentCommand = keyword;

        return $.ajax({
            type: 'POST',
            url: `${printApiBase}?action=get_export_cases_for_print`,
            data: { command: keyword },
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    alert(res.message || 'Không tìm thấy dữ liệu case cho Invoice này');
                    resetExportResult('Không có dữ liệu case phù hợp');
                    return;
                }

                currentCommand = res.command || keyword;
                currentItems = Array.isArray(res.cases) ? res.cases : [];
                displayItems();
            },
            error: function() {
                alert('Lỗi kết nối khi tải danh sách case');
            }
        });
    }

    function displayItems() {
        const container = $('#items-container');
        container.empty();

        if (!currentItems.length) {
            container.html('<div class="text-muted">Không có dữ liệu case.</div>');
            $('#pages-preview').html('<div class="preview-placeholder">Không có dữ liệu case</div>');
            $('#print-pages').empty();
            $('#print-btn').hide();
            refreshWarningState();
            return;
        }

        currentItems.forEach(function(item) {
            container.append(`
                <div class="item-card">
                    <div class="item-header">
                        <div class="product-id">${escapeHtml(item.command_case_id || '')}</div>
                        <div class="qty-info">Case: ${escapeHtml(item.case_no || '-')}</div>
                    </div>
                    <div class="item-meta">FOR: ${escapeHtml(item.for_product || '-')}</div>
                    <div class="item-meta-inline">Số items trong case: ${Number(item.total_items_in_case || 0)} items</div>
                </div>
            `);
        });

        generatePrintPreview();
    }

    function generatePrintPreview() {
        const preview = $('#pages-preview');
        const printPages = $('#print-pages');

        let previewHtml = '';
        let printHtml = '';
        const totalTickets = currentItems.length;

        currentItems.forEach(function(item, index) {
            const command = item.command || currentCommand;
            const caseNo = item.case_no || '-';
            const commandCasePair = item.command_case_id || `[${command}][${caseNo}]`;
            const itemCount = parseInt(item.total_items_in_case, 10) || 0;
            const forProduct = item.for_product || '-';
            const qrDataUrl = generateQRCodeDataUrl(commandCasePair);

            const ticketHtml = `
                <div class="picking-ticket">
                    <div class="row1 picking-header">
                        <span>${new Date().toLocaleDateString('vi-VN')}</span>
                        <span class="ticket-title">PHIẾU PICKING</span>
                        <span class="picking-page-num">Phiếu: ${index + 1}/${totalTickets}</span>
                    </div>

                    <div class="row2 picking-command">
                        <div class="command-box">
                            <span class="ticket-key">INVOICE</span>
                            <span class="command-code">${escapeHtml(command)}</span>
                        </div>
                        <div class="for-product-box">
                            <span class="ticket-key">FOR</span>
                            <span class="for-product-val">${escapeHtml(forProduct)}</span>
                        </div>
                    </div>

                    <div class="row3 picking-product">
                        <div class="left qr-section">
                            ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR ${escapeHtml(commandCasePair)}" class="qr-image">` : '<div class="text-muted no-location">QR lỗi</div>'}
                        </div>
                        <div class="qty-needed">
                            <div class="qty-label">SL PICK</div>
                            <div class="qty-main">${itemCount} items</div>
                            <div class="qty-sub">Số items trong case</div>
                        </div>
                    </div>

                    <div class="row4 product-info">
                        <div class="product-code">${escapeHtml(commandCasePair)}</div>
                        <div class="product-name">Case No: ${escapeHtml(caseNo)}</div>
                    </div>

                    <div class="row5 shelves-section">
                        <div class="shelves-header">CASE NO:</div>
                        <div class="shelf-row"><span>${escapeHtml(caseNo)}</span></div>
                    </div>

                    <div class="row6 picking-footer">
                        <div class="sign-line">Ngày hoàn thành: _____________ Ký tên: _____________</div>
                    </div>
                </div>
            `;

            previewHtml += ticketHtml;
            printHtml += ticketHtml;
        });

        preview.html(previewHtml || '<div class="preview-placeholder">Không có dữ liệu</div>');
        printPages.html(printHtml);
        refreshWarningState();

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
        resetExportResult('Nhập mã Invoice để xem trước phiếu in theo case');

        $('#export-search-input').on('input', function() {
            loadSearchSuggestions($(this).val().trim().toUpperCase());
        });

        $('#export-search-input').on('keypress', function(event) {
            if (event.which === 13) {
                loadExportItems();
            }
        });

        $(document).on('click', '.suggestion-button', function() {
            selectSearchKeyword(decodeURIComponent($(this).data('value') || ''));
        });
    });
</script>
