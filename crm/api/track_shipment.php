<?php
/**
 * Публичный трекинг по номеру (track_code), который выдаётся клиенту при
 * создании заявки. GET /api/track_shipment.php?code=PSLXXXXXX
 * Отвечает тем же форматом, что ждёт сайт: {step_index, dates:[5 строк/null]}.
 */
require_once __DIR__ . '/_bootstrap.php';

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    capi_error('Укажите номер отправления.', 404);
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE track_code = ? LIMIT 1');
$stmt->execute([$code]);
$order = $stmt->fetch();
if (!$order) {
    capi_error('Отправление с таким номером не найдено.', 404);
}

$stmt = $pdo->prepare('SELECT status, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC');
$stmt->execute([(int) $order['id']]);
$history = $stmt->fetchAll();

// Шаги показа на сайте, в порядке: принято → загружено → в пути → прибыло → вручено.
// У CRM только 4 рабочих статуса (new/accepted/in_transit/delivered), поэтому
// "загружено"/"прибыло" не имеют отдельной даты — остаются пустыми до вручения.
$statusToStep = ['new' => 0, 'accepted' => 1, 'in_transit' => 2, 'delivered' => 4, 'cancelled' => null];
$dates = [null, null, null, null, null];
$stepIndex = 0;
foreach ($history as $row) {
    $step = $statusToStep[$row['status']] ?? null;
    if ($step === null) {
        continue;
    }
    $dates[$step] = $row['changed_at'];
    $stepIndex = max($stepIndex, $step);
}

capi_respond(['ok' => true, 'step_index' => $stepIndex, 'dates' => $dates]);
