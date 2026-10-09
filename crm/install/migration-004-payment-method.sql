-- Миграция 004: способ оплаты заявки
-- Добавляет поле "способ оплаты" в заявки: наличный расчёт, оплата через
-- терминал, оплата по счёту, оплата онлайн на сайте.
-- Импортируйте через ispmanager → Базы данных → phpMyAdmin → SQL
-- (или через файловый менеджер, если на хостинге есть доступ к консоли MySQL).

ALTER TABLE orders
    ADD COLUMN payment_method ENUM('online','cash','terminal','invoice') DEFAULT NULL
    AFTER payment_status;

-- Для уже оплаченных заявок, у которых способ оплаты неизвестен (например,
-- принятых онлайн через сайт до этого обновления), можно по желанию
-- проставить значение по умолчанию. Раскомментируйте и выполните при
-- необходимости:
-- UPDATE orders SET payment_method = 'online' WHERE payment_status = 'paid' AND payment_method IS NULL;
