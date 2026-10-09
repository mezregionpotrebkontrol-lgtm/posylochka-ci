<?php
/**
 * GET /crm/api/pricing_formula.php
 * Отдаёт текущие параметры формулы расчёта стоимости (те же, что в разделе
 * "Цены" CRM, таблица pricing_config) — чтобы калькулятор на сайте (и в
 * приложении, которое просто открывает сайт) всегда показывал ту же цену,
 * которую реально спишет сервер при оплате. Это НЕ источник правды для
 * самой оплаты — сервер (create_order.php) всегда пересчитывает сумму сам
 * по includes/pricing-formula.php, этот эндпоинт — только для того, чтобы
 * JS-калькулятор на странице показывал то же самое заранее.
 *
 * Ответ: { ok:true, config: { weight_band1_max: 5, ... } }
 * Если что-то пошло не так — { ok:true, config: null }, и сайт должен
 * продолжить работать на своих встроенных значениях по умолчанию.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/pricing-formula.php';

try {
    $config = crm_price_config();
    capi_respond(['ok' => true, 'config' => $config]);
} catch (Throwable $e) {
    capi_respond(['ok' => true, 'config' => null]);
}
