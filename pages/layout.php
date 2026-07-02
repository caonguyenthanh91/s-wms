<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Ban khong co quyen truy cap trang nay. Can role: Leader tro len.</div>';
    return;
}
?>

<div class="max-w-6xl mx-auto">
    <div class="bg-white p-6 rounded shadow mb-6">
        <h3 id="dashboard-title" class="text-xl font-bold mb-4 text-blue-800">Sức chứa kho B032: Đang tải...</h3>
        <div id="level0-map" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Level0 cells -->
        </div>
    </div>
    <div class="bg-white p-6 rounded shadow">
        <h4 class="font-bold mb-3">Chi tiết khu vực</h4>
        <div id="level0-detail" class="hidden">
            <!-- detail canvas or map -->
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $.getJSON('api.php?action=get_shelves', { all: 1 }, function(shelves) {
        const warehouse = "B032";
        const activeShelves = shelves.filter(s => s.status !== 'Deactive');
        const totalActive = activeShelves.length;
        const usedShelves = activeShelves.filter(s => (parseInt(s.current_usage) || 0) > 0).length;
        const totalPercent = totalActive > 0 ? Math.round((usedShelves / totalActive) * 100) : 0;

        // Cập nhật tiêu đề theo yêu cầu
        $('#dashboard-title').text(`Sức chứa kho ${warehouse}: Tổng kệ: ${totalActive}, đã dùng ${usedShelves} (${totalPercent}%) của level0.`);

        // Group by level1_val
        const l1 = {};
        shelves.forEach(s => { 
            if (!l1[s.level1_val]) l1[s.level1_val] = []; 
            l1[s.level1_val].push(s);
        });

        const keys = Object.keys(l1).sort();
        const container = $('#level0-map');
        container.empty();

        keys.forEach(k => {
            const items = l1[k];
            const usedCount = items.filter(s => (parseInt(s.current_usage) || 0) > 0).length;
            const percent = items.length > 0 ? Math.round((usedCount / items.length) * 100) : 0;

            // Logic màu sắc: <50% xanh nhạt, <75% vàng nhạt, >=75% đỏ nhạt
            let cls = 'bg-green-100 text-green-800 border-green-200';
            if (percent >= 75) cls = 'bg-red-100 text-red-800 border-red-200';
            else if (percent >= 50) cls = 'bg-yellow-100 text-yellow-800 border-yellow-200';

            container.append(`
                <div class="p-6 rounded-lg shadow-sm border ${cls} text-center cursor-pointer hover:shadow-md transition-all" data-l1="${k}">
                    <div class="text-sm font-bold uppercase mb-1">Khu ${k}</div>
                    <div class="text-3xl font-black">${percent}%</div>
                    <div class="text-[10px] mt-2 opacity-70">${usedCount}/${items.length} kệ có hàng</div>
                </div>`);
        });

        $('#level0-map').on('click','div[data-l1]', function(){
            const l1key = $(this).data('l1');
            // show detail (load subset)
            const detail = $('#level0-detail'); 
            detail.removeClass('hidden'); 
            detail.html('<div class="text-sm text-gray-500 animate-pulse">Đang tải sơ đồ chi tiết...</div>');

            const items = l1[l1key];
            const role = '<?php echo $role; ?>';
            const canManage = ['Admin', 'Leader', 'Manager'].includes(role);

            // Group by level2_val (Dòng)
            const rows = {};
            items.forEach(s => {
                if (!rows[s.level2_val]) rows[s.level2_val] = [];
                rows[s.level2_val].push(s);
            });

            // Sắp xếp Dòng giảm dần (Max trên cùng, Min dưới cùng)
            const sortedL2Keys = Object.keys(rows).sort((a, b) => b.localeCompare(a, undefined, {numeric: true}));

            let html = '<div class="flex flex-col gap-6">';
            sortedL2Keys.forEach(l2 => {
                html += `
                    <div class="border-b border-gray-100 pb-2">
                        <div class="text-[10px] font-bold text-gray-400 mb-2 uppercase italic">Dãy/Tầng: ${l2}</div>
                        <div class="flex flex-nowrap gap-3 overflow-x-auto pb-4 custom-scrollbar">
                `;
                
                // Sắp xếp cột theo level3_val tăng dần trong mỗi dòng
                rows[l2].sort((a, b) => a.level3_val.localeCompare(b.level3_val, undefined, {numeric: true})).forEach(s => {
                    const isDeactive = s.status === 'Deactive';
                    const hasStock = !isDeactive && (parseInt(s.current_usage) > 0);
                    
                    let colorCls = isDeactive ? 'bg-gray-600 border-gray-700 text-gray-200 opacity-50' : (hasStock ? 'bg-yellow-50 border-yellow-400 text-yellow-800' : 'bg-green-50 border-green-400 text-green-800');
                    
                    let actionButtons = '';
                    if (canManage && !isDeactive) {
                        actionButtons = `
                            <div class="flex justify-between items-center mt-2 pt-1 border-t border-black/5">
                                <a href="?page=inbound&shelf_id=${s.shelf_id}" class="text-blue-600 hover:bg-blue-100 px-2 rounded font-bold text-xl" title="Nhập kho nhanh">+</a>
                                <a href="?page=outbound&shelf_id=${s.shelf_id}" class="text-red-600 hover:bg-red-100 px-2 rounded font-bold text-xl" title="Xuất kho nhanh">-</a>
                            </div>
                        `;
                    }

                    html += `
                        <div class="min-w-[90px] border-2 p-1.5 rounded shadow-sm ${colorCls} flex flex-col justify-between hover:scale-105 transition-transform">
                            <div class="text-center">
                                <div class="text-[10px] font-mono font-bold truncate">${s.shelf_id}</div>
                                <div class="text-[9px] mt-0.5 whitespace-nowrap">${isDeactive ? 'LOCKED' : 'Stock: <span class="font-bold">'+(s.sku_count || 0)+'</span>'}</div>
                            </div>
                            ${actionButtons}
                        </div>
                    `;
                });
                html += `</div></div>`;
            });
            html += '</div>';
            detail.html(html);
        });
    });
});
</script>
