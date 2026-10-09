-- Миграция 005: параметры формулы расчёта стоимости (как в калькуляторе на
-- сайте) — делаем их редактируемыми в CRM, в разделе "Цены".

CREATE TABLE IF NOT EXISTS pricing_config (
    config_key   VARCHAR(40) NOT NULL PRIMARY KEY,
    config_value DECIMAL(10,4) NOT NULL,
    label        VARCHAR(160) NOT NULL,
    group_name   VARCHAR(40) NOT NULL,
    sort_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pricing_config (config_key, config_value, label, group_name, sort_order) VALUES
('weight_band1_max',  5,      'Вес до, кг (1-я ступень)',                 'weight', 1),
('weight_band1_rate', 180,    '₽/кг на 1-й ступени (до указанного веса)', 'weight', 2),
('weight_band2_max',  15,     'Вес до, кг (2-я ступень)',                 'weight', 3),
('weight_band2_rate', 90,     '₽/кг на 2-й ступени',                      'weight', 4),
('weight_band3_max',  30,     'Вес до, кг (3-я ступень)',                 'weight', 5),
('weight_band3_rate', 80,     '₽/кг на 3-й ступени',                      'weight', 6),
('weight_band4_rate', 65,     '₽/кг свыше 3-й ступени',                   'weight', 7),
('km_mult_base',      0.85,   'Коэффициент расстояния — минимум',         'distance', 8),
('km_mult_div',       10000,  'Коэффициент расстояния — делитель (км)',   'distance', 9),
('min_base_price',    400,    'Минимальная базовая цена, ₽',              'distance', 10),
('pack_none',         0,      'Без упаковки, ₽',                          'pack', 11),
('pack_bag',          50,     'Фирменный пакет, ₽',                       'pack', 12),
('pack_docs',         30,     'Пакет для документов, ₽',                  'pack', 13),
('pack_box_s',        120,    'Коробка S, ₽',                             'pack', 14),
('pack_box_m',        180,    'Коробка M, ₽',                             'pack', 15),
('pack_box_l',        250,    'Коробка L, ₽',                             'pack', 16),
('pack_bubble',       100,    'Пузырчатая плёнка, ₽',                     'pack', 17),
('pack_thermobox',    350,    'Термобокс, ₽',                             'pack', 18),
('surcharge_fragile',   300,  'Наценка «хрупкое», ₽',                     'addon', 19),
('surcharge_inventory', 100,  'Наценка «опись вложения», ₽',              'addon', 20),
('surcharge_sms',       20,   'Наценка «SMS-уведомления», ₽',             'addon', 21),
('insurance_rate',    0.015,  'Страхование — ставка от объявленной ценности', 'addon', 22),
('insurance_min',     50,     'Страхование — минимальная сумма, ₽',       'addon', 23)
ON DUPLICATE KEY UPDATE config_key = config_key;
