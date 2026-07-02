<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- BÊN TRÁI: ĐĂNG KÝ MÃ KỆ MỚI -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md h-fit">
            <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center">
                <span class="mr-2">📍</span> Đăng ký Mã Kệ mới
            </h3>
            <form id="add-shelf-form" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Level 0 (Mặc định)</label>
                        <input type="text" name="level0_val" id="l0" value="B032" readonly class="w-full bg-gray-50 border p-2 rounded text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Level 1 (Chữ cái)</label>
                        <input type="text" name="level1_val" id="l1" required maxlength="1" pattern="[A-Za-z]" placeholder="VD: A" class="w-full border p-2 rounded text-sm font-mono uppercase">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Level 2 (Số - 2 ký tự)</label>
                        <input type="text" name="level2_val" id="l2" required maxlength="2" pattern="[0-9]{2}" placeholder="VD: 01" class="w-full border p-2 rounded text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Level 3 (Số - 2 ký tự)</label>
                        <input type="text" name="level3_val" id="l3" required maxlength="2" pattern="[0-9]{2}" placeholder="VD: 01" class="w-full border p-2 rounded text-sm font-mono">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Level 4 (Không bắt buộc)</label>
                    <input type="text" name="level4_val" id="l4" placeholder="VD: Ghi chú" class="w-full border p-2 rounded text-sm">
                </div>

                <div class="p-3 bg-blue-50 rounded border border-blue-200">
                    <label class="block text-[10px] font-bold text-blue-500 uppercase">Mã vị trí sẽ tạo:</label>
                    <input type="text" name="shelf_id" id="generated_shelf_id" readonly required
                           class="w-full bg-transparent border-none p-0 text-lg font-bold text-blue-800 outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sức chứa (Capacity)</label>
                    <input type="number" name="capacity" value="100" class="w-full border p-2 rounded text-sm">
                </div>
                
                <button type="submit" class="w-full bg-slate-800 text-white py-3 rounded font-bold hover:bg-black transition">
                    LƯU CẤU HÌNH KỆ
                </button>
                <p id="form-message" class="mt-3 text-center text-sm"></p>
            </form>
        </div>
    </section>

    <!-- BÊN PHẢI: TRA CỨU MÃ KỆ -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-bold mb-4 text-gray-800 flex items-center">
                <span class="mr-2">🔍</span> Tra cứu thông tin kệ
            </h3>
            <div class="flex gap-2">
                <input type="text" id="search-shelf-id-input" placeholder="Nhập mã kệ (VD: A-01-01)..." 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none uppercase font-mono text-sm">
                <button onclick="searchByShelf()" class="bg-green-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-green-700 transition text-sm">
                    Tìm kiếm
                </button>
            </div>
        </div>

        <div id="shelf-results-container" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="p-4 border-b bg-green-50">
                <h4 class="font-bold text-green-800 uppercase font-mono">Kệ: <span id="res-shelf-id"></span></h4>
            </div>
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold">
                        <th class="p-3 border-b">Mã Sản Phẩm</th>
                        <th class="p-3 border-b text-right">Số lượng</th>
                    </tr>
                </thead>
                <tbody id="shelf-inventory-results"></tbody>
            </table>
        </div>
        <div id="shelf-no-results" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded hidden text-sm text-yellow-700">
            Kệ này hiện đang trống hoặc không tồn tại.
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Tự động tạo mã kệ khi nhập các level
    $('#l1, #l2, #l3, #l4').on('input', function() {
        const l1 = $('#l1').val().toUpperCase();
        const l2 = $('#l2').val();
        const l3 = $('#l3').val();
        const l4 = $('#l4').val().trim();
        
        if (l1 && l2 && l3) {
            let fullId = `${l1}-${l2}-${l3}`;
            if (l4) fullId += `-${l4}`;
            $('#generated_shelf_id').val(fullId);
        } else {
            $('#generated_shelf_id').val('');
        }
    });

    $('#search-shelf-id-input').on('keypress', function(e) { if(e.which == 13) searchByShelf(); });

    $('#add-shelf-form').submit(function(e) {
        e.preventDefault();
        const sid = $('#generated_shelf_id').val();
        const messageDiv = $('#form-message');
        messageDiv.removeClass('text-green-600 text-red-600').text('');

        // Check duplicate trước khi lưu
        $.post('api.php?action=check_shelf', { shelf_id: sid }, function(check) {
            if (check.success) {
                messageDiv.text('⚠️ Lỗi: Mã kệ này đã tồn tại trong hệ thống!').addClass('text-red-600');
            } else {
                // Nếu chưa tồn tại thì tiến hành lưu
                $.post('api.php?action=add_shelf', $('#add-shelf-form').serialize(), function(res) {
                    if(res.success) {
                        messageDiv.html('<span class="text-green-600 font-bold">✅ Đã thêm kệ thành công!</span>');
                        $('#add-shelf-form')[0].reset();
                        $('#generated_shelf_id').val(''); // Xóa thủ công mã đã tạo
                        $('#l0').val('B032'); // Reset level0 mặc định
                    } else {
                        messageDiv.text(res.message).addClass('text-red-600');
                    }
                }, 'json');
            }
        }, 'json');
    });
});

function searchByShelf() {
    const sid = $('#search-shelf-id-input').val().trim();
    if (!sid) return;

    $('#shelf-results-container, #shelf-no-results').addClass('hidden');

    $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: sid }, function(data) {
        if (data && data.length > 0) {
            $('#res-shelf-id').text(sid.toUpperCase());
            const tbody = $('#shelf-inventory-results');
            tbody.empty();

            data.forEach(item => {
                tbody.append(`
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-3 font-mono font-bold text-gray-700">${item.product_id}</td>
                        <td class="p-3 text-right font-medium">${item.quantity}</td>
                    </tr>
                `);
            });
            $('#shelf-results-container').removeClass('hidden');
        } else {
            $('#shelf-no-results').removeClass('hidden');
        }
    });
}
</script>