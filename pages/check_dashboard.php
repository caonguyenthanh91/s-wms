<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'], true)) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}

$isAdmin = ($role === 'Admin');
?>

<div class="max-w-7xl mx-auto pb-8">
    <div class="bg-white rounded-lg shadow-md p-4 md:p-5 mb-5">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
            <div>
                <div class="text-xs font-bold text-sky-600 uppercase tracking-wide">Kiểm kê tồn kho</div>
                <h3 class="text-xl font-bold text-gray-800">Dashboard tiến độ kiểm kê</h3>
                <div class="text-sm text-gray-500 mt-1">Theo dõi số vị trí đã/chưa kiểm kê và các mã hàng lệch tồn hệ thống.</div>
            </div>
            <div class="flex items-center gap-2">
                <div id="cd-status" class="text-xs text-gray-500">Đang tải dữ liệu...</div>
                <div class="flex flex-col gap-1">
                    <div class="relative">
                        <button type="button" id="cd-history-btn" onclick="cdToggleHistoryPopover()"
                            class="w-full px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold whitespace-nowrap">
                            <i class="fas fa-file-excel"></i> Tải lịch sử kiểm kê
                        </button>
                        <div id="cd-history-popover" class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg p-3 z-20 w-56">
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Chọn tháng</label>
                            <input type="month" id="cd-history-month" class="w-full border border-gray-300 rounded px-2 py-1 text-sm mb-2">
                            <button type="button" onclick="cdExportHistory()" id="cd-history-export-btn"
                                class="w-full px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">
                                Tải xuống
                            </button>
                            <div id="cd-history-export-status" class="text-[11px] text-gray-500 mt-1"></div>
                        </div>
                    </div>
                    <button type="button" onclick="cdLoadAll()" class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold whitespace-nowrap">
                        <i class="fas fa-rotate"></i> Tải lại
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI chính -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-sky-500">
            <div class="text-xs font-bold text-gray-500 uppercase">Vị trí có hàng</div>
            <div id="cd-kpi-stock" class="text-3xl font-black text-sky-700 mt-1">-</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-emerald-500">
            <div class="text-xs font-bold text-gray-500 uppercase">Đã kiểm kê</div>
            <div id="cd-kpi-checked" class="text-3xl font-black text-emerald-700 mt-1">-</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-amber-500">
            <div class="text-xs font-bold text-gray-500 uppercase">Còn lại</div>
            <div id="cd-kpi-remaining" class="text-3xl font-black text-amber-700 mt-1">-</div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <div class="text-xs font-bold text-gray-500 uppercase">Lệch tồn hệ thống</div>
            <div id="cd-kpi-mismatch" class="text-3xl font-black text-red-700 mt-1">-</div>
        </div>
    </div>

    <!-- Chi tiết theo khu vực (level0_val) -->
    <div class="bg-white rounded-lg shadow-md p-4 md:p-5 mb-5">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h4 class="font-bold text-gray-800">Chi tiết theo khu vực</h4>
            <div class="text-xs text-gray-500">Bấm số ở cột "Lệch tồn" để lọc bảng bên dưới theo khu vực đó</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[11px] font-bold">
                        <th class="p-2 text-left border-b">Khu vực</th>
                        <th class="p-2 text-right border-b">Có hàng</th>
                        <th class="p-2 text-right border-b">Đã kiểm</th>
                        <th class="p-2 text-right border-b">Còn lại</th>
                        <th class="p-2 text-right border-b">Lệch tồn</th>
                        <th class="p-2 text-left border-b">Tiến độ</th>
                    </tr>
                </thead>
                <tbody id="cd-zone-body">
                    <tr><td colspan="6" class="p-3 text-center text-gray-400 text-xs">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tìm kiếm + danh sách lệch tồn -->
    <div class="bg-white rounded-lg shadow-md p-4 md:p-5">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h4 class="font-bold text-gray-800">Danh sách kiểm kê bị lệch tồn hệ thống</h4>
            <div id="cd-zone-chip-wrap" class="hidden">
                <span class="inline-flex items-center gap-1 bg-sky-100 text-sky-800 text-xs font-semibold px-2 py-1 rounded-full">
                    Khu vực: <span id="cd-zone-chip-label" class="font-mono"></span>
                    <button type="button" onclick="cdClearZoneFilter()" class="ml-1 text-sky-600 hover:text-sky-900">✕</button>
                </span>
            </div>
        </div>

        <div class="flex gap-2 mb-3">
            <input type="text" id="cd-search-input" placeholder="Tìm theo mã hàng..."
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none uppercase font-mono text-sm">
            <button type="button" onclick="cdSearch()" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">
                Tìm
            </button>
            <button type="button" onclick="cdResetSearch()" class="px-3 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold">
                Xóa
            </button>
        </div>

        <div class="text-xs text-gray-500 mb-2">Mặc định hiển thị các mã hàng đã kiểm kê nhưng số lượng không khớp tồn hệ thống. Gõ mã hàng để lọc thêm.</div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[11px] font-bold">
                        <th class="p-2 text-left border-b">Vị trí</th>
                        <th class="p-2 text-left border-b">Khu vực</th>
                        <th class="p-2 text-left border-b">Mã hàng</th>
                        <th class="p-2 text-left border-b">Tên hàng</th>
                        <th class="p-2 text-right border-b">Đã kiểm</th>
                        <th class="p-2 text-right border-b">Hệ thống</th>
                        <th class="p-2 text-right border-b">Lệch</th>
                        <th class="p-2 text-left border-b">Người kiểm</th>
                        <th class="p-2 text-left border-b">Thời gian</th>
                        <?php if ($isAdmin): ?>
                        <th class="p-2 text-center border-b">Xóa</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="cd-mismatch-body">
                    <tr><td colspan="<?php echo $isAdmin ? 10 : 9; ?>" class="p-3 text-center text-gray-400 text-xs">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between mt-2">
            <div id="cd-mismatch-count" class="text-xs text-gray-500"></div>
            <div id="cd-mismatch-pagination" class="flex items-center gap-2 text-xs text-gray-600 hidden">
                <button type="button" id="cd-mismatch-prev" onclick="cdMismatchGoToPage(cdMismatchPage - 1)"
                    class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">‹ Trước</button>
                <span id="cd-mismatch-page-label">Trang 1 / 1</span>
                <button type="button" id="cd-mismatch-next" onclick="cdMismatchGoToPage(cdMismatchPage + 1)"
                    class="px-3 py-1 rounded border border-gray-300 hover:bg-gray-100">Sau ›</button>
            </div>
        </div>
    </div>
</div>

<script>
const CD_IS_ADMIN = <?php echo $isAdmin ? 'true' : 'false'; ?>;
let cdZoneFilter = '';
let cdSearchTimer = null;
let cdMismatchItems = [];
let cdMismatchTotal = 0;
let cdMismatchPage = 1;
const CD_MISMATCH_PAGE_SIZE = 10;

function cdEsc(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cdSetStatus(text, isError) {
    const el = $('#cd-status');
    el.text(text || '');
    el.toggleClass('text-red-600', !!isError);
    el.toggleClass('text-gray-500', !isError);
}

function cdLoadAll() {
    cdSetStatus('Đang tải dữ liệu...');
    cdLoadSummary();
    cdLoadMismatchList();
}

/* ---------- Tải lịch sử kiểm kê (.xlsx theo tháng) ---------- */

function cdToggleHistoryPopover() {
    const el = $('#cd-history-popover');
    if (el.hasClass('hidden')) {
        if (!$('#cd-history-month').val()) {
            const now = new Date();
            $('#cd-history-month').val(now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0'));
        }
        $('#cd-history-export-status').text('').removeClass('text-red-600 text-emerald-600').addClass('text-gray-500');
        el.removeClass('hidden');
    } else {
        el.addClass('hidden');
    }
}

function cdExportHistory() {
    const month = $('#cd-history-month').val();
    if (!month) {
        $('#cd-history-export-status').text('Vui lòng chọn tháng.').removeClass('text-gray-500 text-emerald-600').addClass('text-red-600');
        return;
    }

    $('#cd-history-export-btn').prop('disabled', true).text('Đang xuất...');
    $('#cd-history-export-status').text('Đang tạo file, có thể mất chút thời gian nếu dữ liệu lớn...').removeClass('text-red-600 text-emerald-600').addClass('text-gray-500');

    $.post('api.php?action=check_dashboard_export_history_xlsx', { month: month }, function(res) {
        $('#cd-history-export-btn').prop('disabled', false).text('Tải xuống');
        if (!res || !res.success) {
            $('#cd-history-export-status').text((res && res.message) || 'Xuất file thất bại.').removeClass('text-gray-500 text-emerald-600').addClass('text-red-600');
            return;
        }
        $('#cd-history-export-status').text('Đã tạo ' + res.rows + ' dòng. Đang tải xuống...').removeClass('text-gray-500 text-red-600').addClass('text-emerald-600');
        window.location.href = res.path;
        setTimeout(function() { $('#cd-history-popover').addClass('hidden'); }, 400);
    }, 'json').fail(function() {
        $('#cd-history-export-btn').prop('disabled', false).text('Tải xuống');
        $('#cd-history-export-status').text('Lỗi kết nối khi xuất file.').removeClass('text-gray-500 text-emerald-600').addClass('text-red-600');
    });
}

$(document).on('click', function(e) {
    if (!$(e.target).closest('#cd-history-btn, #cd-history-popover').length) {
        $('#cd-history-popover').addClass('hidden');
    }
});

/* ---------- KPI + bảng theo khu vực ---------- */

function cdLoadSummary() {
    $.getJSON('api.php?action=check_dashboard_summary', function(res) {
        if (!res || !res.success) {
            cdSetStatus((res && res.message) ? res.message : 'Không tải được KPI kiểm kê.', true);
            return;
        }

        const t = res.totals || {};
        $('#cd-kpi-stock').text(t.shelves_with_stock ?? 0);
        $('#cd-kpi-checked').text(t.shelves_checked ?? 0);
        $('#cd-kpi-remaining').text(t.shelves_remaining ?? 0);
        $('#cd-kpi-mismatch').text(t.shelves_mismatch ?? 0);

        cdRenderZoneTable(res.by_zone || []);
        cdSetStatus('Cập nhật lúc ' + new Date().toLocaleTimeString('vi-VN'));
    }).fail(function() {
        cdSetStatus('Lỗi kết nối khi tải KPI kiểm kê.', true);
    });
}

function cdRenderZoneTable(zones) {
    const body = $('#cd-zone-body');
    if (!zones.length) {
        body.html('<tr><td colspan="6" class="p-3 text-center text-gray-400 text-xs">Chưa có dữ liệu vị trí</td></tr>');
        return;
    }

    let html = '';
    zones.forEach(function(z) {
        const stock = z.shelves_with_stock || 0;
        const checked = z.shelves_checked || 0;
        const remaining = z.shelves_remaining || 0;
        const mismatch = z.shelves_mismatch || 0;
        const pct = stock > 0 ? Math.min(100, Math.round((checked / stock) * 100)) : (checked > 0 ? 100 : 0);
        const zoneAttr = cdEsc(z.zone);

        html += '<tr class="border-b hover:bg-gray-50">' +
            '<td class="p-2 font-mono font-bold text-gray-800">' + cdEsc(z.zone) + '</td>' +
            '<td class="p-2 text-right">' + stock + '</td>' +
            '<td class="p-2 text-right text-emerald-700 font-semibold">' + checked + '</td>' +
            '<td class="p-2 text-right text-amber-700 font-semibold">' + remaining + '</td>' +
            '<td class="p-2 text-right">' +
                (mismatch > 0
                    ? '<button type="button" class="text-red-600 font-bold hover:underline" onclick="cdFilterByZone(\'' + zoneAttr.replace(/'/g, "\\'") + '\')">' + mismatch + '</button>'
                    : '<span class="text-gray-400">0</span>') +
            '</td>' +
            '<td class="p-2">' +
                '<div class="w-full bg-gray-200 rounded-full h-2.5">' +
                    '<div class="bg-emerald-500 h-2.5 rounded-full" style="width:' + pct + '%"></div>' +
                '</div>' +
                '<div class="text-[10px] text-gray-500 mt-0.5">' + pct + '%</div>' +
            '</td>' +
            '</tr>';
    });
    body.html(html);
}

function cdFilterByZone(zone) {
    cdZoneFilter = zone;
    $('#cd-zone-chip-label').text(zone);
    $('#cd-zone-chip-wrap').removeClass('hidden');
    cdLoadMismatchList();
    $('html, body').animate({ scrollTop: $('#cd-mismatch-body').offset().top - 100 }, 300);
}

function cdClearZoneFilter() {
    cdZoneFilter = '';
    $('#cd-zone-chip-wrap').addClass('hidden');
    cdLoadMismatchList();
}

/* ---------- Tìm kiếm + danh sách lệch tồn ---------- */

function cdSearch() {
    cdLoadMismatchList();
}

function cdResetSearch() {
    $('#cd-search-input').val('');
    cdZoneFilter = '';
    $('#cd-zone-chip-wrap').addClass('hidden');
    cdLoadMismatchList();
}

function cdLoadMismatchList() {
    const q = $('#cd-search-input').val();
    const colspan = CD_IS_ADMIN ? 10 : 9;
    $('#cd-mismatch-body').html('<tr><td colspan="' + colspan + '" class="p-3 text-center text-gray-400 text-xs">Đang tải dữ liệu...</td></tr>');

    $.getJSON('api.php?action=check_dashboard_mismatch_list', { q: q, zone: cdZoneFilter, limit: 300 }, function(res) {
        if (!res || !res.success) {
            $('#cd-mismatch-body').html('<tr><td colspan="' + colspan + '" class="p-3 text-center text-red-500 text-xs">' + cdEsc((res && res.message) || 'Không tải được dữ liệu.') + '</td></tr>');
            $('#cd-mismatch-pagination').addClass('hidden');
            return;
        }
        cdMismatchItems = res.items || [];
        cdMismatchTotal = res.total || 0;
        cdMismatchPage = 1;
        cdRenderMismatchPage();
    }).fail(function() {
        $('#cd-mismatch-body').html('<tr><td colspan="' + colspan + '" class="p-3 text-center text-red-500 text-xs">Lỗi kết nối.</td></tr>');
        $('#cd-mismatch-pagination').addClass('hidden');
    });
}

function cdMismatchGoToPage(page) {
    const totalPages = Math.max(1, Math.ceil(cdMismatchItems.length / CD_MISMATCH_PAGE_SIZE));
    if (page < 1 || page > totalPages || page === cdMismatchPage) return;
    cdMismatchPage = page;
    cdRenderMismatchPage();
    $('html, body').animate({ scrollTop: $('#cd-mismatch-body').offset().top - 100 }, 200);
}

function cdRenderMismatchPage() {
    const colspan = CD_IS_ADMIN ? 10 : 9;
    const body = $('#cd-mismatch-body');

    if (!cdMismatchItems.length) {
        body.html('<tr><td colspan="' + colspan + '" class="p-3 text-center text-emerald-600 text-xs font-semibold">Không có mã hàng nào đang lệch tồn khớp điều kiện lọc 🎉</td></tr>');
        $('#cd-mismatch-count').text('');
        $('#cd-mismatch-pagination').addClass('hidden');
        return;
    }

    const totalPages = Math.max(1, Math.ceil(cdMismatchItems.length / CD_MISMATCH_PAGE_SIZE));
    cdMismatchPage = Math.min(Math.max(1, cdMismatchPage), totalPages);
    const start = (cdMismatchPage - 1) * CD_MISMATCH_PAGE_SIZE;
    const pageItems = cdMismatchItems.slice(start, start + CD_MISMATCH_PAGE_SIZE);

    let html = '';
    pageItems.forEach(function(it) {
        const diff = (it.diff === null || it.diff === undefined) ? '(ngoài HT)' : (it.diff > 0 ? '+' + it.diff : it.diff);
        const sys = (it.system_qty === null || it.system_qty === undefined) ? '—' : it.system_qty;
        const shelfAttr = cdEsc(it.shelf_id).replace(/'/g, "\\'");

        html += '<tr class="border-b hover:bg-gray-50">' +
            '<td class="p-2 font-mono font-bold">' + cdEsc(it.shelf_id) + '</td>' +
            '<td class="p-2 font-mono text-xs text-gray-600">' + cdEsc(it.zone) + '</td>' +
            '<td class="p-2 font-mono font-bold">' + cdEsc(it.product_id) + '</td>' +
            '<td class="p-2 text-xs text-gray-600">' + cdEsc(it.product_name) + '</td>' +
            '<td class="p-2 text-right font-semibold">' + it.counted_qty + '</td>' +
            '<td class="p-2 text-right">' + sys + '</td>' +
            '<td class="p-2 text-right font-bold text-red-600">' + diff + '</td>' +
            '<td class="p-2 text-xs">' + cdEsc(it.checked_by) + '</td>' +
            '<td class="p-2 text-xs whitespace-nowrap">' + cdEsc(it.checked_at) + '</td>' +
            (CD_IS_ADMIN
                ? '<td class="p-2 text-center"><button type="button" class="text-red-600 hover:text-red-800" title="Xóa kết quả kiểm kê của vị trí này" onclick="cdDeleteShelf(\'' + shelfAttr + '\')"><i class="fas fa-trash"></i></button></td>'
                : '') +
            '</tr>';
    });
    body.html(html);

    const rangeLabel = 'Hiển thị ' + (start + 1) + '–' + (start + pageItems.length) + ' / ' + cdMismatchItems.length + ' dòng lệch tồn' +
        (cdMismatchTotal > cdMismatchItems.length ? ' (đã giới hạn, tổng khớp bộ lọc: ' + cdMismatchTotal + ')' : '') + '.';
    $('#cd-mismatch-count').text(rangeLabel);

    if (totalPages > 1) {
        $('#cd-mismatch-pagination').removeClass('hidden');
        $('#cd-mismatch-page-label').text('Trang ' + cdMismatchPage + ' / ' + totalPages);
        $('#cd-mismatch-prev').toggleClass('opacity-40 pointer-events-none', cdMismatchPage <= 1);
        $('#cd-mismatch-next').toggleClass('opacity-40 pointer-events-none', cdMismatchPage >= totalPages);
    } else {
        $('#cd-mismatch-pagination').addClass('hidden');
    }
}

function cdDeleteShelf(shelfId) {
    if (!CD_IS_ADMIN) return;
    if (!window.confirm('Xóa TOÀN BỘ kết quả kiểm kê của vị trí ' + shelfId + '?\n\nVị trí sẽ được mở lại để kiểm kê từ đầu. Hành động này không thể hoàn tác.')) {
        return;
    }

    $.post('api.php?action=check_dashboard_delete_shelf', { shelf_id: shelfId }, function(res) {
        if (!res || !res.success) {
            alert((res && res.message) ? res.message : 'Xóa thất bại.');
            return;
        }
        alert(res.message || ('Đã xóa kết quả kiểm kê của vị trí ' + shelfId + '.'));
        cdLoadAll();
    }, 'json').fail(function() {
        alert('Lỗi kết nối khi xóa.');
    });
}

$('#cd-search-input').on('keydown', function(e) {
    if (e.which === 13) { e.preventDefault(); cdSearch(); }
});
$('#cd-search-input').on('input', function() {
    clearTimeout(cdSearchTimer);
    cdSearchTimer = setTimeout(cdSearch, 400);
});

$(document).ready(function() {
    cdLoadAll();
});
</script>
