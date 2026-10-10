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
    payment_status ENUM('unpaid','postpaid','paid') NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('online','cash','terminal','invoice','installment') DEFAULT NULL,
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

-- ==========================================================
-- График платежей по рассрочке
-- ==========================================================
CREATE TABLE IF NOT EXISTS order_installments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    due_date DATE DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_installments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- Параметры формулы расчёта стоимости (редактируются в разделе "Цены")
-- ==========================================================
CREATE TABLE IF NOT EXISTS pricing_config (
    config_key   VARCHAR(40) NOT NULL PRIMARY KEY,
    config_value DECIMAL(10,4) NOT NULL,
    label        VARCHAR(160) NOT NULL,
    group_name   VARCHAR(40) NOT NULL,
    sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pricing_config (config_key, config_value, label, group_name, sort_order) VALUES
('weight_band1_max',  5,      'Вес до, кг (1-я ступень)',                 'weight', 1),
('weight_band1_rate', 180,    '₽/кг на 1-й ступени (до указанного веса)', 'weight', 2),
('weight_band2_max',  15,     'Вес до, кг (2-я ступень)',                 'weight', 3),
('weight_band2_rate', 90,     '₽/кг на 2-й ступени',                      'weight', 4),
('weight_band3_max',  30,     'Вес до, кг (3-я ступень)',                 'weight', 5),
('weight_band3_rate', 80,     '₽/кг на 3-й ступени',                      'weight', 6),
('weight_band4_rate', 65,     '₽/кг свыше 3-й ступени',                   'weight', 7),
('km_mult_base',      0.85,   'Коэффициент расстояния — минимум',         'distance', 8),
('km_mult_div',       10000,  'Коэффициент расстояния — делитель (км)',   'distance', 9),
('min_base_price',    400,    'Минимальная базовая цена, ₽',              'distance', 10),
('pack_none',         0,      'Без упаковки, ₽',                          'pack', 11),
('pack_bag',          50,     'Фирменный пакет, ₽',                       'pack', 12),
('pack_docs',         30,     'Пакет для документов, ₽',                  'pack', 13),
('pack_box_s',        120,    'Коробка S, ₽',                             'pack', 14),
('pack_box_m',        180,    'Коробка M, ₽',                             'pack', 15),
('pack_box_l',        250,    'Коробка L, ₽',                             'pack', 16),
('pack_bubble',       100,    'Пузырчатая плёнка, ₽',                     'pack', 17),
('pack_thermobox',    350,    'Термобокс, ₽',                             'pack', 18),
('surcharge_fragile',   300,  'Наценка «хрупкое», ₽',                     'addon', 19),
('surcharge_inventory', 100,  'Наценка «опись вложения», ₽',              'addon', 20),
('surcharge_sms',       20,   'Наценка «SMS-уведомления», ₽',             'addon', 21),
('insurance_rate',    0.015,  'Страхование — ставка от объявленной ценности', 'addon', 22),
('insurance_min',     50,     'Страхование — минимальная сумма, ₽',       'addon', 23);

-- ==========================================================
-- Учётная запись администратора по умолчанию
-- Логин: admin   Пароль: posylochka2026
-- ОБЯЗАТЕЛЬНО смените пароль после первого входа (раздел «Сотрудники»)!
-- Хеш ниже соответствует паролю posylochka2026 (bcrypt)
-- ==========================================================
INSERT INTO users (name, login, password_hash, role, active)
VALUES ('Наталия Казаченко', 'admin', '$2y$12$AvxcIUdYvlDgJGk5mSs22./UvxOMGN2j6jhfiVcU/tG1Xhk/ShOGG', 'admin', 1)
ON DUPLICATE KEY UPDATE login = login;
