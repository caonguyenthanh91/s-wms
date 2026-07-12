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

<style>
    .error-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .error-modal-content {
        background-color: white;
        border-radius: 12px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        text-align: center;
        animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .error-modal-content h2 {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 16px;
    }
    .error-modal-content.error h2 {
        color: #dc2626;
    }
    .error-modal-content.success h2 {
        color: #059669;
    }
    .error-modal-content.warning h2 {
        color: #d97706;
    }
    .error-modal-content p {
        font-size: 16px;
        color: #374151;
        margin-bottom: 24px;
        line-height: 1.6;
        white-space: pre-wrap;
    }
    .error-modal-btn {
        color: white;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        border: none;
        transition: background-color 0.2s;
    }
    .error-modal-content.error .error-modal-btn {
        background-color: #dc2626;
    }
    .error-modal-content.error .error-modal-btn:hover {
        background-color: #b91c1c;
    }
    .error-modal-content.success .error-modal-btn {
        background-color: #059669;
    }
    .error-modal-content.success .error-modal-btn:hover {
        background-color: #047857;
    }
    .error-modal-content.warning .error-modal-btn {
        background-color: #d97706;
    }
    .error-modal-content.warning .error-modal-btn:hover {
        background-color: #b45309;
    }
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
    const titles = {
        error: '❌ Lỗi',
        success: '✅ Thành công',
        warning: '⚠️ Cảnh báo'
    };

    $('#error-modal-title').text(title || titles[type]);
    $('#error-modal-message').text(message);
    $('#error-modal-content').removeClass('error success warning').addClass(type);
    $('#error-modal').css('display', 'flex');
}

function showErrorModal(message) {
    showModal(message, 'error');
}

function closeErrorModal() {
    $('#error-modal').css('display', 'none');
}

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
                messageDiv.text('Tạo người dùng thành công').removeClass('text-red-600').addClass('text-green-600');
                $('#add-user-form')[0].reset();
                loadUsers();
            } else {
                messageDiv.text(res.message || 'Lỗi khi tạo tài khoản').removeClass('text-green-600').addClass('text-red-600');
            }
        }, 'json');
    });

    loadUsers();

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
        if (e.key === 'Enter' && $('#error-modal').css('display') !== 'none') {
            closeErrorModal();
        }
    });
});
</script>
