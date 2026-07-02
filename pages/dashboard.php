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
        <div id="level0-detail" class="overflow-x-auto">
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
        $('#dashboard-title').text(`Sức chứa kho ${warehouse}: Tổng kệ: ${totalActive}, đã dùng ${usedShelves} (${totalPercent}%).`);

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

        // Hiển thị toàn bộ sơ đồ chi tiết cho tất cả các khu vực
        const detail = $('#level0-detail');
        detail.empty();
        
        // Sử dụng items-end để căn chỉnh các khu vực từ dưới lên (Dòng 01 của các khu luôn thẳng hàng nhau)
        let fullMapHtml = '<div class="flex flex-row flex-nowrap gap-8 items-end py-4">';
        
        keys.sort().forEach(l1key => {
            const items = l1[l1key];
            
            // Group by level2_val (Dòng) cho từng khu vực
            const rows = {};
            items.forEach(s => {
                if (!rows[s.level2_val]) rows[s.level2_val] = [];
                rows[s.level2_val].push(s);
            });

            // Sắp xếp Dòng giảm dần để Dòng 1 ở dưới cùng
            const sortedL2Keys = Object.keys(rows).sort((a, b) => b.localeCompare(a, undefined, {numeric: true}));

            fullMapHtml += `
                <div class="border p-3 rounded-lg bg-gray-50 shadow-sm flex-shrink-0">
                    <div class="text-center font-bold text-gray-600 mb-3 border-b pb-1">KHU ${l1key}</div>
                    <div class="flex flex-col gap-1">`;

            sortedL2Keys.forEach(l2 => {
                fullMapHtml += `<div class="flex flex-row gap-1 justify-start">`;
                
                // Sắp xếp cột (Level 3) tăng dần từ trái sang phải
                rows[l2].sort((a, b) => a.level3_val.localeCompare(b.level3_val, undefined, {numeric: true})).forEach(s => {
                    const isDeactive = s.status === 'Deactive';
                    const hasStock = !isDeactive && (s.sku_count && parseInt(s.sku_count) > 0);
                    
                    let colorCls = isDeactive ? 'bg-gray-600 border-gray-700' : (hasStock ? 'bg-red-200 border-red-300' : 'bg-green-200 border-green-300');
                    const dblClickAction = hasStock ? `ondblclick="window.open('?page=inventory&shelf_id=${s.shelf_id}', '_blank')"` : '';
                    const cursorCls = isDeactive ? 'cursor-not-allowed' : (hasStock ? 'cursor-pointer' : 'cursor-help');
                    const statusLabel = isDeactive ? 'TRẠNG THÁI: TẠM KHÓA' : `Stock: ${s.sku_count || 0}`;
                    
                    fullMapHtml += `
                        <div class="w-4 h-4 border ${colorCls} rounded-sm transition-transform hover:scale-150 ${cursorCls}" 
                             ${dblClickAction}
                             title="Kệ: ${s.shelf_id} | ${statusLabel}">
                        </div>`;
                });
                fullMapHtml += `</div>`;
            });

            fullMapHtml += `</div></div>`;
        });
        
        fullMapHtml += '</div>';
        detail.html(fullMapHtml);
    });
});
</script>
