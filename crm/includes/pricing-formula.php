<?php
/**
 * Серверная копия формулы расчёта стоимости онлайн-оплаты с сайта (app.html,
 * блок "online payment for the order form"). Нужна, чтобы при создании
 * платежа через ЮKassa сервер сам пересчитал сумму — никогда нельзя доверять
 * цене, присланной из браузера, иначе человек может подменить её в devtools
 * и заплатить меньше. Логика продублирована 1:1 из JS; при изменении формулы
 * или тарифов на сайте обязательно обновите и этот файл.
 */

require_once __DIR__ . '/price-data.php';

/**
 * Параметры формулы расчёта — редактируются в CRM (крм/pricing.php) и
 * хранятся в таблице pricing_config. Если таблицы ещё нет (миграция не
 * выполнена) или какого-то параметра нет в БД — используются значения по
 * умолчанию ниже (это те же цифры, что исторически были захардкожены и
 * продублированы в JS-калькуляторе на сайте).
 */
function crm_price_config_defaults(): array
{
    return [
        'weight_band1_max'    => 5,
        'weight_band1_rate'   => 180,
        'weight_band2_max'    => 15,
        'weight_band2_rate'   => 90,
        'weight_band3_max'    => 30,
        'weight_band3_rate'   => 80,
        'weight_band4_rate'   => 65,
        'km_mult_base'        => 0.85,
        'km_mult_div'         => 10000,
        'min_base_price'      => 400,
        'pack_none'           => 0,
        'pack_bag'            => 50,
        'pack_docs'           => 30,
        'pack_box_s'          => 120,
        'pack_box_m'          => 180,
        'pack_box_l'          => 250,
        'pack_bubble'         => 100,
        'pack_thermobox'      => 350,
        'surcharge_fragile'   => 300,
        'surcharge_inventory' => 100,
        'surcharge_sms'       => 20,
        'insurance_rate'      => 0.015,
        'insurance_min'       => 50,
    ];
}

function crm_price_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $config = crm_price_config_defaults();
    try {
        $rows = crm_db()->query('SELECT config_key, config_value FROM pricing_config')->fetchAll();
        foreach ($rows as $row) {
            $config[$row['config_key']] = (float) $row['config_value'];
        }
    } catch (Throwable $e) {
        // Таблица pricing_config ещё не создана (миграция 005 не выполнена) —
        // работаем на значениях по умолчанию, ничего не ломаем.
    }
    return $config;
}

function crm_price_pack_types(): array
{
    $c = crm_price_config();
    return [
        'none'      => $c['pack_none'],
        'bag'       => $c['pack_bag'],
        'docs'      => $c['pack_docs'],
        'box_s'     => $c['pack_box_s'],
        'box_m'     => $c['pack_box_m'],
        'box_l'     => $c['pack_box_l'],
        'bubble'    => $c['pack_bubble'],
        'thermobox' => $c['pack_thermobox'],
    ];
}

function crm_price_lookup_city_key(?string $value): ?string
{
    $val = trim((string) $value);
    if ($val === '') {
        return null;
    }
    $zones = crm_price_city_zones();
    if (array_key_exists($val, $zones)) {
        return $val;
    }
    $lower = mb_strtolower($val);
    foreach (array_keys($zones) as $key) {
        $cityPart = trim(explode(' (', $key)[0]);
        if (mb_strtolower($cityPart) === $lower) {
            return $key;
        }
    }
    return null;
}

function crm_price_lookup_zone(?string $value): ?int
{
    $key = crm_price_lookup_city_key($value);
    if ($key === null) {
        return null;
    }
    return crm_price_city_zones()[$key];
}

function crm_price_city_region_of(string $key): string
{
    if ($key === 'Москва' || $key === 'Санкт-Петербург') {
        return $key;
    }
    if (preg_match('/\(([^)]+)\)$/u', $key, $m)) {
        return $m[1];
    }
    $prefix = 'Другой город — ';
    if (strpos($key, $prefix) === 0) {
        return mb_substr($key, mb_strlen($prefix));
    }
    return $key;
}

function crm_price_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $rLat1 = deg2rad($lat1);
    $rLat2 = deg2rad($lat2);
    $a = sin($dLat / 2) ** 2 + cos($rLat1) * cos($rLat2) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

const CRM_PRICE_ROAD_FACTOR = 1.15;

function crm_price_route_km(?string $originValue, ?string $destValue): ?int
{
    $originKey = crm_price_lookup_city_key($originValue);
    $destKey = crm_price_lookup_city_key($destValue);
    if ($originKey === null || $destKey === null) {
        return null;
    }
    $originRegion = crm_price_city_region_of($originKey);
    $destRegion = crm_price_city_region_of($destKey);
    $coords = crm_price_region_coords();
    if (!array_key_exists($originRegion, $coords) || !array_key_exists($destRegion, $coords)) {
        return null;
    }
    [$oLat, $oLon] = $coords[$originRegion];
    [$dLat, $dLon] = $coords[$destRegion];
    $straight = crm_price_haversine_km($oLat, $oLon, $dLat, $dLon);
    $km = (int) round($straight * CRM_PRICE_ROAD_FACTOR);
    if ($originKey !== $destKey && $km < 60) {
        $km = 60;
    }
    return $km;
}

function crm_price_tiered_cost(float $w): float
{
    if ($w <= 0) {
        return 0;
    }
    $c = crm_price_config();
    $bands = [
        [$c['weight_band1_max'], $c['weight_band1_rate']],
        [$c['weight_band2_max'], $c['weight_band2_rate']],
        [$c['weight_band3_max'], $c['weight_band3_rate']],
        [INF, $c['weight_band4_rate']],
    ];
    $cost = 0;
    $prev = 0;
    foreach ($bands as [$upto, $rate]) {
        $span = min($upto, $w) - $prev;
        if ($span <= 0) {
            break;
        }
        $cost += $span * $rate;
        $prev = min($upto, $w);
        if ($prev >= $w) {
            break;
        }
    }
    return $cost;
}

/**
 * Пересчитывает итоговую сумму заказа так же, как это делает калькулятор на
 * сайте (payBtn → recalc()). Возвращает null, если маршрут не распознан
 * (сервер не должен принимать оплату за неизвестный маршрут).
 *
 * $addons: ['pack_type'=>string, 'fragile'=>bool, 'inventory'=>bool, 'sms'=>bool, 'insure_value'=>float]
 */
function crm_calc_checkout_total(string $originCity, string $destCity, float $weightKg, array $addons): ?array
{
    $originZone = crm_price_lookup_zone($originCity);
    $destZone = crm_price_lookup_zone($destCity);
    $routeKm = crm_price_route_km($originCity, $destCity);
    if ($originZone === null || $destZone === null || $routeKm === null || $weightKg <= 0) {
        return null;
    }

    $c = crm_price_config();
    $kmMult = $c['km_mult_base'] + ($routeKm / $c['km_mult_div']);
    $base = crm_price_tiered_cost($weightKg) * $kmMult;
    if ($base < $c['min_base_price']) {
        $base = $c['min_base_price'];
    }

    $packTypes = crm_price_pack_types();
    $packType = array_key_exists($addons['pack_type'] ?? '', $packTypes) ? $addons['pack_type'] : 'none';
    $extra = $packTypes[$packType];
    if (!empty($addons['fragile'])) {
        $extra += $c['surcharge_fragile'];
    }
    if (!empty($addons['inventory'])) {
        $extra += $c['surcharge_inventory'];
    }
    if (!empty($addons['sms'])) {
        $extra += $c['surcharge_sms'];
    }

    $insure = 0;
    $declared = (float) ($addons['insure_value'] ?? 0);
    if ($declared > 0) {
        $insure = max($declared * $c['insurance_rate'], $c['insurance_min']);
    }

    $total = $base + $extra + $insure;

    return [
        'total' => round($total, 2),
        'base' => round($base, 2),
        'extra' => round($extra, 2),
        'insure' => round($insure, 2),
        'route_km' => $routeKm,
        'origin_zone' => $originZone,
        'dest_zone' => $destZone,
    ];
}
