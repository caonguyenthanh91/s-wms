<?php
$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? '';

if (!in_array($role, ['Staff', 'Leader', 'Manager', 'Admin'])) {
    echo '<div class="alert alert-danger text-center p-4">Bạn không có quyền truy cập trang này. Cần role: Staff trở lên.</div>';
    exit;
}
?>

<style>
    .wizard-step-hidden {
        display: none;
    }
    @media (min-width: 1024px) {
        .wizard-step-hidden {
            display: block;
        }
    }
</style>

<div class="max-w-6xl mx-auto">
    <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white rounded-2xl shadow-xl p-5 sm:p-6 mb-4 sm:mb-6 border border-slate-700">
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs sm:text-sm uppercase tracking-[0.3em] text-slate-400">Picking</div>
                    <h3 class="text-2xl sm:text-3xl font-black mt-2">Quét QR - Chọn kệ - Xác nhận - Trừ tồn</h3>
                    <p class="hidden lg:block text-slate-300 mt-2 max-w-3xl">Luôn hoàn thành đúng bước trước khi sang bước sau. QR sản phẩm phải có dạng [mã_hàng]$[số_lượng]. QR kệ phải trùng đúng vị trí đã chọn. Khi tồn một kệ không đủ, hệ thống sẽ tự chuyển sang kệ tiếp theo theo thứ tự tồn ít đến nhiều.</p>
                </div>
                <div class="text-right">
                    <div class="text-[10px] uppercase tracking-[0.25em] text-slate-400">Trạng thái</div>
                    <div id="workflow-status" class="font-bold text-emerald-300 mt-1 text-sm sm:text-base">Chờ quét QR</div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 sm:gap-4 text-center text-xs sm:text-sm">
                <div class="rounded-xl bg-white/10 border border-white/10 p-3">
                    <div class="text-slate-400 text-[10px] uppercase">Mã hàng</div>
                    <div id="summary-product" class="font-mono font-bold mt-1 truncate">-</div>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/10 p-3">
                    <div class="text-slate-400 text-[10px] uppercase">Cần pick</div>
                    <div id="summary-needed" class="font-bold mt-1">0</div>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/10 p-3">
                    <div class="text-slate-400 text-[10px] uppercase">Còn lại</div>
                    <div id="summary-remaining" class="font-bold mt-1">0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="lg:hidden mb-4">
        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-3 text-sm text-slate-600 flex items-center justify-between">
            <span class="font-bold text-slate-800">Bước hiện tại</span>
            <span id="mobile-step-label">1/3</span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 sm:gap-6">
        <div class="xl:col-span-2 space-y-4 sm:space-y-6">
            <div id="step-1" class="bg-white rounded-2xl shadow-md border border-slate-200 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Bước 1</div>
                        <h4 class="text-xl sm:text-2xl font-black text-slate-800 mt-1">Quét QR sản phẩm</h4>
                        <p class="hidden sm:block text-sm text-slate-500 mt-2">Nhập hoặc quét QR có dạng [mã_hàng]$[số_lượng].</p>
                    </div>
                    <div class="text-4xl sm:text-5xl">📦</div>
                </div>

                <div class="mt-4 sm:mt-5 space-y-3">
                    <div class="relative">
                        <input type="text" id="product-scan-input" placeholder="VD: ABC123$24"
                            class="w-full px-4 py-3 pr-11 border-2 border-slate-300 rounded-xl text-center text-base sm:text-lg uppercase font-mono focus:border-sky-600 outline-none">
                        <button type="button" onclick="openQRScannerModal('product-scan-input', 'QR Sản Phẩm')" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-600 hover:text-sky-800">
                            <i class="fas fa-qrcode text-lg"></i>
                        </button>
                    </div>
                    <button onclick="parseProductQr()" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-bold py-3 rounded-xl transition">Xác nhận QR sản phẩm</button>
                    <p id="product-error" class="text-sm text-red-600 hidden"></p>
                </div>
            </div>

            <div id="step-2" class="bg-white rounded-2xl shadow-md border border-slate-200 p-5 sm:p-6 wizard-step-hidden">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Bước 2</div>
                        <h4 class="text-xl sm:text-2xl font-black text-slate-800 mt-1">Chọn vị trí kệ</h4>
                        <p class="hidden sm:block text-sm text-slate-500 mt-2">Danh sách kệ được sắp xếp theo tồn ít đến nhiều.</p>
                    </div>
                    <div class="text-4xl sm:text-5xl">🏷️</div>
                </div>

                <div id="shelf-selection-wrap" class="mt-4 sm:mt-5 hidden">
                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 sm:p-4 mb-4">
                        <div class="flex flex-wrap gap-3 text-sm">
                            <div><span class="text-slate-400">Sản phẩm:</span> <span id="selected-product-label" class="font-mono font-bold text-slate-800">-</span></div>
                            <div><span class="text-slate-400">Cần lấy:</span> <span id="selected-needed-label" class="font-bold text-slate-800">0</span></div>
                        </div>
                    </div>

                    <div id="shelf-list" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                </div>

                <div id="shelf-empty-state" class="mt-5 text-sm text-slate-500 italic">Hoàn thành bước 1 để tải vị trí kệ.</div>
            </div>
        </div>

        <div class="xl:col-span-3 space-y-4 sm:space-y-6">
            <div id="step-3" class="bg-white rounded-2xl shadow-md border border-slate-200 p-5 sm:p-6 wizard-step-hidden">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Bước 3</div>
                        <h4 class="text-xl sm:text-2xl font-black text-slate-800 mt-1">Xác nhận đúng kệ và nhập số lượng</h4>
                        <p class="hidden sm:block text-sm text-slate-500 mt-2">Chỉ cho phép tiếp tục khi QR kệ khớp với kệ đã chọn và chuỗi quét sản phẩm có chứa mã hàng.</p>
                    </div>
                    <div class="text-4xl sm:text-5xl">✅</div>
                </div>

                <div class="mt-4 sm:mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mb-2 block">Kệ đã chọn</label>
                        <div id="selected-shelf-card" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-slate-500">Chưa chọn kệ</div>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mb-2 block">QR kệ cần quét</label>
                        <div class="relative">
                            <input type="text" id="shelf-scan-input" placeholder="Quét mã kệ tại vị trí này"
                                class="w-full px-4 py-3 pr-11 border-2 border-slate-300 rounded-xl text-center text-base sm:text-lg uppercase font-mono focus:border-amber-500 outline-none" disabled>
                            <button type="button" onclick="openQRScannerModal('shelf-scan-input', 'QR Mã Kệ')" class="absolute right-3 top-1/2 -translate-y-1/2 text-amber-600 hover:text-amber-800">
                                <i class="fas fa-qrcode text-lg"></i>
                            </button>
                        </div>
                        <p id="shelf-error" class="mt-2 text-sm text-red-600 hidden"></p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mb-2 block">Chuỗi quét sản phẩm</label>
                        <div class="relative">
                            <input type="text" id="product-confirm-input" placeholder="Quét QR hoặc nhập chuỗi chứa mã hàng"
                                class="w-full px-4 py-3 pr-11 border-2 border-slate-300 rounded-xl text-center text-base sm:text-lg uppercase font-mono focus:border-emerald-500 outline-none" disabled>
                            <button type="button" onclick="openQRScannerModal('product-confirm-input', 'Xác Nhận Sản Phẩm')" class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-600 hover:text-emerald-800">
                                <i class="fas fa-qrcode text-lg"></i>
                            </button>
                        </div>
                        <p id="confirm-error" class="mt-2 text-sm text-red-600 hidden"></p>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mb-2 block">Số lượng cần lấy</label>
                        <input type="number" id="pick-qty-input" min="1" placeholder="0"
                            class="w-full px-4 py-3 border-2 border-slate-300 rounded-xl text-center text-base sm:text-lg font-bold focus:border-emerald-500 outline-none bg-slate-50" disabled>
                    </div>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row gap-3">
                    <button id="btn-submit-pick" onclick="submitPickQty()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Xác nhận trừ tồn</button>
                    <button onclick="resetPicking(true)" class="sm:w-40 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold py-3 rounded-xl transition">Làm lại</button>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-md border border-slate-200 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Theo dõi</div>
                        <h4 class="text-lg sm:text-xl font-black text-slate-800 mt-1">Lịch sử pick đang thực hiện</h4>
                    </div>
                    <div class="text-sm text-slate-500"><span class="font-bold text-slate-800" id="picked-count">0</span> dòng</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 uppercase text-xs">
                                <th class="p-2 text-left">Kệ</th>
                                <th class="p-2 text-left">Sản phẩm</th>
                                <th class="p-2 text-right">Số lượng</th>
                                <th class="p-2 text-right">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody id="pick-history"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let pickState = {
    productId: '',
    requiredQty: 0,
    remainingQty: 0,
    productName: '',
    unit: '',
    shelfInventory: [],
    selectedShelf: null,
    shelfConfirmed: false,
    productConfirmed: false,
    pickHistory: [],
    loading: false,
    ready: false,
};

function setWorkflowStatus(text, tone) {
    $('#workflow-status').text(text);
    $('#workflow-status').removeClass('text-emerald-300 text-red-300 text-amber-300 text-sky-300');
    $('#workflow-status').addClass(tone || 'text-emerald-300');
}

function showFieldError(selector, message) {
    $(selector).text(message).removeClass('hidden');
}

function hideFieldError(selector) {
    $(selector).addClass('hidden').text('');
}

function normalizeQrText(text) {
    return (text || '').trim().toUpperCase();
}

function setVisibleStep(stepNumber) {
    const isDesktop = window.innerWidth >= 1024;
    $('#mobile-step-label').text(stepNumber + '/3');

    if (isDesktop) {
        $('#step-2, #step-3').removeClass('wizard-step-hidden').show();
        return;
    }

    $('#step-2').toggleClass('wizard-step-hidden', stepNumber !== 2);
    $('#step-3').toggleClass('wizard-step-hidden', stepNumber !== 3);
}

function revealStep2() {
    $('#shelf-selection-wrap').removeClass('hidden');
    $('#shelf-empty-state').addClass('hidden');
    setVisibleStep(2);
}

function revealStep3() {
    setVisibleStep(3);
}

function resetPicking(clearProduct) {
    pickState = {
        productId: '',
        requiredQty: 0,
        remainingQty: 0,
        productName: '',
        unit: '',
        shelfInventory: [],
        selectedShelf: null,
        shelfConfirmed: false,
        productConfirmed: false,
        pickHistory: [],
        loading: false,
        ready: false,
    };

    $('#shelf-selection-wrap').addClass('hidden');
    $('#shelf-empty-state').removeClass('hidden').text('Hoàn thành bước 1 để tải vị trí kệ.');
    $('#shelf-list').empty();
    $('#selected-product-label').text('-');
    $('#selected-needed-label').text('0');
    $('#selected-shelf-card').html('Chưa chọn kệ');
    $('#shelf-scan-input').val('').prop('disabled', true);
    $('#product-confirm-input').val('').prop('disabled', true);
    $('#pick-qty-input').val('').prop('disabled', true);
    $('#btn-submit-pick').prop('disabled', true);
    $('#pick-history').empty();
    $('#picked-count').text('0');
    $('#summary-product').text('-');
    $('#summary-needed').text('0');
    $('#summary-remaining').text('0');
    setWorkflowStatus('Chờ quét QR', 'text-emerald-300');
    hideFieldError('#product-error');
    hideFieldError('#shelf-error');
    hideFieldError('#confirm-error');
    setVisibleStep(1);

    if (clearProduct) {
        $('#product-scan-input').val('').focus();
    }
}

function parseProductQr() {
    if (pickState.loading) return;

    const raw = normalizeQrText($('#product-scan-input').val());
    if (!raw) {
        showFieldError('#product-error', 'Hãy quét QR sản phẩm trước.');
        return;
    }

    const parts = raw.split('$');
    if (parts.length !== 2) {
        showFieldError('#product-error', 'QR sản phẩm phải có dạng [mã_hàng]$[số_lượng].');
        return;
    }

    const productId = parts[0].trim();
    const requiredQty = parseInt(parts[1], 10);

    if (!productId || isNaN(requiredQty) || requiredQty <= 0) {
        showFieldError('#product-error', 'QR sản phẩm không hợp lệ hoặc số lượng không đúng.');
        return;
    }

    pickState.loading = true;
    setWorkflowStatus('Đang kiểm tra sản phẩm...', 'text-sky-300');
    hideFieldError('#product-error');

    $.getJSON('api.php?action=check_product', { product_id: productId }, function(productRes) {
        if (!productRes.success || !productRes.data) {
            showFieldError('#product-error', `Mã hàng ${productId} chưa được đăng ký.`);
            setWorkflowStatus('Lỗi kiểm tra sản phẩm', 'text-red-300');
            pickState.loading = false;
            return;
        }

        $.ajax({
            type: 'POST',
            url: 'api.php?action=get_shelf_inventory',
            dataType: 'json',
            data: { product_id: productId },
            success: function(stockRes) {
                pickState.loading = false;

                if (!stockRes.success) {
                    showFieldError('#product-error', stockRes.message || 'Không thể tải tồn kho theo kệ.');
                    setWorkflowStatus('Lỗi tải tồn kho', 'text-red-300');
                    return;
                }

                const shelves = Array.isArray(stockRes.shelves) ? stockRes.shelves : [];
                const totalStock = parseFloat(stockRes.total_stock || 0);

                if (totalStock < requiredQty) {
                    showFieldError('#product-error', `Tồn kho hiện có ${totalStock} nhỏ hơn số cần pick ${requiredQty}.`);
                    setWorkflowStatus('Tồn kho không đủ', 'text-red-300');
                    return;
                }

                pickState.productId = productId;
                pickState.requiredQty = requiredQty;
                pickState.remainingQty = requiredQty;
                pickState.productName = productRes.data.product_name || '';
                pickState.unit = productRes.data.unit || '';
                pickState.shelfInventory = shelves
                    .map(function(item) {
                        return {
                            shelf_id: normalizeQrText(item.shelf_id),
                            shelf_name: item.shelf_name || '',
                            qty: parseFloat(item.qty || 0),
                        };
                    })
                    .filter(function(item) { return item.qty > 0; })
                    .sort(function(a, b) {
                        if (a.qty === b.qty) {
                            return a.shelf_id.localeCompare(b.shelf_id, undefined, { numeric: true });
                        }
                        return a.qty - b.qty;
                    });
                pickState.shelfConfirmed = false;
                pickState.productConfirmed = false;
                pickState.selectedShelf = null;
                pickState.ready = true;

                $('#summary-product').text(productId);
                $('#summary-needed').text(requiredQty);
                $('#summary-remaining').text(requiredQty);
                $('#selected-product-label').text(`${productId}${pickState.productName ? ' - ' + pickState.productName : ''}`);
                $('#selected-needed-label').text(requiredQty);
                renderShelfList();
                revealStep2();
                setWorkflowStatus('Đã sẵn sàng chọn kệ', 'text-emerald-300');
            },
            error: function() {
                pickState.loading = false;
                showFieldError('#product-error', 'Lỗi kết nối khi tải tồn kho theo kệ.');
                setWorkflowStatus('Lỗi kết nối', 'text-red-300');
            }
        });
    }).fail(function() {
        pickState.loading = false;
        showFieldError('#product-error', 'Lỗi kết nối khi kiểm tra sản phẩm.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-300');
    });
}

function renderShelfList() {
    const list = $('#shelf-list');
    list.empty();

    if (!pickState.shelfInventory.length) {
        list.html('<div class="md:col-span-2 rounded-xl border border-dashed border-slate-300 p-4 text-slate-500 italic">Không có vị trí tồn kho hợp lệ.</div>');
        return;
    }

    pickState.shelfInventory.forEach(function(item, index) {
        const isSelected = pickState.selectedShelf && pickState.selectedShelf.shelf_id === item.shelf_id;
        const cls = isSelected
            ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200'
            : 'border-slate-200 bg-slate-50 hover:bg-slate-100';
        const shelfLabel = item.shelf_name && item.shelf_name !== item.shelf_id ? item.shelf_name : '';

        list.append(`
            <button type="button" data-shelf-id="${item.shelf_id}" onclick="selectShelfById('${item.shelf_id}')" class="text-left rounded-xl border p-4 transition ${cls}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Mã kệ</div>
                        <div class="font-mono font-black text-slate-800">${item.shelf_id}</div>
                        ${shelfLabel ? `<div class="hidden sm:block text-xs text-slate-500 mt-1">${shelfLabel}</div>` : ''}
                    </div>
                    <div class="text-right">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Tồn</div>
                        <div class="text-xl font-black text-slate-800">${item.qty}</div>
                    </div>
                </div>
            </button>
        `);
    });
}

function selectShelfById(shelfId) {
    if (!pickState.ready) return;

    const targetShelfId = normalizeQrText(shelfId);
    const item = pickState.shelfInventory.find(function(entry) {
        return entry.shelf_id === targetShelfId;
    });

    if (!item || item.qty <= 0) {
        showFieldError('#shelf-error', 'Vị trí kệ không còn tồn hợp lệ.');
        return;
    }

    pickState.selectedShelf = item;
    pickState.shelfConfirmed = false;
    pickState.productConfirmed = false;

    $('#selected-shelf-card').html(`
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Mã kệ đã chọn</div>
                <div class="font-mono text-xl font-black text-slate-800 mt-1">${item.shelf_id}</div>
                ${item.shelf_name && item.shelf_name !== item.shelf_id ? `<div class="hidden sm:block text-sm text-slate-500">${item.shelf_name}</div>` : ''}
                <div class="hidden sm:block text-sm text-slate-500">Tồn hiện tại: ${item.qty}</div>
            </div>
            <div class="text-3xl">📍</div>
        </div>
    `);

    $('#shelf-scan-input').prop('disabled', false).val('').focus();
    $('#product-confirm-input').prop('disabled', true).val('');
    $('#pick-qty-input').prop('disabled', true).val('');
    $('#btn-submit-pick').prop('disabled', true);
    hideFieldError('#shelf-error');
    hideFieldError('#confirm-error');
    revealStep3();
    setWorkflowStatus(`Đã chọn kệ ${item.shelf_id}`, 'text-amber-300');
}

function confirmShelfScan() {
    if (!pickState.selectedShelf) {
        showFieldError('#shelf-error', 'Hãy chọn kệ trước.');
        return;
    }

    const scannedShelf = normalizeQrText($('#shelf-scan-input').val());
    const targetShelf = normalizeQrText(pickState.selectedShelf.shelf_id);

    if (!scannedShelf) {
        showFieldError('#shelf-error', 'Hãy quét mã kệ tại đúng vị trí đã chọn.');
        return;
    }

    if (scannedShelf !== targetShelf) {
        showFieldError('#shelf-error', `Sai mã kệ. Cần ${targetShelf} nhưng đã quét ${scannedShelf}.`);
        setWorkflowStatus('Sai kệ đã quét', 'text-red-300');
        return;
    }

    pickState.shelfConfirmed = true;
    hideFieldError('#shelf-error');
    $('#product-confirm-input').prop('disabled', false).val('').focus();
    $('#pick-qty-input').prop('disabled', true).val('');
    $('#btn-submit-pick').prop('disabled', true);
    setWorkflowStatus(`Đã xác nhận đúng kệ ${targetShelf}`, 'text-emerald-300');
}

function confirmProductScan() {
    if (!pickState.shelfConfirmed || !pickState.selectedShelf) {
        showFieldError('#confirm-error', 'Hãy xác nhận mã kệ trước.');
        return;
    }

    const raw = normalizeQrText($('#product-confirm-input').val());
    if (!raw) {
        showFieldError('#confirm-error', 'Hãy quét chuỗi sản phẩm.');
        return;
    }

    if (raw.indexOf(pickState.productId) === -1) {
        showFieldError('#confirm-error', `Chuỗi quét không chứa mã hàng ${pickState.productId}.`);
        setWorkflowStatus('Sai mã hàng đã quét', 'text-red-300');
        return;
    }

    pickState.productConfirmed = true;
    hideFieldError('#confirm-error');
    $('#pick-qty-input').prop('disabled', false).val('').focus();
    $('#btn-submit-pick').prop('disabled', false);
    setWorkflowStatus('Có thể nhập số lượng pick', 'text-emerald-300');
}

function addPickHistory(row) {
    pickState.pickHistory.unshift(row);
    $('#picked-count').text(pickState.pickHistory.length);

    const tbody = $('#pick-history');
    tbody.empty();

    pickState.pickHistory.forEach(function(item) {
        const badgeClass = item.status === 'DONE'
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-amber-100 text-amber-700';

        tbody.append(`
            <tr class="border-b last:border-b-0">
                <td class="p-2 font-mono">${item.shelf_id}</td>
                <td class="p-2 font-mono">${item.product_id}</td>
                <td class="p-2 text-right font-bold">${item.qty}</td>
                <td class="p-2 text-right"><span class="inline-flex px-2 py-1 rounded-full text-xs font-bold ${badgeClass}">${item.status}</span></td>
            </tr>
        `);
    });
}

function goToNextShelfIfNeeded() {
    if (pickState.remainingQty <= 0) {
        setWorkflowStatus('Hoàn tất picking', 'text-emerald-300');
        $('#selected-shelf-card').html('<div class="text-emerald-700 font-bold">Đã hoàn thành toàn bộ số lượng cần pick.</div>');
        $('#shelf-scan-input').prop('disabled', true).val('');
        $('#product-confirm-input').prop('disabled', true).val('');
        $('#pick-qty-input').prop('disabled', true).val('');
        $('#btn-submit-pick').prop('disabled', true);
        $('#shelf-list').empty();
        $('#shelf-empty-state').removeClass('hidden').text('Đã hoàn thành picking.');
        setVisibleStep(3);
        return;
    }

    if (!pickState.shelfInventory.length) {
        setWorkflowStatus('Không còn kệ để pick', 'text-red-300');
        $('#selected-shelf-card').html('<div class="text-red-600 font-bold">Không còn kệ nào có tồn để tiếp tục.</div>');
        return;
    }

    renderShelfList();
    selectShelfById(pickState.shelfInventory[0].shelf_id);
    $('#product-confirm-input').val('');
    $('#pick-qty-input').val('');
    $('#btn-submit-pick').prop('disabled', true);
    setWorkflowStatus(`Còn lại ${pickState.remainingQty}, chuyển sang kệ tiếp theo`, 'text-amber-300');
}

function submitPickQty() {
    if (!pickState.selectedShelf || !pickState.shelfConfirmed || !pickState.productConfirmed) {
        showFieldError('#confirm-error', 'Hãy hoàn thành các bước quét trước khi nhập số lượng.');
        return;
    }

    const qty = parseInt($('#pick-qty-input').val(), 10);
    if (isNaN(qty) || qty <= 0) {
        showFieldError('#confirm-error', 'Số lượng pick không hợp lệ.');
        return;
    }

    if (qty > pickState.remainingQty) {
        showFieldError('#confirm-error', `Số lượng nhập ${qty} vượt quá số cần pick còn lại ${pickState.remainingQty}.`);
        return;
    }

    if (qty > pickState.selectedShelf.qty) {
        showFieldError('#confirm-error', `Kệ ${pickState.selectedShelf.shelf_id} chỉ còn ${pickState.selectedShelf.qty}, không đủ để lấy ${qty}.`);
        return;
    }

    $('#btn-submit-pick').prop('disabled', true);
    setWorkflowStatus('Đang trừ tồn...', 'text-sky-300');

    $.post('api.php?action=outbound_submit', {
        shelf_id: pickState.selectedShelf.shelf_id,
        product_id: pickState.productId,
        quantity: qty
    }, function(res) {
        if (!res.success) {
            showFieldError('#confirm-error', res.message || 'Không thể trừ tồn.');
            setWorkflowStatus('Trừ tồn thất bại', 'text-red-300');
            $('#btn-submit-pick').prop('disabled', false);
            return;
        }

        hideFieldError('#confirm-error');
        hideFieldError('#shelf-error');
        pickState.remainingQty -= qty;

        addPickHistory({
            shelf_id: pickState.selectedShelf.shelf_id,
            product_id: pickState.productId,
            qty: qty,
            status: pickState.remainingQty > 0 ? 'CONTINUE' : 'DONE'
        });

        $('#summary-remaining').text(pickState.remainingQty);
        $('#selected-needed-label').text(pickState.remainingQty);

        const currentShelf = pickState.selectedShelf.shelf_id;
        pickState.shelfInventory = pickState.shelfInventory
            .map(function(item) {
                if (item.shelf_id === currentShelf) {
                    return Object.assign({}, item, { qty: Math.max(0, item.qty - qty) });
                }
                return item;
            })
            .filter(function(item) { return item.qty > 0; })
            .sort(function(a, b) {
                if (a.qty === b.qty) {
                    return a.shelf_id.localeCompare(b.shelf_id, undefined, { numeric: true });
                }
                return a.qty - b.qty;
            });

        if (pickState.remainingQty <= 0) {
            setWorkflowStatus('Pick thành công', 'text-emerald-300');
            $('#selected-shelf-card').html('<div class="text-emerald-700 font-bold">Đã trừ xong số lượng cần pick.</div>');
            $('#shelf-scan-input').prop('disabled', true).val('');
            $('#product-confirm-input').prop('disabled', true).val('');
            $('#pick-qty-input').prop('disabled', true).val('');
            $('#btn-submit-pick').prop('disabled', true);
            renderShelfList();
            return;
        }

        goToNextShelfIfNeeded();
    }, 'json').fail(function() {
        showFieldError('#confirm-error', 'Lỗi kết nối khi trừ tồn.');
        setWorkflowStatus('Lỗi kết nối', 'text-red-300');
        $('#btn-submit-pick').prop('disabled', false);
    });
}

$('#product-scan-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        parseProductQr();
    }
});

$('#shelf-scan-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        confirmShelfScan();
    }
});

$('#product-confirm-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        confirmProductScan();
    }
});

$('#pick-qty-input').on('keydown', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        submitPickQty();
    }
});

$('#product-scan-input').on('change', function() {
    if (pickState.ready) {
        parseProductQr();
    }
});

$('#shelf-scan-input').on('change', function() {
    if (pickState.selectedShelf) {
        confirmShelfScan();
    }
});

$('#product-confirm-input').on('change', function() {
    if (pickState.selectedShelf && pickState.shelfConfirmed) {
        confirmProductScan();
    }
});

$(document).ready(function() {
    resetPicking(false);

    const urlParams = new URLSearchParams(window.location.search);
    const productFromUrl = urlParams.get('product_id');
    if (productFromUrl) {
        $('#product-scan-input').val(productFromUrl);
    }
});

$(window).on('resize', function() {
    if (window.innerWidth >= 1024) {
        $('#step-2, #step-3').removeClass('wizard-step-hidden').show();
    }
});
</script>