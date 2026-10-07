-- Миграция №2: личный кабинет клиента (вход по телефону+паролю, сессия по cookie),
-- публичное API для сайта/приложения, склад (приём/остатки/выдача по пунктам),
-- гибкие цены по маршрутам (ручной админский тариф — отдельно от калькулятора сайта),
-- отзывы и заявки с сайта (доставка/B2B).
--
-- Выполняется ОДИН РАЗ на уже работающей базе через phpMyAdmin (Импорт), ровно так же,
-- как ранее импортировался install/schema.mysql.sql. Существующие таблицы и данные
-- не затрагиваются — только добавляются новые.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================================
-- Учётные данные личного кабинета клиента (пароль + паспортные/КИЦ данные).
-- Привязана к clients(id) один-к-одному; сама clients используется и CRM-заявками.
-- ==========================================================
CREATE TABLE IF NOT EXISTS client_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    passport_series VARCHAR(20) DEFAULT NULL,
    passport_number VARCHAR(20) DEFAULT NULL,
    passport_issued_by VARCHAR(255) DEFAULT NULL,
    passport_issued_code VARCHAR(20) DEFAULT NULL,
    passport_issue_date DATE DEFAULT NULL,
    reg_city VARCHAR(150) DEFAULT NULL,
    reg_street VARCHAR(255) DEFAULT NULL,
    reg_house VARCHAR(30) DEFAULT NULL,
    reg_apartment VARCHAR(30) DEFAULT NULL,
    reg_postcode VARCHAR(20) DEFAULT NULL,
    consent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_ca_phone (phone),
    UNIQUE KEY uniq_ca_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Коды подтверждения для восстановления пароля по телефону (MAX и/или SMS.ru)
-- ==========================================================
CREATE TABLE IF NOT EXISTS client_auth_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(40) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cac_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Токены восстановления пароля по email-ссылке
-- ==========================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_prt_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Пункты приёма/выдачи (склады)
-- ==========================================================
CREATE TABLE IF NOT EXISTS warehouse_points (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    city VARCHAR(150) NOT NULL,
    address VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO warehouse_points (name, city, address)
SELECT * FROM (SELECT 'Пункт Дербент' AS name, 'Дербент' AS city, NULL AS address) t
WHERE NOT EXISTS (SELECT 1 FROM warehouse_points WHERE city = 'Дербент');

INSERT INTO warehouse_points (name, city, address)
SELECT * FROM (SELECT 'Пункт Санкт-Петербург' AS name, 'Санкт-Петербург' AS city, NULL AS address) t
WHERE NOT EXISTS (SELECT 1 FROM warehouse_points WHERE city = 'Санкт-Петербург');

-- ==========================================================
-- Складские события: приём груза в пункте / выдача получателю
-- ==========================================================
CREATE TABLE IF NOT EXISTS warehouse_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    point_id INT UNSIGNED NOT NULL,
    event_type ENUM('received','issued') NOT NULL,
    weight_kg DECIMAL(8,2) DEFAULT NULL,
    note VARCHAR(500) DEFAULT NULL,
    recipient_name VARCHAR(200) DEFAULT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (point_id) REFERENCES warehouse_points(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_wh_order (order_id),
    INDEX idx_wh_point (point_id),
    INDEX idx_wh_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Ручные тарифы по маршрутам (резерв/запасной вариант для CRM — калькулятор
-- и онлайн-оплата на сайте считают цену своей формулой, см. includes/pricing-formula.php)
-- ==========================================================
CREATE TABLE IF NOT EXISTS pricing_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_city VARCHAR(150) NOT NULL,
    to_city VARCHAR(150) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_per_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_price DECIMAL(10,2) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_route (from_city, to_city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pricing_rules (from_city, to_city, base_price, price_per_kg, min_price)
SELECT * FROM (SELECT 'Дербент' AS from_city, 'Санкт-Петербург' AS to_city, 0.00 AS base_price, 0.00 AS price_per_kg, NULL AS min_price) t
WHERE NOT EXISTS (SELECT 1 FROM pricing_rules WHERE from_city = 'Дербент' AND to_city = 'Санкт-Петербург');

-- ==========================================================
-- Платежи ЮKassa, привязанные к заявкам (для сверки webhook'ов и статуса).
-- Названа отдельно от существующей таблицы payments (та — по счетам invoices).
-- ==========================================================
CREATE TABLE IF NOT EXISTS yookassa_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    yk_payment_id VARCHAR(64) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    confirmation_url VARCHAR(500) DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_yk_payment (yk_payment_id),
    INDEX idx_pay_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Отзывы с сайта. Публикуются сразу (is_published=1) — как на реальном сайте
-- сейчас; скрыть/удалить отзыв можно вручную в CRM (reviews.php).
-- submitter_ip — для анти-спам проверки (не больше 1 отзыва за 5 минут с IP).
-- ==========================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(150) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    review_text TEXT NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    submitter_ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rev_published (is_published),
    INDEX idx_rev_ip (submitter_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Заявки "оформить доставку" (простая форма с сайта, без оплаты онлайн)
-- ==========================================================
CREATE TABLE IF NOT EXISTS site_order_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    city VARCHAR(150) DEFAULT NULL,
    dest_city VARCHAR(150) DEFAULT NULL,
    weight VARCHAR(50) DEFAULT NULL,
    comment VARCHAR(1000) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Заявки на сотрудничество (B2B)
-- ==========================================================
CREATE TABLE IF NOT EXISTS business_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    volume VARCHAR(50) DEFAULT NULL,
    cities VARCHAR(255) DEFAULT NULL,
    comment VARCHAR(1000) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Пометка источника заявки (crm / site) — для отчётности
-- ==========================================================
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'source'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE orders ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT ''crm'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- track_code — публичный трек-номер вида MP-YYMM-NNN, который видит клиент
-- и по которому работает публичный трекер (track_shipment.php), как на сайте сейчас.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'track_code'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE orders ADD COLUMN track_code VARCHAR(20) DEFAULT NULL AFTER source, ADD UNIQUE KEY uniq_track_code (track_code)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- service — вид отправления (Посылка/Продукты/B2B), как в приложении.
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'service'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE orders ADD COLUMN service VARCHAR(50) DEFAULT NULL AFTER track_code',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Публичный трекер на сайте строится из order_status_history (она уже есть
-- в основной схеме) — отдельных step0..step4_at колонок не нужно, см.
-- crm/api/track_shipment.php.

SET FOREIGN_KEY_CHECKS = 1;
