<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$statusFilter = $_GET['status'] ?? '';
$reasonFilter = $_GET['reason'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['open', 'in_progress', 'resolved', 'rejected'], true)) {
    $where[] = 'cl.status = ?';
    $params[] = $statusFilter;
}
if ($reasonFilter !== '' && in_array($reasonFilter, ['defect', 'loss', 'return', 'other'], true)) {
    $where[] = 'cl.reason = ?';
    $params[] = $reasonFilter;
}
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR cl.order_id = ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = ctype_digit($search) ? (int) $search : 0;
}

$sql = 'SELECT cl.*, o.from_city, o.to_city, c.name AS client_name, c.id AS client_id
        FROM claims cl
        JOIN orders o ON o.id = cl.order_id
        JOIN clients c ON c.id = o.client_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY cl.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

$totals = $pdo->query("SELECT
    COUNT(CASE WHEN status IN ('open','in_progress') THEN 1 END) AS open_cnt,
    COALESCE(SUM(CASE WHEN status IN ('open','in_progress') THEN compensation_amount ELSE 0 END), 0) AS open_compensation
    FROM claims")->fetch();

$pageTitle = 'Претензии и возвраты';
$activeNav = 'claims';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="grid-stats">
  <div class="stat"><div class="num"><?= (int) $totals['open_cnt'] ?></div><div class="label">Открытых претензий / в работе</div></div>
  <div class="stat"><div class="num"><?= crm_money((float) $totals['open_compensation']) ?></div><div class="label">Компенсация по открытым (заявлено)</div></div>
</div>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>Поиск</label>
      <input type="text" name="q" placeholder="Клиент, телефон, № заявки" value="<?= e($search) ?>" style="min-width:220px;">
    </div>
    <div class="field">
      <label>Статус</label>
      <select name="status">
        <option value="">Все</option>
        <?php foreach (['open', 'in_progress', 'resolved', 'rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(crm_claim_status_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Причина</label>
      <select name="reason">
        <option value="">Все</option>
        <?php foreach (['defect', 'loss', 'return', 'other'] as $r): ?>
          <option value="<?= $r ?>" <?= $reasonFilter === $r ? 'selected' : '' ?>><?= e(crm_claim_reason_label($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn secondary" type="submit">Применить</button>
    <?php if ($search !== '' || $statusFilter !== '' || $reasonFilter !== ''): ?><a class="btn secondary" href="/crm/claims.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <?php if (!$claims): ?>
    <div class="empty-state">Претензий пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Заявка</th><th>Клиент</th><th>Маршрут</th><th>Причина</th><th>Статус</th><th>Компенсация</th><th>Срок ответа</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($claims as $cl): ?>
      <tr>
        <td><a href="/crm/claim.php?id=<?= (int) $cl['id'] ?>">№<?= (int) $cl['id'] ?></a></td>
        <td><a href="/crm/order.php?id=<?= (int) $cl['order_id'] ?>">#<?= (int) $cl['order_id'] ?></a></td>
        <td><a href="/crm/client.php?id=<?= (int) $cl['client_id'] ?>"><?= e($cl['client_name']) ?></a></td>
        <td><?= e($cl['from_city']) ?> → <?= e($cl['to_city']) ?></td>
        <td><?= e(crm_claim_reason_label($cl['reason'])) ?></td>
        <td><span class="badge <?= crm_claim_status_class($cl['status']) ?>"><?= e(crm_claim_status_label($cl['status'])) ?></span></td>
        <td><?= crm_money($cl['compensation_amount'] !== null ? (float) $cl['compensation_amount'] : null) ?></td>
        <td><?= $cl['response_due_date'] ? crm_date($cl['response_due_date'], 'd.m.Y') : '—' ?></td>
        <td><?= crm_date($cl['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
