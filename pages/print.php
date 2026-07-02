<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Ban khong co quyen truy cap trang nay. Can role: Staff tro len.</div>';
    exit;
}

$stmt = $pdo->query("SELECT command, DATE(MAX(created_at)) AS command_date FROM export_temp GROUP BY command ORDER BY MAX(created_at) DESC");
$commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-main">
    <div class="panel">
        <h3><i class="fas fa-list"></i> Chon Chi Thi Picking</h3>

        <div class="form-group">
            <label>Ma Chi Thi:</label>
            <div class="qr-inline-wrap">
                <input type="text" id="command-input" class="form-control" placeholder="Nhap hoac chon ma chi thi">
                <button type="button" class="btn btn-outline-primary" onclick="openQRScannerModal('command-input', 'Ma Chi Thi')" title="Quet ma chi thi">
                    <i class="fas fa-qrcode"></i>
                </button>
            </div>
            <div id="command-suggestions" class="command-suggestions"></div>
        </div>

        <button onclick="loadExportItems()" class="btn btn-primary search-command-btn">
            <i class="fas fa-search"></i> Tim Chi Thi
        </button>

        <div id="warning-container" class="warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="warning-text"></span>
        </div>

        <div id="items-container" class="items-scroll"></div>
    </div>

    <div class="panel">
        <h3><i class="fas fa-print"></i> Xem Truoc Phieu Picking</h3>

        <div class="pages-container" id="pages-preview">
            <div class="preview-placeholder">
                Chon mot chi thi de xem truoc phieu in
            </div>
        </div>

        <button onclick="printTickets()" class="btn btn-print" id="print-btn">
            <i class="fas fa-print"></i> In Phieu Picking (80mm)
        </button>

        <div class="mt-3 text-sm text-gray-600" id="print-options-wrap">
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-test-mode">
                Che do test (khong in that)
            </label>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-split-mode" checked>
                In tach tung phieu (may in nhiet, co the hien nhieu popup)
            </label>
            <label style="display:block; margin-bottom:6px;">
                <input type="checkbox" id="print-single-mode">
                In gop 1 lan (it popup hon, may in co the khong cat tung phieu)
            </label>
            <div id="print-mode-note" class="text-xs text-gray-500"></div>
        </div>
    </div>
</div>

<div id="print-pages"></div>

<script>
    let currentCommand = '';
    let currentItems = [];
    let shelvesData = {};
    const printApiBase = 'api.php';

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
            ? 'Dang chon: In tach tung phieu.'
            : 'Dang chon: In gop 1 lan.';
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

    $('#command-input').on('input', function() {
        const input = $(this).val().toUpperCase();
        const suggestions = $('#command-suggestions');
        const commands = <?php echo json_encode($commands); ?>;

        if (!input.length) {
            suggestions.empty();
            return;
        }

        const filtered = commands.filter(c => c.command.toUpperCase().includes(input));
        suggestions.empty();

        if (!filtered.length) return;

        suggestions.append('<div class="suggestion-label">Goi y:</div>');
        filtered.slice(0, 5).forEach(c => {
            suggestions.append(
                '<div class="suggestion-item" onclick="selectCommand(\'' + c.command + '\')">' +
                c.command + ' (' + c.command_date + ')' +
                '</div>'
            );
        });
    });

    function selectCommand(cmd) {
        $('#command-input').val(cmd);
        $('#command-suggestions').empty();
    }

    function loadExportItems() {
        const command = $('#command-input').val().trim().toUpperCase();
        if (!command) {
            alert('Vui long nhap ma chi thi');
            return;
        }

        currentCommand = command;

        $.ajax({
            type: 'POST',
            url: `${printApiBase}?action=get_export_items`,
            data: { command: command },
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    alert(res.message || 'Khong tim thay du lieu');
                    return;
                }

                currentItems = res.items || [];
                displayItems();
            },
            error: function() {
                alert('Loi ket noi khi tai danh sach chi thi');
            }
        });
    }

    function displayItems() {
        const container = $('#items-container');
        container.empty();
        shelvesData = {};

        if (!currentItems.length) {
            container.html('<div class="text-muted">Khong co du lieu.</div>');
            $('#pages-preview').html('<div class="preview-placeholder">Khong co du lieu</div>');
            $('#print-btn').hide();
            return;
        }

        let pending = currentItems.length;

        currentItems.forEach(item => {
            container.append(`
                <div class="item-card">
                    <div class="item-header">
                        <div class="product-id">${item.product_id}</div>
                        <div class="qty-info">${item.num_pages} phieu</div>
                    </div>
                    <div class="item-meta">
                        ${item.product_name || 'N/A'} (${item.unit || 'pcs'})
                    </div>
                    <div class="item-qty-summary">
                        <strong>Tong:</strong> ${item.total_qty} |
                        <strong>Moi phieu:</strong> ${item.bucket_qty}
                    </div>
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
                        html = '<div class="insufficient-stock">Khong co hang tren ke</div>';
                    } else {
                        res.shelves.forEach(shelf => {
                            html += `<div class="shelf-item"><span>${shelf.shelf_id}</span><span>${shelf.qty} ${unit}</span></div>`;
                        });
                        if (res.total_stock < item.total_qty) {
                            html += `<div class="insufficient-stock stock-summary">Ton khong du: ${res.total_stock}/${item.total_qty}</div>`;
                        } else {
                            html += `<div class="sufficient-stock stock-summary">Ton du: ${res.total_stock}/${item.total_qty}</div>`;
                        }
                    }

                    $(`#shelf-${item.id}`).html(html);
                },
                error: function() {
                    $(`#shelf-${item.id}`).html('<div class="insufficient-stock">Loi ket noi khi tai ton ke</div>');
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
            const numPages = Math.max(1, parseInt(item.num_pages, 10) || 1);
            let remainingQty = parseInt(item.total_qty, 10) || 0;

            for (let page = 1; page <= numPages; page++) {
                const bucketQty = parseInt(item.bucket_qty, 10) || 1;
                const pageQty = Math.min(bucketQty, remainingQty);
                const totalQty = parseInt(item.total_qty, 10) || 0;
                const forProduct = item.for_product || '-';
                const qrDataUrl = generateQRCodeDataUrl(item.product_id);

                let shelvesHtml = '';
                if (shelves.length) {
                    shelves.forEach(shelf => {
                        shelvesHtml += `<div class="shelf-row"><span>${shelf.shelf_id}</span><span>${shelf.qty}</span></div>`;
                    });
                } else {
                    shelvesHtml = '<div class="text-muted no-location">Khong co vi tri</div>';
                }

                const ticketHtml = `
                    <div class="picking-ticket">
                        <div class="row1 picking-header">
                            <span>${new Date().toLocaleDateString('vi-VN')}</span>
                            <span class="ticket-title">PHIEU PICKING</span>
                            <span class="picking-page-num">Phieu: ${page}/${numPages}</span>
                        </div>

                        <div class="row2 picking-command">
                            <div class="command-box">
                                <span class="ticket-key">CTSX</span>
                                <span class="command-code">${currentCommand}</span>
                            </div>
                            <div class="for-product-box">
                                <span class="ticket-key">FOR</span>
                                <span class="for-product-val">${forProduct}</span>
                            </div>
                        </div>

                        <div class="row3 picking-product">
                            <div class="left qr-section">
                                ${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR ${item.product_id}" class="qr-image">` : '<div class="text-muted no-location">QR loi</div>'}
                            </div>
                            <div class="qty-needed">
                                <div class="qty-label">SL PICK</div>
                                <div class="qty-main">${pageQty} ${item.unit || 'pcs'}</div>
                                <div class="qty-sub">/ Tong ${totalQty}</div>
                            </div>
                        </div>

                        <div class="row4 product-info">
                            <div class="product-code">${item.product_id}</div>
                            <div class="product-name">${item.product_name || 'N/A'}</div>
                        </div>

                        <div class="row5 shelves-section">
                            <div class="shelves-header">Vi Tri Ke:</div>
                            ${shelvesHtml}
                        </div>

                        <div class="row6 picking-footer">
                            <div class="sign-line">Ngay hoan thanh: _____________ Ky ten: _____________</div>
                        </div>
                    </div>
                `;

                previewHtml += ticketHtml;
                printHtml += ticketHtml;
                remainingQty -= pageQty;
            }
        });

        preview.html(previewHtml || '<div class="preview-placeholder">Khong co du lieu</div>');
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
            alert(`Che do test: Da bo qua lenh in that. So phieu: ${tickets.length}`);
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
    });
</script>
