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

$sql = 'SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id = i.client_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

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
      <button class="btn secondary" type="submit">Применить</button>
    </form>
    <a class="btn" href="/crm/invoice.php">+ Новый счёт</a>
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
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
