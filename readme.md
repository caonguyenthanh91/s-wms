# Đây là dự án quản lý kho thông minh Smart WMS (S-WMS).
Dự án này nhằm mục đích cung cấp một giải pháp quản lý kho hiệu quả, giúp doanh nghiệp tối ưu hóa quy trình nhập xuất kho, quản lý tồn kho và phân quyền người dùng. Đồng thời, kiểm soát các luồng nghiệp vụ nhập xuất kho phòng chóng nhầm lẫn.

## Yêu cầu dự án
Xây dựng bằng ngôn ngữ lập trình website dễ bảo trì: PHP, CSS, JS. Chạy offline cho mạng nội bộ.
Giao diện phải tối ưu cho cả màn hình lớn (PC/Laptop/Tablets) và cả màn hình nhỏ (PDA chuyên dụng/Smartphone).
Giao diện chức năng tập trung vào các khu vực nhập liệu, nút nhấn, con số thay vì các chú thích dài dòng.

### Các chức năng chính
1. **Nhận Pallet (import.php)**: Nhận các pallet hàng hóa từ các Nhà máy tập trung về. Hàng hóa và số lượng tương ứng trên Pallet được đăng ký theo mã số Pallet. Ghi nhận ai, thời điểm nhận hàng.
2. **Pallet >>> kệ (transfer.php)**: Để chuyển nhanh toàn bộ Pallet lên vị trí kệ chỉ định để bảo quản. Ghi nhận cộng tồn, đồng thời lưu lịch sử ai, lúc nào nhập kho (IN).
3. **Nhập kho (inbound.php)**: Luồng nhập kho cơ bản, thường dùng cho cấp Leader để cộng tồn, đồng thời lưu lịch sử ai, lúc nào nhập kho (IN).
4. **Xuất kho (outbound.php)**: Luồng xuất kho cơ bản, thường dùng cho cấp Leader để trừ tồn, đồng thời lưu lịch sử ai, lúc nào xuất kho (OUT).
5. **Tồn kho (inventory.php)**: Cho phép tra cứu tồn kho theo mã hàng, mã vị trí, mã pallet đầu vào.
6. **Đổi kệ (change.php)**: Cho phép đổi toàn bộ mã hàng hoặc một mã hàng trong kệ với số lượng tùy chỉnh sang kệ khác nhanh chóng. Thực hiện 2 lệnh trừ tồn ở vị trí cũ và cộng tồn vào vị trí mới, đồng thời ghi nhận lịch sử ai, lúc nào trừ tồn, cộng tồn.
7. **Picking (picking.php)**: Luồng nghiệp vụ picking theo chỉ thị (Invoice), kiểm soát tối đa sai sót thông qua việc xác nhận QR mã vị trí, QR mã hàng chứa định lượng. Picking đúng chỗ, đúng mã hàng, đúng số lượng. Trừ tồn tương tự luồng Nhập kho. Ngoài ghi nhận lịch sử trừ tồn, còn ghi nhận lịch sử picking theo Invoice.
8. **Packing (packing.php)**: Phân bổ đơn hàng đã picking theo kiện nhỏ trong mỗi chỉ thị (Invoice), kiểm soát mã QR trên thùng hàng khớp với packing list, hỗ trợ in đúng tem Xuất hàng cho từng thùng hàng, shipping mark. Ghi nhận lịch sử packing theo Invoice.
9. **Pickup (pickup.php)**: Xác nhận kiện hàng (số lượng thùng, nơi xuất, số Invoice,...) có trùng với thông tin trên tem do Phòng Xuất nhập khẩu in. Ghi nhận lịch sử pickup.
10. **In phiếu picking (print.php)**: Phiếu in nhiệt 80x80mm chứa thông tin mã hàng cần picking theo từng Invoice, picking gộp theo mã hàng.
11. **In tem Invoice (print_case.php)**: In tem QR chứa thông tin xác nhận của từng Invoice, từng kiện để tách kiện từ hàng hóa đã picking gộp.
12. **In tem Pallet (print_pallet.php)**: In tem dán cho Pallet đầu vào, đảm bảo Tem được tạo ra là duy nhất (chưa từng đăng ký).
13. **Layout (layout.php)**: Thể hiện trực quan các vị trí kệ đang có hay không có hàng hóa hay không thông qua màu sắc. Dùng 2 cấp để biến layout kệ hàng 3D thành các cấp nhỏ 2D.
14. **Dashboard (dashboard.php)**: Thể hiện danh sách các Invoice theo ngày và tiến độ picking (theo số items), packing (theo items), pickup (theo số kiện hàng, ngày bốc hàng lên xe).
15. **Sản phẩm (product.php)**: Cho phép đăng ký mới Master sản phẩm vào hệ thống.
16. **Kệ hàng (shelves.php)**: Cho phép đăng ký mới Master kệ hàng vào hệ thống.
17. **Quản trị (admin.php)**: Quản trị và phân quyền người dùng được phép truy cập đến mức nào trong hệ thống.
18. **[Mới] Nhập dữ liệu (import_data.php)**: Cho phép import các dữ liệu từ nguồn Excel vào hệ thống.
19. **[Mới] Xuất dữ liệu (export_data.php)**: Cho phép xuất các dữ liệu từ hệ thống ra Excel.

---

## Chi tiết luồng chức năng (Flow & Database Integration)

* Các hàm chức năng chuẩn được lưu trữ trong `api.php` để gọi nhiều lần, có thể thông qua Ajax.
* **Phân quyền truy cập (Role)** (Tham chiếu bảng `log_users`):
  - **Guest**: Xem thông tin Dashboard, tra cứu Tồn kho.
  - **Staff**: Thực thi luồng Nhận Pallet, Nhập kho, Xuất kho, Picking, Packing, Pickup, Tồn kho.
  - **Leader**: Gồm quyền Staff + Đổi kệ, Chuyển Pallet >>> kệ, in ấn phiếu, tem.
  - **Manager**: Gồm quyền Leader + Cập nhật Master Data, Quản trị User, xuất dữ liệu.
  - **Admin**: Full quyền, bao gồm Quản trị User.

### 1. Sản phẩm (product.php)
* **Mục tiêu:** Đăng ký mã hàng vào bảng `products`.
* **Luồng xử lý:**
  - Bước 1: Nhập `product_id` (Mã hàng), `product_name` (Tên) được phép Null, `unit` (Đơn vị) được phép Null, `description` (Mô tả) được phép Null.
  - Bước 2: Hệ thống kiểm tra trùng lặp `product_id`. Nếu trùng, báo lỗi.
  - Bước 3: Nếu hợp lệ, `INSERT INTO products (product_id, product_name, unit, description)`. Có thể kết hợp import từ Excel.

### 2. Kệ hàng (shelves.php)
* **Mục tiêu:** Đăng ký kệ lưu trữ vào bảng `shelves`.
* **Luồng xử lý:**
  - Bước 1: Nhập `shelf_id`, `shelf_name`, phân cấp (level từ 0 đến 4), `capacity` (sức chứa) được phép Null, `current_usage` (Sử dụng hiện tại) được phép Null
  - Bước 2: Kiểm tra `shelf_id` đã tồn tại chưa.
  - Bước 3: Nếu chưa, `INSERT INTO shelves`. Mặc định `current_usage = 0`, `status = 'Active'`.

### 3. Nhận Pallet (import.php)
* **Mục tiêu:** Ghi nhận hàng hóa lên một Pallet đầu vào (chưa lên kệ), lưu vào `import_temp`.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét/Nhập QR mã Pallet (`pallet_id`).
  - Bước 2: Quét/Nhập QR mã hàng (`product_id`) dạng chuỗi [TEXT]$[product_id]$[TEXT]$[quantity]$[TEXT]. Hệ thống đối chiếu `products.product_id` xem mã hàng có tồn tại không. Nếu không, cảnh báo bằng popup.
  - Bước 3: Nhập số lượng (`qty`).
  - Bước 4: Lưu dữ liệu vào `import_temp` với `status = 'received'`, `created_by = [current_user]`.

### 4. Pallet >>> Kệ (transfer.php)
* **Mục tiêu:** Cất nguyên Pallet lên kệ nhanh chóng.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR mã kệ (`shelf_id`). Kiểm tra mã kệ có trong bảng `shelves` và còn sức chứa (`capacity > current_usage`) không. Sai/Đầy thì cảnh báo.
  - Bước 2: Quét QR mã Pallet. Kiểm tra Pallet có trong `import_temp` và `status = 'received'` không.
  - Bước 3: Xác nhận cất kệ.
  - Bước 4: Chạy vòng lặp cập nhật DB (Transaction xử lý đồng thời):
    - Lấy thông tin các mã hàng trên Pallet từ `import_temp`.
    - Lấy ID (khóa chính) của kệ và sản phẩm.
    - Cập nhật tồn kho: Nếu cặp (shelf_id, product_id) đã có trong `inventory` -> `UPDATE quantity`. Chưa có -> `INSERT`.
    - Ghi lịch sử: `INSERT INTO transactions (product_id, shelf_id, quantity, type='IN', created_by)`.
    - Đánh dấu hoàn tất: `UPDATE import_temp SET status='transferred' WHERE pallet_id = ...`.
    - Cộng sức chứa kệ: `UPDATE shelves SET current_usage = current_usage + [Tổng số lượng]`.

### 5. Nhập kho cơ bản (inbound.php)
* **Mục tiêu:** Nhập thủ công/lẻ hàng hóa lên kệ.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR mã kệ. Đúng định dạng thì hiển thị tên kệ/sức chứa trống.
  - Bước 2: Quét QR mã hàng. Kiểm tra tồn tại trong `products`.
  - Bước 3: Nhập số lượng. Cảnh báo nếu số lượng nhập làm `current_usage > capacity` của kệ.
  - Bước 4: Xác nhận. Cập nhật bảng `inventory` (+ tồn), `shelves` (+ usage), và ghi `transactions` (type='IN').

### 6. Xuất kho cơ bản (outbound.php)
* **Mục tiêu:** Xuất thủ công/lẻ hàng hóa khỏi kệ (trừ tồn trực tiếp).
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR mã kệ.
  - Bước 2: Quét QR mã hàng. Hệ thống truy vấn `inventory` kiểm tra (shelf, product) này có tồn tại không. Không có -> cảnh báo.
  - Bước 3: Nhập số lượng xuất. Hệ thống kiểm tra số lượng xuất <= `quantity` hiện có. Vượt quá -> Cảnh báo.
  - Bước 4: Xác nhận. Trừ tồn `inventory` (- tồn), `shelves` (- usage) và ghi `transactions` (type='OUT').

### 7. Đổi kệ (change.php)
* **Mục tiêu:** Di chuyển vị trí tồn kho nội bộ.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét mã kệ CŨ. 
  - Bước 2: Quét mã kệ MỚI.
  - Bước 3: Quét mã hàng cần chuyển.
  - Bước 4: Nhập số lượng. Kiểm tra kệ CŨ có đủ hàng, kệ MỚI có đủ sức chứa hay không.
  - Bước 5: Thực thi DB: 
    - Trừ tồn kệ CŨ (Update `inventory`, `shelves`).
    - Cộng tồn kệ MỚI (Update/Insert `inventory`, `shelves`).
    - Ghi 2 dòng vào `transactions`: 1 dòng `type='MOVE_OUT'`, 1 dòng `type='MOVE_IN'`.

### 8. Tồn kho (inventory.php)
* **Mục tiêu:** Tra cứu số lượng tức thời.
* **Luồng nghiệp vụ:**
  - Ô tìm kiếm đa năng: Nhập mã sản phẩm, hoặc mã kệ.
  - Ajax gọi API: Truy vấn `SELECT i.*, p.product_id, p.product_name, s.shelf_id FROM inventory i JOIN products p ON... JOIN shelves s ON...`.
  - Trả về bảng danh sách, hiển thị rõ số lượng, đơn vị, vị trí cụ thể.

### 9. In phiếu picking (print.php)
* **Mục tiêu:** In ra phiếu có đủ thông tin cần thiết để chỉ thị cho nhân viên picking hàng hóa từ kệ theo Invoice, số lượng sẽ được gộp theo mã hàng. Phiếu này có bao gồm QR code để sử dụng cho bước Picking.
* **Luồng nghiệp vụ:**
  - Bước 1: Cho phép nhập toàn bộ thông tin của packing list từ file Excel vào export_temp.sql. Ghi nhận `INSERT INTO export_temp`.
  - Bước 2: Tạo bộ lọc theo ngày (mặc định là today). Danh sách hiển thị toàn bộ các Invoice `command` cần picking trong ngày, đếm số kiện `case_no`, ngày xuất `created_at`. Đương nhiên vẫn sử dụng thanh tìm kiếm nhanh theo Invoice mà không phụ thuộc vào bộ lọc ngày.
  - Bước 3: Chọn Invoice cần in phiếu picking. Hệ thống sẽ tổng hợp số lượng theo từng mã hàng, hiển thị danh sách các mã hàng cần picking, số lượng gộp cần picking. Xuất ra chế độ Xem trước phiếu picking, mỗi phiếu phải bao gồm thông tin: mã Invoice `command`, ngày xuất `created_at`, loại hình vận chuyển `transport_type`, mã khách hàng `for_product`, mã hàng `product_id` cần picking (mỗi mã 1 phiếu), số lượng gộp cần picking dùng để in trực tiếp, ngày/giờ in ấn. QR code tạo từ chuỗi [product_id]$[quantity], lưu ý quantity là số lượng gộp theo mã hàng cho từng invoice.
  - Bước 4: Nhấn nút In phiếu picking. Ghi nhận `INSERT INTO export_log (status='print')` theo từng Invoice.

### 10. Picking (picking.php)
* **Mục tiêu:** Soạn hàng theo chỉ thị (Invoice), đảm bảo đúng hàng, đúng chỗ, đúng số lượng.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR Chỉ thị/Invoice (`command`) dạng chuỗi [product_id]$[quantity]. Hệ thống load danh sách các vị trí đang có hàng tương ứng từ `invetory` sắp xếp theo thứ tự vị trí có số lượng ít nhất lên đầu danh sách. Danh sách đề xuất chỉ cần có tổng tồn lớn hơn số lượng cần picking là được. Tuy nhiên dể phòng tránh vị trí đó tồn kho ảo thì đề xuất thêm 1-2 vị trí dự phòng.
  - Bước 2: Nhân viên chọn vị trí (Ghim vị trí) muốn picking trong danh sách đề xuất.-> Quét mã kệ (vị trí) cần lấy hàng. Không khớp thì báo lỗi bằng poup màu ĐỎ
  - Bước 3: Quét mã hàng trên thùng hàng dạng chuỗi [TEXT]$[product_id]$[TEXT]$[quantity]$[TEXT]. Không khớp mã hàng cần picking `product_id` thì báo lỗi bằng poup màu ĐỎ.
  - Bước 3: Nếu khớp mã thì tự động lấy số lượng từ chuỗi `quantity` và trừ tồn hệ thống, ghi log OUT vào `transaction`. Cần ràng buộc nếu số lượng trên thùng vẫn bé hơn số lượng còn lại cần picking thì tự động ghi nhận số lượng, NHƯNG nếu số lượng trên thùng lớn hơn số lượng còn lại cần picking thì chỉ để xuất số lượng còn lại cần picking vào ô nhập số lượng và dừng ở đây để nhân viên picking xác nhận thử công. Nếu số lượng tại vị trí được ghim đã trừ tồn hết nhưng chưa đủ số lượng cần picking thì thông báo Popup màu VÀNG là "Vị trí này đã hết hàng, vui lòng chọn vị trí tiếp theo" rồi sau đó quay lại bước 2 với danh sách mã kệ đề xuất đã được reload lại, số lượng cần picking là số lượng còn lại chưa picking. Nếu không còn vị trí nào trên danh sách đề xuất thì xuất hiện cảnh báo ĐỎ là "Không còn vị trí nào có hàng, vui lòng liên hệ Leader để kiểm tra tồn kho".
  - Bước 4: Bất cứ khi nào nhân viên nhấn HOÀN THÀNH thì ghi nhận số lượng đã picking vào `export_log` với `status='picking'`.

### 11. In tem packing (print_case.php)
* **Mục tiêu:** In tem packing cho từng kiện `case_no` theo Invoice, dùng để dán lên kiện hàng bao gồm các thông tin: mã Invoice `command`, mã kiện `case_no`, mã khách hàng `for_product`, loại hình vận chuyển `transport_type`, số items `product_id` có trong kiện.
* **Luồng nghiệp vụ:** Layout giống hệt trang chức năng in phiếu picking, nhưng thay vì thống kê theo mã hàng thì thống kê theo mã kiện `case_no`. Mỗi kiện sẽ có 1 tem riêng, tem này được tạo ra từ dữ liệu packing list trong bảng `export_temp` theo từng Invoice.
- Bước 1. Chọn Invoice cần in tem packing. Hệ thống sẽ tổng hợp số lượng theo từng kiện `case_no`, hiển thị danh sách các kiện, số items có trong kiện. Xuất ra chế độ Xem trước tem packing, mỗi tem phải bao gồm thông tin: mã Invoice `command`, ngày xuất `created_at`, loại hình vận chuyển `transport_type`, mã khách hàng `for_product`, mã kiện `case_no`, số items có trong kiện dùng để in trực tiếp, ngày/giờ in ấn. QR code tạo từ chuỗi [command]$[case_no]$[for_product]$[count distinct product_id]$[transprot_type]$[created_at].
- Bước 2. Nhấn nút In tem packing. Ghi nhận `INSERT INTO export_log (status='print_case')` theo từng Invoice.

### 12. Packing (packing.php)
* **Mục tiêu:** Chia số lượng đã picking thành từng kiện `case_no` riêng biệt theo Invoice, dữ liệu packing list lấy từ bảng `export_temp`.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR trên phiếu Packing đã in ở bước trước dạng chuỗi [command]$[case_no]$[for_product]$[count distinct product_id]$[transprot_type]$[created_at].
  - Bước 2: Bắt đầu đưa từng thùng hàng vào kiện. -> Quét mã Thùng hàng dạng chuỗi [TEXT]$[product_id]$[TEXT]$[quantity]$[TEXT], kiểm tra với dữ liệu của mã hàng này trong bảng `export_temp` theo Invoice `command` và `case_no`. Nếu không khớp Cả về mã hàng và số lượng thì báo lỗi bằng poup màu ĐỎ. Nếu đúng thì ghi nhận lịch sử vào bảng `export_log` với `status='packing'`.
  - Bước 3: Khi đã quét hết tất cả các thùng hàng đầu vào, dù đủ hay chưa đủ thì cũng có thể nhấn HOÀN THÀNH để kết thúc Packing. Tuyệt đối không ghi nhận trường hợp thừa số lượng cho mỗi `case_no`. Nếu còn thiếu số lượng thì báo Popup màu VÀNG là "Packing chưa đủ số lượng, vui lòng kiểm tra lại". Nếu đủ số lượng thì báo Popup màu XANH là "Packing hoàn tất, vui lòng chuyển sang bước Pickup".
  
### 13. Pickup (pickup.php)
* **Mục tiêu:** Quét mã vạch khi bốc hàng lên xe, bàn giao cho đơn vị vận chuyển.
* **Luồng nghiệp vụ:**
  - Bước 1: Quét QR Invoice -> Quét mã Thùng (`case_no`).
  - Bước 2: Kiểm tra kiện hàng này đã qua bước packing chưa (`export_log`). Nếu chưa, báo lỗi kiện chưa hợp lệ.
  - Bước 3: Xác nhận bốc hàng. Ghi nhận `INSERT INTO export_log (status='pickup')`. Cập nhật `bucket_qty` (nếu cần).

