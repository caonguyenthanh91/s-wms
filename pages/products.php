<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- BÊN TRÁI: ĐĂNG KÝ SẢN PHẨM MỚI -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center">
                <span class="mr-2">📝</span> Đăng ký Sản phẩm Mới
            </h3>
            <form id="add-product-form" class="space-y-4">
                <div>
                    <label for="product_id" class="block text-xs font-bold text-gray-500 uppercase mb-1">Mã Sản Phẩm (SKU) *</label>
                    <input type="text" id="product_id" name="product_id" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none uppercase font-mono text-sm">
                </div>
                <div>
                    <label for="product_name" class="block text-xs font-bold text-gray-500 uppercase mb-1">Tên Sản Phẩm</label>
                    <input type="text" id="product_name" name="product_name" placeholder="(Không bắt buộc)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                </div>
                <div>
                    <label for="unit" class="block text-xs font-bold text-gray-500 uppercase mb-1">Đơn Vị Tính</label>
                    <input type="text" id="unit" name="unit" placeholder="(Không bắt buộc)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md font-bold hover:bg-blue-700 transition">
                    LƯU SẢN PHẨM
                </button>
                <p id="form-message" class="mt-3 text-center text-sm"></p>
            </form>
        </div>
    </section>

    <!-- BÊN PHẢI: TRA CỨU THEO MÃ SẢN PHẨM -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center">
                <span class="mr-2">🔍</span> Tra cứu Vị trí & Tồn kho
            </h3>
            <div class="flex gap-2">
                <input type="text" id="search-product-id-input" placeholder="Nhập mã SKU..." 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none uppercase font-mono text-sm">
                <button onclick="searchByProduct()" class="bg-green-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-green-700 transition text-sm">
                    Tìm kiếm
                </button>
            </div>
        </div>

        <!-- Kết quả tìm kiếm -->
        <div id="product-results-container" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="p-4 border-b bg-green-50">
                <h4 class="font-bold text-green-800">SKU: <span id="res-sku" class="font-mono"></span></h4>
                <p class="text-xs text-gray-600 mt-1" id="res-product-name"></p>
            </div>
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold">
                        <th class="p-3 border-b">Vị trí kệ</th>
                        <th class="p-3 border-b text-right">Số lượng tồn</th>
                    </tr>
                </thead>
                <tbody id="product-inventory-results"></tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold border-t">
                        <td class="p-3">TỔNG TỒN</td>
                        <td class="p-3 text-right text-green-700" id="product-total-qty">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div id="product-no-results" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded hidden text-sm">
            Mã sản phẩm này hiện không có tồn kho tại bất kỳ vị trí nào.
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    $('#add-product-form').submit(function(e) {
        e.preventDefault();
        const form = $(this);
        const messageDiv = $('#form-message');
        messageDiv.removeClass('text-green-600 text-red-600').text('');

        $.post('api.php?action=add_product', form.serialize(), function(res) {
            if (res.success) {
                messageDiv.text(res.message).addClass('text-green-600');
                form[0].reset();
            } else {
                messageDiv.text(res.message).addClass('text-red-600');
            }
        }, 'json').fail(function() {
            messageDiv.text('Lỗi kết nối máy chủ.').addClass('text-red-600');
        });
    });

    $('#search-product-id-input').on('keypress', function(e) { if(e.which == 13) searchByProduct(); });
});

function searchByProduct() {
    const pid = $('#search-product-id-input').val().trim();
    if (!pid) return;

    $('#product-results-container, #product-no-results').addClass('hidden');

    $.getJSON('api.php?action=search_sku', { product_id: pid }, function(data) {
        if (data && data.length > 0) {
            $('#res-sku').text(pid.toUpperCase());
            $('#res-product-name').text(data[0].product_name || '');
            
            const tbody = $('#product-inventory-results');
            tbody.empty();
            let total = 0;

            data.forEach(item => {
                total += parseInt(item.quantity);
                tbody.append(`
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-3 font-mono font-bold text-gray-700">${item.shelf_id}</td>
                        <td class="p-3 text-right font-medium">${item.quantity}</td>
                    </tr>
                `);
            });

            $('#product-total-qty').text(total);
            $('#product-results-container').removeClass('hidden');
        } else {
            $('#product-no-results').removeClass('hidden');
        }
    });
}
</script>