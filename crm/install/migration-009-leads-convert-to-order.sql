-- Миграция 009: возможность конвертировать заявку с сайта (лид) в полноценную
-- заявку в CRM (orders) одним нажатием — добавляем поле-отметку, чтобы не
-- создать по одному лиду несколько заявок повторно.
-- Импортируйте через ispmanager → Базы данных → phpMyAdmin → SQL.

SET NAMES utf8mb4;

ALTER TABLE site_order_requests
    ADD COLUMN converted_order_id INT UNSIGNED DEFAULT NULL AFTER comment,
    ADD CONSTRAINT fk_site_order_requests_order FOREIGN KEY (converted_order_id) REFERENCES orders(id) ON DELETE SET NULL;

ALTER TABLE business_requests
    ADD COLUMN converted_order_id INT UNSIGNED DEFAULT NULL AFTER comment,
    ADD CONSTRAINT fk_business_requests_order FOREIGN KEY (converted_order_id) REFERENCES orders(id) ON DELETE SET NULL;
