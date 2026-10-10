<?php
/**
 * &#x41b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x43a;&#x430;&#x431;&#x438;&#x43d;&#x435;&#x442; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435;: &#x441;&#x43f;&#x438;&#x441;&#x43e;&#x43a; &#x432;&#x441;&#x435;&#x445; &#x435;&#x433;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43e;&#x43a; (&#x434;&#x43b;&#x44f; &#x432;&#x43a;&#x43b;&#x430;&#x434;&#x43a;&#x438;
 * &#xab;&#x41c;&#x43e;&#x438; &#x437;&#x430;&#x43a;&#x430;&#x437;&#x44b;&#xbb;), &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43d;&#x435; &#x434;&#x435;&#x440;&#x436;&#x430;&#x442;&#x44c; &#x438;&#x441;&#x442;&#x43e;&#x440;&#x438;&#x44e; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x432; localStorage &#x431;&#x440;&#x430;&#x443;&#x437;&#x435;&#x440;&#x430;.
 * &#x422;&#x440;&#x435;&#x431;&#x443;&#x435;&#x442; &#x430;&#x432;&#x442;&#x43e;&#x440;&#x438;&#x437;&#x430;&#x446;&#x438;&#x438; &#x43a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x430; (capi-&#x441;&#x435;&#x441;&#x441;&#x438;&#x44f;), &#x43a;&#x430;&#x43a; &#x438; &#x43e;&#x441;&#x442;&#x430;&#x43b;&#x44c;&#x43d;&#x43e;&#x439; &#x43b;&#x438;&#x447;&#x43d;&#x44b;&#x439; &#x43a;&#x430;&#x431;&#x438;&#x43d;&#x435;&#x442;.
 */
require_once __DIR__ . '/_bootstrap.php';

$client = capi_require_client($pdo);

$stmt = $pdo->prepare('SELECT id, track_code, from_city, to_city, from_address, to_address, cargo_description,
        weight_kg, status, payment_status, is_cod, cod_amount, is_insured, insured_amount, price, created_at
    FROM orders WHERE client_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([(int) $client['id']]);
$rows = $stmt->fetchAll();

$orders = array_map(function (array $o): array {
    return [
        'id'              => (int) $o['id'],
        'track'           => $o['track_code'],
        'fromCity'        => $o['from_city'],
        'toCity'          => $o['to_city'],
        'fromAddress'     => $o['from_address'],
        'toAddress'       => $o['to_address'],
        'cargo'           => $o['cargo_description'],
        'weightKg'        => $o['weight_kg'] !== null ? (float) $o['weight_kg'] : null,
        'status'          => $o['status'],
        'statusLabel'     => crm_order_status_label($o['status']),
        'paymentStatus'   => $o['payment_status'],
        'paymentStatusLabel' => crm_payment_status_label($o['payment_status']),
        'isCod'           => (bool) $o['is_cod'],
        'codAmount'       => $o['cod_amount'] !== null ? (float) $o['cod_amount'] : null,
        'isInsured'       => (bool) $o['is_insured'],
        'insuredAmount'   => $o['insured_amount'] !== null ? (float) $o['insured_amount'] : null,
        'price'           => $o['price'] !== null ? (float) $o['price'] : null,
        'createdAt'       => $o['created_at'],
    ];
}, $rows);

capi_respond(['ok' => true, 'orders' => $orders]);
