<?php
// pages/change.php
// This page is for transferring products between shelves.
// Accessible by Admin, Leader, Manager roles.
?>

<h2 class="text-2xl font-semibold mb-6 text-gray-800">Điều chuyển sản phẩm giữa các kệ</h2>

<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Current Shelf Section -->
        <div>
            <h3 class="text-xl font-medium mb-4 text-gray-700">Kệ nguồn</h3>
            <div class="mb-4">
                <label for="current_shelf_id" class="block text-sm font-medium text-gray-700">Mã kệ nguồn:</label>
                <div class="flex mt-1">
                    <input type="text" id="current_shelf_id" class="form-input flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 uppercase" placeholder="Quét mã kệ nguồn">
                    <button type="button" id="btn_scan_current_shelf" class="ml-2 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center gap-2">
                        <span aria-hidden="true">🔍</span>
                        <span class="hidden sm:inline">Quét</span>
                    </button>
                </div>
                <p id="current_shelf_name" class="text-sm text-gray-500 mt-1"></p>
            </div>

            <div id="current_shelf_products_container" class="hidden">
                <h4 class="text-lg font-medium mb-2 text-gray-700">Sản phẩm trên kệ nguồn:</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select_all_products" class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã SP</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kệ lẻ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tồn kho</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Số lượng chuyển</th>
                            </tr>
                        </thead>
                        <tbody id="current_shelf_products_table_body" class="bg-white divide-y divide-gray-200">
                            <!-- Products will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- New Shelf Section -->
        <div>
            <h3 class="text-xl font-medium mb-4 text-gray-700">Kệ đích</h3>
            <div class="mb-4">
                <label for="new_shelf_id" class="block text-sm font-medium text-gray-700">Mã kệ đích:</label>
                <div class="flex mt-1">
                    <input type="text" id="new_shelf_id" class="form-input flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 uppercase" placeholder="Quét mã kệ đích">
                    <button type="button" id="btn_scan_new_shelf" class="ml-2 px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50 flex items-center gap-2">
                        <span aria-hidden="true">🔍</span>
                        <span class="hidden sm:inline">Quét</span>
                    </button>
                </div>
                <p id="new_shelf_name" class="text-sm text-gray-500 mt-1"></p>
                <p id="new_shelf_warning" class="text-sm text-yellow-600 mt-1 hidden"><i class="fas fa-exclamation-triangle"></i> Kệ đích đã có sản phẩm!</p>
            </div>

            <button type="button" id="btn_transfer_products" class="w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" disabled>
                <span aria-hidden="true">↔️</span>
                <span>Điều chuyển sản phẩm</span>
            </button>
        </div>
    </div>

    <div id="transfer_message" class="mt-6 p-3 rounded-md text-sm hidden"></div>
</div>

<script>
$(document).ready(function() {
    attachScanOnlyGuard('#current_shelf_id, #new_shelf_id');

    let currentShelfProducts = []; // Stores products from the current shelf
    let newShelfExists = false;

    function showMessage(message, type = 'success') {
        const msgDiv = $('#transfer_message');
        msgDiv.removeClass().addClass('mt-6 p-3 rounded-md text-sm').show();
        if (type === 'success') {
            msgDiv.addClass('bg-green-100 text-green-800');
        } else if (type === 'error') {
            msgDiv.addClass('bg-red-100 text-red-800');
        } else if (type === 'warning') {
            msgDiv.addClass('bg-yellow-100 text-yellow-800');
        }
        msgDiv.html(message);
    }

    function clearMessages() {
        $('#transfer_message').hide().html('');
    }

    function resetForm() {
        $('#current_shelf_id').val('');
        $('#current_shelf_name').text('');
        $('#new_shelf_id').val('');
        $('#new_shelf_name').text('');
        $('#new_shelf_warning').hide();
        $('#current_shelf_products_container').hide();
        $('#current_shelf_products_table_body').empty();
        $('#btn_transfer_products').prop('disabled', true);
        $('#select_all_products').prop('checked', false);
        currentShelfProducts = [];
        newShelfExists = false;
        clearMessages();
    }

    // Scan Current Shelf
    $('#btn_scan_current_shelf').on('click', function() {
        const shelfId = $('#current_shelf_id').val().trim();
        if (!shelfId) {
            showMessage('Vui lòng nhập mã kệ nguồn.', 'warning');
            return;
        }
        clearMessages();
        $('#current_shelf_products_table_body').empty();
        $('#current_shelf_products_container').hide();
        $('#btn_transfer_products').prop('disabled', true);

        $.getJSON('api.php?action=get_products_on_shelf', { shelf_id: shelfId }, function(res) {
            if (res.success) {
                currentShelfProducts = res.products;
                if (currentShelfProducts.length > 0) {
                    $('#current_shelf_name').text(`Kệ nguồn: ${shelfId}`); // Display shelf ID for confirmation
                    $('#current_shelf_products_container').show();
                    currentShelfProducts.forEach(function(product) {
                        const row = `
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" class="product-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" data-product-id="${product.product_id}" data-quantity="${product.quantity}" checked>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.product_id}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.odd_shelves || '-'}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${product.quantity}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" class="transfer-qty-input w-24 border-gray-300 rounded-md shadow-sm" value="${product.quantity}" min="1" max="${product.quantity}" data-product-id="${product.product_id}">
                                </td>
                            </tr>
                        `;
                        $('#current_shelf_products_table_body').append(row);
                    });
                    $('#select_all_products').prop('checked', true);
                    checkTransferButtonState();
                } else {
                    showMessage('Kệ nguồn không có sản phẩm nào.', 'warning');
                    $('#current_shelf_name').text(`Kệ nguồn: ${shelfId}`);
                }
            } else {
                showMessage(res.message || 'Không tìm thấy kệ nguồn hoặc có lỗi.', 'error');
            }
        }).fail(function() {
            showMessage('Lỗi kết nối API khi lấy sản phẩm kệ nguồn.', 'error');
        });
    });

    // Select/Deselect all products
    $('#select_all_products').on('change', function() {
        $('.product-checkbox').prop('checked', $(this).prop('checked'));
        checkTransferButtonState();
    });

    // Handle individual product checkbox change
    $(document).on('change', '.product-checkbox', function() {
        if (!$(this).prop('checked')) {
            $('#select_all_products').prop('checked', false);
        } else {
            const allChecked = $('.product-checkbox').length === $('.product-checkbox:checked').length;
            $('#select_all_products').prop('checked', allChecked);
        }
        checkTransferButtonState();
    });

    // Handle quantity input change
    $(document).on('input', '.transfer-qty-input', function() {
        const maxQty = parseInt($(this).attr('max'));
        let currentVal = parseInt($(this).val());
        if (isNaN(currentVal) || currentVal < 1) {
            $(this).val(1);
        } else if (currentVal > maxQty) {
            $(this).val(maxQty);
        }
        checkTransferButtonState();
    });

    // Scan New Shelf
    $('#btn_scan_new_shelf').on('click', function() {
        const shelfId = $('#new_shelf_id').val().trim();
        if (!shelfId) {
            showMessage('Vui lòng nhập mã kệ đích.', 'warning');
            return;
        }
        clearMessages();
        $('#new_shelf_name').text('');
        $('#new_shelf_warning').hide();
        $('#btn_transfer_products').prop('disabled', true);
        newShelfExists = false;

        $.getJSON('api.php?action=check_shelf_existence_and_content', { shelf_id: shelfId }, function(res) {
            if (res.success) {
                if (res.exists) {
                    newShelfExists = true;
                    $('#new_shelf_name').text(`Kệ đích: ${shelfId}`);
                    if (res.has_products) {
                        $('#new_shelf_warning').show();
                    }
                    checkTransferButtonState();
                } else {
                    showMessage('Kệ đích không tồn tại hoặc không hoạt động.', 'error');
                }
            } else {
                showMessage(res.message || 'Lỗi khi kiểm tra kệ đích.', 'error');
            }
        }).fail(function() {
            showMessage('Lỗi kết nối API khi kiểm tra kệ đích.', 'error');
        });
    });

    function checkTransferButtonState() {
        const currentShelfIdVal = $('#current_shelf_id').val().trim();
        const newShelfIdVal = $('#new_shelf_id').val().trim();

        const currentShelfFilled = currentShelfIdVal !== '' && currentShelfProducts.length > 0;
        const newShelfReady = newShelfIdVal !== '' && newShelfExists;
        const productsSelected = $('.product-checkbox:checked').length > 0;
        const quantitiesValid = validateTransferQuantities();
        const shelvesAreDifferent = currentShelfIdVal !== newShelfIdVal;

        if (currentShelfFilled && newShelfReady && productsSelected && quantitiesValid && shelvesAreDifferent) {
            $('#btn_transfer_products').prop('disabled', false);
        } else {
            $('#btn_transfer_products').prop('disabled', true);
        }
    }

    function validateTransferQuantities() {
        let isValid = true;
        $('.product-checkbox:checked').each(function() {
            const productId = $(this).data('product-id');
            const maxQty = parseInt($(this).data('quantity'));
            const transferQtyInput = $(`.transfer-qty-input[data-product-id="${productId}"]`);
            const transferQty = parseInt(transferQtyInput.val());

            if (isNaN(transferQty) || transferQty <= 0 || transferQty > maxQty) {
                isValid = false;
                return false; // Break out of each loop
            }
        });
        return isValid;
    }

    // Transfer Products
    $('#btn_transfer_products').on('click', function() {
        const currentShelfId = $('#current_shelf_id').val().trim();
        const newShelfId = $('#new_shelf_id').val().trim();

        if (currentShelfId === newShelfId) {
            showMessage('Kệ nguồn và kệ đích không được trùng nhau.', 'error');
            return;
        }

        const productsToTransfer = [];
        $('.product-checkbox:checked').each(function() {
            const productId = $(this).data('product-id');
            const transferQty = parseInt($(`.transfer-qty-input[data-product-id="${productId}"]`).val());
            productsToTransfer.push({ product_id: productId, quantity: transferQty });
        });

        if (productsToTransfer.length === 0) {
            showMessage('Vui lòng chọn ít nhất một sản phẩm để điều chuyển.', 'warning');
            return;
        }

        if (!validateTransferQuantities()) {
            showMessage('Số lượng điều chuyển không hợp lệ cho một hoặc nhiều sản phẩm.', 'warning');
            return;
        }

        clearMessages();
        $(this).prop('disabled', true).text('Đang điều chuyển...');

        $.post('api.php?action=transfer_products', {
            current_shelf_id: currentShelfId,
            new_shelf_id: newShelfId,
            products_to_transfer: JSON.stringify(productsToTransfer)
        }, function(res) {
            if (res.success) {
                resetForm();
                showMessage(res.message || 'Điều chuyển sản phẩm thành công!', 'success');
            } else {
                showMessage(res.message || 'Lỗi khi điều chuyển sản phẩm.', 'error');
            }
        }, 'json').fail(function() {
            showMessage('Lỗi kết nối API khi điều chuyển sản phẩm.', 'error');
        }).always(function() {
            $('#btn_transfer_products').prop('disabled', false).text('Điều chuyển sản phẩm');
        });
    });

    // Initial check for button state
    checkTransferButtonState();
});
</script>