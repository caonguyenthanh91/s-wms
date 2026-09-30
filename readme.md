# Smart WMS (S-WMS) — Tài liệu luồng xử lý lõi

Dự án quản lý kho thông minh Smart WMS nhằm tối ưu quy trình nhập/xuất kho, quản lý tồn kho và phân quyền người dùng; kiểm soát chặt các luồng nghiệp vụ bằng mã QR và các bước xác nhận để chống nhầm lẫn.

Tài liệu này mô tả **đúng hiện trạng mã nguồn** (cấu trúc, CSDL, API, luồng nghiệp vụ, định dạng QR, công thức tiến độ) để có thể chuyển phần lõi sang giao diện mới. Phần style giao diện (màu sắc, bố cục, CSS) **không** nằm trong phạm vi tài liệu; chỉ giữ lại các quy ước UI có ảnh hưởng tới nghiệp vụ (màu popup, focus, phím tắt, hành vi máy quét).

> Nguyên tắc khi làm giao diện mới: **giữ nguyên `api.php`, `config/`, CSDL**, chỉ thay phần HTML/CSS. Mọi thao tác ghi dữ liệu đều đi qua `api.php`, nên giao diện mới chỉ cần gọi đúng action, đúng tham số, xử lý đúng response như mô tả bên dưới.

---

## 1. Yêu cầu dự án

- PHP, CSS, JS dễ bảo trì; chạy **offline trong mạng nội bộ** (tất cả thư viện JS/CSS nằm trong `assets/`).
- Giao diện tối ưu cho màn hình lớn (PC/Laptop/Tablet) và màn hình nhỏ (PDA chuyên dụng/Smartphone).
- Ưu tiên ô nhập liệu, nút nhấn, con số; tránh chú thích dài dòng.

## 2. Công nghệ & thư viện

| Thành phần | Chi tiết |
|---|---|
| Backend | PHP 7.4+/8.x, PDO MySQL (`ERRMODE_EXCEPTION`), session PHP (thời hạn 28800s = 8 giờ) |
| CSDL | MySQL/MariaDB, InnoDB, `utf8mb4_unicode_ci`, database mặc định `cnt_smart_wms` |
| Múi giờ | `Asia/Ho_Chi_Minh` (đặt trong `config/db.php`) |
| Frontend lõi | jQuery (`$.getJSON`, `$.post`, `$.ajax`) |
| Quét QR bằng camera | `html5-qrcode` (`Html5QrcodeScanner`) |
| Sinh QR để in | `qrcodejs` (`new QRCode(...)`, mức sửa lỗi H) |
| Xuất Excel | PhpSpreadsheet (`assets/vendor/phpoffice`, nạp qua `assets/vendor/autoload.php`) |
| Đọc Excel khi import | Tự parse `.xlsx` bằng `ZipArchive` + SimpleXML (chỉ đọc `sheet1`), hoặc `.csv` (tự nhận dấu phân cách `,` `;` `TAB`) |
| Khác (thuộc giao diện) | Tailwind, Bootstrap, FontAwesome, Chart.js — có thể thay tùy giao diện mới |

`config/setup.php` tải các thư viện frontend về `assets/` để chạy offline (chỉ cần chạy 1 lần khi có Internet).

## 3. Cấu trúc thư mục

```
s-wms/
├── index.php              # Khung ứng dụng: session, phân quyền menu, router ?page=, modal quét QR, đăng nhập
├── api.php                # TOÀN BỘ nghiệp vụ phía server (JSON), định tuyến bằng ?action=
├── monitor.php            # Bảng giám sát công khai (không cần đăng nhập), trang độc lập
├── config/
│   ├── db.php             # Kết nối PDO ($pdo), timezone
│   ├── setup.php          # Tải thư viện offline
│   └── scan_qr.php        # Trang thử quét QR độc lập (demo, không dùng trong luồng chính)
├── pages/                 # Mỗi file = 1 màn hình, được index.php include
│   ├── import.php         # Nhận Pallet (kho tạm)
│   ├── transfer.php       # Pallet >>> Kệ
│   ├── inbound.php        # Nhập kho
│   ├── outbound.php       # Xuất kho
│   ├── change.php         # Đổi kệ
│   ├── inventory.php      # Tra tồn
│   ├── picking.php        # Picking theo Invoice
│   ├── packing.php        # Packing theo kiện
│   ├── pickup.php         # Pickup (bốc hàng)
│   ├── check_box.php      # Đối chiếu 2 tem thùng
│   ├── check_box_v1.php   # Bản cũ của check_box (không có trong menu)
│   ├── print.php          # In phiếu picking
│   ├── print_case.php     # In tem packing (tem kiện)
│   ├── print_pallet.php   # In tem pallet
│   ├── dashboard.php      # Tiến độ Invoice theo ngày
│   ├── layout.php         # Sơ đồ kệ
│   ├── shelves.php        # Đăng ký kệ
│   ├── products.php       # Đăng ký sản phẩm
│   ├── data_import.php    # Import packing list (export_temp)
│   ├── data_export.php    # Xuất tồn kho ra .xlsx
│   ├── wms_import.php     # Import tồn WMS theo mã thùng (wms_inventory)
│   ├── system_check.php   # Kiểm tra quyền thư mục, cấu hình PHP
│   └── admin.php          # Quản trị người dùng
├── assets/
│   ├── database/database.sql   # Schema chuẩn + user mặc định
│   ├── database/               # Nơi lưu file xuất inventory_*.xlsx và log export_debug_*.log
│   ├── packing_list.xlsx       # File mẫu packing list
│   ├── js/ css/ webfonts/ img/ vendor/
└── check_log.sql          # Schema bảng check_log (API cũng tự tạo)
```

## 4. Cài đặt & khởi tạo

1. Tạo database `cnt_smart_wms`, import `assets/database/database.sql` (tạo 8 bảng cơ bản + 4 user mẫu).
2. Sửa thông số kết nối trong `config/db.php` nếu cần.
3. Các bảng phụ được `api.php` **tự tạo khi cần** (`CREATE TABLE IF NOT EXISTS`): `export_log`, `check_log`, `wms_inventory`. Hàm `ensure_export_temp_schema()` tự bỏ unique index sai trên `export_temp.product_id` và thêm index còn thiếu.
4. Thư mục `assets/database/` phải ghi được (xuất Excel + log).
5. PHP cần bật extension `zip` (đọc .xlsx) và `pdo_mysql`.

User mặc định (mật khẩu lưu MD5):

| username | password | role |
|---|---|---|
| admin | admin123 | Admin |
| manager | manager123 | Manager |
| leader | leader123 | Leader |
| staff | staff123 | Staff |

---

## 5. Khung ứng dụng (`index.php`)

### 5.1 Router
- URL: `index.php?page=<tên>`; mặc định `inventory`.
- Chỉ include `pages/<page>.php` khi `<page>` nằm trong danh sách được phép theo role; ngược lại include `pages/inventory.php`.
- Trang được include **bên trong** `index.php` nên dùng chung `$pdo`, `$_SESSION`, `$role`, jQuery và các hàm toàn cục (modal quét QR, đăng nhập).

### 5.2 Đăng nhập / phiên
- Trạng thái đăng nhập được nạp bằng `GET api.php?action=get_current_user` → `{ user: {id, username, full_name, role} | null }`.
- Đăng nhập: `POST api.php?action=login` (`username`, `password`) → thành công thì `location.reload()`.
- Đăng xuất: `POST api.php?action=logout` → reload.
- Không đăng nhập = **Guest** (role `Guest` không có trong bảng `log_users`, chỉ là giá trị suy ra).

### 5.3 Modal quét QR bằng camera (dùng chung)
Giao diện mới **phải cung cấp lại** các hàm toàn cục sau vì nhiều trang gọi tới:

| Hàm | Vai trò |
|---|---|
| `openQRScannerModal(targetId, label)` | Mở camera, gán ô đích nhận kết quả |
| `closeQRScannerModal()` | Đóng modal, dừng camera |
| `resetQRScannerModalState()` | Xóa kết quả đang hiển thị để quét lại (không đóng camera) |
| `applyQRCodeToTarget()` | Gán giá trị quét vào ô `targetId`, bắn sự kiện `input` + `change`, focus ô đó |
| `playQRScanBeep()` | Bíp 900Hz/120ms khi quét thành công |
| `window.handleQRScannerScan(targetId, value)` | **Hook do từng trang định nghĩa**. Khi camera quét được, index gọi hook này trước; nếu trả `true` thì trang đã tự xử lý (không cần bấm "Áp dụng") |

Cấu hình scanner: `fps: 10`, `qrbox: 240x240`, `rememberLastUsedCamera: true`, chỉ dùng camera.

### 5.4 Chế độ PDA
Các trang `import, inbound, outbound, transfer, change, inventory, packing, pickup, check_box` được tối ưu cho màn hình ≤ 900px (thu gọn header/sidebar). Đây là hành vi giao diện — giao diện mới tự quyết định cách hiển thị, nhưng nên giữ tiêu chí "PDA dùng được 1 tay, ô quét luôn có focus".

---

## 6. Phân quyền

### 6.1 Thứ bậc role
`Guest (1) < Staff (2) < Leader (3) < Manager (4) < Admin (5)`. Role cao hơn có mọi quyền của role thấp hơn **ở tầng menu**.

### 6.2 Quyền truy cập màn hình (menu trong `index.php`)

| Trang | Nhãn | Role tối thiểu |
|---|---|---|
| inventory | Tra tồn | Guest |
| dashboard | Dashboard | Guest |
| import | Nhận hàng (Pallet) | Staff |
| picking | Picking | Staff |
| packing | Packing | Staff |
| pickup | Pickup | Staff |
| check_box | Check Box | Staff |
| transfer | Pallet >>> Kệ | Leader |
| inbound | Nhập Kho | Leader |
| outbound | Xuất Kho | Leader |
| change | Đổi kệ | Leader |
| print | In phiếu [Picking] | Leader |
| print_case | In tem [Packing] | Leader |
| print_pallet | In tem [Pallet] | Leader |
| layout | Layout | Manager |
| shelves | Kệ hàng | Manager |
| products | Sản phẩm | Manager |
| data_export | Data Export | Manager |
| wms_import | WMS Import | Manager |
| admin | Quản trị | Manager |
| data_import | Data Import | Admin |
| system_check | Kiểm tra hệ thống (không hiện trong menu) | Admin |

Một số trang tự kiểm tra role thêm lần nữa ở đầu file: `print*.php` (Staff+), `layout.php` (Leader+), `data_export.php` (Manager+), `data_import.php` (Admin), `system_check.php` (Admin).

### 6.3 Quyền ở tầng API
`api.php` dùng `require_role([...])` — **danh sách liệt kê, không theo thứ bậc**. Nếu không đạt trả về `{ success: false, message: 'Permission denied' }`. Quyền cụ thể từng action xem mục 9. Lưu ý: quyền API đôi khi rộng hơn quyền menu (ví dụ `inbound_submit` cho phép Staff dù menu Nhập kho yêu cầu Leader). Giao diện mới **phải tự ẩn chức năng theo bảng 6.2**, không dựa vào API để chặn.

---

## 7. Cơ sở dữ liệu

### 7.1 Danh sách bảng

| Bảng | Vai trò | Khóa nghiệp vụ |
|---|---|---|
| `log_users` | Người dùng, role, trạng thái khóa | `username` (unique) |
| `products` | Master sản phẩm | `product_id` (unique, chữ HOA) |
| `shelves` | Master kệ / vị trí | `shelf_id` (unique, chữ HOA) |
| `inventory` | Tồn kho chính theo cặp (kệ, sản phẩm) | unique (`shelf_id`, `product_id`) — **đều là khóa chính số `id`** của `shelves`/`products` |
| `transactions` | Lịch sử tăng/giảm tồn | `product_id`, `shelf_id` là **id số** |
| `import_temp` | Kho tạm: hàng trên pallet đã nhận nhưng chưa lên kệ | `pallet_id` + `part_no` (chuỗi) |
| `export_temp` | Packing list (chỉ thị xuất) import từ Excel | `command` + `case_no` + `product_id` (chuỗi) |
| `export_log` | Nhật ký picking/packing/pickup theo Invoice | `command`, `case_no`, `product_id` (chuỗi) |
| `wms_inventory` | Tồn WMS theo mã thùng (dùng cho Check Box) — tự tạo | unique (`box_code`, `product_id`) |
| `check_log` | Nhật ký đối chiếu 2 tem — tự tạo | — |

> **Quan trọng:** `inventory` và `transactions` tham chiếu bằng **id số**; còn `import_temp`, `export_temp`, `export_log`, `wms_inventory` lưu **mã chuỗi**. API luôn nhận/trả mã chuỗi và tự tra id.

### 7.2 Cột và ý nghĩa trạng thái

**`shelves`**: `shelf_id`, `shelf_name`, `level0_val` (kho, mặc định `B032`), `level1_val` (khu vực, 1 chữ cái), `level2_val` (dãy, 2 số), `level3_val` (khung, 2 số), `level4_val` (ghi chú/tầng), `status` (`Active` | `Deactive`), `capacity` (mặc định 100), `current_usage` (tổng số lượng đang chứa). Kệ `Deactive` bị loại khỏi mọi truy vấn nghiệp vụ.

**`import_temp.status`** (kho tạm):
- `NULL` hoặc `''` → pallet **đang chờ** lên kệ (hiển thị "KHO TẠM").
- `<shelf_id>` → pallet **đã chuyển** lên kệ đó (được ghi bởi `mark_pallet_transferred`).

**`transactions.type`**: `IN`, `OUT`, `ADJ_IN`, `ADJ_OUT`. (Đổi kệ ghi `OUT` ở kệ cũ + `IN` ở kệ mới.)

**`export_temp`**: `command` (Invoice, 6 ký tự), `case_no` (kiện, 3 ký tự, mặc định `001`), `transport_type` (mặc định `SEA`, có thể `AIR`), `for_product` (mã khách hàng), `product_id`, `total_qty` (số lượng cần), `bucket_qty`, `created_at` (**ngày xuất** — mọi bộ lọc theo ngày đều dùng `DATE(created_at)`).

**`export_log.status`**: ENUM `picking` | `packing` | `pickup`.
- `picking`: mỗi lần trừ tồn trong màn Picking, `quantity` = số lượng pick.
- `packing`: mỗi lần quét thùng trong màn Packing, `quantity` = số lượng trên thùng.
- `pickup`: mỗi lần pickup 1 kiện, chèn **1 dòng cho mỗi product_id** trong kiện với `quantity = 1`.

**`check_log.result_code`**: `ok` | `qty_mismatch` | `product_mismatch`.

### 7.3 Quy ước chuẩn hóa dữ liệu
- Mọi mã (`product_id`, `shelf_id`, `pallet_id`, `command`, `case_no`) được `trim` + **chuyển chữ HOA** ở cả client và server.
- Mã kệ có tiền tố kho `B032-` được **tự cắt bỏ** ở các action: `check_shelf`, `get_products_on_shelf`, `check_shelf_existence_and_content`, `get_inventory_by_shelf`, `get_inventory_current_by_shelf`, `inbound_submit`, `outbound_basic_submit`, `outbound_submit`. (Không cắt ở `transfer_products`, `inventory_adjustment`, và so khớp QR kệ trong Picking.)
- Mã kệ khi đăng ký = `L1-L2-L3` hoặc `L1-L2-L3-L4` (ví dụ `A-01-02`). File xuất Excel ghi mã vị trí đầy đủ dạng `B032-<shelf_id>`.
- Chuẩn hóa ký tự `$` trong chuỗi QR trước khi tách: `＄` (full-width U+FF04) → `$`, `\$` → `$`, `&#36;` → `$`.

---

## 8. Định dạng QR / mã vạch

| Tem | Nội dung | Nơi tạo | Nơi quét | Cách parse |
|---|---|---|---|---|
| Tem Pallet | `<pallet_id>` (chuỗi thuần) | `print_pallet.php` | `import.php`, `transfer.php` | Lấy nguyên chuỗi, HOA |
| Tem thùng hàng (nhà cung cấp) | `SMC001$<product_id>$<kani_code>$<quantity>$<lot_no>$...` | Có sẵn trên thùng | import, inbound, outbound, picking, packing, inventory | `product_id` = phần tử **thứ 2**; `quantity` = **token toàn số cuối cùng** tính từ cuối về (import/inbound/outbound: từ vị trí ≥2; picking/packing: từ vị trí ≥3, bắt buộc ≥4 phần) |
| Phiếu picking | `<command>$<product_id>$<quantity>` (cũ: `<product_id>$<quantity>`) | `print.php` | `picking.php` bước 1 | Tách `$` theo vị trí |
| Tem packing (tem kiện) | `<command>$<case_no>$<for_product>$<số items>$<transport_type>$<created_at>` | `print_case.php` | `packing.php` bước 1, `pickup.php` bước 1 | Packing: chỉ cần `command` (6 ký tự A-Z0-9) + `case_no` (3 ký tự); Pickup: bắt buộc ≥6 phần |
| Shipping mark | `<command 6 ký tự><case_no 3 ký tự>` (ví dụ `ABC123001`) | Phòng XNK in | `pickup.php` bước 2, cũng được chấp nhận ở packing bước 1 | Regex `^([A-Z0-9]{6})([A-Z0-9]{3})$` |
| Mã kệ | `<shelf_id>` hoặc `B032-<shelf_id>` | Dán tại kệ | inbound, outbound, transfer, change, picking | Picking so khớp **chính xác** với `shelf_id` trong DB (không cắt `B032-`) |
| Check Box – Tem 1 | `B$$<box_code>` | Tem WMS | `check_box.php` | Có chứa `$$` → Tem 1, tra `wms_inventory` |
| Check Box – Tem 2 | `SMC001$<product_id>$...$<quantity>$...` | Tem nhà cung cấp | `check_box.php` | Phần tử 1 phải là `SMC001`; product = phần tử 2, qty = phần tử 4 (cho phép số thập phân) |

**Hành vi máy quét cầm tay (keyboard wedge)** — giao diện mới cần giữ nguyên để không lỗi dữ liệu:
- Lắng nghe sự kiện `input` trên ô quét; khi chuỗi có `$`, **debounce ~100–120ms** rồi mới parse (đợi máy quét gõ xong).
- Coi là "đã quét xong" khi chuỗi **kết thúc bằng `$`** hoặc có **≥ 7 phần** (picking box: ≥ 4 phần).
- Ghi nhớ chuỗi vừa xử lý (`lastHandledQRRaw`) để không xử lý 2 lần cùng một lần quét; reset sau khi thêm dòng để có thể quét lại cùng thùng.
- Phím `Enter` trong ô quét = xác nhận thủ công.

---

## 9. API (`api.php`)

Quy ước chung:
- URL: `api.php?action=<tên>`. Tham số GET qua query string, POST qua `application/x-www-form-urlencoded` (hoặc `multipart/form-data` khi upload file).
- Response luôn là JSON. Đa số dạng `{ success: bool, message?: string, ... }`; một số action trả **mảng trần** (ghi rõ bên dưới).
- `created_by` luôn lấy `username` trong session (không có thì `system`).
- Action không tồn tại → `{ error: 'Invalid action' }`.

### 9.1 Xác thực & người dùng

| Action | Method | Tham số | Role | Response |
|---|---|---|---|---|
| `login` | POST | `username`, `password` | — | `{success, user}`; lỗi: thiếu thông tin / không tồn tại / sai mật khẩu / bị khóa. Mật khẩu chấp nhận MD5 hoặc `password_hash`. Cập nhật `last_login` |
| `logout` | POST | — | — | `{success:true}` |
| `get_current_user` | GET | — | — | `{user: object\|null}` |
| `get_users` | GET | — | Admin, Manager | **mảng** user (`id, username, full_name, role, status, last_login, created_at`) |
| `add_user` | POST | `username`, `password`, `full_name`, `role` | Admin, Manager | `{success}`; lưu MD5; trùng username → lỗi |
| `update_user` | POST | `id`, `role`, `status` (1/0) | Admin, Manager | `{success}` |

### 9.2 Master

| Action | Method | Tham số | Role | Ghi chú |
|---|---|---|---|---|
| `check_product` | GET | `product_id` | — | `{success, data:{product_id, product_name, unit}}` |
| `get_products` | GET | `q` | — | **mảng**, tối đa 50 (chưa dùng) |
| `add_product` | POST | `product_id`, `product_name?`, `unit?` | Admin, Leader, Manager | Mặc định tên `Sản phẩm <SKU>`, đơn vị `Cái`; trùng → lỗi |
| `check_shelf` | POST | `shelf_id` | — | `{success, data: shelf}`; chỉ kệ không Deactive |
| `get_shelves` | GET | `q?`, `all=1?` | — | **mảng** kệ + `sku_count` (số SKU có tồn > 0); không có `all=1` thì giới hạn 50 |
| `add_shelf` | POST | `shelf_id`, `shelf_name`, `level0_val..level4_val`, `capacity` | Admin, Leader, Manager | Insert thẳng; trùng → lỗi DB |
| `update_shelf` | POST | `id`, `shelf_id`, `shelf_name`, `status` | Admin, Leader, Manager | Đổi mã/khóa kệ (chưa có UI) |
| `get_dashboard_stats` | GET | — | — | Tổng kệ, kệ có hàng, kệ trống (chưa dùng) |

### 9.3 Tồn kho & kho tạm

| Action | Method | Tham số | Role | Ghi chú |
|---|---|---|---|---|
| `search_sku` | GET | `product_id` | — | **mảng**: dòng kho chính (`source='INVENTORY'`, `shelf_id`, `quantity`, `pallet_id` = các pallet đã chuyển lên kệ đó) + dòng kho tạm (`source='IMPORT_TEMP'`, `shelf_id='TEMP-<pallet>'`) |
| `get_inventory_by_shelf` | GET | `shelf_id` | — | **mảng**: tồn kho chính trên kệ + hàng kho tạm nếu `shelf_id` là mã pallet hoặc `TEMP-<pallet>` |
| `get_inventory_current_by_shelf` | GET | `shelf_id` | — | **mảng**: chỉ tồn kho chính, gộp theo sản phẩm (dùng để kiểm tra realtime khi xuất) |
| `get_products_on_shelf` | GET | `shelf_id` | Staff+ | `{success, products:[{shelf_pk, product_id, product_name, quantity}]}` |
| `check_shelf_existence_and_content` | GET | `shelf_id` | Staff+ | `{success, exists, has_products, shelf_name}` |
| `get_import_temp_by_pallet` | GET | `pallet_id` | Staff+ | **mảng** `{product_id, product_name, quantity}` — chỉ hàng chưa chuyển |
| `get_import_temp_status_by_pallet` | GET | `pallet_id` | — | **mảng** kèm `status` (NULL hoặc mã kệ đã chuyển) |
| `get_pending_pallets` | GET | — | Staff+ | **mảng** pallet đang chờ: `pallet_id, created_at, created_by, sku_count` |
| `get_pallet_transfer_summary` | GET | `keyword?` | Staff+ | `{total_pallets, transferred_pallets, pending_pallets}` |
| `check_pallet_unique` | POST | `pallet_id` | Staff+ | `success=false` nếu pallet đã từng có trong `import_temp` |
| `import_temp_submit` | POST | `pallet_id`, `product_id`, `quantity` | Staff+ | Kiểm tra sản phẩm tồn tại → insert 1 dòng `import_temp` (status NULL) |
| `mark_pallet_transferred` | POST | `pallet_id`, `shelf_id` | Staff+ | `UPDATE import_temp SET status=<shelf_id>` cho các dòng đang chờ |
| `get_aux_stock_by_product` | GET | `product_id` | Staff+ | Tồn kho tạm theo pallet: `{total_aux, pallets:[{pallet_id, qty}]}` |

### 9.4 Giao dịch tồn kho (đều chạy trong transaction DB)

| Action | Tham số (POST) | Role | Tác động DB |
|---|---|---|---|
| `inbound_submit` | `shelf_id`, `product_id`, `quantity` | Staff+ | `inventory` + qty (update hoặc insert), `shelves.current_usage` + qty, `transactions` IN |
| `outbound_basic_submit` | `shelf_id`, `product_id`, `quantity` | Staff+ | Khóa các dòng tồn (`FOR UPDATE`), kiểm tra đủ tồn, trừ dần, `current_usage` − qty, `transactions` OUT. Lỗi trả kèm `debug` |
| `outbound_submit` | `shelf_id`, `product_id`, `quantity`, `command?`, `case_no?` (mặc định `001`), `is_picking` (0/1) | Staff+ | Như trên (1 dòng tồn, `FOR UPDATE`); nếu `is_picking=1` và có `command` thì ghi thêm `export_log` status `picking` |
| `transfer_products` | `current_shelf_id`, `new_shelf_id`, `products_to_transfer` (JSON `[{product_id, quantity}]`) | Admin, Leader, Manager | Kiểm tra 2 kệ tồn tại & khác nhau, đủ tồn → trừ kệ nguồn (OUT) + cộng kệ đích (IN), cập nhật `current_usage` 2 kệ. Tất cả hoặc không gì cả |
| `inventory_adjustment` | `shelf_id`, `product_id`, `quantity` (số tồn mới) | Admin, Manager | Đặt tồn = giá trị mới, `current_usage` + chênh lệch, ghi `ADJ_IN`/`ADJ_OUT` (chưa có UI) |

> `capacity` của kệ **không** được kiểm tra ở bất kỳ action nào; chỉ `current_usage` được cộng/trừ.

### 9.5 Xuất hàng (export_temp / export_log)

| Action | Method | Tham số | Role | Ghi chú |
|---|---|---|---|---|
| `get_shelf_inventory` | POST | `product_id`, `command?` | Staff+ | `{shelves:[{shelf_id, shelf_name, qty}] (tồn tăng dần), total_stock, required_qty, picked_qty, remaining_qty}`. `required` = Σ`total_qty` của command+product (mọi case); `picked` = Σ log `picking` |
| `get_invoices_by_date` | GET | `date` (YYYY-MM-DD, mặc định hôm nay) | Staff+ | `{invoices:[{command, created_at, item_count, case_count}]}` |
| `get_invoice_items_by_date` | GET | `command`, `date` | Staff+ | Gộp theo product: `{product_id, product_name, unit, for_product, case_no (chuỗi "001,002"), total_qty, created_at}` |
| `get_cases_by_invoice` | GET | `command` | Staff+ | `{cases:[{case_no, for_product, transport_type, created_at, item_count}]}` (`item_count` = số product_id khác nhau) |
| `get_export_invoice_detail` | GET | `command` (6 ký tự), `case_no` (3 ký tự) | Staff+ | Chi tiết 1 kiện: `items[{product_id, required_qty, picked_qty, packed_qty, pickup_qty, remain_qty}]`, `required_total`, `status_totals{picking,packing,pickup}`, `is_picked_done`, `is_packed_done`, `is_pickup_done`, `picking_done_products`, `case_total_products`, `packing_done_products`, `total_products`, `pickup_case_total` |
| `export_log_scan` | POST | `command`, `case_no`, `product_id`, `quantity`, `status` (mặc định `packing`) | Staff+ | Từ chối nếu mã hàng không thuộc kiện hoặc `đã ghi + quantity > yêu cầu`. Ghi 1 dòng log, trả `status_totals`, `is_*_done` |
| `pickup_submit` | POST | `command`, `case_no` | Staff+ | Từ chối nếu kiện không thuộc invoice hoặc **chưa packing đủ** (Σpacking < Σtotal_qty). Ghi log `pickup` cho từng product trong kiện |
| `pickup_scan_case` | POST | `command`, `case_no` | Staff+ | Biến thể của pickup, trả thêm trạng thái mọi kiện (chưa dùng) |
| `get_pickup_cases_by_command` | GET | `command` | Staff+ | Trạng thái pickup từng kiện (chưa dùng) |
| `get_incomplete_cases_by_command` | GET | `command` | Staff+ | `{incomplete_packing_cases:[{case_no, incomplete_items_count, items[]}], incomplete_pickup_cases:[case_no]}` |
| `get_monitor_board` | GET | `date` | **Không cần đăng nhập** | Dữ liệu Dashboard/Monitor (công thức mục 11) |
| `get_command_flight_board` | GET | `date` | Leader+ | Phiên bản cũ tính theo số lượng (chưa dùng) |
| `insert_export_log_print_case` | POST | `command` | Staff+ | Ghi log in tem packing (xem mục 13) |
| `get_export_items`, `get_export_search_suggestions`, `update_export_item`, `get_export_cases_for_print` | | | Staff+ | Còn trong API, không trang nào gọi |

### 9.6 Import / quản trị dữ liệu

| Action | Method | Tham số | Role | Ghi chú |
|---|---|---|---|---|
| `import_export_temp` | POST multipart | `excel_file`, `clear_existing` (`1` mặc định) | **Admin** | Import packing list vào `export_temp` (mục 12.1). `clear_existing=1` → **xóa toàn bộ** `export_temp` trước khi nạp |
| `get_export_temp_command_case_rows` | GET | `date?` | Admin | Danh sách cặp command/case_no kèm số dòng, số SP, tổng SL |
| `update_export_temp_case_date` | POST | `command`, `case_no`, `export_date` | Admin | Đổi ngày xuất (giữ nguyên giờ) |
| `delete_export_temp_case_rows` | POST | `command`, `case_no` | Admin | Xóa cặp command/case_no |
| `import_wms_inventory` | POST multipart | `excel_file` | Manager, Admin | **Thay toàn bộ** `wms_inventory` (mục 12.2) |
| `get_check_box_tem1_inventory` | GET | `box_code` | Staff+ | `{product_id, quantity}`; lỗi nếu không có hoặc thùng có >1 mã hàng |
| `check_box_log` | POST | `tem1_raw`, `tem2_raw`, `tem1_product`, `tem1_qty`, `tem2_product`, `tem2_qty`, `is_product_match`, `is_qty_match`, `result_code`, `result_message`, `type` | Staff+ | Ghi `check_log` |

---

## 10. Luồng nghiệp vụ theo màn hình

Quy ước popup (giữ nguyên ý nghĩa màu ở giao diện mới):
- **ĐỎ (error)**: sai mã, sai vị trí, không đủ tồn, lỗi hệ thống — dừng thao tác.
- **VÀNG (warning)**: cảnh báo, chưa đủ số lượng, vị trí hết hàng.
- **XANH (success)**: hoàn tất.
- Khi popup đang mở, `Enter` hoặc `Esc` đóng popup. Sau khi đóng: nếu là success thì reset màn hình về bước 1, nếu là lỗi thì xóa ô đang nhập và focus lại ô quét tương ứng.

### 10.1 Nhận Pallet — `import.php` (Staff+)
Mục tiêu: ghi nhận hàng trên pallet vào **kho tạm** `import_temp` (chưa tăng tồn chính).
1. Quét/nhập **mã pallet** (tem từ `print_pallet.php`) → sang bước 2, tải hàng đã nhận trước đó của pallet (`get_import_temp_by_pallet`). Cho phép nhận bổ sung vào pallet cũ.
2. Quét **tem thùng** → parse `product_id` + `quantity` → `check_product`. Không tồn tại → popup ĐỎ, xóa ô và quét lại.
3. Hai chế độ quét:
   - **Gián đoạn** (mặc định): điền SL vào ô số lượng, người dùng sửa nếu cần rồi `Enter` để thêm.
   - **Liên tục**: tự thêm dòng ngay khi QR hợp lệ.
4. Danh sách tạm hiển thị số lượt quét và tổng SL (tính cả dòng đang nhập dở); cho phép xóa dòng.
5. Nhấn Lưu → gọi `import_temp_submit` **tuần tự từng dòng** (khóa nút để chống bấm 2 lần). Lỗi ở dòng nào thì dừng và báo lỗi (các dòng trước đó đã được lưu). Thành công → popup XANH, tải lại danh sách pallet.
6. Đổi pallet khi danh sách còn dữ liệu → hỏi xác nhận.

### 10.2 Pallet >>> Kệ — `transfer.php` (Leader+)
1. Hiển thị pallet đang chờ (`get_pending_pallets`) + thống kê tổng/đã chuyển/chờ (`get_pallet_transfer_summary`); tìm kiếm lọc theo `pallet_id`; bấm mã pallet xem chi tiết.
2. Bấm **Transfer** → nhập/quét **mã kệ đích** → `check_shelf`. Sai → ĐỎ.
3. Nếu kệ đích đang có hàng (`get_inventory_by_shelf`) → hỏi xác nhận gộp.
4. Chuyển sang `index.php?page=inbound&shelf_id=<kệ>&pallet_id=<pallet>` — phần ghi dữ liệu thực hiện ở màn Nhập kho (10.3).

### 10.3 Nhập kho — `inbound.php` (Leader+)
1. Quét **mã kệ** → `check_shelf`; sai → ĐỎ. Đúng → hiển thị hàng hiện có trên kệ.
2. Quét tem thùng / nhập mã → `check_product` → thêm vào danh sách (2 chế độ quét như 10.1).
3. Xác nhận → `inbound_submit` **tuần tự từng dòng**; lỗi dừng tại dòng lỗi.
4. Nếu URL có `pallet_id` (đến từ Transfer): danh sách được nạp sẵn từ `import_temp`; sau khi nhập xong gọi `mark_pallet_transferred(pallet_id, shelf_id)` rồi quay về trang Transfer sau 0,5s.
5. URL có `shelf_id` → tự điền và kiểm tra kệ (dùng cho link nhanh từ Tra tồn/Layout).

### 10.4 Xuất kho — `outbound.php` (Leader+)
1. Quét **mã kệ** → `check_shelf` → hiển thị hàng trên kệ (`get_inventory_by_shelf`, gắn nhãn nguồn) và nạp bản đồ tồn thực (`get_inventory_current_by_shelf`, chỉ kho chính).
2. Quét tem thùng / nhập mã: mã phải **có trên kệ** (nếu không → ĐỎ). SL thêm vào không được vượt `tồn − đã thêm trong danh sách`.
3. Xác nhận → tải lại tồn realtime, gộp danh sách theo mã hàng, nếu thiếu thì báo chi tiết `mã: cần X, còn Y` và dừng; đủ thì gọi `outbound_basic_submit` cho từng mã.
4. Nút Xác nhận chỉ hiện khi danh sách có SL > 0.

### 10.5 Đổi kệ — `change.php` (Leader+)
1. Nhập **kệ nguồn** → `get_products_on_shelf` → bảng sản phẩm, mặc định chọn tất cả, SL = toàn bộ tồn (có thể giảm, giới hạn 1…tồn).
2. Nhập **kệ đích** → `check_shelf_existence_and_content`; không tồn tại → lỗi; có hàng → cảnh báo (vẫn cho chuyển).
3. Nút chuyển chỉ bật khi: kệ nguồn có hàng, kệ đích hợp lệ, 2 kệ khác nhau, có ít nhất 1 sản phẩm được chọn, SL hợp lệ.
4. Gọi `transfer_products` (1 transaction). Thành công → reset form.

### 10.6 Tra tồn — `inventory.php` (Guest+)
- **Theo mã hàng** (nhập mã hoặc quét tem thùng — tự lấy phần tử thứ 2): `search_sku`. Hiển thị kho chính (kèm các pallet gốc) và kho tạm (`TEMP-<pallet>`), tổng tồn, link nhanh Nhập/Xuất cho dòng kho chính.
- **Theo mã kệ**: `get_inventory_by_shelf`.
- **Theo mã pallet**: `get_import_temp_status_by_pallet` (trạng thái NULL = chờ, hoặc mã kệ đã chuyển).
- URL `?shelf_id=` hoặc `?pallet_id=` → tự tìm.

### 10.7 In phiếu picking — `print.php` (Leader+ ở menu)
1. Chọn ngày (Hôm qua / Hôm nay / chọn ngày) → `get_invoices_by_date`.
2. Chọn Invoice → `get_invoice_items_by_date` (gộp SL theo mã hàng của Invoice đó).
3. Mỗi mã hàng = **1 phiếu nhiệt 80mm**: thời gian in, "PHIẾU PICKING", số phiếu `n/tổng`, Invoice, khách hàng (`for_product`), ngày xuất, QR `command$product_id$total_qty`, SL cần pick + đơn vị, mã/tên hàng, danh sách `case_no`, dòng ký tên.
4. Tùy chọn in (lưu `localStorage`: `print_test_mode`, `print_split_mode`, `print_single_mode`):
   - **Test**: không in thật, chỉ báo số phiếu.
   - **Tách** (mặc định): in từng phiếu qua iframe ẩn, `@page { size: 80mm auto; margin: 0 }`, phiếu sau in khi `onafterprint` hoặc sau 2s.
   - **Gộp**: `window.print()` một lần.
5. Không ghi log vào CSDL.

### 10.8 Picking — `picking.php` (Staff+)
Mục tiêu: đúng hàng, đúng chỗ, đúng số lượng; trừ tồn + ghi log picking theo Invoice.

**Bước 1 – Quét phiếu picking** `command$product_id$qty`:
- Sai định dạng → ĐỎ.
- `check_product` → không có → báo lỗi.
- `get_shelf_inventory(product_id, command)`:
  - Nếu có `command` và `remaining_qty ≤ 0` (đã pick đủ) → popup XANH "ĐÃ HOÀN TẤT PICKING", dừng.
  - Nếu không có vị trí tồn kho chính → popup ĐỎ "KHÔNG CÓ TỒN KHO", kèm thông tin kho tạm (`get_aux_stock_by_product`, tối đa 6 pallet).
  - SL cần pick = `remaining_qty` (nếu có command) hoặc SL trên QR. Hiển thị "Yêu cầu | Đã picking | Còn lại" khi đang pick dở.
- Danh sách vị trí đề xuất: chỉ vị trí tồn > 0, **tồn ít nhất lên đầu**, cùng tồn thì theo mã kệ.

**Bước 2 – Chọn & xác nhận vị trí**:
- Chế độ **Chỉ đầu danh sách** (mặc định): chỉ được chọn vị trí đầu tiên. Chế độ **Bất kỳ**: chọn vị trí nào cũng được.
- Quét QR kệ, phải **khớp chính xác** `shelf_id` đã chọn; sai → popup ĐỎ "Sai vị trí".

**Bước 3 – Quét thùng hàng** (`[TEXT]$[product_id]$[TEXT]$[qty]...`, ≥4 phần):
- Mã hàng khác mã cần pick → ĐỎ "SAI MÃ HÀNG".
- `đã pick + trong danh sách + SL thùng > SL cần` → ĐỎ "ĐỦ LƯỢNG RỒI", khóa ô quét.
- `SL thùng > tồn còn lại tại vị trí (tồn ban đầu − trong danh sách)` → ĐỎ "HẾT TỒN KHO", khóa ô quét.
- SL đề xuất = `min(SL thùng, min(còn cần, tồn vị trí))`.
- Chế độ **Gián đoạn**: điền SL, người dùng sửa (không vượt tối đa) rồi `Enter` để thêm vào danh sách. Chế độ **Liên tục**: tự thêm.
- Nút **Xác nhận trừ tồn** bật khi `đã pick + danh sách = cần pick`, hoặc khi đây là thùng cuối của vị trí (tồn vị trí ≤ tổng danh sách).

**Xác nhận trừ tồn**: gọi `outbound_submit` **tuần tự cho từng thùng** với `is_picking=1`, `command`, `case_no='001'` → trừ `inventory`, `current_usage`, ghi `transactions` OUT và `export_log` picking. Sau đó:
- Cập nhật tồn vị trí trên client, thêm vào lịch sử phiên.
- Nếu vị trí vừa pick **hết sạch** → popup XANH rồi **tải lại trang sau 1,5s** (người dùng quét lại phiếu; `remaining_qty` được tính lại từ log nên tiếp tục đúng chỗ).
- Nếu đã đủ SL → bật **Xác nhận hoàn thành** (bước 4).
- Nếu vị trí hết mà chưa đủ và còn vị trí khác → popup VÀNG "VỊ TRÍ HẾT TỒN", quay lại bước 2 với danh sách đã loại vị trí đó. Không còn vị trí nào → popup VÀNG "HẾT VỊ TRÍ CÓ TỒN", cho phép hoàn thành khi chưa đủ.
- Lỗi ở 1 thùng: các thùng khác vẫn được gửi, báo lỗi cuối cùng.

**Hoàn thành**: chỉ reset giao diện về bước 1 (dữ liệu đã được ghi ở mỗi lần xác nhận trừ tồn).

### 10.9 In tem packing — `print_case.php` (Leader+ ở menu)
1. Chọn ngày → `get_invoices_by_date`; chọn Invoice → `get_cases_by_invoice`.
2. Mỗi kiện = **1 tem 100×80mm**: QR (40×40mm, có chỉnh lệch dọc `qr_vertical_offset` lưu localStorage) nội dung `command$case_no$for_product$item_count$transport_type$created_at`; text `<command><case_no>`, khách hàng/vận chuyển, số items, ngày xuất, giờ in.
3. Tùy chọn in giống 10.7. Khi in (kể cả chế độ test) gọi `insert_export_log_print_case`.
4. Có ô import packing list ngay trên trang (gọi `import_export_temp`, chỉ Admin thành công; **không gửi `clear_existing` nên sẽ xóa toàn bộ `export_temp` cũ**).

### 10.10 Packing — `packing.php` (Staff+)
1. Quét **tem packing** (`command$case_no$...`) hoặc chuỗi 9 ký tự `command+case_no` → `get_export_invoice_detail`. Không có dữ liệu → lỗi.
2. Hiển thị: bảng mã hàng (cần / đã pick / đã pack / còn thiếu), danh sách mã còn thiếu, thẻ tiến độ Picking (`picking_done_products / case_total_products`), Packing (`packing_done_products / total_products`), Pickup (Có/Chưa).
3. Quét **tem thùng** liên tục:
   - Mã hàng không thuộc kiện → ĐỎ.
   - `đã pack + SL thùng > cần` → VÀNG "Vượt quá số lượng", không ghi.
   - Hợp lệ → `export_log_scan(status='packing')` (server kiểm tra lại) → cập nhật bảng, đếm số lượt quét.
4. **Hoàn thành** (bấm bất cứ lúc nào): tổng packing < cần → VÀNG "Packing chưa đủ số lượng"; bằng → XANH "Packing hoàn tất, vui lòng chuyển sang bước Pickup". Không bao giờ ghi nhận vượt số lượng.

### 10.11 Pickup — `pickup.php` (Staff+)
1. Quét **tem kiện** (≥6 phần) → hiển thị Invoice, kiện, khách hàng, vận chuyển, số SP, ngày. Gọi `get_export_invoice_detail`; nếu `is_packed_done=false` → popup ĐỎ (kiện chưa packing đủ), không cho sang bước 2.
2. Quét **shipping mark** `command6case3` → so với bước 1. Không khớp → báo lỗi ĐỎ. Khớp → "đã khớp thông tin" và gọi `pickup_submit`.
3. Thành công → thêm vào lịch sử phiên, sau 0,8s reset và focus lại ô quét tem kiện.

### 10.12 Check Box — `check_box.php` (Staff+)
Đối chiếu tem WMS (Tem 1) và tem nhà cung cấp (Tem 2) trên cùng thùng, quét theo thứ tự bất kỳ trong **một ô duy nhất**:
1. Chuỗi chứa `$$` → Tem 1 `B$$<box_code>` → `get_check_box_tem1_inventory` lấy mã hàng + SL từ `wms_inventory`.
2. Ngược lại → Tem 2 (`SMC001$product$...$qty`).
3. Có đủ 2 tem → so sánh:
   - Khớp mã + SL → thẻ XANH "OK", `result_code=ok`.
   - Khớp mã, lệch SL → thẻ VÀNG "Số lượng 2 tem lần lượt là X và Y", `qty_mismatch`.
   - Lệch mã → popup ĐỎ chặn quét cho tới khi đóng, `product_mismatch`.
4. Mỗi kết quả ghi vào bảng `check_log` (qua action `check_box_log`) và lịch sử 20 dòng gần nhất trên màn hình. Quét tem tiếp theo khi đã đủ cặp → tự bắt đầu cặp mới.

### 10.13 In tem pallet — `print_pallet.php` (Leader+ ở menu)
1. Nhập nhiều mã pallet, mỗi dòng 1 mã (`Ctrl+Enter` = kiểm tra).
2. Chuẩn hóa HOA, bỏ dòng trống; trùng trong danh sách → lỗi; gọi `check_pallet_unique` cho từng mã (đã tồn tại trong `import_temp` → lỗi).
3. Chỉ tạo tem cho mã hợp lệ: QR = mã pallet; phần chữ tách theo dấu `-` thành nhiều dòng, cỡ chữ theo số dòng (1→27px, 2→20, 3→15, 4→12, 5→10, ≥6→9).
4. In bằng `window.print()`. Không ghi log.

### 10.14 Dashboard — `dashboard.php` (Guest+)
- Chọn ngày (Hôm qua/Hôm nay/Ngày mai hoặc chọn ngày) → `get_monitor_board`.
- KPI: tổng Invoice; số Invoice đã xong Picking / Packing / Pickup.
- Mỗi dòng Invoice: mã, khách hàng, ngày xuất, loại vận chuyển (AIR/SEA), 3 thanh tiến độ (đỏ = chưa bắt đầu, vàng = đang làm, xanh = đủ), giờ pickup gần nhất.
- Bấm vào dòng → `get_incomplete_cases_by_command`: liệt kê kiện chưa packing đủ (từng mã hàng đã pack/cần) và kiện chưa pickup.

### 10.15 Monitor — `monitor.php` (công khai)
- Trang độc lập, không cần đăng nhập, luôn lấy dữ liệu **hôm nay** qua `get_monitor_board`.
- Tự tải lại dữ liệu mỗi **5 phút** (`RELOAD_MS = 300000`), đồng hồ cập nhật mỗi giây.
- Tự cuộn kiểu bảng sân bay: dừng ở đầu 3,5s → cuộn xuống 0,4px/frame → dừng ở cuối 3,5s → nhảy về đầu.
- Trạng thái mỗi Invoice: Chờ (không có dữ liệu) → Picking → Packing (đã pick đủ) → Chờ bốc (pick + pack đủ) → Hoàn tất (pickup đủ).
- Theme Dark (mặc định)/Light, lưu `localStorage['swms-monitor-theme']`.

### 10.16 Layout — `layout.php` (Manager+ ở menu)
- `get_shelves?all=1`, bỏ kệ Deactive.
- Cấp 0: nhóm theo `level1_val` (khu vực), hiển thị tổng SKU; 0 SKU = trống, > 0 = có hàng.
- Bấm khu vực → cấp 1: nhóm theo `level2_val` (dãy, sắp giảm dần), trong dãy sắp `level3_val` tăng dần; mỗi ô kệ hiển thị `shelf_id` và số SKU; kệ Deactive hiển thị LOCKED; role Admin/Leader/Manager có nút nhanh `+` (Nhập kho) và `−` (Xuất kho).

### 10.17 Sản phẩm — `products.php` (Manager+)
Form `product_id` (bắt buộc), `product_name`, `unit` → `add_product`. Kèm ô tra tồn theo mã (`search_sku`).

### 10.18 Kệ hàng — `shelves.php` (Manager+)
- `level0_val` cố định `B032`; `level1_val` 1 chữ cái; `level2_val`, `level3_val` 2 chữ số; `level4_val` tùy chọn; `capacity` mặc định 100.
- Mã kệ tự sinh: `L1-L2-L3[-L4]`.
- Lưu: `check_shelf` (đã tồn tại → báo lỗi) → `add_shelf`. Kèm ô tra hàng trên kệ.

### 10.19 Quản trị — `admin.php` (Manager+)
Danh sách user (`get_users`), đổi role/khóa từng user (`update_user`), tạo user (`add_user`).

### 10.20 Data Import — `data_import.php` (Admin)
- Upload packing list (`import_export_temp`) với tùy chọn "Xóa dữ liệu cũ" (`clear_existing`).
- Bảng command/case_no theo ngày (`get_export_temp_command_case_rows`), sửa ngày xuất (`update_export_temp_case_date`), xóa (`delete_export_temp_case_rows`).

### 10.21 Data Export — `data_export.php` (Manager+)
- Xử lý **phía server trong chính trang** (không qua api.php): POST `export_action=export_xlsx` → truy vấn tồn > 0 → tạo file `assets/database/inventory_YYYYmmdd_His.xlsx` (sheet "Inventory Snapshot", cột: Mã vị trí full `B032-<shelf>`, Mã hàng, Tồn kho hiện tại) → redirect về trang (PRG), thông báo qua `$_SESSION`.
- Liệt kê các file `inventory_*.xlsx` đã xuất để tải về.
- Ghi log từng bước vào `assets/database/export_debug_YYYYMMDD.log`.
- Vì dùng `header('Location')`, trang cần output buffering (`index.php` đã `ob_start()`). Giao diện mới phải giữ điều kiện này hoặc tách phần xuất file sang endpoint riêng.

### 10.22 WMS Import — `wms_import.php` (Manager+)
Upload file tồn WMS → `import_wms_inventory` (thay toàn bộ `wms_inventory`), dùng cho Check Box.

### 10.23 Kiểm tra hệ thống — `system_check.php` (Admin)
Kiểm tra thư mục ghi được, thử ghi file, thông tin PHP (`memory_limit`, `upload_max_filesize`, `post_max_size`, hàm bị tắt...).

---

## 11. Công thức tiến độ (`get_monitor_board`)

Phạm vi: các dòng `export_temp` có `DATE(created_at) = ngày chọn`, nhóm theo `command`. Log `export_log` được cộng dồn **không lọc ngày**.

| Chỉ số | Công thức |
|---|---|
| `total_items` | Số `product_id` khác nhau của Invoice |
| `picking_items` | Số `product_id` có Σ log `picking` (mọi case) ≥ Σ `total_qty` |
| `total_cases` | Số `case_no` khác nhau |
| `packing_items` | Số **kiện** có Σ log `packing` của kiện ≥ Σ `total_qty` của kiện |
| `picked_cases` | Số `case_no` khác nhau có log `pickup` |
| `last_pickup_at` | Thời điểm log `pickup` gần nhất |
| `pickup_done` | `total_cases > 0` và `picked_cases ≥ total_cases` |
| Thêm | `picking_wait_items`, `packing_wait_items`/`packing_wait_cases`, `pickup_wait_cases` = phần còn lại |

Hiển thị: Picking = `picking_items / total_items` (SP); Packing = `packing_items / total_cases` (kiện); Pickup = `picked_cases / total_cases` (kiện). Màu: done ≥ total → xanh, 0 < done < total → vàng, 0 → đỏ. Sắp xếp: Invoice tạo sớm nhất lên đầu.

---

## 12. Định dạng file import

### 12.1 Packing list → `export_temp` (`import_export_temp`)
- Chấp nhận `.xlsx` (chỉ đọc sheet đầu tiên `sheet1.xml`) hoặc `.csv`.
- **Dòng không trống đầu tiên luôn bị bỏ qua** (coi là tiêu đề). Nếu ô đầu là `id` thì file có cột id.
- Thứ tự cột (không có id): `command | case_no | transport_type | for_product | product_id | total_qty | bucket_qty | created_at`. Có id thì thêm cột `id` ở đầu.
- Chuẩn hóa: `command`, `case_no`, `transport_type`, `product_id` → HOA; `case_no` trống → `001`; `transport_type` trống → `SEA`; SL bỏ ký tự không phải số.
- `created_at` chấp nhận số serial Excel hoặc `Y-m-d`, `Y/m/d`, `d/m/Y`, `d-m-Y`, `m/d/Y`, `m-d-Y`, `d.m.Y`; trống → hôm nay. Lưu dạng ngày (giờ = 00:00:00).
- Bỏ dòng thiếu `command`/`product_id` hoặc `total_qty ≤ 0` hoặc `bucket_qty ≤ 0` (trả về trong `errors`).
- Mẫu: `assets/packing_list.xlsx`.

### 12.2 Tồn WMS → `wms_inventory` (`import_wms_inventory`)
- Tự tìm dòng tiêu đề có các nhãn (không phân biệt hoa thường): `Mã sản phẩm`, `Mã thùng`, `SL ĐVT chính` (tùy chọn). Không thấy tiêu đề → cột 0 = mã sản phẩm, cột 1 = mã thùng, SL = 0.
- Bỏ dòng thiếu mã và cặp (mã thùng, mã sản phẩm) trùng. SL chấp nhận thập phân (dấu `,` hoặc `.`).
- Xóa toàn bộ bảng rồi nạp lại trong 1 transaction.

---

## 13. Vấn đề đã biết (cần lưu ý hoặc sửa khi chuyển giao diện)

Các điểm dưới đây là hành vi **hiện tại** của lõi; nếu chuyển nguyên trạng thì giao diện mới cũng thừa hưởng chúng.

1. **Log in tem packing không ghi được**: `insert_export_log_print_case` chèn `status='print_case'` nhưng cột `export_log.status` là ENUM chỉ có `picking/packing/pickup` → lỗi ở MySQL strict mode (hoặc lưu chuỗi rỗng). Trang In phiếu picking không ghi log.
2. **Picking luôn ghi `case_no='001'`**: với Invoice nhiều kiện, số liệu picking theo từng kiện trong `get_export_invoice_detail` (màn Packing) sai; tiến độ picking trên Dashboard/Monitor vẫn đúng vì tính theo `command + product_id`.
3. **Pickup không chặn quét lặp**: `pickup_submit` dùng `ON DUPLICATE KEY` nhưng bảng không có unique key → mỗi lần quét lại chèn thêm dòng. Tiến độ vẫn đếm `DISTINCT case_no` nên không sai số kiện. `is_pickup_done` trong `get_export_invoice_detail` so Σ quantity (=1 mỗi dòng) với tổng SL nên gần như luôn `false`.
4. **`get_incomplete_cases_by_command`**: khi mọi kiện đã packing đủ, API trả sớm `{cases: []}` mà không kiểm tra kiện chưa pickup → popup Dashboard báo "đã đủ" dù còn kiện chưa pickup.
5. **Ghi nhiều dòng không nguyên tử**: Nhận Pallet, Nhập kho, Xuất kho, Picking gửi từng dòng một; lỗi giữa chừng để lại dữ liệu đã ghi một phần. Transfer pallet (nhập kho rồi mới `mark_pallet_transferred`) cũng không nguyên tử.
6. **Parse số lượng từ tem thùng** lấy token toàn số **cuối cùng**; nếu `lot_no` (hoặc trường sau số lượng) là số thuần thì sẽ bị đọc nhầm thành số lượng.
7. **Mã kệ có tiền tố `B032-`**: Picking so khớp QR kệ chính xác với `shelf_id` (không cắt tiền tố); `transfer_products` cũng không cắt. Nếu tem kệ in kèm `B032-` thì 2 luồng này sẽ báo sai vị trí/không tìm thấy kệ.
8. **Import packing list từ trang In tem packing** không gửi `clear_existing` → mặc định xóa toàn bộ `export_temp` cũ; và chỉ Admin mới import được dù trang mở cho Leader.
9. **`add_shelf`** đọc `shelf_name` nhưng form Kệ hàng không có ô này (giá trị rỗng/NULL). `capacity` không được kiểm tra khi nhập kho.
10. **Bảo mật**: mật khẩu MD5; một số action đọc dữ liệu không yêu cầu đăng nhập (`check_product`, `check_shelf`, `get_shelves`, `search_sku`, `get_inventory_by_shelf`, `get_monitor_board`...); nhiều chỗ chèn dữ liệu vào HTML bằng template string không escape; không có CSRF token.
11. **Role API ≠ role menu** (mục 6.3): giao diện mới phải tự ẩn chức năng.
12. **Đặc tả cũ khác hiện trạng**: `import_temp.status` không dùng `received/transferred` mà dùng `NULL`/mã kệ; Đổi kệ ghi `OUT/IN` thay vì `MOVE_OUT/MOVE_IN`; Picking báo lỗi khi thùng vượt SL còn cần thay vì tự điền phần còn lại.

---

## 14. Checklist chuyển sang giao diện mới

- [ ] Giữ nguyên `api.php`, `config/db.php`, schema CSDL; không đổi tên action/tham số/response.
- [ ] Giữ router `?page=` và bảng phân quyền mục 6.2 (hoặc cơ chế tương đương) + kiểm tra role đầu trang ở các trang nhạy cảm.
- [ ] Nạp jQuery (hoặc viết lại lời gọi AJAX tương đương), `html5-qrcode`, `qrcodejs` từ `assets/` (offline).
- [ ] Cung cấp lại các hàm toàn cục của modal quét QR (mục 5.3) và hook `window.handleQRScannerScan`.
- [ ] Mỗi màn hình giữ nguyên thứ tự bước, điều kiện chặn và màu popup theo mục 10; giữ các `id` ô nhập hoặc cập nhật lại selector trong JS tương ứng.
- [ ] Giữ hành vi máy quét: debounce, điều kiện "quét xong", chống xử lý trùng, `Enter` xác nhận, auto-focus ô quét sau mỗi thao tác.
- [ ] Giữ khổ in: phiếu picking 80mm, tem packing 100×80mm, tem pallet; CSS in (`@page`) thuộc về giao diện nhưng phải đúng khổ giấy.
- [ ] `data_export.php` cần output buffering trước khi redirect (hoặc tách thành endpoint riêng).
- [ ] `monitor.php` là trang độc lập, không cần đăng nhập, tự reload 5 phút.
- [ ] Kiểm thử hồi quy theo chuỗi nghiệp vụ: In tem pallet → Nhận pallet → Pallet >>> Kệ → Tra tồn → Import packing list → In phiếu picking → Picking → In tem packing → Packing → Pickup → Dashboard/Monitor.
