<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['draft','sent','paid','cancelled'], true)) {
    $where[] = 'i.status = ?';
    $params[] = $statusFilter;
}
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
if ($dateFrom !== '') {
    $where[] = 'i.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'i.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$fromSql = ' FROM invoices i JOIN clients c ON c.id = i.client_id';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$pg = crm_paginate($pdo, 'SELECT COUNT(*)' . $fromSql . $whereSql, $params);
$sql = 'SELECT i.*, c.name AS client_name, c.inn AS client_inn' . $fromSql . $whereSql . ' ORDER BY i.created_at DESC' . crm_limit_sql($pg);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

if (crm_is_export()) {
    $rows = [];
    foreach ($invoices as $i) {
        $rows[] = [
            $i['number'], crm_date($i['created_at'], 'd.m.Y'), $i['client_name'], $i['client_inn'],
            $i['order_id'], crm_csv_num($i['amount']), crm_invoice_status_label($i['status']),
            $i['paid_at'] ? crm_date($i['paid_at'], 'd.m.Y') : '',
        ];
    }
    crm_csv_download('scheta', ['№ счёта', 'Дата', 'Клиент', 'ИНН', '№ заявки', 'Сумма, ₽', 'Статус', 'Оплачен'], $rows);
}

$totals = $pdo->query("SELECT
    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid_total,
    SUM(CASE WHEN status IN ('sent','draft') THEN amount ELSE 0 END) AS pending_total
    FROM invoices")->fetch();

$pageTitle = 'Финансы';
$activeNav = 'finance';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= crm_money((float)($totals['paid_total'] ?? 0)) ?></div><div class="label">Оплачено всего</div></div>
  <div class="stat"><div class="num"><?= crm_money((float)($totals['pending_total'] ?? 0)) ?></div><div class="label">Ожидает оплаты</div></div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <form method="get" class="filters mb-0">
      <div class="field">
        <label>Статус</label>
        <select name="status">
          <option value="">Все</option>
          <?php foreach (['draft','sent','paid','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_invoice_status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>С</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
      <div class="field"><label>По</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
      <button class="btn secondary" type="submit">Применить</button>
    </form>
    <span style="display:flex;gap:8px;"><?= crm_export_button() ?>
    <a class="btn" href="/crm/invoice.php">+ Новый счёт</a></span>
  </div>
</div>

<div class="card">
  <?php if (!$invoices): ?>
    <div class="empty-state">Счетов пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№ счёта</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th>Создан</th><th>Оплачен</th></tr></thead>
    <tbody>
      <?php foreach ($invoices as $i): ?>
      <tr>
        <td><a href="/crm/invoice.php?id=<?= (int)$i['id'] ?>"><?= e($i['number']) ?></a></td>
        <td><a href="/crm/client.php?id=<?= (int)$i['client_id'] ?>"><?= e($i['client_name']) ?></a></td>
        <td><?= crm_money((float)$i['amount']) ?></td>
        <td>
          <?php
            $cls = ['draft'=>'badge-grey','sent'=>'badge-blue','paid'=>'badge-green','cancelled'=>'badge-red'][$i['status']] ?? 'badge-grey';
          ?>
          <span class="badge <?= $cls ?>"><?= e(crm_invoice_status_label($i['status'])) ?></span>
        </td>
        <td><?= crm_date($i['created_at'], 'd.m.Y') ?></td>
        <td><?= $i['paid_at'] ? crm_date($i['paid_at'], 'd.m.Y') : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= crm_pagination_html($pg) ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
