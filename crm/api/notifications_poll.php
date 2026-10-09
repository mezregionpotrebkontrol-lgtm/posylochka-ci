<?php
/**
 * Опрос новых событий для всплывающих оповещений в CRM (без перезагрузки
 * страницы): новые заявки и смены статуса. Клиентский JS (layout_bottom.php)
 * дёргает этот файл раз в несколько секунд с параметром since=YYYY-MM-DD HH:MM:SS
 * и получает список событий, случившихся после этого момента.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator', 'courier']);
$pdo = crm_db();

header('Content-Type: application/json; charset=utf-8');

$since = $_GET['since'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    // Без корректного since отдаём пусто, чтобы не обваливать историей старых событий.
    echo json_encode(['server_time' => date('Y-m-d H:i:s'), 'events' => []]);
    exit;
}

$isCourier = $user['role'] === 'courier';
$events = [];

// Новые заявки (курьеру не показываем — ему заявки назначают, а не он их заводит)
if (!$isCourier) {
    $stmt = $pdo->prepare("SELECT o.id, o.from_city, o.to_city, o.created_at, o.created_by, c.name AS client_name
        FROM orders o
        LEFT JOIN clients c ON c.id = o.client_id
        WHERE o.created_at > ?
        ORDER BY o.created_at ASC
        LIMIT 50");
    $stmt->execute([$since]);
    foreach ($stmt->fetchAll() as $o) {
        // Не показываем сотруднику всплывашку про заявку, которую он только что сам создал в CRM.
        if ($o['created_by'] !== null && (int) $o['created_by'] === (int) $user['id']) {
            continue;
        }
        $events[] = [
            'type'     => 'new_order',
            'order_id' => (int) $o['id'],
            'text'     => 'Новая заявка №' . $o['id'] . ($o['client_name'] ? ' — ' . $o['client_name'] : '') . ' (' . $o['from_city'] . ' → ' . $o['to_city'] . ')',
            'time'     => $o['created_at'],
        ];
    }
}

// Смена статуса
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
    // Не показываем сотруднику всплывашку про статус, который он только что сам поставил.
    if ($h['changed_by'] !== null && (int) $h['changed_by'] === (int) $user['id']) {
        continue;
    }
    $events[] = [
        'type'     => 'status_change',
        'order_id' => (int) $h['order_id'],
        'text'     => 'Заявка №' . $h['order_id'] . ': статус «' . crm_order_status_label($h['status']) . '» (' . $h['from_city'] . ' → ' . $h['to_city'] . ')',
        'time'     => $h['changed_at'],
    ];
}

usort($events, static function ($a, $b) {
    return strcmp($a['time'], $b['time']);
});

echo json_encode(['server_time' => date('Y-m-d H:i:s'), 'events' => array_values($events)]);
