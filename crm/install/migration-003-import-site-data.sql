-- Миграция №3: разовый перенос уже накопленных данных сайта
-- (база u3661226_posylochka — старый, реально работающий сейчас backend)
-- в новую CRM (база u3661226_posylochka_crm).
--
-- ВАЖНО:
--   1) Сделайте экспорт (бэкап) ОБЕИХ баз в phpMyAdmin перед запуском
--      ("Экспорт" → "Быстрый" → SQL) — на случай, если что-то пойдёт не так.
--   2) Выполнять через phpMyAdmin ОДИН РАЗ, уже ПОСЛЕ того как будет
--      импортирован migration-002-client-api-warehouse.sql в базу
--      u3661226_posylochka_crm (в ней должны уже существовать таблицы
--      clients, client_accounts, orders, order_status_history,
--      yookassa_payments, reviews).
--   3) Запускать нужно пользователем, у которого есть доступ на ЧТЕНИЕ базы
--      u3661226_posylochka И на ЗАПИСЬ в u3661226_posylochka_crm одновременно
--      (обычный логин в ispmanager/phpMyAdmin от имени владельца хостинга
--      такой доступ даёт; отдельные "пользователи баз" u3661226_posylochka
--      и u3661226_posylochka_crm — каждый видит только свою базу).
--   4) Не переносит файлы фото отзывов — их нужно отдельно скопировать
--      (см. инструкцию в конце файла).

SET NAMES utf8mb4;

-- ==========================================================
-- 1) Клиенты сайта (users) → clients + client_accounts
-- ==========================================================
INSERT INTO u3661226_posylochka_crm.clients (name, phone, email, created_at)
SELECT u.name, u.phone, u.email, u.created_at
FROM u3661226_posylochka.users u
WHERE NOT EXISTS (
    SELECT 1 FROM u3661226_posylochka_crm.clients c WHERE c.phone = u.phone
);

INSERT INTO u3661226_posylochka_crm.client_accounts
    (client_id, phone, password_hash, email, birth_date, passport_series, passport_number,
     passport_issued_by, passport_issued_code, passport_issue_date,
     reg_city, reg_street, reg_house, reg_apartment, reg_postcode, consent_at, created_at)
SELECT c.id, u.phone, u.password_hash, u.email, u.birth_date, u.passport_series, u.passport_number,
       u.passport_issued_by, u.passport_issued_code, u.passport_issue_date,
       u.reg_city, u.reg_street, u.reg_house, u.reg_apartment, u.reg_postcode, u.consent_at, u.created_at
FROM u3661226_posylochka.users u
JOIN u3661226_posylochka_crm.clients c ON c.phone = u.phone
WHERE NOT EXISTS (
    SELECT 1 FROM u3661226_posylochka_crm.client_accounts ca WHERE ca.phone = u.phone
);

-- ==========================================================
-- 2) Заказы с онлайн-оплатой (orders: pending/paid/canceled/error) → orders
-- ==========================================================
INSERT INTO u3661226_posylochka_crm.clients (name, phone, created_at)
SELECT o.name, o.phone, o.created_at
FROM u3661226_posylochka.orders o
WHERE NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.clients c WHERE c.phone = o.phone);

INSERT INTO u3661226_posylochka_crm.orders
    (client_id, status, from_city, to_city, weight_kg, price, payment_status, comment, source, created_at)
SELECT
    c.id,
    CASE o.status WHEN 'paid' THEN 'accepted' WHEN 'canceled' THEN 'cancelled' ELSE 'new' END,
    o.city, 'Санкт-Петербург', o.weight, o.amount,
    CASE o.status WHEN 'paid' THEN 'paid' ELSE 'unpaid' END,
    CONCAT('[Перенесено со старого сайта, заказ №', o.id, ']',
           CASE WHEN o.comment IS NOT NULL AND o.comment <> '' THEN CONCAT(CHAR(10), o.comment) ELSE '' END),
    'site_migrated',
    o.created_at
FROM u3661226_posylochka.orders o
JOIN u3661226_posylochka_crm.clients c ON c.phone = o.phone
WHERE NOT EXISTS (
    SELECT 1 FROM u3661226_posylochka_crm.orders x
    WHERE x.source = 'site_migrated' AND x.comment LIKE CONCAT('[Перенесено со старого сайта, заказ №', o.id, ']%')
);

INSERT INTO u3661226_posylochka_crm.order_status_history (order_id, status, changed_by, comment, changed_at)
SELECT n.id, n.status, NULL, 'Перенесено со старого сайта', n.created_at
FROM u3661226_posylochka_crm.orders n
WHERE n.source = 'site_migrated'
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.order_status_history h WHERE h.order_id = n.id);

-- Платежи ЮKassa по перенесённым заказам (если был payment_id)
INSERT INTO u3661226_posylochka_crm.yookassa_payments (order_id, yk_payment_id, amount, confirmation_url, status, created_at)
SELECT n.id, o.payment_id, o.amount, o.confirmation_url,
       CASE o.status WHEN 'paid' THEN 'succeeded' WHEN 'canceled' THEN 'canceled' ELSE 'pending' END,
       o.created_at
FROM u3661226_posylochka.orders o
JOIN u3661226_posylochka_crm.orders n
    ON n.source = 'site_migrated' AND n.comment LIKE CONCAT('[Перенесено со старого сайта, заказ №', o.id, ']%')
WHERE o.payment_id IS NOT NULL AND o.payment_id <> ''
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.yookassa_payments p WHERE p.yk_payment_id = o.payment_id);

-- ==========================================================
-- 3) Посылки с трек-номером (shipments) → orders
--    Внимание: 5-шаговый трекер сайта (0..4) грубо сведён к 4 рабочим
--    статусам CRM (new/accepted/in_transit/delivered) — шаги 1 и 2
--    ("загружено"/"в пути") оба лягут в in_transit. Для уже доставленных
--    посылок это не важно (трекер покажет "вручено"), для посылок в пути —
--    покажет текущий статус корректно, просто один промежуточный шаг не
--    будет виден отдельно в истории.
-- ==========================================================
INSERT INTO u3661226_posylochka_crm.clients (name, phone, created_at)
SELECT s.sender_name, s.phone, s.created_at
FROM u3661226_posylochka.shipments s
WHERE NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.clients c WHERE c.phone = s.phone);

INSERT INTO u3661226_posylochka_crm.orders
    (client_id, status, from_city, to_city, to_address, weight_kg, comment, source, track_code, service, created_at)
SELECT
    c.id,
    CASE
        WHEN s.step_index >= 4 THEN 'delivered'
        WHEN s.step_index >= 1 THEN 'in_transit'
        ELSE 'new'
    END,
    'Дербент', 'Санкт-Петербург', s.address,
    CAST(REGEXP_SUBSTR(s.weight, '[0-9]+([.,][0-9]+)?') AS DECIMAL(8,2)),
    CONCAT('[Перенесено со старого сайта, трек ', s.track_number, ']',
           CASE WHEN s.comment IS NOT NULL AND s.comment <> '' THEN CONCAT(CHAR(10), s.comment) ELSE '' END),
    'site_migrated', s.track_number, s.service, s.created_at
FROM u3661226_posylochka.shipments s
JOIN u3661226_posylochka_crm.clients c ON c.phone = s.phone
WHERE NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.orders x WHERE x.track_code = s.track_number);

-- История статусов для перенесённых посылок — из реальных дат step0..step4,
-- чтобы трекер показывал подлинные даты, а не дату миграции.
INSERT INTO u3661226_posylochka_crm.order_status_history (order_id, status, changed_by, comment, changed_at)
SELECT n.id, 'new', NULL, 'Перенесено со старого сайта', s.step0_at
FROM u3661226_posylochka.shipments s
JOIN u3661226_posylochka_crm.orders n ON n.track_code = s.track_number
WHERE s.step0_at IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.order_status_history h WHERE h.order_id = n.id AND h.status = 'new');

INSERT INTO u3661226_posylochka_crm.order_status_history (order_id, status, changed_by, comment, changed_at)
SELECT n.id, 'accepted', NULL, 'Перенесено со старого сайта', COALESCE(s.step1_at, s.step2_at)
FROM u3661226_posylochka.shipments s
JOIN u3661226_posylochka_crm.orders n ON n.track_code = s.track_number
WHERE (s.step1_at IS NOT NULL OR s.step2_at IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.order_status_history h WHERE h.order_id = n.id AND h.status = 'accepted');

INSERT INTO u3661226_posylochka_crm.order_status_history (order_id, status, changed_by, comment, changed_at)
SELECT n.id, 'in_transit', NULL, 'Перенесено со старого сайта', COALESCE(s.step2_at, s.step3_at)
FROM u3661226_posylochka.shipments s
JOIN u3661226_posylochka_crm.orders n ON n.track_code = s.track_number
WHERE s.step_index >= 2 AND (s.step2_at IS NOT NULL OR s.step3_at IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.order_status_history h WHERE h.order_id = n.id AND h.status = 'in_transit');

INSERT INTO u3661226_posylochka_crm.order_status_history (order_id, status, changed_by, comment, changed_at)
SELECT n.id, 'delivered', NULL, 'Перенесено со старого сайта', s.step4_at
FROM u3661226_posylochka.shipments s
JOIN u3661226_posylochka_crm.orders n ON n.track_code = s.track_number
WHERE s.step4_at IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM u3661226_posylochka_crm.order_status_history h WHERE h.order_id = n.id AND h.status = 'delivered');

-- ==========================================================
-- 4) Отзывы (schema уже совпадает один в один)
-- ==========================================================
INSERT INTO u3661226_posylochka_crm.reviews
    (author_name, rating, review_text, photo_path, is_published, submitter_ip, created_at)
SELECT r.author_name, r.rating, r.review_text, r.photo_path, r.is_published, r.submitter_ip, r.created_at
FROM u3661226_posylochka.reviews r
WHERE NOT EXISTS (
    SELECT 1 FROM u3661226_posylochka_crm.reviews x
    WHERE x.author_name = r.author_name AND x.created_at = r.created_at
);

-- ==========================================================
-- После выполнения — вручную через Менеджер файлов ispmanager скопируйте
-- содержимое папки  api/uploads/reviews/  (старый сайт) в новую папку,
-- которую будет отдавать переписанный /api (путь подскажу перед заливкой
-- новых файлов — она останется той же, uploads/reviews, просто будет
-- физически в другом месте на сервере).
-- ==========================================================
