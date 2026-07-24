<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Manager', 'Admin'], true)) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Chỉ Manager hoặc Admin mới được phép import WMS Inventory.</div>';
    exit;
}
?>

<div class="container-main">
    <div class="panel">
        <h3><i class="fas fa-file-import"></i> Import WMS Inventory</h3>

        <div class="form-group import-box">
            <label>Import dữ liệu Excel vào bảng wms_inventory:</label>
            <!-- <div class="import-note">Thứ tự cột Excel đang hỗ trợ: STT, Mã sản phẩm, Tên sản phẩm, Mã quy cách, Bundle, Mã thùng, Ngày nhập hàng, Vị trí, Trạng thái, SL ĐVT chính, ĐVT chính, SL ĐVT phụ, ĐVT phụ. Hệ thống chỉ đọc Mã thùng, Mã sản phẩm, SL ĐVT chính; xóa toàn bộ dữ liệu cũ trong wms_inventory trước khi import và chỉ giữ cặp Mã thùng + Mã sản phẩm duy nhất.</div>
            <div class="import-note">Nếu file lớn hơn giới hạn PHP, hệ thống sẽ hiển thị chi tiết lỗi như upload_max_filesize hoặc post_max_size để bạn biết cần tăng cấu hình máy chủ.</div> -->
            <div class="import-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="file" id="wms-inventory-file" class="form-control" accept=".xlsx,.csv" style="flex:1; min-width:220px;">
                <button type="button" class="btn btn-primary import-btn" id="import-wms-inventory-btn">
                    <i class="fas fa-file-import"></i> Import Excel
                </button>
            </div>
            <div id="import-result" class="import-result"></div>
        </div>
    </div>
</div>

<script>
    const dataImportApiBase = 'api.php';

    async function importWmsInventory() {
        const input = document.getElementById('wms-inventory-file');
        const file = input?.files?.[0];
        if (!file) {
            $('#import-result').removeClass('success').addClass('error').text('Vui lòng chọn file Excel để import');
            return;
        }

        const button = $('#import-wms-inventory-btn');
        const result = $('#import-result');
        const formData = new FormData();
        formData.append('excel_file', file);

        button.prop('disabled', true).text('Đang import...');
        result.removeClass('error success').text('Đang tải và xử lý file...');

        try {
            const res = await $.ajax({
                type: 'POST',
                url: `${dataImportApiBase}?action=import_wms_inventory`,
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
                ? ` Có ${res.warning_count} dòng bị bỏ qua hoặc trùng.`
                : '';

            result.removeClass('error').addClass('success').text(`Import thành công ${res.imported_count} cặp box_code/product_id.${warningText}`);
            input.value = '';
        } catch (error) {
            result.removeClass('success').addClass('error').text(error.message || 'Import thất bại');
        } finally {
            button.prop('disabled', false).html('<i class="fas fa-file-import"></i> Import Excel');
        }
    }

    $(document).ready(function() {
        $('#import-wms-inventory-btn').on('click', function() {
            importWmsInventory();
        });
    });
</script>
