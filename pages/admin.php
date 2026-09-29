<div class="max-w-6xl mx-auto space-y-5">
    <!-- TẠO TÀI KHOẢN -->
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-slate-700 to-slate-900 text-white">
            <h3 class="text-xl font-bold flex items-center gap-2"><span>👥</span> Quản trị Người dùng</h3>
            <p class="text-slate-300 text-sm mt-1">Tạo tài khoản mới, phân quyền và khóa / mở tài khoản.</p>
        </div>
        <form id="add-user-form" class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4" autocomplete="off">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">MSNV / Tên đăng nhập *</label>
                <input name="username" required
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Mật khẩu *</label>
                <input name="password" type="password" required autocomplete="new-password"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Họ tên</label>
                <input name="full_name"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Quyền</label>
                <select name="role"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm bg-white transition">
                    <option value="Staff">Staff</option>
                    <option value="Leader">Leader</option>
                    <!-- <option value="Manager">Manager</option> -->
                    <!-- <option value="Admin">Admin</option> -->
                </select>
            </div>
            <div class="md:col-span-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="submit" class="sm:w-56 py-3 px-4 rounded-xl font-bold text-white bg-green-600 hover:bg-green-700 shadow-md transition">
                    TẠO TÀI KHOẢN
                </button>
                <p id="form-message" class="text-sm font-medium"></p>
            </div>
        </form>
    </div>

    <!-- DANH SÁCH -->
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="font-bold text-gray-800">Danh sách người dùng</h4>
                <p class="text-xs text-gray-500 mt-0.5" id="users-summary">Đang tải...</p>
            </div>
            <div class="relative w-full sm:w-72">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                <input type="text" id="user-search" placeholder="Tìm theo họ tên hoặc MSNV..."
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm transition">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-[11px] font-semibold tracking-wide">
                    <tr>
                        <th class="px-4 py-3">MSNV</th>
                        <th class="px-4 py-3">Họ tên</th>
                        <th class="px-4 py-3">Quyền</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3">Đăng nhập gần nhất</th>
                        <th class="px-4 py-3 text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody id="users-list" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <div id="users-empty" class="hidden px-6 py-12 text-center text-gray-400 text-sm">
            <div class="text-4xl mb-2">🔎</div>Không tìm thấy người dùng phù hợp.
        </div>
        <div class="px-6 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-gray-500" id="page-info"></span>
            <div class="flex items-center gap-1" id="pagination"></div>
        </div>
    </div>
</div>

<style>
    .error-modal-overlay {
        position: fixed; inset: 0; background-color: rgba(0, 0, 0, 0.5);
        display: flex; align-items: center; justify-content: center; z-index: 9999;
    }
    .error-modal-content {
        background-color: white; border-radius: 16px; padding: 32px; max-width: 500px; width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); text-align: center; animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .error-modal-content h2 { font-size: 24px; font-weight: bold; margin-bottom: 16px; }
    .error-modal-content.error h2 { color: #dc2626; }
    .error-modal-content.success h2 { color: #059669; }
    .error-modal-content.warning h2 { color: #d97706; }
    .error-modal-content p { font-size: 16px; color: #374151; margin-bottom: 24px; line-height: 1.6; white-space: pre-wrap; }
    .error-modal-btn {
        color: white; padding: 12px 32px; border-radius: 12px; font-weight: bold; font-size: 16px;
        cursor: pointer; border: none; transition: background-color 0.2s;
    }
    .error-modal-content.error .error-modal-btn { background-color: #dc2626; }
    .error-modal-content.error .error-modal-btn:hover { background-color: #b91c1c; }
    .error-modal-content.success .error-modal-btn { background-color: #059669; }
    .error-modal-content.success .error-modal-btn:hover { background-color: #047857; }
    .error-modal-content.warning .error-modal-btn { background-color: #d97706; }
    .error-modal-content.warning .error-modal-btn:hover { background-color: #b45309; }
</style>

<!-- Universal Modal -->
<div id="error-modal" class="error-modal-overlay" style="display: none;">
    <div id="error-modal-content" class="error-modal-content error">
        <h2 id="error-modal-title">⚠️ Cảnh báo</h2>
        <p id="error-modal-message"></p>
        <button onclick="closeErrorModal()" class="error-modal-btn">OK</button>
    </div>
</div>

<script>
function showModal(message, type = 'error', title = null) {
    const titles = { error: '❌ Lỗi', success: '✅ Thành công', warning: '⚠️ Cảnh báo' };
    $('#error-modal-title').text(title || titles[type]);
    $('#error-modal-message').text(message);
    $('#error-modal-content').removeClass('error success warning').addClass(type);
    $('#error-modal').css('display', 'flex');
}

function showErrorModal(message) { showModal(message, 'error'); }

function closeErrorModal() { $('#error-modal').css('display', 'none'); }

$(document).ready(function() {
    const PAGE_SIZE = 10;
    const ROLES = ['Admin', 'Manager', 'Leader', 'Staff'];
    const ROLE_BADGE = {
        Admin: 'bg-red-100 text-red-800', Manager: 'bg-purple-100 text-purple-800',
        Leader: 'bg-blue-100 text-blue-800', Staff: 'bg-gray-100 text-gray-700'
    };
    let allUsers = [];
    let page = 1;

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Bỏ dấu tiếng Việt để tìm "nguyen" khớp "Nguyễn"
    const norm = v => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase().trim();
    const selectCls = 'px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 outline-none';

    function filtered() {
        const kw = norm($('#user-search').val());
        if (!kw) return allUsers;
        return allUsers.filter(u => norm(u.username).includes(kw) || norm(u.full_name).includes(kw));
    }

    function render() {
        const list = filtered();
        const totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
        page = Math.min(Math.max(1, page), totalPages);
        const start = (page - 1) * PAGE_SIZE;
        const rows = list.slice(start, start + PAGE_SIZE);

        const active = allUsers.filter(u => u.status == 1).length;
        $('#users-summary').text(`${allUsers.length} tài khoản · ${active} đang hoạt động`);

        $('#users-list').html(rows.map(u => `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono font-bold text-gray-800">${esc(u.username)}</td>
                <td class="px-4 py-3 text-gray-700">${esc(u.full_name) || '<span class="text-gray-300">—</span>'}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full ${(ROLE_BADGE[u.role] || '').split(' ')[0]}"></span>
                        <select class="role-select ${selectCls}" data-id="${u.id}">
                            ${ROLES.map(r => `<option value="${r}" ${u.role === r ? 'selected' : ''}>${r}</option>`).join('')}
                        </select>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <select class="status-select ${selectCls} ${u.status == 1 ? 'text-green-700' : 'text-red-700'}" data-id="${u.id}">
                        <option value="1" ${u.status == 1 ? 'selected' : ''}>● Active</option>
                        <option value="0" ${u.status == 0 ? 'selected' : ''}>● Locked</option>
                    </select>
                </td>
                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${esc(u.last_login) || '—'}</td>
                <td class="px-4 py-3 text-center">
                    <button data-id="${u.id}" class="update-user px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-sm transition">LƯU</button>
                </td>
            </tr>`).join(''));

        $('#users-empty').toggleClass('hidden', list.length > 0);
        $('#page-info').text(list.length ? `Hiển thị ${start + 1}–${start + rows.length} / ${list.length}` : '');
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const btn = (label, target, { active = false, disabled = false } = {}) =>
            `<button type="button" data-page="${target}" ${disabled ? 'disabled' : ''}
                class="min-w-[2.25rem] h-9 px-2 rounded-lg text-sm font-semibold transition
                ${active ? 'bg-blue-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}
                disabled:opacity-30 disabled:cursor-not-allowed">${label}</button>`;
        // Hiện tối đa 5 số trang quanh trang hiện tại
        let from = Math.max(1, page - 2), to = Math.min(totalPages, from + 4);
        from = Math.max(1, to - 4);
        let html = btn('‹', page - 1, { disabled: page <= 1 });
        if (from > 1) html += btn(1, 1) + (from > 2 ? '<span class="px-1 text-gray-400">…</span>' : '');
        for (let p = from; p <= to; p++) html += btn(p, p, { active: p === page });
        if (to < totalPages) html += (to < totalPages - 1 ? '<span class="px-1 text-gray-400">…</span>' : '') + btn(totalPages, totalPages);
        html += btn('›', page + 1, { disabled: page >= totalPages });
        $('#pagination').html(totalPages > 1 ? html : '');
    }

    function loadUsers() {
        $.getJSON('api.php?action=get_users', function(data) {
            allUsers = Array.isArray(data) ? data : [];
            render();
        }).fail(() => $('#users-summary').text('Lỗi tải danh sách người dùng.'));
    }

    $('#user-search').on('input', function() { page = 1; render(); });
    $('#pagination').on('click', 'button[data-page]', function() { page = Number($(this).data('page')); render(); });

    $('#users-list').on('click', '.update-user', function() {
        const id = $(this).data('id');
        const role = $(`select.role-select[data-id='${id}']`).val();
        const status = $(`select.status-select[data-id='${id}']`).val();
        $.post('api.php?action=update_user', { id, role, status }, function(res) {
            if (res.success) {
                showModal('Cập nhật thành công', 'success');
                loadUsers();
            } else {
                showModal(res.message || 'Lỗi khi cập nhật user', 'error');
            }
        }, 'json');
    });

    $('#add-user-form').submit(function(e) {
        e.preventDefault();
        const messageDiv = $('#form-message');
        messageDiv.text('');
        $.post('api.php?action=add_user', $(this).serialize(), function(res) {
            if (res.success) {
                messageDiv.text('✅ Tạo người dùng thành công').removeClass('text-red-600').addClass('text-green-600');
                $('#add-user-form')[0].reset();
                loadUsers();
            } else {
                messageDiv.text(res.message || 'Lỗi khi tạo tài khoản').removeClass('text-green-600').addClass('text-red-600');
            }
        }, 'json');
    });

    loadUsers();

    $(document).on('keydown', function(e) {
        if ((e.key === 'Escape' || e.key === 'Enter') && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
    });
});
</script>
