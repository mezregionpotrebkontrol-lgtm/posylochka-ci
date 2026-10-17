<?php
/**
 * Журнал действий сотрудников: кто, когда и что менял в CRM.
 * Доступ — только администратор.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin']);
$pdo = crm_db();

$tableReady = true;
try {
    $pdo->query('SELECT 1 FROM audit_log LIMIT 1');
} catch (Throwable $e) {
    $tableReady = false;
}

$userFilter = (string) ($_GET['user'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

$rows = [];
$pg = ['page' => 1, 'per' => CRM_PER_PAGE, 'offset' => 0, 'total' => 0, 'pages' => 1, 'param' => 'page'];
$staff = [];
if ($tableReady) {
    $where = [];
    $params = [];
    if ($userFilter !== '' && ctype_digit($userFilter)) {
        $where[] = 'a.user_id = ?';
        $params[] = (int) $userFilter;
    }
    if ($search !== '') {
        $where[] = '(a.action LIKE ? OR a.details LIKE ? OR a.entity_id = ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = ctype_digit($search) ? (int) $search : 0;
    }
    if ($dateFrom !== '') {
        $where[] = 'a.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'a.created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $pg = crm_paginate($pdo, 'SELECT COUNT(*) FROM audit_log a' . $whereSql, $params);
    $stmt = $pdo->prepare('SELECT a.* FROM audit_log a' . $whereSql . ' ORDER BY a.id DESC' . crm_limit_sql($pg));
    $stmt->execute($params);

    if (crm_is_export()) {
        $out = [];
        foreach ($stmt as $r) {
            $out[] = [crm_date($r['created_at'], 'd.m.Y H:i:s'), $r['user_name'], crm_audit_action_label($r['action']), $r['entity_id'], $r['details'], $r['ip']];
        }
        crm_csv_download('journal', ['Когда', 'Сотрудник', 'Действие', '№ объекта', 'Подробности', 'IP'], $out);
    }
    $rows = $stmt->fetchAll();
    $staff = $pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
}

/** Ссылка на объект, с которым работали (заявка, клиент и т.д.). */
function audit_entity_link(array $r): string
{
    if (!$r['entity_id']) {
        return '';
    }
    $map = [
        'order' => '/crm/order.php?id=', 'orders' => '/crm/order.php?id=',
        'client' => '/crm/client.php?id=', 'clients' => '/crm/client.php?id=',
        'claim' => '/crm/claim.php?id=', 'invoice' => '/crm/invoice.php?id=',
    ];
    $id = (int) $r['entity_id'];
    return isset($map[$r['entity']])
        ? '<a href="' . $map[$r['entity']] . $id . '">№' . $id . '</a>'
        : '№' . $id;
}

$pageTitle = 'Журнал действий';
$activeNav = 'audit';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if (!$tableReady): ?>
<div class="card">
  <div class="empty-state">
    Журнал ещё не подключён к базе. Импортируйте файл
    <code>install/migration-013-audit-promo-consent.sql</code> через phpMyAdmin и обновите страницу.
  </div>
</div>
<?php else: ?>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>Сотрудник</label>
      <select name="user">
        <option value="">Все</option>
        <?php foreach ($staff as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $userFilter === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Поиск</label>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="№ заявки, слово из изменений">
    </div>
    <div class="field"><label>С</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div class="field"><label>По</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <button class="btn secondary" type="submit">Показать</button>
    <a class="btn secondary" href="/crm/audit.php">Сбросить</a>
    <span style="margin-left:auto;"><?= crm_export_button() ?></span>
  </form>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty-state">Записей нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Когда</th><th>Сотрудник</th><th>Действие</th><th>Объект</th><th>Подробности</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr<?= $r['action'] === 'login_failed' ? ' style="background:rgba(220,60,60,.07);"' : '' ?>>
        <td style="white-space:nowrap;"><?= crm_date($r['created_at'], 'd.m.Y H:i') ?></td>
        <td><?= $r['user_name'] ? e($r['user_name']) : '<span class="text-muted">—</span>' ?></td>
        <td><?= e(crm_audit_action_label($r['action'])) ?></td>
        <td><?= audit_entity_link($r) ?></td>
        <td style="max-width:420px;font-size:.82rem;word-break:break-word;"><?= e($r['details']) ?></td>
        <td class="text-muted" style="font-size:.78rem;"><?= e($r['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= crm_pagination_html($pg) ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
