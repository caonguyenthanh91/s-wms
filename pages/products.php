<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <span>📦</span> Quản lý Sản phẩm
            </h3>
            <p class="text-blue-100 text-sm mt-1">Nhập mã SKU và nhấn <kbd class="px-1.5 py-0.5 bg-white/20 rounded text-xs font-mono">Enter</kbd> để kiểm tra đăng ký.</p>
        </div>

        <form id="product-form" class="p-6 space-y-5" autocomplete="off">
            <!-- SKU -->
            <div>
                <label for="product_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Mã Sản Phẩm (SKU) *</label>
                <div class="relative">
                    <input type="text" id="product_id" name="product_id" required placeholder="Nhập mã SKU rồi nhấn Enter..."
                           class="w-full pl-4 pr-24 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase font-mono text-base transition">
                    <button type="button" id="btn-reset-sku" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold transition">
                        Đổi mã
                    </button>
                </div>
                <div id="sku-status" class="hidden mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"></div>
            </div>

            <!-- Các thông số -->
            <fieldset id="detail-fields" disabled class="space-y-5 transition disabled:opacity-50">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="box_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Mã Thùng *</label>
                        <input type="text" id="box_name" name="box_name" required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono text-sm transition">
                    </div>
                    <div>
                        <label for="box_nom" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Định Lượng Thùng *</label>
                        <input type="number" id="box_nom" name="box_nom" required min="1" step="1"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="product_name" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Tên Sản Phẩm</label>
                        <input type="text" id="product_name" name="product_name" placeholder="(Không bắt buộc)"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
                    </div>
                    <div>
                        <label for="unit" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Đơn Vị Tính</label>
                        <input type="text" id="unit" name="unit" placeholder="(Không bắt buộc)"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
                    </div>
                </div>

                <button type="submit" id="btn-submit"
                        class="w-full py-3 px-4 rounded-xl font-bold text-white bg-gray-400 shadow-md transition">
                    LƯU
                </button>
            </fieldset>

            <p id="form-message" class="text-center text-sm font-medium min-h-[1.25rem]"></p>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    let mode = null; // 'add' | 'update'

    function setMessage(text, ok) {
        $('#form-message').removeClass('text-green-600 text-red-600')
            .addClass(ok ? 'text-green-600' : 'text-red-600').text(text || '');
    }

    function resetForm() {
        mode = null;
        $('#product-form')[0].reset();
        $('#product_id').prop('readonly', false).removeClass('bg-gray-100 text-gray-500').focus();
        $('#btn-reset-sku, #sku-status').addClass('hidden');
        $('#detail-fields').prop('disabled', true);
        $('#btn-submit').text('LƯU').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-gray-400 shadow-md transition');
    }

    function applyMode(exists, data) {
        mode = exists ? 'update' : 'add';
        $('#product_id').prop('readonly', true).addClass('bg-gray-100 text-gray-500');
        $('#btn-reset-sku').removeClass('hidden');
        $('#detail-fields').prop('disabled', false);

        const status = $('#sku-status').removeClass('hidden bg-yellow-100 text-yellow-800 bg-green-100 text-green-800');
        const btn = $('#btn-submit');
        if (exists) {
            status.addClass('bg-yellow-100 text-yellow-800').html('<span>●</span> Mã hàng đã đăng ký');
            btn.text('CẬP NHẬT').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-amber-500 hover:bg-amber-600 shadow-md transition');
            $('#box_name').val(data.box_name || '');
            $('#box_nom').val(data.box_nom || '');
            $('#product_name').val(data.product_name || '');
            $('#unit').val(data.unit || '');
        } else {
            status.addClass('bg-green-100 text-green-800').html('<span>●</span> Mã hàng chưa đăng ký');
            btn.text('ĐĂNG KÝ').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-green-600 hover:bg-green-700 shadow-md transition');
            $('#box_name, #box_nom, #product_name, #unit').val('');
        }
        $('#box_name').focus();
    }

    function checkSku() {
        const sku = $('#product_id').val().trim().toUpperCase();
        if (!sku) return;
        $('#product_id').val(sku);
        setMessage('');
        $.getJSON('api.php?action=get_product_detail', { product_id: sku }, function(res) {
            if (res.success) applyMode(res.exists, res.data);
            else setMessage(res.message || 'Không kiểm tra được mã hàng.', false);
        }).fail(function() { setMessage('Lỗi kết nối máy chủ.', false); });
    }

    $('#product_id').on('keydown', function(e) {
        if (e.which === 13) { e.preventDefault(); if (!mode) checkSku(); }
    });
    $('#btn-reset-sku').on('click', function() { setMessage(''); resetForm(); });

    $('#product-form').submit(function(e) {
        e.preventDefault();
        if (!mode) { checkSku(); return; }
        const action = mode === 'update' ? 'update_product' : 'add_product';
        $.post('api.php?action=' + action, $(this).serialize(), function(res) {
            setMessage(res.message, res.success);
            if (res.success) resetForm();
        }, 'json').fail(function() { setMessage('Lỗi kết nối máy chủ.', false); });
    });
});
</script>
