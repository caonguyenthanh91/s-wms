<?php
require_once __DIR__ . '/../config/session_init.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Ban khong co quyen truy cap trang nay. Can role: Leader tro len.</div>';
    return;
}
?>

<div class="max-w-6xl mx-auto">
    <div class="bg-white p-6 rounded shadow mb-6">
        <h3 id="dashboard-title" class="text-xl font-bold mb-4 text-blue-800">Tổng quan Kho: Đang tải...</h3>
        <div id="level0-map" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Level 1 (Khu vực) cards will be loaded here -->
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
        const activeShelves = shelves.filter(s => (s.status || 'Active') !== 'Deactive');
        const totalSkusInWarehouse = activeShelves.reduce((sum, shelf) => sum + (parseInt(shelf.sku_count) || 0), 0);

        // Cập nhật tiêu đề theo yêu cầu
        $('#dashboard-title').text(`Tổng quan Kho ${warehouse}: ${activeShelves.length} kệ đang hoạt động.`);

        // Cấp 0: Group by level1_val (Khu vực)
        const level1Groups = {};
        activeShelves.forEach(shelf => {
            const key = shelf.level1_val;
            if (!key) return;
            if (!level1Groups[key]) {
                level1Groups[key] = { shelves: [], total_sku: 0 };
            }
            level1Groups[key].shelves.push(shelf);
            level1Groups[key].total_sku += (parseInt(shelf.sku_count) || 0);
        });

        const level1Keys = Object.keys(level1Groups).sort();
        const container = $('#level0-map');
        container.empty();

        // Render Cấp 0
        level1Keys.forEach(l1Key => {
            const group = level1Groups[l1Key];
            const totalSku = group.total_sku;

            // Logic màu sắc: 0 SKU -> xanh, > 0 SKU -> vàng
            let cls = totalSku > 0
                ? 'bg-yellow-100 text-yellow-800 border-yellow-200'
                : 'bg-green-100 text-green-800 border-green-200';

            container.append(`
                <div class="p-6 rounded-lg shadow-sm border ${cls} text-center cursor-pointer hover:shadow-md transition-all" data-l1="${l1Key}">
                    <div class="text-sm font-bold uppercase mb-1">Khu vực ${l1Key}</div>
                    <div class="text-3xl font-black">${totalSku}</div>
                    <div class="text-[10px] mt-2 opacity-70">Tổng số loại hàng (SKU)</div>
                </div>`);
        });

        // Cấp 1: Xử lý sự kiện click vào thẻ Khu vực
        $('#level0-map').on('click','div[data-l1]', function(){
            const l1key = $(this).data('l1');
            const detail = $('#level0-detail');
            detail.removeClass('hidden');
            detail.html('<div class="text-sm text-gray-500 animate-pulse">Đang tải sơ đồ chi tiết...</div>');

            const itemsInL1 = level1Groups[l1key].shelves;
            const role = '<?php echo $role; ?>';
            const canManage = ['Admin', 'Leader', 'Manager'].includes(role);

            // Group by level2_val (Dãy kệ)
            const level2Groups = {};
            itemsInL1.forEach(shelf => {
                const key = shelf.level2_val;
                if (!key) return;
                if (!level2Groups[key]) level2Groups[key] = [];
                level2Groups[key].push(shelf);
            });

            const sortedL2Keys = Object.keys(level2Groups).sort((a, b) => b.localeCompare(a, undefined, {numeric: true}));

            let html = '<div class="flex flex-col gap-6">';
            sortedL2Keys.forEach(l2 => {
                html += `
                    <div class="border-b border-gray-100 pb-2">
                        <div class="text-[10px] font-bold text-gray-400 mb-2 uppercase italic">Dãy kệ: ${l2}</div>
                        <div class="flex flex-nowrap gap-3 overflow-x-auto pb-4 custom-scrollbar">
                `;
                
                // Sắp xếp Khung kệ theo level3_val tăng dần
                level2Groups[l2].sort((a, b) => a.level3_val.localeCompare(b.level3_val, undefined, {numeric: true})).forEach(s => {
                    const isDeactive = s.status === 'Deactive';
                    const skuCount = parseInt(s.sku_count) || 0;
                    const hasStock = !isDeactive && skuCount > 0;
                    
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
                                <div class="text-[9px] mt-0.5 whitespace-nowrap">${isDeactive ? 'LOCKED' : 'SKU: <span class="font-bold">'+ skuCount +'</span>'}</div>
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
