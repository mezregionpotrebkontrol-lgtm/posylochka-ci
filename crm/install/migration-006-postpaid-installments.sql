-- Миграция 006: постоплата и рассрочка
--
-- 1) payment_status получает новое значение 'postpaid' — заявка, по которой
--    договорились, что клиент заплатит после получения груза (наличными
--    курьеру/на складе или переводом). Это отдельный статус, а не способ
--    оплаты: как только деньги реально получены, заявка переводится в
--    обычное "paid" с указанием настоящего способа оплаты.
-- 2) payment_method получает новое значение 'installment' (рассрочка) —
--    сама рассрочка (график платежей) ведётся в новой таблице
--    order_installments.
-- 3) Новая таблица order_installments — график платежей по рассрочке:
--    каждая строка — один платёж (дата + сумма), отмечается оплаченным
--    отдельно. Когда оплачены все платежи графика, заявка автоматически
--    переводится в payment_status = 'paid'.

ALTER TABLE orders
    MODIFY COLUMN payment_status ENUM('unpaid','postpaid','paid') NOT NULL DEFAULT 'unpaid';

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM('online','cash','terminal','invoice','installment') DEFAULT NULL;

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
