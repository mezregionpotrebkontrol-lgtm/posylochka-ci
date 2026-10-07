<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT DATE(paid_at) AS day, SUM(amount) AS total
    FROM invoices
    WHERE status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?
    GROUP BY DATE(paid_at) ORDER BY day");
$stmt->execute([$from, $to]);
$byDay = $stmt->fetchAll();

$totalStmt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
    FROM invoices WHERE status = 'paid' AND DATE(paid_at) BETWEEN ? AND ?");
$totalStmt->execute([$from, $to]);
$totals = $totalStmt->fetch();

$ordersStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$ordersStmt->execute([$from, $to]);
$ordersByStatus = $ordersStmt->fetchAll();

$pageTitle = 'Отчёты';
$activeNav = 'reports';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form method="get" class="filters">
    <div class="field"><label>С</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label>По</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn secondary" type="submit">Показать</button>
  </form>
</div>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= crm_money((float)$totals['total']) ?></div><div class="label">Выручка за период (оплачено)</div></div>
  <div class="stat"><div class="num"><?= (int)$totals['cnt'] ?></div><div class="label">Оплаченных счетов</div></div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Заявки по статусам за период</h3>
  <?php if (!$ordersByStatus): ?>
    <div class="empty-state">Нет заявок за выбранный период.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Статус</th><th>Количество</th></tr></thead>
    <tbody>
      <?php foreach ($ordersByStatus as $row): ?>
      <tr>
        <td><span class="badge <?= crm_order_status_class($row['status']) ?>"><?= e(crm_order_status_label($row['status'])) ?></span></td>
        <td><?= (int)$row['cnt'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Выручка по дням</h3>
  <?php if (!$byDay): ?>
    <div class="empty-state">Оплат за выбранный период нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Дата</th><th>Сумма</th></tr></thead>
    <tbody>
      <?php foreach ($byDay as $row): ?>
      <tr><td><?= crm_date($row['day'], 'd.m.Y') ?></td><td><?= crm_money((float)$row['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
