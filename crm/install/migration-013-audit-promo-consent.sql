-- Migration 013: журнал действий сотрудников, промокоды/скидки,
-- согласие на обработку персональных данных (152-ФЗ), отметки напоминаний.
-- Импорт: ispmanager -> Базы данных -> u3661226_posylochka_crm -> phpMyAdmin -> SQL.
-- Безопасно для работающей CRM: только добавляет таблицы и столбцы.

SET NAMES utf8mb4;

-- 1. Журнал действий
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    user_name VARCHAR(150) DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    entity VARCHAR(60) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_entity (entity, entity_id),
    INDEX idx_audit_action_ip (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Промокоды
CREATE TABLE IF NOT EXISTS promo_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    min_order_amount DECIMAL(12,2) DEFAULT NULL,
    valid_from DATE DEFAULT NULL,
    valid_to DATE DEFAULT NULL,
    max_uses INT UNSIGNED DEFAULT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN promo_code VARCHAR(40) DEFAULT NULL,
    ADD COLUMN discount_amount DECIMAL(12,2) DEFAULT NULL;

-- 3. Согласие на обработку персональных данных (когда и откуда дано)
ALTER TABLE clients
    ADD COLUMN pd_consent_at DATETIME DEFAULT NULL,
    ADD COLUMN pd_consent_source VARCHAR(60) DEFAULT NULL;

-- 4. Отметка «напоминание уже отправлено», чтобы не слать повторно каждый день
ALTER TABLE invoices
    ADD COLUMN reminded_at DATETIME DEFAULT NULL;
