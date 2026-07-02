<div class="max-w-5xl mx-auto">
    <div class="bg-white p-6 rounded shadow mb-6">
        <h3 class="text-xl font-bold mb-4">Quản Trị Người Dùng</h3>
        <form id="add-user-form" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input name="username" placeholder="Tên đăng nhập" class="border p-2 rounded" required>
            <input name="password" placeholder="Mật khẩu (sẽ MD5)" type="password" class="border p-2 rounded" required>
            <select name="role" class="border p-2 rounded">
                <option value="Staff">Staff</option>
                <option value="Leader">Leader</option>
                <!-- <option value="Manager">Manager</option> -->
                <!-- <option value="Admin">Admin</option> -->
            </select>
            <input name="full_name" placeholder="Họ tên" class="border p-2 rounded md:col-span-2">
            <button class="bg-blue-600 text-white py-2 rounded md:col-span-1" type="submit">Tạo tài khoản</button>
        </form>
        <p id="form-message" class="mt-3 text-sm"></p>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h3 class="text-lg font-bold mb-3">Danh sách người dùng</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Username</th>
                        <th class="px-4 py-2 text-left">Họ tên</th>
                        <th class="px-4 py-2 text-left">Role</th>
                        <th class="px-4 py-2 text-left">Trạng thái</th>
                        <th class="px-4 py-2 text-left">Last login</th>
                        <th class="px-4 py-2 text-left">Hành động</th>
                    </tr>
                </thead>
                <tbody id="users-list" class="bg-white divide-y divide-gray-200"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function loadUsers() {
        $.getJSON('api.php?action=get_users', function(data) {
            const c = $('#users-list'); c.empty();
            data.forEach(u => {
                c.append(`
                    <tr>
                        <td class="px-4 py-3 font-mono">${u.username}</td>
                        <td class="px-4 py-3">${u.full_name || ''}</td>
                        <td class="px-4 py-3">
                            <select class="role-select border px-2 py-1 rounded" data-id="${u.id}">
                                <option value="Admin" ${u.role==='Admin'?'selected':''}>Admin</option>
                                <option value="Leader" ${u.role==='Leader'?'selected':''}>Leader</option>
                                <option value="Manager" ${u.role==='Manager'?'selected':''}>Manager</option>
                                <option value="Staff" ${u.role==='Staff'?'selected':''}>Staff</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <select class="status-select border px-2 py-1 rounded" data-id="${u.id}">
                                <option value="1" ${u.status==1?'selected':''}>Active</option>
                                <option value="0" ${u.status==0?'selected':''}>Locked</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">${u.last_login || '-'}</td>
                        <td class="px-4 py-3">
                            <button data-id="${u.id}" class="update-user bg-green-600 text-white px-3 py-1 rounded">Lưu</button>
                        </td>
                    </tr>
                `);
            });
        });
    }

    $('#users-list').on('click', '.update-user', function() {
        const id = $(this).data('id');
        const role = $(`select.role-select[data-id='${id}']`).val();
        const status = $(`select.status-select[data-id='${id}']`).val();
        $.post('api.php?action=update_user', { id, role, status }, function(res) {
            if (res.success) {
                alert('Cập nhật thành công');
                loadUsers();
            } else {
                alert(res.message || 'Lỗi khi cập nhật user');
            }
        }, 'json');
    });

    $('#add-user-form').submit(function(e) {
        e.preventDefault();
        const messageDiv = $('#form-message');
        messageDiv.text('');
        $.post('api.php?action=add_user', $(this).serialize(), function(res) {
            if (res.success) {
                messageDiv.text('Tạo người dùng thành công').removeClass('text-red-600').addClass('text-green-600');
                $('#add-user-form')[0].reset();
                loadUsers();
            } else {
                messageDiv.text(res.message || 'Lỗi khi tạo tài khoản').removeClass('text-green-600').addClass('text-red-600');
            }
        }, 'json');
    });

    loadUsers();
});
</script>
