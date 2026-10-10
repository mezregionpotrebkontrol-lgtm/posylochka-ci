-- Миграция 011: страхование груза, фотофиксация приёма/вручения,
-- геопозиция курьера для живой карты на сайте.
-- Импортируйте через ispmanager → Базы данных → phpMyAdmin → SQL.

SET NAMES utf8mb4;

-- 1) Страхование груза: отметка + страховая сумма + стоимость страховки.
ALTER TABLE orders
    ADD COLUMN is_insured TINYINT(1) NOT NULL DEFAULT 0 AFTER cod_amount,
    ADD COLUMN insured_amount DECIMAL(12,2) DEFAULT NULL AFTER is_insured,
    ADD COLUMN insurance_fee DECIMAL(12,2) DEFAULT NULL AFTER insured_amount;

-- 2) Фотофиксация груза при приёме и при вручении.
CREATE TABLE IF NOT EXISTS order_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    event ENUM('pickup','delivery') NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_photos_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Последняя известная геопозиция курьера (для живой карты в трекинге).
CREATE TABLE IF NOT EXISTS courier_locations (
    courier_id INT UNSIGNED PRIMARY KEY,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
