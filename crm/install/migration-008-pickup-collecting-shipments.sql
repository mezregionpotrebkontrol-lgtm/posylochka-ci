-- Миграция 008: выездной автосбор заявок + статус "Сбор груза" + автоматическая
-- группировка заявок по маршруту в сборные рейсы.
-- Импортируйте через ispmanager → Базы данных → phpMyAdmin → SQL.

SET NAMES utf8mb4;

-- 1) Новый статус заявки "collecting" (Сбор груза) — между "Принята" и "В пути":
--    отражает этап, когда груз уже собирается в сборный рейс, но ещё не выехал.
ALTER TABLE orders
    MODIFY COLUMN status ENUM('new','accepted','collecting','in_transit','delivered','cancelled')
    NOT NULL DEFAULT 'new';

ALTER TABLE order_status_history
    MODIFY COLUMN status ENUM('new','accepted','collecting','in_transit','delivered','cancelled')
    NOT NULL;

-- 2) Способ получения груза: клиент привозит сам, или нужен выездной сбор курьером
--    по адресу отправителя (from_address).
ALTER TABLE orders
    ADD COLUMN pickup_type ENUM('self','courier') NOT NULL DEFAULT 'self' AFTER courier_id;

-- 3) Сборные рейсы (группировка заявок по маршруту/дате для совместной перевозки).
CREATE TABLE IF NOT EXISTS shipment_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_city VARCHAR(150) NOT NULL DEFAULT 'Дербент',
    to_city VARCHAR(150) NOT NULL DEFAULT 'Санкт-Петербург',
    run_date DATE DEFAULT NULL,
    status ENUM('forming','in_transit','completed') NOT NULL DEFAULT 'forming',
    notes VARCHAR(500) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_shipment_runs_status (status),
    INDEX idx_shipment_runs_date (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN shipment_run_id INT UNSIGNED DEFAULT NULL AFTER courier_id,
    ADD CONSTRAINT fk_orders_shipment_run FOREIGN KEY (shipment_run_id) REFERENCES shipment_runs(id) ON DELETE SET NULL,
    ADD INDEX idx_orders_shipment_run (shipment_run_id);
