# Đây là dự án quản lý kho thông minh Smart WMS (S-WMS).
Dự án này nhằm mục đích cung cấp một giải pháp quản lý kho hiệu quả, giúp doanh nghiệp tối ưu hóa quy trình nhập xuất kho, quản lý tồn kho và phân quyền người dùng. 
Đồng thời, kiểm soát các luồng nghiệp vụ nhập xuất kho phòng chống nhầm lẫn nhập xuất hàng thông qua việc sử dụng mã QR và các quy trình xác nhận chặt chẽ.

## Yêu cầu dự án
* Xây dựng bằng ngôn ngữ lập trình website dễ bảo trì: PHP, CSS, JS. Chạy offline cho mạng nội bộ Công ty.
* Giao diện phải tối ưu cho cả màn hình lớn (PC/Laptop/Tablets) và cả màn hình nhỏ (PDA chuyên dụng/Smartphone). Giao diện chức năng tập trung vào các khu vực nhập liệu, nút nhấn, con số, thẻ cảnh báo thay vì các chú thích dài dòng.
* Tất cả thao tác từ người dùng đều được ghi nhận vào lịch sử giao dịch, đồng thời có thể xuất ra Excel để kiểm tra, đối chiếu.
* Các hàm chức năng chuẩn được lưu trữ trong `api.php` để gọi nhiều lần, có thể thông qua Ajax.
* Phân quyền truy cập bằng ma trận chức năng (CRUD) x quyền. Các quyền được phân theo cấp bậc từ thấp đến cao: Guest, Staff, Leader, Manager, Admin. Mô tả cơ bản như sau:
  **Guest**: Xem thông tin Dashboard, tra cứu Tồn kho.
  **Staff**: Gồm quyền Guest + Nhận Pallet, Picking, Packing, Pickup.
  **Leader**: Gồm quyền Staff + Chuyển Pallet >>> kệ, Nhập kho, Xuất kho, Đổi kệ, In phiếu picking, In tem Case, In tem Pallet.
  **Manager**: Gồm quyền Leader + Đăng ký Kệ hàng, Sản phẩm, Quản trị User, Xuất dữ liệu, Layout.
  **Admin**: Full quyền.

### Giải thích cơ bản các chức năng hệ thống (theo luồng tiêu chuẩn)

**In tem Pallet (pallet-print.php)**: Tạo và in tem dán cho pallet mới trước khi nhập hàng vào khu vực tạm. Mục tiêu là đảm bảo mỗi pallet có mã định danh duy nhất và không trùng với các pallet đã được đăng ký trong `import_temp`.

**Nhập Pallet (pallet-import.php)**: Đây là bước đăng ký hàng hóa vào pallet trước khi nhập chính thức vào kho S-WMS. Hệ thống nhận mã pallet, quét từng mã hàng và kiểm tra tính hợp lệ của sản phẩm theo master `products`. Nếu SKU không tồn tại, hệ thống dừng thao tác và báo lỗi. Sau khi xác nhận, dữ liệu sẽ được lưu tạm vào `import_temp` để chờ xử lý tiếp theo.

**Nhận Pallet (pallet-receive.php)**: Nhân viên kho xác nhận pallet đã được nhập tạm và sẵn sàng để chuyển sang khu vực xử lý tiếp theo. Hệ thống kiểm tra pallet có tồn tại trong `import_temp` và trạng thái của nó. Nếu hợp lệ, hệ thống ghi nhận ai đã nhận pallet, thời gian nhận và trạng thái tương ứng.

**Pallet >>> kệ (pallet-transfer.php)**: Sau khi pallet ở trạng thái chờ nhận, nhân viên sẽ quét mã kệ và mã pallet để cất hàng vào vị trí lưu trữ. Hệ thống kiểm tra kệ còn chỗ trống hay không. Nếu đủ điều kiện, hệ thống cộng tồn kho vào `inventory`, cập nhật `shelves.current_usage`, ghi lịch sử giao dịch vào `transactions` với `type = 'IN'`, đồng thời cập nhật trạng thái pallet thành đã chuyển xong.

**Import Monitor (import-monitor.php)**: Giám sát tiến độ nhập hàng theo pallet: tiến độ nhập, tiến độ nhận pallet và tiến độ cất lên kệ. Mục đích là theo dõi tình trạng xử lý từ đầu vào đến khi hàng vào kho chính.

**Kế hoạch xuất hàng (export_plan.php)**: Tạo kế hoạch xuất hàng theo từng Invoice. Mỗi đơn xuất phải bao gồm thông tin: số Invoice, ngày xuất, loại hình vận chuyển, mã khách hàng, số lượng cần picking và số kiện cần packing. Dữ liệu này được lưu ở bảng kế hoạch xuất để làm căn cứ cho các bước in phiếu, picking và packing.

**In phiếu picking (picking-print.php)**: Dựa trên kế hoạch xuất hàng, hệ thống nhóm số lượng theo SKU và in phiếu picking cho từng mã hàng. Mỗi phiếu chứa thông tin invoice, mã khách hàng, mã hàng, số lượng cần lấy và QR code để dùng cho bước picking.

**Picking (picking.php)**: Đây là bước lấy hàng từ kệ theo chỉ thị xuất. Hệ thống phải xác nhận đúng vị trí, đúng SKU và đúng số lượng. Nếu vị trí không hợp lệ, hàng không khớp hoặc số lượng vượt quá tồn kho, thao tác bị dừng và cảnh báo. Khi hoàn tất, hệ thống trừ tồn kho `inventory`, ghi `transactions` với `type = 'OUT'` và log trạng thái picking vào `export_log`.

**In phiếu packing (packing-print.php)**: Dựa trên kế hoạch xuất hàng, hệ thống in phiếu packing theo từng kiện (`case_no`). Mỗi kiện có một tem riêng, chứa thông tin invoice, mã khách hàng, số kiện, số lượng và QR code dùng để xác thực ở bước packing.

**Packing (packing.php)**: Tách hàng đã picking theo từng kiện nhỏ trong mỗi Invoice. Nhân viên quét QR trên thùng hàng và đối chiếu với thông tin trong packing list. Nếu sản phẩm hoặc số lượng không khớp, hệ thống báo lỗi. Khi hoàn tất, hệ thống kiểm tra xem từng kiện đã đủ số lượng chưa và ghi trạng thái packing.

**Pickup (pickup.php)**: Xác nhận kiện hàng đã sẵn sàng để bốc lên xe. Hệ thống so khớp thông tin trên tem pallet với thông tin trên shipping mark. Nếu khớp, xác nhận thành công; nếu không khớp, dừng thao tác và báo lỗi.

**Export Monitor (export-monitor.php)**: Theo dõi tiến độ xuất hàng theo Invoice, bao gồm tiến độ picking, packing và pickup. Mục tiêu là giúp quản lý nhìn thấy trạng thái thực tế của từng đơn xuất.

**Dashboard (monitor.php)**: Hiển thị màn hình giám sát tổng quan tiến độ theo ngày ở chế độ fullscreen. Giao diện này chủ yếu phục vụ mục đích trình chiếu, khách quan sát và theo dõi tiến độ vận hành kho.

**Nhập kho (inbound.php)**: Đây là luồng nhập kho cơ bản, dùng khi Leader cần tăng tồn kho trực tiếp mà không qua pallet tạm. Hệ thống kiểm tra kệ còn chỗ, kiểm tra SKU hợp lệ, cập nhật `inventory`, `shelves.current_usage` và ghi vào `transactions` với `type = 'IN'`.

**Xuất kho (outbound.php)**: Đây là luồng xuất kho cơ bản, dùng khi cần trừ tồn kho trực tiếp mà không qua quá trình picking chuẩn. Hệ thống kiểm tra tồn kho thực tế, xác nhận số lượng xuất, cập nhật `inventory` và `shelves.current_usage`, rồi ghi `transactions` với `type = 'OUT'`.

**Tồn kho (inventory.php)**: Cho phép tra cứu tồn kho theo mã hàng, mã vị trí, hoặc thông tin khác như pallet đầu vào. Hệ thống truy vấn dữ liệu từ `inventory` và `import_temp` để hiển thị số lượng và vị trí lưu trữ.

**Đổi kệ (change.php)**: Chuyển hàng từ kệ cũ sang kệ mới với số lượng tùy chọn. Hệ thống phải kiểm tra hàng ở kệ cũ có đủ không và kệ mới còn chỗ không. Nếu hợp lệ, thực hiện trừ tồn ở kệ cũ, cộng tồn ở kệ mới và ghi hai giao dịch `MOVE_OUT` và `MOVE_IN`.

**Kiểm kê (check-stock.php)**: Thực hiện đối chiếu số lượng thực tế trong kho với dữ liệu tồn kho của hệ thống. Nếu sai lệch, hệ thống ghi nhận kết quả kiểm kê vào `check_log` kèm trạng thái khớp/không khớp.

**Sản phẩm (product.php)**: Đăng ký master SKU mới vào hệ thống để phục vụ các nghiệp vụ nhập/xuất/picking/packing. Mỗi `product_id` phải là duy nhất.

**Kệ hàng (shelves.php)**: Đăng ký vị trí lưu hàng mới vào hệ thống, bao gồm mã kệ, tên kệ, cấp vị trí và dung lượng tối đa. Mỗi kệ phải có mã duy nhất và hệ thống tự động khởi tạo mức sử dụng ban đầu bằng 0.

**Layout (layout.php)**: Trình bày trạng thái các vị trí kệ theo hình thức trực quan bằng màu sắc, cho phép người dùng biết kệ nào còn trống, kệ nào đang chứa hàng và mức độ đầy/đã sử dụng.

**Xuất dữ liệu (data-export.php)**: Cho phép xuất dữ liệu cần thiết ra file Excel/CSV như tồn kho, lịch sử nhập-xuất, sản phẩm và dữ liệu kiểm kê theo quyền truy cập tương ứng.

**Quản trị (admin.php)**: Quản lý tài khoản người dùng và phân quyền theo ma trận chức năng từng vai trò: Guest, Staff, Leader, Manager, Admin. Mục tiêu là giới hạn quyền truy cập theo từng chức năng và cấp độ người dùng.

### Trình tự chi tiết các chức năng

#### 1. Sản phẩm (product.php)
* Mục tiêu: đăng ký master sản phẩm để các luồng nhập/xuất/picking có thể đối chiếu mã hàng.
* Thao tác:
  1. Người dùng nhập `product_id`, `product_name`, `unit`, `description`.
  2. Hệ thống kiểm tra `product_id` có trống không và có tồn tại trong `products` chưa.
  3. Nếu hợp lệ, thực hiện `INSERT INTO products (product_id, product_name, unit, description)`.
  4. Nếu trùng khóa `products.product_id` (UNIQUE KEY `sku`), hiển thị popup màu ĐỎ và dừng thủ tục.
* Bảng tương tác:
  - `products`: lưu master SKU, tên, đơn vị.
* Điều kiện bắt buộc:
  - `product_id` không được rỗng và phải là duy nhất.
  - `product_name`, `unit`, `description` có thể null theo thiết kế ban đầu.

#### 2. Kệ hàng (shelves.php)
* Mục tiêu: đăng ký vị trí lưu hàng.
* Thao tác:
  1. Người dùng nhập `shelf_id`, `shelf_name`, các cấp `level0_val` đến `level4_val`, `capacity`, `status`.
  2. Hệ thống kiểm tra `shelf_id` đã tồn tại trong `shelves` chưa.
  3. Nếu chưa có, thực hiện `INSERT INTO shelves (...)`.
  4. Mặc định: `current_usage = 0`, `status = 'Active'` nếu không nhập.
* Bảng tương tác:
  - `shelves`: lưu thông tin kệ, sức chứa, mức sử dụng hiện tại.
* Điều kiện bắt buộc:
  - `shelf_id` là duy nhất (`UNIQUE KEY shelf_code`).
  - `capacity` mặc định = 100 nếu null.
  - `current_usage` mặc định = 0 nếu null.

#### 3. In tem Pallet (pallet-print.php)
* Mục tiêu: tạo mã pallet độc lập dùng cho bước nhập kho tạm.
* Thao tác:
  1. Hệ thống sinh mã pallet duy nhất hoặc lấy ID mới dạng `pallet_id`.
  2. In tem pallet để dán lên pallet rỗng.
  3. Sau khi in, người dùng quét mã pallet ở bước nhập hàng.
* Bảng tương tác:
  - Không bắt buộc phải ghi vào SQL ngay nếu chưa có bảng `pallet` riêng; dữ liệu chính sẽ được lưu vào `import_temp` sau khi nhập hàng thực tế.
* Điều kiện bắt buộc:
  - `pallet_id` phải duy nhất, tránh trùng với pallet đã đăng ký trước đó.

#### 4. Nhập Pallet (pallet-import.php)
* Mục tiêu: đăng ký sản phẩm vào pallet tạm trước khi nhập kho chính thức.
* Thao tác:
  1. Tạo và in tem Pallet, dán vào pallet rỗng.
  2. Quét QR mã Pallet (`pallet_id`).
  3. Quét QR từng mã hàng theo định dạng: `[SMC001]$[product_id]$[kani_code]$[quantity]$[Lot_no]`.
  4. Hệ thống tách chuỗi và kiểm tra `product_id` có tồn tại trong `products` không.
  5. Nếu mã hàng không có trong `products`, hiển thị popup màu ĐỎ và dừng thao tác.
  6. Nếu đúng, hệ thống tự động lấy `quantity`, thêm dòng vào danh sách tạm và cộng tổng số lượng đang quét.
  7. Người dùng tiếp tục quét các sản phẩm cho đến khi xong.
  8. Nhấn nút lưu, hệ thống ghi dữ liệu vào `import_temp`.
* Bảng tương tác:
  - `import_temp`: lưu từng dòng sản phẩm của pallet với các trường `pallet_id`, `part_no`, `qty`, `status`, `created_by`, `created_at`.
* Điều kiện bắt buộc:
  - `pallet_id` không được rỗng.
  - `part_no` phải khớp `products.product_id`.
  - `qty` > 0.
  - Mỗi 1 dòng trong `import_temp` đại diện cho 1 mặt hàng thuộc 1 pallet.

#### 5. Nhận Pallet (pallet-receive.php)
* Mục tiêu: xác nhận pallet đã được nhập tạm và sẵn sàng cho bước cất kệ.
* Thao tác:
  1. Nhân viên quét hoặc chọn `pallet_id` đang ở trạng thái tạm.
  2. Hệ thống kiểm tra pallet có tồn tại trong `import_temp` không.
  3. Nếu pallet hợp lệ, xác nhận nhận pallet để chờ chuyển lên kệ.
  4. Nếu có bảng log nhập kho bổ sung (`import_log`), lưu trạng thái `RECEIVED`; nếu chưa có, có thể quy ước theo `import_temp.status` như `received`.
* Bảng tương tác:
  - `import_temp`: kiểm tra `pallet_id` và `status`.
  - Bảng log bổ sung nếu có: `import_log`.
* Điều kiện bắt buộc:
  - Pallet phải nằm trong `import_temp` và đang ở trạng thái phù hợp (`received`/`waiting`/`ready`, tùy quy ước triển khai).
  - `created_by` phải được ghi nhận người nhận.

#### 6. Pallet >>> Kệ (transfer.php)
* Mục tiêu: cất pallet từ khu vực tạm lên hệ thống kho chính.
* Thao tác:
  1. Quét QR mã kệ (`shelf_id`).
  2. Hệ thống kiểm tra kệ có tồn tại trong `shelves` không.
  3. Kiểm tra `capacity > current_usage` để đảm bảo kệ còn chỗ.
  4. Quét QR mã pallet đang được nhận.
  5. Hệ thống kiểm tra pallet có trong `import_temp` và `status` phù hợp (`received`/đã nhận).
  6. Xác nhận cất kệ.
  7. Thực hiện transaction: lặp qua từng dòng sản phẩm của pallet từ `import_temp`.
  8. Với mỗi `part_no`/`product_id` trên pallet:
     - Nếu cặp `(shelf_id, product_id)` đã tồn tại trong `inventory`, thì `UPDATE inventory SET quantity = quantity + qty`.
     - Nếu chưa có, thì `INSERT INTO inventory (shelf_id, product_id, quantity) VALUES (...)`.
     - Ghi vào `transactions` với `type='IN'`, `product_id`, `shelf_id`, `quantity`, `created_by`.
  9. Cập nhật `shelves.current_usage = current_usage + tổng_số_lượng_của_pallet`.
  10. Cập nhật `import_temp.status = 'transferred'` cho pallet đã xong.
* Bảng tương tác:
  - `import_temp`
  - `inventory`
  - `transactions`
  - `shelves`
* Điều kiện bắt buộc:
  - Kệ phải tồn tại và còn sức chứa.
  - Pallet phải đã được nhận và `status` hợp lệ.
  - Tổng lượng nhập phải không làm `current_usage > capacity`.

#### 7. Nhập kho cơ bản (inbound.php)
* Mục tiêu: bổ sung hàng hóa vào kho mà không cần qua pallet tạm.
* Thao tác:
  1. Quét QR mã kệ.
  2. Hệ thống kiểm tra kệ có tồn tại trong `shelves` không.
  3. Quét QR mã hàng; kiểm tra `products` có chứa mã đó không.
  4. Nhập số lượng cần tăng tồn.
  5. Tính toán: `new_usage = current_usage + qty`; nếu `new_usage > capacity`, cảnh báo và dừng.
  6. Xác nhận nhập kho.
  7. Cập nhật `inventory` theo `(shelf_id, product_id)`.
  8. Cập nhật `shelves.current_usage`.
  9. Ghi lịch sử vào `transactions` với `type = 'IN'`.
* Bảng tương tác:
  - `products`
  - `shelves`
  - `inventory`
  - `transactions`
* Điều kiện bắt buộc:
  - `product_id` phải tồn tại trong `products`.
  - `shelf_id` phải tồn tại.
  - `qty > 0` và `capacity` không vượt quá giới hạn.

#### 8. Xuất kho cơ bản (outbound.php)
* Mục tiêu: trừ tồn kho theo đơn điều chỉnh nhanh.
* Thao tác:
  1. Quét QR mã kệ.
  2. Quét QR mã hàng; kiểm tra `(shelf_id, product_id)` có tồn tại trong `inventory` không.
  3. Nhập số lượng cần xuất.
  4. Hệ thống kiểm tra `qty <= current inventory quantity`.
  5. Nếu không đủ, cảnh báo đỏ và dừng.
  6. Xác nhận xuất kho.
  7. `UPDATE inventory SET quantity = quantity - qty`.
  8. `UPDATE shelves SET current_usage = current_usage - qty`.
  9. Ghi `transactions` với `type='OUT'`.
* Bảng tương tác:
  - `inventory`
  - `shelves`
  - `transactions`
* Điều kiện bắt buộc:
  - `(shelf_id, product_id)` phải tồn tại trong `inventory`.
  - `qty <= quantity` trong bảng `inventory`.

#### 9. Đổi kệ (change.php)
* Mục tiêu: di chuyển hàng từ kệ cũ sang kệ mới.
* Thao tác:
  1. Quét mã kệ cũ.
  2. Quét mã kệ mới.
  3. Quét mã hàng cần chuyển.
  4. Nhập số lượng cần đổi.
  5. Kiểm tra:
     - Kệ cũ có đủ tồn kho cho `(shelf_old, product_id)`.
     - Kệ mới có đủ không gian còn lại để chứa số lượng chuyển.
  6. Xác nhận.
  7. Thực hiện 2 bước đồng thời:
     - Trừ tồn ở kệ cũ: `UPDATE inventory SET quantity = quantity - qty` và `UPDATE shelves SET current_usage = current_usage - qty`.
     - Cộng tồn ở kệ mới: `INSERT/UPDATE inventory` và `UPDATE shelves SET current_usage = current_usage + qty`.
  8. Ghi 2 dòng `transactions`: `MOVE_OUT` và `MOVE_IN`.
* Bảng tương tác:
  - `inventory`
  - `shelves`
  - `transactions`
* Điều kiện bắt buộc:
  - Tồn kho trên kệ cũ phải >= qty.
  - Kệ mới còn sức chứa theo `capacity - current_usage >= qty`.

#### 10. Tồn kho (inventory.php)
* Mục tiêu: tra cứu và rà soát hàng tồn tại trong kho theo vị trí hoặc mã hàng.
* Thao tác:
  1. Người dùng nhập mã sản phẩm hoặc mã kệ trên ô tìm kiếm.
  2. Hệ thống thực hiện truy vấn kết hợp `inventory`, `products`, `shelves`.
  3. Hiển thị danh sách: `shelf_id`, `product_id`, `product_name`, `quantity`, vị trí, đơn vị.
* Bảng tương tác:
  - `inventory`
  - `products`
  - `shelves`
* Điều kiện bắt buộc:
  - Chỉ hiển thị các dòng có `quantity > 0` hoặc theo lựa chọn filter của người dùng.
* Ví dụ truy vấn chuẩn:
  - `SELECT i.*, p.product_id, p.product_name, s.shelf_id FROM inventory i JOIN products p ON p.product_id = i.product_id JOIN shelves s ON s.shelf_id = i.shelf_id WHERE ...;`

#### 11. Kế hoạch xuất hàng và in phiếu picking (export_plan.php / picking-print.php)
* Mục tiêu: chuẩn bị thông tin xuất theo Invoice, số kiện và số lượng hàng cần picking.
* Thao tác:
  1. Người dùng nhập toàn bộ packing list từ Excel vào `export_temp`.
  2. Hệ thống lọc theo ngày, hiển thị Invoice, `case_no`, `transport_type`, `for_product`, `created_at`.
  3. Chọn Invoice cần xử lý.
  4. Hệ thống nhóm theo `product_id` và cộng `total_qty` theo từng mã hàng.
  5. Hiển thị phiếu picking trước khi in.
  6. In phiếu: mỗi phiếu chứa `command`, `created_at`, `transport_type`, `for_product`, `product_id`, `quantity`, thời gian in.
  7. Ghi log `export_log` với `status='print'` (nếu bảng log phụ được triển khai).
* Bảng tương tác:
  - `export_temp`
  - `export_log`
* Điều kiện bắt buộc:
  - `command` phải là mã Invoice, `product_id` phải tồn tại trong master sản phẩm.
  - `total_qty` và `bucket_qty` phải phù hợp từng case / từng hàng.

#### 12. Picking (picking.php)
* Mục tiêu: lấy hàng từ vị trí kệ theo chỉ thị xuất hàng.
* Thao tác:
  1. Quét QR chỉ thị/Invoice và lấy `product_id`, `quantity` cần picking.
  2. Hệ thống tra `inventory` để tìm các vị trí còn hàng theo `product_id`, sắp xếp theo vị trí có lượng ít nhất trước.
  3. Người dùng chọn kệ đề xuất; quét mã kệ và đối chiếu với vị trí đã chọn.
  4. Quét mã hàng trên thùng hàng; phải khớp với `product_id` cần picking.
  5. Nếu số lượng trên thùng nhỏ hơn số lượng còn lại cần picking, hệ thống ghi nhận phần đã lấy và tiếp tục chọn vị trí khác.
  6. Nếu số lượng trên thùng lớn hơn lượng còn lại, chỉ lấy phần còn lại cần thiết và dừng để nhân viên xác nhận.
  7. Nếu vị trí đã hết hàng, popup màu VÀNG: "Vị trí này đã hết hàng, vui lòng chọn vị trí tiếp theo".
  8. Nếu không còn vị trí phù hợp, popup màu ĐỎ: "Không còn vị trí nào có hàng".
  9. Khi hoàn thành, ghi log `transactions` với `type='OUT'` và cập nhật `inventory` theo từng vị trí.
  10. Ghi `export_log` với `status='picking'` khi nhấn Hoàn thành.
* Bảng tương tác:
  - `inventory`
  - `transactions`
  - `export_log`
* Điều kiện bắt buộc:
  - `product_id` tại vị trí phải khớp với chỉ thị picking.
  - Tổng lượng đã lấy không được vượt quá lượng cần picking.
  - Mỗi lần trừ tồn phải đảm bảo `inventory.quantity >= 0`.

#### 13. In tem packing (print_case.php)
* Mục tiêu: tạo tem cho từng kiện hàng (`case_no`) trong Invoice.
* Thao tác:
  1. Chọn Invoice và lọc `export_temp` theo `command`.
  2. Nhóm theo `case_no` và tính số item trong từng kiện.
  3. In tem packing cho từng `case_no`.
  4. Mỗi tem chứa `command`, `case_no`, `for_product`, `transport_type`, `created_at`, số lượng item.
  5. QR code trong tem được tạo từ `[command]$[case_no]$[for_product]$[count distinct product_id]$[transport_type]$[created_at]`.
  6. Ghi log `export_log` với `status='print_case'`.
* Bảng tương tác:
  - `export_temp`
  - `export_log`
* Điều kiện bắt buộc:
  - `case_no` phải là mã kiện riêng trong từng Invoice.
  - Không được in thiếu hoặc dư kiện so với `export_temp`.

#### 14. Packing (packing.php)
* Mục tiêu: xếp từng thùng hàng vào kiện theo `case_no`.
* Thao tác:
  1. Quét QR tem packing.
  2. Hệ thống đọc `command`, `case_no`, `for_product`, `transport_type`, `created_at`.
  3. Quét mã thùng hàng theo dạng `[TEXT]$[product_id]$[TEXT]$[quantity]$[TEXT]`.
  4. Lấy `product_id` và `quantity` từ thùng, đối chiếu với dữ liệu `export_temp` theo `command` và `case_no`.
  5. Nếu `product_id` hoặc `quantity` không khớp, báo popup màu ĐỎ.
  6. Nếu khớp, ghi một log `export_log` với `status='packing'`.
  7. Khi nhấn Hoàn thành, kiểm tra tổng lượng đã packing trên từng `case_no`.
  8. Nếu thiếu số lượng, popup màu VÀNG: "Packing chưa đủ số lượng, vui lòng kiểm tra lại".
  9. Nếu đủ, popup màu XANH: "Packing hoàn tất, vui lòng chuyển sang bước Pickup".
* Bảng tương tác:
  - `export_temp`
  - `export_log`
* Điều kiện bắt buộc:
  - Không được vượt quá số lượng theo `case_no` đã quy định trong `export_temp`.
  - Mỗi thùng phải khớp đúng `product_id` và `quantity` ban đầu.

#### 15. Pickup (pickup.php)
* Mục tiêu: xác nhận kiện hàng đã sẵn sàng bốc lên xe theo tem pallet và shipping mark.
* Thao tác:
  1. Quét QR tem pallet chứa thông tin `command`, `case_no`, `for_product`, `transport_type`, `created_at`.
  2. Quét tiếp shipping mark với định dạng `[command][case_no]`.
  3. Hệ thống đối chiếu thông tin 2 mã này.
  4. Nếu khớp, hiển thị "đã khớp thông tin" và quét tiếp cho lần kế tiếp.
  5. Nếu không khớp, popup màu ĐỎ.
  6. Ghi `export_log` với `status='pickup'`.
* Bảng tương tác:
  - `export_log`
* Điều kiện bắt buộc:
  - `command` và `case_no` trên tem pallet phải khớp hoàn toàn với shipping mark.

#### 16. Monitor / Dashboard (monitor.php)
* Mục tiêu: giám sát tiến độ xuất hàng theo ngày, hiển thị cho khách hàng hoặc leader.
* Thao tác:
  1. Mở trang không cần đăng nhập.
  2. Hệ thống tự động refresh sau 5 phút để cập nhật tiến độ mới nhất.
  3. Hiển thị mỗi dòng là 1 Invoice, bao gồm: ngày xuất, `transport_type`, `for_product`, số item cần picking, số item đã picking, số item còn lại, số item đã packing, số kiện đã pickup, số kiện còn lại.
  4. Giao diện full màn hình, có Light/Dark mode. Mặc định là Dark mode.
* Bảng tương tác:
  - `export_temp`
  - `export_log`
  - `inventory`
  - `transactions`
* Điều kiện bắt buộc:
  - Dữ liệu phải được tổng hợp theo `command` và cập nhật theo trạng thái `picking`, `packing`, `pickup`.

#### 17. Kiểm kê (check-stock.php)
* Mục tiêu: đối chiếu thực tế kho với dữ liệu hệ thống.
* Thao tác:
  1. Nhân viên quét mã hàng / vị trí cần kiểm kê.
  2. So sánh số lượng thực tế với dữ liệu `inventory`.
  3. Ghi kết quả vào `check_log`.
* Bảng tương tác:
  - `check_log`
  - `inventory`
* Điều kiện bắt buộc:
  - `result_code` và `result_message` dùng để đánh giá khớp/không khớp.
  - `is_product_match`, `is_qty_match` lưu trạng thái đối chiếu.

#### 18. Quản lý người dùng và phân quyền (admin.php / log_users)
* Mục tiêu: phân quyền truy cập theo ma trận quyền.
* Thao tác:
  1. Thêm/sửa/xóa tài khoản trong `log_users`.
  2. Gán vai trò: Guest, Staff, Leader, Manager, Admin.
  3. Kiểm tra quyền theo chức năng và màn hình được phép truy cập.
* Bảng tương tác:
  - `log_users`
* Điều kiện bắt buộc:
  - `username` phải unique.
  - `role` phải nằm trong danh sách quyền được định nghĩa.

#### 19. Xuất dữ liệu (data-export.php)
* Mục tiêu: xuất dữ liệu ra Excel/CSV phục vụ kiểm tra và đối chiếu.
* Thao tác:
  1. Lựa chọn loại dữ liệu cần xuất: sản phẩm, tồn kho, lịch sử nhập/xuất, kiểm kê.
  2. Hệ thống truy vấn bảng tương ứng.
  3. Xuất file Excel/CSV cho người dùng tải xuống.
* Bảng tương tác:
  - `products`, `inventory`, `transactions`, `check_log`, `export_temp`, `import_temp`
* Điều kiện bắt buộc:
  - Chỉ export những dữ liệu theo quyền truy cập được phép.

#### 20. Layout (layout.php)
* Mục tiêu: hiển thị trực quan trạng thái kệ trong kho.
* Thao tác:
  1. Hệ thống đọc `shelves` và `inventory` để biết kệ nào còn hàng, kệ nào hết hàng.
  2. Hiển thị màu sắc trạng thái các vị trí khe/cột/kệ.
* Bảng tương tác:
  - `shelves`
  - `inventory`
* Điều kiện bắt buộc:
  - Nếu `current_usage = 0` hoặc `quantity = 0`, hiển thị trạng thái trống.
  - Nếu `current_usage > 0`, hiển thị trạng thái đã có hàng.

### Tóm tắt mối quan hệ dữ liệu chính
- `products` là master SKU dùng cho nhập, xuất, picking, packing.
- `shelves` là master vị trí kho, có `capacity` và `current_usage`.
- `inventory` lưu tồn kho theo cặp `(shelf_id, product_id)`.
- `transactions` ghi lịch sử nhập/xuất/đổi kệ liên quan đến sự thay đổi tồn kho với `type` và `created_by`.
- `import_temp` lưu pallet đang ở trạng thái tạm, phục vụ nhập và cất kệ.
- `import_log` ghi quá trình nhập, nhận, cất kệ Pallet.
- `export_temp` lưu kế hoạch xuất hàng theo `command`, `case_no`, `product_id`.
- `export_log` ghi quá trình picking, packing, pickup.
- `check_log` lưu lịch sử kiểm kê thực tế.