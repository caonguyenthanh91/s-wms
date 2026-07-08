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
            <div class="import-note">Tải file Excel mẫu tại đây.</div>
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
            <label>Kiểu tìm kiếm:</label>
            <div class="search-mode-group">
                <label class="search-mode-option active">
                    <input type="radio" name="export-search-type" value="command" checked>
                    Theo Invoice
                </label>
                <label class="search-mode-option">
                    <input type="radio" name="export-search-type" value="product_id">
                    Theo Mã hàng
                </label>
            </div>
        </div>
        <div class="form-group">
            <label id="search-input-label">Invoice hoặc Mã hàng:</label>
            <div class="qr-inline-wrap">
                <input type="text" id="export-search-input" class="form-control" placeholder="Nhập mã Invoice hoặc mã hàng">
                <button type="button" class="btn btn-outline-primary" id="search-qr-btn" onclick="openQRScannerModal('export-search-input', 'Mã CTSX')" title="Quét mã">
                    <i class="fas fa-qrcode"></i>
                </button>
            </div>
            <div id="export-search-suggestions" class="command-suggestions"></div>
        </div>

        <button onclick="loadExportItems()" class="btn btn-primary search-command-btn" id="search-export-btn">
            <i class="fas fa-search"></i> Tìm kiếm
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
    let currentSearchType = 'command';
    let currentSearchKeyword = '';
    let currentItems = [];
    let shelvesData = {};
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

    function getSearchMeta() {
        return currentSearchType === 'product_id'
            ? {
                label: 'Theo Mã LK:',
                placeholder: 'Nhập hoặc chọn mã linh kiện',
                buttonText: 'Tìm Mã LK',
                qrLabel: 'Mã linh kiện'
            }
            : {
                label: 'Theo CTSX:',
                placeholder: 'Nhập hoặc chọn mã CTSX',
                buttonText: 'Tìm CTSX',
                qrLabel: 'Mã CTSX'
            };
    }

    function setSearchMode(mode) {
        currentSearchType = mode === 'product_id' ? 'product_id' : 'command';
        const meta = getSearchMeta();

        $('#search-input-label').text(meta.label);
        $('#export-search-input').attr('placeholder', meta.placeholder);
        $('#search-export-btn').html(`<i class="fas fa-search"></i> ${meta.buttonText}`);
        $('#search-qr-btn').attr('title', `Quét ${meta.qrLabel}`);

        $('.search-mode-option').removeClass('active');
        $(`input[name="export-search-type"][value="${currentSearchType}"]`).closest('.search-mode-option').addClass('active');

        $('#export-search-input').val('');
        $('#export-search-suggestions').empty();
        resetExportResult('Chọn một chỉ thị để xem trước phiếu in');
        refreshWarningState();
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
        shelvesData = {};
        $('#items-container').html('<div class="text-muted">Không có dữ liệu.</div>');
        $('#pages-preview').html(`<div class="preview-placeholder">${placeholderText || 'Không có dữ liệu'}</div>`);
        $('#print-pages').empty();
        $('#print-btn').hide();
    }

    function refreshWarningState() {
        if (!currentItems.length) {
            showWarning('');
            return;
        }

        const shortages = [];
        currentItems.forEach(item => {
            const shelves = shelvesData[item.product_id] || [];
            const totalStock = shelves.reduce(function(sum, shelf) {
                return sum + Number(shelf.qty || 0);
            }, 0);

            if (totalStock < Number(item.total_qty || 0)) {
                shortages.push(`${item.product_id}: ${totalStock}/${item.total_qty}`);
            }
        });

        const hasEditableRows = currentItems.some(function(item) {
            return !!item.is_editable;
        });
        const modeNote = hasEditableRows
            ? 'Đang xem theo CTSX. Bạn có thể sửa Tổng và Mỗi phiếu trên từng dòng rồi nhấn Lưu.'
            : (currentSearchType === 'command'
                ? 'Đang xem tổng số lượng gộp theo Invoice. Chế độ này chỉ hiển thị tổng, không cho sửa từng dòng.'
                : 'Đang xem tổng số lượng gộp theo Mã LK. Chế độ này chỉ hiển thị tổng, không cho sửa từng dòng.');

        if (!shortages.length) {
            showWarning(modeNote);
            return;
        }

        showWarning(`${modeNote} Tồn không đủ cho: ${shortages.join(' | ')}`);
    }

    function renderSuggestionItem(item) {
        const encodedValue = encodeURIComponent(item.value || '');
        let detail = '';

        if (currentSearchType === 'product_id') {
            detail = `Tổng: ${item.total_qty || 0}`;
            if (item.command_list) {
                detail += ` | Lệnh: ${item.command_list}`;
            }
        } else {
            detail = item.command_date || '';
            if (item.row_count) {
                detail += `${detail ? ' | ' : ''}${item.row_count} dòng`;
            }
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
                search_type: currentSearchType,
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
            resetExportResult('Dữ liệu đã thay đổi. Hãy tìm lại để xem phiếu in.');
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
            alert(currentSearchType === 'command' ? 'Vui lòng nhập mã chỉ thị' : 'Vui lòng nhập mã linh kiện');
            return;
        }

        currentSearchKeyword = keyword;

        return $.ajax({
            type: 'POST',
            url: `${printApiBase}?action=get_export_items`,
            data: {
                search_type: currentSearchType,
                keyword: keyword
            },
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    alert(res.message || 'Không tìm thấy dữ liệu');
                    return;
                }

                currentSearchType = res.search_type || currentSearchType;
                currentSearchKeyword = res.keyword || keyword;
                currentItems = res.items || [];
                displayItems();
            },
            error: function() {
                alert('Lỗi kết nối khi tải danh sách dữ liệu picking');
            }
        });
    }

    async function updateExportItem(id) {
        const row = $(`.item-card[data-item-id="${id}"]`);
        const totalQty = parseInt(row.find('.edit-total-qty').val(), 10);
        const bucketQty = parseInt(row.find('.edit-bucket-qty').val(), 10);

        if (!Number.isInteger(totalQty) || !Number.isInteger(bucketQty) || totalQty <= 0 || bucketQty <= 0) {
            alert('Tổng số lượng và Mỗi phiếu phải lớn hơn 0');
            return;
        }

        const button = row.find('.save-item-btn');
        button.prop('disabled', true).text('Đang lưu...');

        try {
            const res = await $.ajax({
                type: 'POST',
                url: `${printApiBase}?action=update_export_item`,
                data: {
                    id: id,
                    total_qty: totalQty,
                    bucket_qty: bucketQty
                },
                dataType: 'json'
            });

            if (!res.success) {
                throw new Error(res.message || 'Không cập nhật được dữ liệu');
            }

            await loadExportItems();
        } catch (error) {
            alert(error.message || 'Cập nhật thất bại');
        } finally {
            button.prop('disabled', false).text('Lưu');
        }
    }

    function displayItems() {
        const container = $('#items-container');
        container.empty();
        shelvesData = {};

        if (!currentItems.length) {
            container.html('<div class="text-muted">Không có dữ liệu.</div>');
            $('#pages-preview').html('<div class="preview-placeholder">Không có dữ liệu</div>');
            $('#print-pages').empty();
            $('#print-btn').hide();
            refreshWarningState();
            return;
        }

        let pending = currentItems.length;

        currentItems.forEach(item => {
            const isEditableRow = !!item.is_editable;
            const qtyContent = isEditableRow
                ? `
                    <div class="item-edit-grid">
                        <label class="item-edit-field">
                            <span>Tổng</span>
                            <input type="number" min="1" class="form-control edit-total-qty" value="${item.total_qty}">
                        </label>
                        <label class="item-edit-field">
                            <span>Mỗi phiếu</span>
                            <input type="number" min="1" class="form-control edit-bucket-qty" value="${item.bucket_qty}">
                        </label>
                        <button type="button" class="btn btn-save-inline save-item-btn" onclick="updateExportItem(${item.id})">Lưu</button>
                    </div>
                `
                : `
                    <div class="item-qty-summary">
                        <strong>Tổng gộp:</strong> ${item.total_qty} ${escapeHtml(item.unit || 'pcs')}
                    </div>
                    <div class="item-meta-inline">
                        Lệnh liên quan: ${escapeHtml(item.command_list || '-')}
                    </div>
                `;

            container.append(`
                <div class="item-card" data-item-id="${item.id}">
                    <div class="item-header">
                        <div class="product-id">${escapeHtml(item.product_id)}</div>
                        <div class="qty-info">${isEditableRow ? `${item.num_pages} phiếu` : 'Tổng gộp'}</div>
                    </div>
                    <div class="item-meta">
                        ${escapeHtml(item.product_name || 'N/A')} (${escapeHtml(item.unit || 'pcs')})
                    </div>
                    <div class="item-meta-inline">
                        FOR: ${escapeHtml(item.for_product || '-')}
                    </div>
                    ${qtyContent}
                    <div id="shelf-${item.id}" class="item-shelf-wrap"></div>
                </div>
            `);

            $.ajax({
                type: 'POST',
                url: `${printApiBase}?action=get_shelf_inventory`,
                data: { product_id: item.product_id },
                dataType: 'json',
                complete: function() {
                    pending -= 1;
                    if (pending === 0) {
                        generatePrintPreview();
                    }
                },
                success: function(res) {
                    if (!res.success) return;

                    shelvesData[item.product_id] = res.shelves || [];
                    const unit = item.unit || 'pcs';
                    let html = '';

                    if (!res.shelves.length) {
                        html = '<div class="insufficient-stock">Không có tồn ở KHO CHINH</div>';
                    } else {
                        res.shelves.forEach(shelf => {
                            html += `<div class="shelf-item"><span>${escapeHtml(shelf.shelf_id)}</span><span>${shelf.qty} ${escapeHtml(unit)}</span></div>`;
                        });
                        if (res.total_stock < item.total_qty) {
                            html += `<div class="insufficient-stock stock-summary">Tồn không đủ: ${res.total_stock}/${item.total_qty}</div>`;
                        } else {
                            html += `<div class="sufficient-stock stock-summary">Tồn đủ: ${res.total_stock}/${item.total_qty}</div>`;
                        }
                    }

                    $(`#shelf-${item.id}`).html(html);
                },
                error: function() {
                    $(`#shelf-${item.id}`).html('<div class="insufficient-stock">Lỗi kết nối khi tải tồn kho KHO CHINH</div>');
                }
            });
        });
    }

    function generatePrintPreview() {
        const preview = $('#pages-preview');
        const printPages = $('#print-pages');

        let previewHtml = '';
        let printHtml = '';

        currentItems.forEach(item => {
            const shelves = shelvesData[item.product_id] || [];
            const groupedMode = !item.is_editable;
            const numPages = groupedMode ? 1 : Math.max(1, parseInt(item.num_pages, 10) || 1);
            let remainingQty = parseInt(item.total_qty, 10) || 0;

            for (let page = 1; page <= numPages; page++) {
                const bucketQty = parseInt(item.bucket_qty, 10) || 1;
                const pageQty = groupedMode ? remainingQty : Math.min(bucketQty, remainingQty);
                const totalQty = parseInt(item.total_qty, 10) || 0;
                const forProduct = item.for_product || '-';
                const qrDataUrl = generateQRCodeDataUrl(item.product_id + '$' + pageQty);
                const commandLabel = currentSearchType === 'command' ? 'INVOICE' : 'LỆNH';
                const commandValue = currentSearchType === 'command'
                    ? (item.command || currentSearchKeyword)
                    : (item.command_list || currentSearchKeyword);
                const qtySubText = groupedMode
                    ? (currentSearchType === 'command' ? 'Tổng gộp theo Invoice' : 'Tổng gộp theo Mã LK')
                    : `/ Tổng ${totalQty}`;

                let shelvesHtml = '';
                if (shelves.length) {
                    shelves.forEach(shelf => {
                        shelvesHtml += `<div class="shelf-row"><span>${escapeHtml(shelf.shelf_id)}</span><span> (${shelf.qty})</span></div>`;
                    });
                } else {
                    shelvesHtml = '<div class="text-muted no-location">Không có tồn kho</div>';
                }

                const ticketHtml = `
                    <div class="picking-ticket">
                        <div class="row1 picking-header">
                            <span>${new Date().toLocaleDateString('vi-VN')}</span>
                            <span class="ticket-title">PHIẾU PICKING</span>
                            <span class="picking-page-num">Phiếu: ${page}/${numPages}</span>
                        </div>

                        <div class="row2 picking-command">
                            <div class="command-box">
                                <span class="ticket-key">${commandLabel}</span>
                                <span class="command-code">${escapeHtml(commandValue)}</span>
                            </div>
                            <div class="for-product-box">
                                <span class="ticket-key">FOR</span>
                                <span class="for-product-val">${escapeHtml(forProduct)}</span>
                            </div>
                        </div>

                        <div class="row3 picking-product">
                            <div class="left qr-section">
                                ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR ${item.product_id}" class="qr-image">` : '<div class="text-muted no-location">QR lỗi</div>'}
                            </div>
                            <div class="qty-needed">
                                <div class="qty-label">SL PICK</div>
                                <div class="qty-main">${pageQty} ${escapeHtml(item.unit || 'pcs')}</div>
                                <div class="qty-sub">${qtySubText}</div>
                            </div>
                        </div>

                        <div class="row4 product-info">
                            <div class="product-code">${escapeHtml(item.product_id)}</div>
                            <div class="product-name">${escapeHtml(item.product_name || 'N/A')}</div>
                        </div>

                        <div class="row5 shelves-section">
                            <div class="shelves-header">Vị trí (SL):</div>
                            ${shelvesHtml}
                        </div>

                        <div class="row6 picking-footer">
                            <div class="sign-line">Ngày hoàn thành: _____________ Ký tên: _____________</div>
                        </div>
                    </div>
                `;

                previewHtml += ticketHtml;
                printHtml += ticketHtml;
                remainingQty -= pageQty;
            }
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
        setSearchMode('command');

        $('input[name="export-search-type"]').on('change', function() {
            setSearchMode($(this).val());
        });

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
