-- ============================================================
--  Bổ sung products.box_nom - định lượng số lượng chuẩn của 1 thùng
--  Dùng để quy đổi "X box * Y pcs" khi kiểm kê (check_inventory.note),
--  đặc biệt cho trường hợp "Xác nhận hàng loạt".
--  Chạy: mysql -u root cnt_smart_wms < products_box_nom.sql
--  (hoặc import qua phpMyAdmin) - an toàn để chạy nhiều lần.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `box_nom` INT DEFAULT NULL
  COMMENT 'Định lượng số lượng chuẩn của 1 thùng, dùng để quy đổi khi kiểm kê' AFTER `unit`;

ALTER TABLE `products`
  ADD COLUMN IF NOT EXISTS `box_name` VARCHAR(50) DEFAULT NULL
  COMMENT 'Mã thùng' AFTER `unit`;
