<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Chỉ Admin mới được phép import và chỉnh sửa dữ liệu export_temp.</div>';
    exit;
}
?>

<div class="container-main">
    <div class="panel">
        <h3><i class="fas fa-file-import"></i> Data Import (Quản trị)</h3>

        <div class="form-group import-box">
            <label>Import dữ liệu Packing list từ Excel:</label>
            <div class="import-note"><a href="../s-wms/assets/packing_list.xlsx">Tải file Excel mẫu tại đây.</a></div>
            <div class="import-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="file" id="export-file" class="form-control" accept=".xlsx,.csv" style="flex:1; min-width:220px;">
                <button type="button" class="btn btn-primary import-btn" id="import-export-btn">
                    <i class="fas fa-file-import"></i> Import Excel
                </button>
            </div>
            <!-- <label class="inline-checkbox" style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                <input type="checkbox" id="clear-existing-export">
                Xóa dữ liệu export_temp cũ trước khi import
            </label> -->
            <div id="import-result" class="import-result"></div>
        </div>

        <div class="form-group" style="margin-top:16px;">
            <label>Lọc theo ngày xuất hàng:</label>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="date" id="filter-date" class="form-control" style="max-width:220px;">
                <button type="button" class="btn btn-secondary" id="btn-filter-today">Hôm nay</button>
                <button type="button" class="btn btn-outline-primary" id="btn-refresh-rows">Tải lại danh sách</button>
            </div>
            <!-- <div class="text-muted" style="margin-top:6px; font-size:12px;">Mỗi dòng đại diện cho một cặp Invoice command + case_no.</div> -->
        </div>

        <div id="rows-message" class="text-muted" style="margin:10px 0;">Đang tải dữ liệu...</div>

        <div style="overflow:auto; border:1px solid #e5e7eb; border-radius:8px; background:#fff;">
            <table style="width:100%; border-collapse:collapse; min-width:980px;">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:left;">Invoice</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:left;">Case No</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:left;">Transport</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:left;">Customers</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:right;">SKU</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:right;">Quantity</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:center;">Pickup</th>
                        <th style="padding:10px; border-bottom:1px solid #e5e7eb; text-align:center;"></th>
                    </tr>
                </thead>
                <tbody id="command-case-rows">
                    <tr>
                        <td colspan="8" style="padding:14px; text-align:center; color:#6b7280;">Đang tải dữ liệu...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const dataImportApiBase = 'api.php';
    let commandCaseRows = [];

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

    function toYmd(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function setRowsMessage(text, isError = false) {
        const el = $('#rows-message');
        el.text(text || '');
        el.css('color', isError ? '#dc2626' : '#6b7280');
    }

    function renderCommandCaseRows(rows) {
        commandCaseRows = Array.isArray(rows) ? rows : [];
        const tbody = $('#command-case-rows');

        if (!commandCaseRows.length) {
            tbody.html('<tr><td colspan="8" style="padding:14px; text-align:center; color:#6b7280;">Không có dữ liệu</td></tr>');
            return;
        }

        let html = '';
        commandCaseRows.forEach(function(row, idx) {
            const exportDate = String(row.export_date || '').slice(0, 10);
            html += `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px; font-weight:700;">${escapeHtml(row.command)}</td>
                    <td style="padding:10px;">${escapeHtml(row.case_no)}</td>
                    <td style="padding:10px;">${escapeHtml(row.transport_type || '-')}</td>
                    <td style="padding:10px;">${escapeHtml(row.for_product || '-')}</td>
                    <td style="padding:10px; text-align:right;">${Number(row.product_count || 0)}</td>
                    <td style="padding:10px; text-align:right;">${Number(row.total_qty || 0)}</td>
                    <td style="padding:10px; text-align:center;">
                        <input type="date" id="row-date-${idx}" value="${escapeHtml(exportDate)}" class="form-control" style="min-width:140px; display:inline-block;">
                    </td>
                    <td style="padding:10px; text-align:center; white-space:nowrap;">
                        <button type="button" class="btn btn-sm btn-success btn-update-date" data-row-idx="${idx}" style="margin-right:6px;">Lưu</button>
                        <button type="button" class="btn btn-sm btn-danger btn-delete-row" data-row-idx="${idx}">Xóa</button>
                    </td>
                </tr>
            `;
        });

        tbody.html(html);
    }

    async function loadCommandCaseRows() {
        const dateVal = $('#filter-date').val();
        setRowsMessage('Đang tải dữ liệu...');

        try {
            const res = await $.ajax({
                type: 'GET',
                url: `${dataImportApiBase}?action=get_export_temp_command_case_rows`,
                data: dateVal ? { date: dateVal } : {},
                dataType: 'json'
            });

            if (!res.success) {
                throw new Error(res.message || 'Không thể tải dữ liệu');
            }

            renderCommandCaseRows(res.rows || []);
            const count = Array.isArray(res.rows) ? res.rows.length : 0;
            setRowsMessage(`Tổng ${count} dòng command/case_no.`);
        } catch (error) {
            renderCommandCaseRows([]);
            setRowsMessage(error.message || 'Lỗi kết nối', true);
        }
    }

    async function importExportTemp() {
        const input = document.getElementById('export-file');
        const file = input?.files?.[0];
        if (!file) {
            $('#import-result').removeClass('success').addClass('error').text('Vui lòng chọn file Excel để import');
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
                url: `${dataImportApiBase}?action=import_export_temp`,
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
            await loadCommandCaseRows();
        } catch (error) {
            result.removeClass('success').addClass('error').text(error.message || 'Import thất bại');
        } finally {
            button.prop('disabled', false).html('<i class="fas fa-file-import"></i> Import Excel');
        }
    }

    async function updateCommandCaseDate(rowIdx) {
        const row = commandCaseRows[rowIdx];
        if (!row) {
            return;
        }

        const exportDate = $(`#row-date-${rowIdx}`).val();
        if (!exportDate) {
            setRowsMessage('Vui lòng chọn ngày hợp lệ trước khi lưu', true);
            return;
        }

        const button = $(`.btn-update-date[data-row-idx="${rowIdx}"]`);
        button.prop('disabled', true).text('Đang lưu...');

        try {
            const res = await $.ajax({
                type: 'POST',
                url: `${dataImportApiBase}?action=update_export_temp_case_date`,
                data: {
                    command: row.command,
                    case_no: row.case_no,
                    export_date: exportDate,
                },
                dataType: 'json'
            });

            if (!res.success) {
                throw new Error(res.message || 'Lưu ngày thất bại');
            }

            setRowsMessage(`Đã cập nhật ${res.updated_rows || 0} dòng cho ${row.command}/${row.case_no}.`);
            await loadCommandCaseRows();
        } catch (error) {
            setRowsMessage(error.message || 'Lưu ngày thất bại', true);
        } finally {
            button.prop('disabled', false).text('Lưu ngày');
        }
    }

    async function deleteCommandCaseRow(rowIdx) {
        const row = commandCaseRows[rowIdx];
        if (!row) {
            return;
        }

        const ok = window.confirm(`Xóa toàn bộ dữ liệu cho cặp ${row.command}/${row.case_no}?`);
        if (!ok) {
            return;
        }

        const button = $(`.btn-delete-row[data-row-idx="${rowIdx}"]`);
        button.prop('disabled', true).text('Đang xóa...');

        try {
            const res = await $.ajax({
                type: 'POST',
                url: `${dataImportApiBase}?action=delete_export_temp_case_rows`,
                data: {
                    command: row.command,
                    case_no: row.case_no,
                },
                dataType: 'json'
            });

            if (!res.success) {
                throw new Error(res.message || 'Xóa thất bại');
            }

            setRowsMessage(`Đã xóa ${res.deleted_rows || 0} dòng cho ${row.command}/${row.case_no}.`);
            await loadCommandCaseRows();
        } catch (error) {
            setRowsMessage(error.message || 'Xóa thất bại', true);
        } finally {
            button.prop('disabled', false).text('Xóa dòng');
        }
    }

    $(document).ready(function() {
        const today = toYmd(new Date());
        $('#filter-date').val(today);

        $('#import-export-btn').on('click', function() {
            importExportTemp();
        });

        $('#btn-filter-today').on('click', function() {
            $('#filter-date').val(today);
            loadCommandCaseRows();
        });

        $('#btn-refresh-rows').on('click', function() {
            loadCommandCaseRows();
        });

        $('#filter-date').on('change', function() {
            loadCommandCaseRows();
        });

        $(document).on('click', '.btn-update-date', function() {
            const idx = Number($(this).attr('data-row-idx'));
            updateCommandCaseDate(idx);
        });

        $(document).on('click', '.btn-delete-row', function() {
            const idx = Number($(this).attr('data-row-idx'));
            deleteCommandCaseRow(idx);
        });

        loadCommandCaseRows();
    });
</script>
