-- Схема базы данных CRM "Посылочка"
-- Импортируйте этот файл в вашу MySQL-базу через ispmanager → Базы данных → phpMyAdmin (или "Импорт").

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- Пользователи (сотрудники: администратор, оператор, курьер)
-- ==========================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    login VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','operator','courier') NOT NULL DEFAULT 'operator',
    phone VARCHAR(40) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Клиенты
-- ==========================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_clients_name (name),
    INDEX idx_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Заявки на доставку
-- ==========================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    courier_id INT UNSIGNED DEFAULT NULL,
    status ENUM('new','accepted','in_transit','delivered','cancelled') NOT NULL DEFAULT 'new',
    from_city VARCHAR(150) DEFAULT 'Дербент',
    to_city VARCHAR(150) DEFAULT 'Санкт-Петербург',
    from_address VARCHAR(500) DEFAULT NULL,
    to_address VARCHAR(500) DEFAULT NULL,
    cargo_description VARCHAR(500) DEFAULT NULL,
    weight_kg DECIMAL(8,2) DEFAULT NULL,
    declared_value DECIMAL(12,2) DEFAULT NULL,
    price DECIMAL(12,2) DEFAULT NULL,
    payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    planned_date DATE DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_courier (courier_id),
    INDEX idx_orders_planned_date (planned_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- История изменения статусов заявки (аудит)
-- ==========================================================
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    status ENUM('new','accepted','in_transit','delivered','cancelled') NOT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    comment VARCHAR(500) DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Счета на оплату
-- ==========================================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(50) NOT NULL UNIQUE,
    order_id INT UNSIGNED DEFAULT NULL,
    client_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('draft','sent','paid','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Платежи по счетам
-- ==========================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(50) DEFAULT 'перевод',
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- Учётная запись администратора по умолчанию
-- Логин: admin   Пароль: posylochka2026
-- ОБЯЗАТЕЛЬНО смените пароль после первого входа (раздел «Сотрудники»)!
-- Хеш ниже соответствует паролю posylochka2026 (bcrypt)
-- ==========================================================
INSERT INTO users (name, login, password_hash, role, active)
VALUES ('Наталия Казаченко', 'admin', '$2y$12$AvxcIUdYvlDgJGk5mSs22./UvxOMGN2j6jhfiVcU/tG1Xhk/ShOGG', 'admin', 1)
ON DUPLICATE KEY UPDATE login = login;
