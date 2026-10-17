<?php
/**
 * Промокоды и скидки (таблица promo_codes, поля orders.promo_code /
 * orders.discount_amount — миграция 013).
 *
 * Промокод даёт скидку в процентах или фиксированной суммой, может иметь
 * минимальную сумму заказа, срок действия и лимит использований.
 * Если миграция ещё не применена, все функции ведут себя так, будто
 * промокодов нет — ничего не ломается.
 */

function crm_promo_normalize(?string $code): string
{
    return mb_strtoupper(trim((string) $code));
}

function crm_promo_type_label(string $type): string
{
    return $type === 'fixed' ? 'Сумма, ₽' : 'Процент, %';
}

/** Описание скидки для людей: «10%» или «300 ₽». */
function crm_promo_value_label(array $promo): string
{
    $v = (float) $promo['discount_value'];
    return $promo['discount_type'] === 'fixed'
        ? number_format($v, 0, ',', ' ') . ' ₽'
        : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%';
}

/**
 * Проверяет промокод для суммы заказа $amount.
 * @return array{0:?array,1:float,2:?string} [промокод|null, скидка в рублях, ошибка|null]
 */
function crm_promo_check(PDO $pdo, ?string $code, float $amount): array
{
    $code = crm_promo_normalize($code);
    if ($code === '') {
        return [null, 0.0, 'Введите промокод.'];
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $promo = $stmt->fetch();
    } catch (Throwable $e) {
        return [null, 0.0, 'Промокоды пока не подключены.'];
    }
    if (!$promo || !(int) $promo['active']) {
        return [null, 0.0, 'Такого промокода нет или он отключён.'];
    }
    $today = date('Y-m-d');
    if (!empty($promo['valid_from']) && $today < $promo['valid_from']) {
        return [null, 0.0, 'Промокод начнёт действовать ' . date('d.m.Y', strtotime($promo['valid_from'])) . '.'];
    }
    if (!empty($promo['valid_to']) && $today > $promo['valid_to']) {
        return [null, 0.0, 'Срок действия промокода истёк.'];
    }
    if ($promo['max_uses'] !== null && (int) $promo['used_count'] >= (int) $promo['max_uses']) {
        return [null, 0.0, 'Промокод уже использован максимальное число раз.'];
    }
    if ($promo['min_order_amount'] !== null && $amount < (float) $promo['min_order_amount']) {
        return [null, 0.0, 'Промокод действует для заказов от ' . number_format((float) $promo['min_order_amount'], 0, ',', ' ') . ' ₽.'];
    }
    $discount = crm_promo_discount($promo, $amount);
    if ($discount <= 0) {
        return [null, 0.0, 'Промокод не даёт скидки для этой суммы.'];
    }
    return [$promo, $discount, null];
}

/** Размер скидки в рублях (не больше суммы заказа, округление до копеек). */
function crm_promo_discount(array $promo, float $amount): float
{
    if ($amount <= 0) {
        return 0.0;
    }
    $value = (float) $promo['discount_value'];
    $discount = $promo['discount_type'] === 'fixed' ? $value : $amount * $value / 100;
    if (!empty($promo['max_discount']) && (float) $promo['max_discount'] > 0) {
        $discount = min($discount, (float) $promo['max_discount']);
    }
    return round(min($discount, $amount), 2);
}

/**
 * Атомарно засчитывает одно использование промокода (с учётом лимита).
 * Возвращает false, если лимит уже исчерпан (например, два клиента одновременно).
 */
function crm_promo_consume(PDO $pdo, int $promoId): bool
{
    $stmt = $pdo->prepare('UPDATE promo_codes SET used_count = used_count + 1
        WHERE id = ? AND active = 1 AND (max_uses IS NULL OR used_count < max_uses)');
    $stmt->execute([$promoId]);
    return $stmt->rowCount() === 1;
}

function crm_promo_release(PDO $pdo, ?string $code): void
{
    $code = crm_promo_normalize($code);
    if ($code === '') {
        return;
    }
    $pdo->prepare('UPDATE promo_codes SET used_count = GREATEST(used_count - 1, 0) WHERE code = ?')->execute([$code]);
}
