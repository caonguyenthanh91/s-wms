<div id="inventory-container" class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-6">
    <style>
        @media (max-width: 900px) {
            #inventory-container .pda-search-card {
                padding: 0.75rem;
                margin-bottom: 0.65rem;
            }

            #inventory-container .pda-results-scroll {
                max-height: 44vh;
                overflow-y: auto;
            }

            #inventory-container .pda-table-compact th,
            #inventory-container .pda-table-compact td {
                padding: 0.45rem 0.35rem;
                font-size: 0.72rem;
                line-height: 1.15;
            }
        }
    </style>
    <!-- BÊN TRÁI: TÌM THEO MÃ SẢN PHẨM -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6 pda-search-card">
            <h3 class="text-sm sm:text-lg font-bold mb-2 sm:mb-4 text-gray-800 flex items-center">
                <span class="mr-2">🔍</span> Tìm theo Product ID
            </h3>
            <div class="grid grid-cols-12 gap-2">
                <div class="relative col-span-9">
                    <input type="text" id="search-product-id" placeholder="Product ID"
                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none uppercase font-mono text-sm">
                    <button type="button" onclick="openQRScannerModal('search-product-id', 'Mã Sản Phẩm')" class="absolute right-2 top-1/2 -translate-y-1/2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-qrcode"></i>
                    </button>
                </div>
                <button onclick="searchByProduct()" class="col-span-3 bg-blue-600 text-white px-2 py-2 rounded-lg font-bold hover:bg-blue-700 transition text-xs sm:text-sm">
                    Tìm
                </button>
            </div>
        </div>

        <div id="product-results-container" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="p-4 border-b bg-blue-50">
                <h4 class="text-sm sm:text-base font-bold text-blue-800">SKU: <span id="res-sku" class="font-mono"></span></h4>
                <p class="hidden sm:block text-xs text-gray-600 mt-1" id="res-product-name"></p>
            </div>
            <div class="pda-results-scroll">
            <table class="w-full text-left border-collapse text-sm pda-table-compact">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold">
                        <th class="p-3 border-b">Nguồn</th>
                        <th class="p-3 border-b">Vị trí kệ</th>
                        <th class="p-3 border-b text-right">Số lượng</th>
                        <th class="p-3 border-b text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody id="product-inventory-results"></tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-bold border-t">
                        <td class="p-3">TỔNG TỒN</td>
                        <td class="p-3 text-right text-blue-700" id="product-total-qty">0</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        <div id="product-no-results" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded hidden text-sm">
            Không tìm thấy vị trí chứa mã này.
        </div>
    </section>

    <!-- BÊN PHẢI: TÌM THEO MÃ KỆ -->
    <section>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6 pda-search-card">
            <h3 class="text-sm sm:text-lg font-bold mb-2 sm:mb-4 text-gray-800 flex items-center">
                <span class="mr-2">📍</span> Tìm theo Shelf ID
            </h3>
            <!-- <p class="hidden sm:block text-xs text-gray-500 mb-3">Co the nhap ma ke hoac ma tam dang TEMP-&lt;PALLET_ID&gt;.</p> -->
            <div class="grid grid-cols-12 gap-2">
                <div class="relative col-span-9">
                    <input type="text" id="search-shelf-id" placeholder="Shelf ID"
                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none uppercase font-mono text-sm">
                    <button type="button" onclick="openQRScannerModal('search-shelf-id', 'Mã Vị Trí')" class="absolute right-2 top-1/2 -translate-y-1/2 text-green-600 hover:text-green-800">
                        <i class="fas fa-qrcode"></i>
                    </button>
                </div>
                <button onclick="searchByShelf()" class="col-span-3 bg-green-600 text-white px-2 py-2 rounded-lg font-bold hover:bg-green-700 transition text-xs sm:text-sm">
                    Tìm
                </button>
            </div>
        </div>

        <div id="shelf-results-container" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
            <div class="p-4 border-b bg-green-50">
                <h4 class="text-sm sm:text-base font-bold text-green-800">Kệ: <span id="res-shelf" class="font-mono"></span></h4>
            </div>
            <div class="pda-results-scroll">
            <table class="w-full text-left border-collapse text-sm pda-table-compact">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 uppercase text-[10px] font-bold">
                        <th class="p-3 border-b">Nguồn</th>
                        <th class="p-3 border-b">Mã Sản Phẩm</th>
                        <th class="p-3 border-b">Tên Sản Phẩm</th>
                        <th class="p-3 border-b text-right">Số lượng</th>
                        <th class="p-3 border-b text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody id="shelf-inventory-results"></tbody>
            </table>
            </div>
        </div>
        <div id="shelf-no-results" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded hidden text-sm">
            Kệ này hiện đang trống hoặc không tồn tại.
        </div>
    </section>
</div>

<script>
function normalizeInventoryQRRaw(rawValue) {
    return (rawValue || '')
        .replace(/\uFF04/g, '$')
        .replace(/\\\$/g, '$')
        .replace(/&#36;/g, '$')
        .trim();
}

function extractProductIdFromInventoryQR(rawValue) {
    const normalizedValue = normalizeInventoryQRRaw(rawValue);
    if (!normalizedValue || normalizedValue.indexOf('$') === -1) return null;

    const parts = normalizedValue.split('$').map(part => part.trim());
    if (parts.length < 3) return null;

    const productId = (parts[1] || '').toUpperCase();
    return productId || null;
}

function resolveInventorySearchProductId(rawValue) {
    const normalizedValue = normalizeInventoryQRRaw(rawValue);
    const parsedProductId = extractProductIdFromInventoryQR(normalizedValue);
    return (parsedProductId || normalizedValue).toUpperCase();
}

function searchByProduct() {
    const pid = resolveInventorySearchProductId($('#search-product-id').val());
    if (!pid) return;

    $('#search-product-id').val(pid);

    $('#product-results-container, #product-no-results').addClass('hidden');

    $.getJSON('api.php?action=search_sku', { product_id: pid }, function(data) {
        if (data && data.length > 0) {
            $('#res-sku').text(pid.toUpperCase());
            $('#res-product-name').text(data[0].product_name || '');
            
            const tbody = $('#product-inventory-results');
            tbody.empty();
            let total = 0;

            data.forEach(item => {
                total += parseInt(item.quantity);
                const inboundUrl = `?page=inbound&shelf_id=${encodeURIComponent(item.shelf_id)}&product_id=${encodeURIComponent(pid)}`;
                const outboundUrl = `?page=outbound&shelf_id=${encodeURIComponent(item.shelf_id)}&product_id=${encodeURIComponent(pid)}`;
                const isTemp = item.source === 'IMPORT_TEMP';
                const sourceBadge = isTemp
                    ? '<span class="inline-block px-2 py-1 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">KHO TAM</span>'
                    : '<span class="inline-block px-2 py-1 rounded bg-blue-100 text-blue-800 text-[10px] font-bold">KHO CHINH</span>';
                tbody.append(`
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-3">${sourceBadge}</td>
                        <td class="p-3 font-mono font-bold text-gray-700">${item.shelf_id}</td>
                        <td class="p-3 text-right font-medium">${item.quantity}</td>
                        <td class="p-3 text-center">
                            ${isTemp
                                ? '<span class="text-xs text-gray-500">Xu ly tai trang Nhap hang</span>'
                                : `<a href="${inboundUrl}" class="inline-block bg-blue-600 text-white px-2 py-1 rounded text-xs mr-1">Nhập</a>
                                   <a href="${outboundUrl}" class="inline-block bg-red-600 text-white px-2 py-1 rounded text-xs">Xuất</a>`}
                        </td>
                    </tr>
                `);
            });

            $('#product-total-qty').text(total);
            $('#product-results-container').removeClass('hidden');
        } else {
            $('#product-no-results').removeClass('hidden');
        }
    });
}

function searchByShelf() {
    const sid = $('#search-shelf-id').val().trim();
    if (!sid) return;

    $('#shelf-results-container, #shelf-no-results').addClass('hidden');

    $.getJSON('api.php?action=get_inventory_by_shelf', { shelf_id: sid }, function(data) {
        if (data && data.length > 0) {
            $('#res-shelf').text(sid.toUpperCase());
            const tbody = $('#shelf-inventory-results');
            tbody.empty();

            data.forEach(item => {
                const inboundUrl = `?page=inbound&shelf_id=${encodeURIComponent(sid)}&product_id=${encodeURIComponent(item.product_id)}`;
                const outboundUrl = `?page=outbound&shelf_id=${encodeURIComponent(sid)}&product_id=${encodeURIComponent(item.product_id)}`;
                const isTemp = item.source === 'IMPORT_TEMP';
                const sourceBadge = isTemp
                    ? '<span class="inline-block px-2 py-1 rounded bg-amber-100 text-amber-800 text-[10px] font-bold">KHO TAM</span>'
                    : '<span class="inline-block px-2 py-1 rounded bg-green-100 text-green-800 text-[10px] font-bold">KHO CHINH</span>';
                tbody.append(`
                    <tr class="hover:bg-gray-50 border-b">
                        <td class="p-3">${sourceBadge}</td>
                        <td class="p-3 font-mono font-bold text-gray-700">${item.product_id}</td>
                        <td class="p-3 text-gray-600">${item.product_name || '-'}</td>
                        <td class="p-3 text-right font-medium">${item.quantity}</td>
                        <td class="p-3 text-center">
                            ${isTemp
                                ? '<span class="text-xs text-gray-500">Pallet tam: khong xuat truc tiep</span>'
                                : `<a href="${inboundUrl}" class="inline-block bg-blue-600 text-white px-2 py-1 rounded text-xs mr-1">Nhập</a>
                                   <a href="${outboundUrl}" class="inline-block bg-red-600 text-white px-2 py-1 rounded text-xs">Xuất</a>`}
                        </td>
                    </tr>
                `);
            });
            $('#shelf-results-container').removeClass('hidden');
        } else {
            $('#shelf-no-results').removeClass('hidden');
        }
    });
}

$('#search-product-id').on('keypress', function(e) { if(e.which == 13) searchByProduct(); });
$('#search-shelf-id').on('keypress', function(e) { if(e.which == 13) searchByShelf(); });

$('#search-product-id').on('change', function() {
    const pid = resolveInventorySearchProductId($(this).val());
    if (pid) $(this).val(pid);
});

$(document).ready(function() {
    // Tự động tìm kiếm kệ nếu có tham số shelf_id từ URL
    const urlParams = new URLSearchParams(window.location.search);
    const shelfId = urlParams.get('shelf_id');
    if (shelfId) {
        $('#search-shelf-id').val(shelfId);
        searchByShelf();
    }
});
</script>