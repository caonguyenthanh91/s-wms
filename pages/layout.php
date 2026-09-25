<?php
require_once __DIR__ . '/../config/session_init.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? ($role ?? '');

if (!in_array($role, ['Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Ban khong co quyen truy cap trang nay. Can role: Leader tro len.</div>';
    return;
}
?>

<div id="layout-container" class="max-w-7xl mx-auto space-y-4">
    <style>
        #layout-container .rack-grid { display: grid; gap: 6px; }
        #layout-container .rack-cell { display: grid; gap: 3px; padding: 4px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; min-width: 0; }
        #layout-container .pos { aspect-ratio: 1 / 1; border-radius: 5px; display: flex; align-items: center; justify-content: center;
                                 font-weight: 700; line-height: 1; container-type: inline-size; cursor: pointer; min-width: 18px; transition: transform .1s; }
        #layout-container .pos span { font-size: clamp(8px, 45cqw, 16px); }
        #layout-container .pos:hover { transform: scale(1.12); box-shadow: 0 2px 6px rgba(0,0,0,.15); position: relative; z-index: 1; }
        #layout-container .pos-empty { background: #e5e7eb; color: #9ca3af; }
        #layout-container .pos-low   { background: #bbf7d0; color: #166534; }
        #layout-container .pos-high  { background: #fef08a; color: #854d0e; }
        #layout-container .axis { font-size: 11px; font-weight: 700; color: #64748b; display: flex; align-items: center; justify-content: center; }
    </style>

    <!-- Thanh điều khiển -->
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 overflow-hidden">
        <div class="px-5 py-4 bg-gradient-to-r from-slate-700 to-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold flex items-center gap-2"><span>🗺️</span> Sơ đồ không gian kệ</h3>
                <p class="text-slate-300 text-xs mt-0.5">Xem nhanh vị trí trống để cất hàng.</p>
            </div>
            <div class="flex items-center gap-2">
                <label for="level0-select" class="text-xs font-semibold uppercase tracking-wide text-slate-300">Khu</label>
                <select id="level0-select" class="w-48 px-3 py-2 rounded-lg text-gray-800 font-mono font-bold text-sm outline-none focus:ring-2 focus:ring-blue-400"></select>
            </div>
        </div>
        <div class="px-5 py-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-600 border-b border-gray-100">
            <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded pos-empty inline-block" style="background:#e5e7eb"></span> Trống (có thể cất hàng)</span>
            <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded inline-block" style="background:#bbf7d0"></span> Dưới 10 mã hàng</span>
            <span class="flex items-center gap-1.5"><span class="w-4 h-4 rounded inline-block" style="background:#fef08a"></span> Từ 10 mã hàng trở lên</span>
            <span class="ml-auto text-gray-500" id="rack-stats"></span>
        </div>
    </div>

    <!-- Lưới 2D -->
    <div class="bg-white rounded-2xl shadow-lg ring-1 ring-gray-100 p-4">
        <div class="flex items-center justify-between gap-3 mb-4">
            <button type="button" id="btn-prev" class="w-10 h-10 shrink-0 rounded-full bg-gray-100 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed text-xl font-bold text-gray-700 transition">&lsaquo;</button>
            <div class="text-center">
                <div class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold">Dãy kệ</div>
                <div class="text-2xl font-extrabold font-mono text-gray-800" id="rack-title">—</div>
                <div class="text-xs text-gray-400" id="rack-index"></div>
            </div>
            <button type="button" id="btn-next" class="w-10 h-10 shrink-0 rounded-full bg-gray-100 hover:bg-gray-200 disabled:opacity-30 disabled:cursor-not-allowed text-xl font-bold text-gray-700 transition">&rsaquo;</button>
        </div>
        <div class="overflow-x-auto pb-2">
            <div id="rack-grid" class="rack-grid"></div>
        </div>
        <div id="rack-empty" class="py-14 text-center text-gray-400 text-sm">Hãy chọn khu để xem sơ đồ kệ.</div>
    </div>
</div>

<script>
$(document).ready(function() {
    let level1List = [];
    let current = { level0: '', level1: '' };

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const natSort = (a, b) => String(a).localeCompare(String(b), undefined, { numeric: true });
    const posClass = n => n <= 0 ? 'pos-empty' : (n < 10 ? 'pos-low' : 'pos-high');

    // Mở trang: chỉ tải danh sách khu, chưa tải sơ đồ
    function loadZones() {
        $.getJSON('api.php?action=layout_rack', function(res) {
            if (!res.success) { showEmpty(res.message || 'Lỗi tải danh sách khu.'); return; }
            $('#level0-select').html('<option value="">Hãy chọn khu</option>'
                + res.level0_list.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join(''));
        }).fail(() => showEmpty('Lỗi kết nối máy chủ.'));
    }

    function load(level0, level1) {
        if (!level0) { resetRack(); return; }
        $.getJSON('api.php?action=layout_rack', { level0, level1: level1 || '' }, function(res) {
            if (!res.success) { showEmpty(res.message || 'Lỗi tải dữ liệu.'); return; }
            current = { level0: res.level0, level1: res.level1 };
            level1List = res.level1_list;
            // cells: [shelf_id, level2, level3, level4, item_count]
            render(res.cells.map(c => ({ shelf_id: c[0], level2_val: c[1], level3_val: c[2], level4_val: c[3], item_count: c[4] })));
        }).fail(() => showEmpty('Lỗi kết nối máy chủ.'));
    }

    function showEmpty(text) {
        $('#rack-grid').empty();
        $('#rack-stats').text('');
        $('#rack-empty').removeClass('hidden').text(text);
    }

    function resetRack() {
        current = { level0: '', level1: '' };
        level1List = [];
        $('#rack-title').text('—');
        $('#rack-index').text('');
        $('#btn-prev, #btn-next').prop('disabled', true);
        showEmpty('Hãy chọn khu để xem sơ đồ kệ.');
    }

    function render(cells) {
        const idx = level1List.indexOf(current.level1);
        $('#rack-title').text(current.level1 ? `${current.level0}-${current.level1}` : '—');
        $('#rack-index').text(level1List.length ? `${idx + 1} / ${level1List.length}` : '');
        $('#btn-prev').prop('disabled', idx <= 0);
        $('#btn-next').prop('disabled', idx < 0 || idx >= level1List.length - 1);

        const grid = $('#rack-grid').empty();
        if (!cells.length) { showEmpty('Không có dữ liệu kệ.'); return; }
        $('#rack-empty').addClass('hidden');

        // Gom vị trí theo (cột level2, tầng level3)
        const cols = [...new Set(cells.map(c => c.level2_val))].sort(natSort);
        const rows = [...new Set(cells.map(c => c.level3_val))].sort(natSort).reverse(); // tầng cao ở trên
        const map = {};
        let maxPos = 1, empty = 0;
        cells.forEach(c => {
            const k = c.level2_val + '|' + c.level3_val;
            (map[k] = map[k] || []).push(c);
            maxPos = Math.max(maxPos, map[k].length);
            if (Number(c.item_count) <= 0) empty++;
        });
        $('#rack-stats').html(`<b class="text-gray-700">${empty}</b> / ${cells.length} vị trí trống`);

        const perRow = Math.min(maxPos, 5);
        const cellMin = Math.max(56, perRow * 24);
        grid.css('grid-template-columns', `56px repeat(${cols.length}, minmax(${cellMin}px, 1fr))`);

        let html = '<div></div>' + cols.map(c => `<div class="axis py-1">Kệ ${esc(c)}</div>`).join('');
        rows.forEach(r => {
            html += `<div class="axis whitespace-nowrap">Tầng ${esc(String(r).replace(/^0+(?=\d)/, ''))}</div>`;
            cols.forEach(c => {
                const list = (map[c + '|' + r] || []).sort((a, b) => natSort(a.level4_val, b.level4_val));
                if (!list.length) { html += '<div></div>'; return; }
                html += `<div class="rack-cell" style="grid-template-columns: repeat(${Math.min(list.length, perRow)}, minmax(0, 1fr))">`
                     + list.map(p => {
                         const n = Number(p.item_count) || 0;
                         return `<a href="?page=inventory&shelf_id=${encodeURIComponent(p.shelf_id)}" class="pos ${posClass(n)}" title="${esc(p.shelf_id)} — ${n} mã hàng"><span>${n}</span></a>`;
                     }).join('')
                     + '</div>';
            });
        });
        grid.html(html);
    }

    function go(step) {
        const idx = level1List.indexOf(current.level1) + step;
        if (idx >= 0 && idx < level1List.length) load(current.level0, level1List[idx]);
    }

    $('#level0-select').on('change', function() { load($(this).val(), ''); });
    $('#btn-prev').on('click', () => go(-1));
    $('#btn-next').on('click', () => go(1));
    $(document).on('keydown', function(e) {
        if ($(e.target).is('input, select, textarea')) return;
        if (e.key === 'ArrowLeft') go(-1);
        if (e.key === 'ArrowRight') go(1);
    });

    resetRack();
    loadZones();
});
</script>
