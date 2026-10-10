-- Миграция 007: тип клиента (физ./юр. лицо) + раздел "Претензии"
--
-- 1) У клиента появляется тип — физическое или юридическое лицо.
--    Для юр. лица дополнительно хранятся ИНН, КПП и контактное лицо
--    (поле name при этом используется как название организации).
-- 2) Новая таблица claims — претензии и возвраты по заявкам: причина,
--    описание/фото, сумма компенсации клиенту, статус рассмотрения и срок
--    ответа.

ALTER TABLE clients
    ADD COLUMN client_type ENUM('individual','company') NOT NULL DEFAULT 'individual' AFTER name,
    ADD COLUMN inn VARCHAR(20) DEFAULT NULL AFTER address,
    ADD COLUMN kpp VARCHAR(20) DEFAULT NULL AFTER inn,
    ADD COLUMN contact_person VARCHAR(255) DEFAULT NULL AFTER kpp;

CREATE TABLE IF NOT EXISTS claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    reason ENUM('defect','loss','return','other') NOT NULL DEFAULT 'other',
    description TEXT DEFAULT NULL,
    photo_path VARCHAR(500) DEFAULT NULL,
    compensation_amount DECIMAL(12,2) DEFAULT NULL,
    status ENUM('open','in_progress','resolved','rejected') NOT NULL DEFAULT 'open',
    response_due_date DATE DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_claims_order (order_id),
    INDEX idx_claims_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
