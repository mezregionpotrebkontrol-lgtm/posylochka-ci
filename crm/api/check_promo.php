<?php
/**
 * Проверка промокода с сайта/приложения перед оплатой.
 * POST JSON {promo_code, amount} -> {ok:true, code, discount, total, label}
 * Итоговая сумма всё равно пересчитывается на сервере в create_order.php.
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$payload = capi_json_input();
$amount = (float) ($payload['amount'] ?? 0);
if ($amount <= 0) {
    capi_error('Сначала рассчитайте стоимость доставки.');
}

[$promo, $discount, $err] = crm_promo_check($pdo, (string) ($payload['promo_code'] ?? ''), $amount);
if ($err !== null) {
    capi_error($err);
}

capi_respond([
    'ok'       => true,
    'code'     => $promo['code'],
    'label'    => crm_promo_value_label($promo),
    'discount' => $discount,
    'total'    => round($amount - $discount, 2),
]);
