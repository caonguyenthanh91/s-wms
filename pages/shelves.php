<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-5 bg-gradient-to-r from-slate-700 to-slate-900 text-white">
            <h3 class="text-xl font-bold flex items-center gap-2">
                <span>📍</span> Quản lý Mã Vị Trí (Kệ)
            </h3>
            <p class="text-slate-300 text-sm mt-1">Nhập mã kệ đầy đủ (VD: <span class="font-mono">SMC_4--P01-02-03-04-05</span>) và nhấn <kbd class="px-1.5 py-0.5 bg-white/20 rounded text-xs font-mono">Enter</kbd>.</p>
        </div>

        <form id="shelf-form" class="p-6 space-y-5" autocomplete="off">
            <!-- Mã kệ -->
            <div>
                <label for="shelf_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Mã Kệ (Full) *</label>
                <div class="relative">
                    <input type="text" id="shelf_id" name="shelf_id" required placeholder="Nhập mã kệ rồi nhấn Enter..."
                           class="w-full pl-4 pr-24 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none uppercase font-mono text-base transition">
                    <button type="button" id="btn-reset-shelf" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold transition">
                        Đổi mã
                    </button>
                </div>
                <div id="shelf-status" class="hidden mt-2 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"></div>
            </div>

            <fieldset id="detail-fields" disabled class="space-y-5 transition disabled:opacity-50">
                <!-- Thông tin tách từ mã kệ -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">Thông tin tách từ mã kệ</div>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 text-center">
                        <div class="col-span-3 sm:col-span-1">
                            <div class="text-[10px] text-gray-400 uppercase">Tên kệ</div>
                            <div id="p-shelf_name" class="font-mono font-bold text-gray-800 truncate">—</div>
                        </div>
                        <div><div class="text-[10px] text-gray-400 uppercase">Level 0</div><div id="p-level0_val" class="font-mono font-bold text-gray-800">—</div></div>
                        <div><div class="text-[10px] text-gray-400 uppercase">Level 1</div><div id="p-level1_val" class="font-mono font-bold text-gray-800">—</div></div>
                        <div><div class="text-[10px] text-gray-400 uppercase">Level 2</div><div id="p-level2_val" class="font-mono font-bold text-gray-800">—</div></div>
                        <div><div class="text-[10px] text-gray-400 uppercase">Level 3</div><div id="p-level3_val" class="font-mono font-bold text-gray-800">—</div></div>
                        <div><div class="text-[10px] text-gray-400 uppercase">Level 4</div><div id="p-level4_val" class="font-mono font-bold text-gray-800">—</div></div>
                    </div>
                </div>

                <div>
                    <label for="capacity" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Sức Chứa (Capacity) *</label>
                    <input type="number" id="capacity" name="capacity" required min="1" step="1" value="100"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
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
    const PARTS = ['shelf_name', 'level0_val', 'level1_val', 'level2_val', 'level3_val', 'level4_val'];
    let mode = null; // 'add' | 'update'

    function setMessage(text, ok) {
        $('#form-message').removeClass('text-green-600 text-red-600')
            .addClass(ok ? 'text-green-600' : 'text-red-600').text(text || '');
    }

    function resetForm() {
        mode = null;
        $('#shelf-form')[0].reset();
        PARTS.forEach(k => $('#p-' + k).text('—'));
        $('#shelf_id').prop('readonly', false).removeClass('bg-gray-100 text-gray-500').focus();
        $('#btn-reset-shelf, #shelf-status').addClass('hidden');
        $('#detail-fields').prop('disabled', true);
        $('#btn-submit').text('LƯU').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-gray-400 shadow-md transition');
    }

    function applyMode(exists, parsed, data) {
        mode = exists ? 'update' : 'add';
        $('#shelf_id').prop('readonly', true).addClass('bg-gray-100 text-gray-500');
        $('#btn-reset-shelf').removeClass('hidden');
        $('#detail-fields').prop('disabled', false);
        PARTS.forEach(k => $('#p-' + k).text(parsed[k] || '—'));

        const status = $('#shelf-status').removeClass('hidden bg-yellow-100 text-yellow-800 bg-green-100 text-green-800');
        const btn = $('#btn-submit');
        if (exists) {
            status.addClass('bg-yellow-100 text-yellow-800').html('<span>●</span> Mã kệ đã đăng ký');
            btn.text('CẬP NHẬT').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-amber-500 hover:bg-amber-600 shadow-md transition');
            $('#capacity').val(data.capacity || 100);
        } else {
            status.addClass('bg-green-100 text-green-800').html('<span>●</span> Mã kệ chưa đăng ký');
            btn.text('ĐĂNG KÝ').attr('class', 'w-full py-3 px-4 rounded-xl font-bold text-white bg-green-600 hover:bg-green-700 shadow-md transition');
            $('#capacity').val(100);
        }
        $('#capacity').focus();
    }

    function checkShelf() {
        const sid = $('#shelf_id').val().trim().toUpperCase();
        if (!sid) return;
        $('#shelf_id').val(sid);
        setMessage('');
        $.getJSON('api.php?action=get_shelf_detail', { shelf_id: sid }, function(res) {
            if (res.success) applyMode(res.exists, res.parsed, res.data);
            else setMessage(res.message || 'Không kiểm tra được mã kệ.', false);
        }).fail(function() { setMessage('Lỗi kết nối máy chủ.', false); });
    }

    $('#shelf_id').on('keydown', function(e) {
        if (e.which === 13) { e.preventDefault(); if (!mode) checkShelf(); }
    });
    $('#btn-reset-shelf').on('click', function() { setMessage(''); resetForm(); });

    $('#shelf-form').submit(function(e) {
        e.preventDefault();
        if (!mode) { checkShelf(); return; }
        const action = mode === 'update' ? 'update_shelf' : 'add_shelf';
        $.post('api.php?action=' + action, $(this).serialize(), function(res) {
            setMessage(res.message, res.success);
            if (res.success) resetForm();
        }, 'json').fail(function() { setMessage('Lỗi kết nối máy chủ.', false); });
    });
});
</script>
