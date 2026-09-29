-- ============================================================
--  Bảng check_inventory - Lưu dữ liệu kiểm kê tồn kho theo vị trí
--  1 dòng = 1 lần quét thùng hàng (hoặc 1 mã hàng khi "Xác nhận hàng loạt")
--  Chạy: mysql -u root cnt_smart_wms < check_inventory.sql
--  (hoặc import qua phpMyAdmin)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `check_inventory`;

CREATE TABLE `check_inventory` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`  VARCHAR(40)  NOT NULL                       COMMENT 'Nhóm 1 lượt kiểm kê 1 vị trí',
  `shelf_id`    VARCHAR(100) NOT NULL                       COMMENT 'Mã vị trí full',
  `product_id`  VARCHAR(120) NOT NULL                       COMMENT 'Mã hàng hóa full',
  `quantity`    INT          NOT NULL DEFAULT 0             COMMENT 'Số lượng mỗi lần quét',
  `raw_qr`      TEXT             NULL                       COMMENT 'Nội dung QR gốc (hoặc "BULK-CONFIRM" nếu xác nhận hàng loạt)',
  `system_qty`  INT              NULL DEFAULT NULL          COMMENT 'Tồn hệ thống theo mã hàng tại thời điểm kiểm (snapshot)',
  `is_match`    TINYINT(1)   NOT NULL DEFAULT 1             COMMENT '1 = SL kiểm khớp tồn hệ thống',
  `note`        VARCHAR(255)     NULL DEFAULT NULL          COMMENT 'Quy đổi số lượng, VD "10 box * 10 pcs + 8 pcs" hoặc "1 box * 108 pcs"',
  `checked_by`  VARCHAR(50)  NOT NULL                       COMMENT 'Người kiểm',
  `checked_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian kiểm',
  PRIMARY KEY (`id`),
  KEY `idx_check_inventory_shelf`      (`shelf_id`),
  KEY `idx_check_inventory_product`    (`product_id`),
  KEY `idx_check_inventory_session`    (`session_id`),
  KEY `idx_check_inventory_checked_at` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
