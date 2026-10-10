<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$granularityParam = $_GET['granularity'] ?? 'day';
$granularity = in_array($granularityParam, ['day', 'week', 'month'], true) ? $granularityParam : 'day';

/**
 * Заявки за период вместе с суммой уже оплаченных платежей по рассрочке
 * (нужно, чтобы для заявок в рассрочке считать реально собранные деньги,
 * а не всю стоимость заявки целиком).
 */
$stmt = $pdo->prepare('SELECT o.*, c.name AS client_name,
        COALESCE(inst.paid_sum, 0) AS installment_paid
    FROM orders o
    JOIN clients c ON c.id = o.client_id
    LEFT JOIN (
        SELECT order_id, SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) AS paid_sum
        FROM order_installments GROUP BY order_id
    ) inst ON inst.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    ORDER BY o.created_at');
$stmt->execute([$from, $to]);
$periodOrders = $stmt->fetchAll();

/**
 * Для одной заявки считает, сколько реально собрано денег и сколько ещё
 * должны (долг). Правила:
 *  - payment_status = 'paid' → собрано = вся стоимость заявки, долг = 0
 *    (для рассрочки "paid" выставляется автоматически, когда оплачены
 *    все платежи графика — к этому моменту собранное = стоимости).
 *  - payment_method = 'installment' (ещё не всё оплачено) → собрано = сумма
 *    оплаченных платежей графика, долг = стоимость заявки минус собранное.
 *  - иначе (unpaid / postpaid, не рассрочка) → собрано = 0, долг = вся стоимость.
 */
function crm_report_order_money(array $o): array
{
    $price = $o['price'] !== null ? (float) $o['price'] : 0.0;
    if ($o['payment_status'] === 'paid') {
        return ['collected' => $price, 'debt' => 0.0];
    }
    if ($o['payment_method'] === 'installment') {
        $paid = (float) $o['installment_paid'];
        $collected = min($paid, $price > 0 ? $price : $paid);
        $debt = max($price - $paid, 0.0);
        return ['collected' => $collected, 'debt' => $debt];
    }
    return ['collected' => 0.0, 'debt' => $price];
}

$totalCollected = 0.0;
$totalDebtInPeriod = 0.0;
$byMethod = []; // method => ['collected'=>, 'cnt'=>]
$byStatus = []; // payment_status => ['collected'=>,'debt'=>,'cnt'=>]
$byBucket = []; // период (день/неделя/месяц) => собрано
$byRoute = [];  // "from → to" => собрано
$byClient = []; // client_name => собрано

foreach ($periodOrders as $o) {
    $money = crm_report_order_money($o);
    $totalCollected += $money['collected'];
    $totalDebtInPeriod += $money['debt'];

    $method = $o['payment_method'] ?: '—';
    if (!isset($byMethod[$method])) {
        $byMethod[$method] = ['collected' => 0.0, 'cnt' => 0];
    }
    $byMethod[$method]['collected'] += $money['collected'];
    $byMethod[$method]['cnt']++;

    $status = $o['payment_status'];
    if (!isset($byStatus[$status])) {
        $byStatus[$status] = ['collected' => 0.0, 'debt' => 0.0, 'cnt' => 0];
    }
    $byStatus[$status]['collected'] += $money['collected'];
    $byStatus[$status]['debt'] += $money['debt'];
    $byStatus[$status]['cnt']++;

    $ts = strtotime($o['created_at']);
    if ($granularity === 'day') {
        $bucketKey = date('Y-m-d', $ts);
        $bucketLabel = date('d.m.Y', $ts);
    } elseif ($granularity === 'week') {
        $weekStart = strtotime('monday this week', $ts);
        $bucketKey = date('Y-m-d', $weekStart);
        $bucketLabel = 'с ' . date('d.m.Y', $weekStart);
    } else {
        $bucketKey = date('Y-m', $ts);
        $bucketLabel = date('m.Y', $ts);
    }
    if (!isset($byBucket[$bucketKey])) {
        $byBucket[$bucketKey] = ['label' => $bucketLabel, 'collected' => 0.0, 'cnt' => 0];
    }
    $byBucket[$bucketKey]['collected'] += $money['collected'];
    $byBucket[$bucketKey]['cnt']++;

    $routeKey = $o['from_city'] . ' → ' . $o['to_city'];
    if (!isset($byRoute[$routeKey])) {
        $byRoute[$routeKey] = ['collected' => 0.0, 'cnt' => 0];
    }
    $byRoute[$routeKey]['collected'] += $money['collected'];
    $byRoute[$routeKey]['cnt']++;

    if (!isset($byClient[$o['client_name']])) {
        $byClient[$o['client_name']] = ['collected' => 0.0, 'cnt' => 0];
    }
    $byClient[$o['client_name']]['collected'] += $money['collected'];
    $byClient[$o['client_name']]['cnt']++;
}

ksort($byBucket);
arsort($byRoute);
arsort($byClient);
$byRoute = array_slice($byRoute, 0, 10, true);
$byClient = array_slice($byClient, 0, 10, true);

/**
 * Текущие долги — по ВСЕМ заявкам (не только за выбранный период), потому
 * что должок за прошлый месяц остаётся долгом и сегодня.
 */
$debtStmt = $pdo->query('SELECT o.*, c.name AS client_name,
        COALESCE(inst.paid_sum, 0) AS installment_paid
    FROM orders o
    JOIN clients c ON c.id = o.client_id
    LEFT JOIN (
        SELECT order_id, SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) AS paid_sum
        FROM order_installments GROUP BY order_id
    ) inst ON inst.order_id = o.id
    WHERE o.payment_status != "paid" AND o.status != "cancelled"
    ORDER BY o.created_at');
$allUnpaidOrders = $debtStmt->fetchAll();
$debts = [];
$totalDebtNow = 0.0;
foreach ($allUnpaidOrders as $o) {
    $money = crm_report_order_money($o);
    if ($money['debt'] <= 0) {
        continue;
    }
    $debts[] = $o + ['debt' => $money['debt']];
    $totalDebtNow += $money['debt'];
}
usort($debts, function ($a, $b) { return $b['debt'] <=> $a['debt']; });

// ---------- экспорт в CSV (открывается в Excel) ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="otchet_' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM, чтобы Excel правильно показал кириллицу
    $out = fopen('php://output', 'w');
    fputcsv($out, ['№ заявки', 'Дата', 'Клиент', 'Маршрут', 'Статус доставки', 'Статус оплаты', 'Способ оплаты', 'Стоимость', 'Собрано', 'Долг'], ';');
    foreach ($periodOrders as $o) {
        $money = crm_report_order_money($o);
        fputcsv($out, [
            $o['id'],
            date('d.m.Y', strtotime($o['created_at'])),
            $o['client_name'],
            $o['from_city'] . ' → ' . $o['to_city'],
            crm_order_status_label($o['status']),
            crm_payment_status_label($o['payment_status']),
            crm_payment_method_label($o['payment_method']),
            $o['price'] !== null ? (float) $o['price'] : 0,
            $money['collected'],
            $money['debt'],
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Отчёты';
$activeNav = 'reports';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form method="get" class="filters">
    <div class="field"><label>С</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label>По</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="field">
      <label>Группировать по</label>
      <select name="granularity">
        <option value="day" <?= $granularity === 'day' ? 'selected' : '' ?>>дням</option>
        <option value="week" <?= $granularity === 'week' ? 'selected' : '' ?>>неделям</option>
        <option value="month" <?= $granularity === 'month' ? 'selected' : '' ?>>месяцам</option>
      </select>
    </div>
    <button class="btn secondary" type="submit">Показать</button>
    <a class="btn" href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&granularity=<?= urlencode($granularity) ?>&export=csv">Экспорт в CSV (Excel)</a>
  </form>
</div>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= crm_money($totalCollected) ?></div><div class="label">Собрано за период</div></div>
  <div class="stat"><div class="num"><?= crm_money($totalDebtInPeriod) ?></div><div class="label">Долг по заявкам за период</div></div>
  <div class="stat"><div class="num"><?= crm_money($totalDebtNow) ?></div><div class="label">Все текущие долги (на сегодня)</div></div>
  <div class="stat"><div class="num"><?= count($periodOrders) ?></div><div class="label">Заявок за период</div></div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Выручка по способу и статусу оплаты</h3>
  <div class="form-row">
    <div style="flex:1;">
      <table>
        <thead><tr><th>Способ оплаты</th><th>Собрано</th><th>Заявок</th></tr></thead>
        <tbody>
          <?php foreach ($byMethod as $method => $row): ?>
          <tr>
            <td><?= $method === '—' ? '—' : e(crm_payment_method_label($method)) ?></td>
            <td><?= crm_money($row['collected']) ?></td>
            <td><?= (int) $row['cnt'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="flex:1;">
      <table>
        <thead><tr><th>Статус оплаты</th><th>Собрано</th><th>Долг</th><th>Заявок</th></tr></thead>
        <tbody>
          <?php foreach ($byStatus as $status => $row): ?>
          <tr>
            <td><span class="badge <?= crm_payment_status_class($status) ?>"><?= e(crm_payment_status_label($status)) ?></span></td>
            <td><?= crm_money($row['collected']) ?></td>
            <td><?= crm_money($row['debt']) ?></td>
            <td><?= (int) $row['cnt'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Выручка по периодам</h3>
  <?php if (!$byBucket): ?>
    <div class="empty-state">Заявок за выбранный период нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Период</th><th>Собрано</th><th>Заявок</th></tr></thead>
    <tbody>
      <?php foreach ($byBucket as $row): ?>
      <tr><td><?= e($row['label']) ?></td><td><?= crm_money($row['collected']) ?></td><td><?= (int) $row['cnt'] ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="form-row">
    <div style="flex:1;">
      <h3 style="margin-top:0;">Топ-10 маршрутов по выручке</h3>
      <?php if (!$byRoute): ?>
        <div class="empty-state">Нет данных за период.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Маршрут</th><th>Собрано</th><th>Заявок</th></tr></thead>
        <tbody>
          <?php foreach ($byRoute as $route => $row): ?>
          <tr><td><?= e($route) ?></td><td><?= crm_money($row['collected']) ?></td><td><?= (int) $row['cnt'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div style="flex:1;">
      <h3 style="margin-top:0;">Топ-10 клиентов по выручке</h3>
      <?php if (!$byClient): ?>
        <div class="empty-state">Нет данных за период.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Клиент</th><th>Собрано</th><th>Заявок</th></tr></thead>
        <tbody>
          <?php foreach ($byClient as $clientName => $row): ?>
          <tr><td><?= e($clientName) ?></td><td><?= crm_money($row['collected']) ?></td><td><?= (int) $row['cnt'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Текущие долги (на сегодня, по всем заявкам)</h3>
  <p class="text-muted">Неоплаченные заявки, постоплата, которую ещё не собрали, и остаток по рассрочкам — отменённые заявки не учитываются.</p>
  <?php if (!$debts): ?>
    <div class="empty-state">Долгов нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Клиент</th><th>Маршрут</th><th>Статус оплаты</th><th>Создана</th><th>Долг</th></tr></thead>
    <tbody>
      <?php foreach ($debts as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?></a></td>
        <td><a href="/crm/client.php?id=<?= (int) $o['client_id'] ?>"><?= e($o['client_name']) ?></a></td>
        <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_payment_status_class($o['payment_status']) ?>"><?= e(crm_payment_status_label($o['payment_status'])) ?><?= $o['payment_method'] === 'installment' ? ' (рассрочка)' : '' ?></span></td>
        <td><?= crm_date($o['created_at'], 'd.m.Y') ?></td>
        <td><strong><?= crm_money($o['debt']) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
