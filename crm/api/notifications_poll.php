<?php
/**
 * &#x41e;&#x43f;&#x440;&#x43e;&#x441; &#x43d;&#x43e;&#x432;&#x44b;&#x445; &#x441;&#x43e;&#x431;&#x44b;&#x442;&#x438;&#x439; &#x434;&#x43b;&#x44f; &#x432;&#x441;&#x43f;&#x43b;&#x44b;&#x432;&#x430;&#x44e;&#x449;&#x438;&#x445; &#x43e;&#x43f;&#x43e;&#x432;&#x435;&#x449;&#x435;&#x43d;&#x438;&#x439; &#x432; CRM (&#x431;&#x435;&#x437; &#x43f;&#x435;&#x440;&#x435;&#x437;&#x430;&#x433;&#x440;&#x443;&#x437;&#x43a;&#x438;
 * &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x44b;): &#x43d;&#x43e;&#x432;&#x44b;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x438; &#x441;&#x43c;&#x435;&#x43d;&#x44b; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430;. &#x41a;&#x43b;&#x438;&#x435;&#x43d;&#x442;&#x441;&#x43a;&#x438;&#x439; JS (layout_bottom.php)
 * &#x434;&#x451;&#x440;&#x433;&#x430;&#x435;&#x442; &#x44d;&#x442;&#x43e;&#x442; &#x444;&#x430;&#x439;&#x43b; &#x440;&#x430;&#x437; &#x432; &#x43d;&#x435;&#x441;&#x43a;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x441;&#x435;&#x43a;&#x443;&#x43d;&#x434; &#x441; &#x43f;&#x430;&#x440;&#x430;&#x43c;&#x435;&#x442;&#x440;&#x43e;&#x43c; since=YYYY-MM-DD HH:MM:SS
 * &#x438; &#x43f;&#x43e;&#x43b;&#x443;&#x447;&#x430;&#x435;&#x442; &#x441;&#x43f;&#x438;&#x441;&#x43e;&#x43a; &#x441;&#x43e;&#x431;&#x44b;&#x442;&#x438;&#x439;, &#x441;&#x43b;&#x443;&#x447;&#x438;&#x432;&#x448;&#x438;&#x445;&#x441;&#x44f; &#x43f;&#x43e;&#x441;&#x43b;&#x435; &#x44d;&#x442;&#x43e;&#x433;&#x43e; &#x43c;&#x43e;&#x43c;&#x435;&#x43d;&#x442;&#x430;.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator', 'courier']);
$pdo = crm_db();

header('Content-Type: application/json; charset=utf-8');

$since = $_GET['since'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    // &#x411;&#x435;&#x437; &#x43a;&#x43e;&#x440;&#x440;&#x435;&#x43a;&#x442;&#x43d;&#x43e;&#x433;&#x43e; since &#x43e;&#x442;&#x434;&#x430;&#x451;&#x43c; &#x43f;&#x443;&#x441;&#x442;&#x43e;, &#x447;&#x442;&#x43e;&#x431;&#x44b; &#x43d;&#x435; &#x43e;&#x431;&#x432;&#x430;&#x43b;&#x438;&#x432;&#x430;&#x442;&#x44c; &#x438;&#x441;&#x442;&#x43e;&#x440;&#x438;&#x435;&#x439; &#x441;&#x442;&#x430;&#x440;&#x44b;&#x445; &#x441;&#x43e;&#x431;&#x44b;&#x442;&#x438;&#x439;.
    echo json_encode(['server_time' => date('Y-m-d H:i:s'), 'events' => []]);
    exit;
}

$isCourier = $user['role'] === 'courier';
$events = [];

// &#x41d;&#x43e;&#x432;&#x44b;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (&#x43a;&#x443;&#x440;&#x44c;&#x435;&#x440;&#x443; &#x43d;&#x435; &#x43f;&#x43e;&#x43a;&#x430;&#x437;&#x44b;&#x432;&#x430;&#x435;&#x43c; &#x2014; &#x435;&#x43c;&#x443; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x43d;&#x430;&#x437;&#x43d;&#x430;&#x447;&#x430;&#x44e;&#x442;, &#x430; &#x43d;&#x435; &#x43e;&#x43d; &#x438;&#x445; &#x437;&#x430;&#x432;&#x43e;&#x434;&#x438;&#x442;)
if (!$isCourier) {
    $stmt = $pdo->prepare("SELECT o.id, o.from_city, o.to_city, o.created_at, o.created_by, c.name AS client_name
        FROM orders o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE o.created_at > ?
        ORDER BY o.created_at ASC
        LIMIT 50");
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $o) {
        // &#x41d;&#x435; &#x43f;&#x43e;&#x43a;&#x430;&#x437;&#x44b;&#x432;&#x430;&#x435;&#x43c; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x443; &#x432;&#x441;&#x43f;&#x43b;&#x44b;&#x432;&#x430;&#x448;&#x43a;&#x443; &#x43f;&#x440;&#x43e; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x443;, &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x443;&#x44e; &#x43e;&#x43d; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x447;&#x442;&#x43e; &#x441;&#x430;&#x43c; &#x441;&#x43e;&#x437;&#x434;&#x430;&#x43b; &#x432; CRM.
        if ($o['created_by'] !== null && (int) $o['created_by'] === (int) $user['id']) {
            continue;
        }
        $events[] = [
            'type'     => 'new_order',
            'order_id' => (int) $o['id'],
            'text'     => "\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $o['id'] . ($o['client_name'] ? " \u{2014} " . $o['client_name'] : '') . ' (' . $o['from_city'] . " \u{2192} " . $o['to_city'] . ')',
            'time'     => $o['created_at'],
        ];
    }
}

// &#x41d;&#x43e;&#x432;&#x44b;&#x435; &#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430; (&#x43b;&#x435;&#x43d;&#x434;&#x438;&#x43d;&#x433;, &#x431;&#x435;&#x437; &#x43e;&#x43f;&#x43b;&#x430;&#x442;&#x44b;) -- &#x43e;&#x442;&#x434;&#x435;&#x43b;&#x44c;&#x43d;&#x430;&#x44f; &#x442;&#x430;&#x431;&#x43b;&#x438;&#x446;&#x430; site_order_requests,
// &#x43f;&#x43e;&#x43f;&#x430;&#x434;&#x430;&#x44e;&#x442; &#x43d;&#x430; &#x441;&#x442;&#x440;&#x430;&#x43d;&#x438;&#x446;&#x443; "&#x417;&#x430;&#x44f;&#x432;&#x43a;&#x438; &#x441; &#x441;&#x430;&#x439;&#x442;&#x430;", &#x430; &#x43d;&#x435; &#x432; "&#x417;&#x430;&#x43a;&#x430;&#x437;&#x44b;".
if (!$isCourier) {
    $stmt = $pdo->prepare('SELECT id, name, phone, city, dest_city, created_at
        FROM site_order_requests
        WHERE created_at > ?
        ORDER BY created_at ASC
        LIMIT 50');
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $r) {
        $events[] = [
            'type'     => 'new_lead',
            'order_id' => null,
            'text'     => "\u{41d}\u{43e}\u{432}\u{430}\u{44f} \u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{441} \u{441}\u{430}\u{439}\u{442}\u{430}: " . $r['name'] . ', ' . $r['phone']
                . ($r['city'] || $r['dest_city'] ? ' (' . $r['city'] . " \u{2192} " . $r['dest_city'] . ')' : ''),
            'time'     => $r['created_at'],
        ];
    }

    // &#x41d;&#x43e;&#x432;&#x44b;&#x435; B2B-&#x437;&#x430;&#x44f;&#x432;&#x43a;&#x438; (&#x43c;&#x43e;&#x434;&#x430;&#x43b;&#x43a;&#x430; "&#x421;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x447;&#x435;&#x441;&#x442;&#x432;&#x43e;" &#x43d;&#x430; &#x441;&#x430;&#x439;&#x442;&#x435;)
    $stmt = $pdo->prepare('SELECT id, company, name, phone, created_at
        FROM business_requests
        WHERE created_at > ?
        ORDER BY created_at ASC
        LIMIT 50');
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $r) {
        $events[] = [
            'type'     => 'new_lead',
            'order_id' => null,
            'text'     => "\u{41d}\u{43e}\u{432}\u{430}\u{44f} B2B-\u{437}\u{430}\u{44f}\u{432}\u{43a}\u{430}: " . $r['company'] . ' (' . $r['name'] . ', ' . $r['phone'] . ')',
            'time'     => $r['created_at'],
        ];
    }
}

// &#x421;&#x43c;&#x435;&#x43d;&#x430; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;&#x430;
$sql = "SELECT h.order_id, h.status, h.changed_at, h.changed_by, o.from_city, o.to_city, o.courier_id
    FROM order_status_history h
    JOIN orders o ON o.id = h.order_id
    WHERE h.changed_at > ?";
$params = [$since];
if ($isCourier) {
    $sql .= ' AND o.courier_id = ?';
    $params[] = (int) $user['id'];
}
$sql .= ' ORDER BY h.changed_at ASC LIMIT 50';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $h) {
    // &#x41d;&#x435; &#x43f;&#x43e;&#x43a;&#x430;&#x437;&#x44b;&#x432;&#x430;&#x435;&#x43c; &#x441;&#x43e;&#x442;&#x440;&#x443;&#x434;&#x43d;&#x438;&#x43a;&#x443; &#x432;&#x441;&#x43f;&#x43b;&#x44b;&#x432;&#x430;&#x448;&#x43a;&#x443; &#x43f;&#x440;&#x43e; &#x441;&#x442;&#x430;&#x442;&#x443;&#x441;, &#x43a;&#x43e;&#x442;&#x43e;&#x440;&#x44b;&#x439; &#x43e;&#x43d; &#x442;&#x43e;&#x43b;&#x44c;&#x43a;&#x43e; &#x447;&#x442;&#x43e; &#x441;&#x430;&#x43c; &#x43f;&#x43e;&#x441;&#x442;&#x430;&#x432;&#x438;&#x43b;.
    if ($h['changed_by'] !== null && (int) $h['changed_by'] === (int) $user['id']) {
        continue;
    }
    $events[] = [
        'type'     => 'status_change',
        'order_id' => (int) $h['order_id'],
        'text'     => "\u{417}\u{430}\u{44f}\u{432}\u{43a}\u{430} \u{2116}" . $h['order_id'] . ": \u{441}\u{442}\u{430}\u{442}\u{443}\u{441} \u{ab}" . crm_order_status_label($h['status']) . "\u{bb} (" . $h['from_city'] . " \u{2192} " . $h['to_city'] . ')',
        'time'     => $h['changed_at'],
    ];
}

usort($events, static function ($a, $b) {
    return strcmp($a['time'], $b['time']);
});

echo json_encode(['server_time' => date('Y-m-d H:i:s'), 'events' => array_values($events)]);
