-- Миграция 010: наложенный платёж (COD), штрихкоды грузомест и подпись
-- получателя при вручении.
-- Импортируйте через ispmanager → Базы данных → phpMyAdmin → SQL.

SET NAMES utf8mb4;

-- 1) Наложенный платёж: отметка на заявке + сумма к получению при вручении.
ALTER TABLE orders
    ADD COLUMN is_cod TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_method,
    ADD COLUMN cod_amount DECIMAL(12,2) DEFAULT NULL AFTER is_cod;

-- 2) Количество грузомест (для печати этикеток со штрихкодом на каждое место).
ALTER TABLE orders
    ADD COLUMN places_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER weight_kg;

-- 3) Подпись получателя при вручении (PNG, base64, из подписи на экране курьера).
ALTER TABLE orders
    ADD COLUMN recipient_signature LONGTEXT DEFAULT NULL AFTER comment;

-- 4) Грузоместа со своим штрихкодом — для печати этикеток и сканирования на складе.
CREATE TABLE IF NOT EXISTS order_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    barcode VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('created','packed','loaded','delivered') NOT NULL DEFAULT 'created',
    scanned_at DATETIME DEFAULT NULL,
    scanned_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_packages_order (order_id),
    INDEX idx_order_packages_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
