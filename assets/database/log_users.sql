-- Bảng quản lý người dùng và phân quyền (RBAC)
CREATE TABLE log_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Khuyến nghị dùng password_hash() của PHP
    full_name VARCHAR(100),
    role VARCHAR(20) NOT NULL DEFAULT 'Staff', -- 'Admin', 'Leader', 'Manager', 'Staff'
    status BOOLEAN DEFAULT TRUE, -- TRUE: Active, FALSE: Locked
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dữ liệu mẫu khởi tạo (Password gốc: admin123, leader123, staff123)
INSERT INTO log_users (username, password, full_name, role) VALUES 
('admin', '0192023a7bbd73250516f069df18b500', 'Quản Trị Viên', 'Admin'),
('leader01', '7733246f663f733973c5132549298e3b', 'Trưởng Nhóm Kho', 'Leader'),
('staff01', '663673c24230876403612d46e927054f', 'Nhân Viên Kho', 'Staff');