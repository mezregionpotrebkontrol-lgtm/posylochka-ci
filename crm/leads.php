<?php
/**
 * Заявки с лендинга (доставка без оплаты + B2B), которые пришли через
 * submit_order_request.php / submit_business_request.php. Это просто лог
 * обращений с сайта — на отличие от "Заявок" (orders), здесь нет статуса
 * и трекинга, это сырые лиды для обработки менеджером (их дублирует MAX-
 * уведомление владельцу в момент поступления).
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

// Конвертация лида с сайта в полноценную заявку (orders). Создаёт (или находит
// по телефону) клиента и заявку со статусом "new", отмечает лид как обработанный.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'convert_order_request') {
    crm_csrf_check();
    $leadId = (int) ($_POST['lead_id'] ?? 0);
    $lStmt = $pdo->prepare('SELECT * FROM site_order_requests WHERE id = ?');
    $lStmt->execute([$leadId]);
    $lead = $lStmt->fetch();
    if (!$lead) {
        crm_flash_set('Заявка с сайта не найдена.', 'err');
    } elseif ($lead['converted_order_id']) {
        crm_flash_set('Эта заявка уже была перенесена в CRM (заявка №' . (int) $lead['converted_order_id'] . ').', 'err');
    } else {
        $phoneDigits = crm_phone_digits($lead['phone']);
        $clientId = null;
        if ($phoneDigits) {
            $cStmt = $pdo->prepare("SELECT id FROM clients WHERE REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','') LIKE ?");
            $cStmt->execute(['%' . substr($phoneDigits, -10)]);
            $existing = $cStmt->fetch();
            if ($existing) {
                $clientId = (int) $existing['id'];
            }
        }
        if (!$clientId) {
            $pdo->prepare('INSERT INTO clients (name, phone, created_by) VALUES (?,?,?)')
                ->execute([$lead['name'], $lead['phone'], $user['id']]);
            $clientId = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('INSERT INTO orders
            (client_id, created_by, from_city, to_city, weight_kg, comment, status)
            VALUES (?,?,?,?,?,?,\'new\')')
            ->execute([
                $clientId,
                $user['id'],
                $lead['city'] ?: 'Дербент',
                $lead['dest_city'] ?: 'Санкт-Петербург',
                $lead['weight'] !== null && $lead['weight'] !== '' && is_numeric($lead['weight']) ? (float) $lead['weight'] : null,
                trim(($lead['comment'] ? $lead['comment'] . "\n\n" : '') . 'Источник: заявка с сайта №' . $leadId),
            ]);
        $newOrderId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, comment) VALUES (?, \'new\', ?, \'Создана автоматически из заявки с сайта\')')
            ->execute([$newOrderId, $user['id']]);
        $pdo->prepare('UPDATE site_order_requests SET converted_order_id = ? WHERE id = ?')->execute([$newOrderId, $leadId]);
        crm_flash_set('Заявка №' . $newOrderId . ' создана на основе обращения с сайта.');
    }
    crm_redirect('/crm/leads.php');
}

$orderRequests = $pdo->query('SELECT * FROM site_order_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();
$businessRequests = $pdo->query('SELECT * FROM business_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();

$pageTitle = 'Заявки с сайта';
$activeNav = 'leads';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Заявки на доставку с лендинга (<?= count($orderRequests) ?>)</h3>
  <?php if (!$orderRequests): ?>
    <div class="empty-state">Пока нет заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Когда</th><th>Имя</th><th>Телефон</th><th>Откуда</th><th>Куда</th><th>Вес</th><th>Комментарий</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orderRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['city'] ?? '—') ?></td>
          <td><?= e($r['dest_city'] ?? '—') ?></td>
          <td><?= e($r['weight'] ?? '—') ?></td>
          <td><?= e($r['comment'] ?? '') ?></td>
          <td>
            <?php if (!empty($r['converted_order_id'])): ?>
              <a class="btn small secondary" href="/crm/order.php?id=<?= (int) $r['converted_order_id'] ?>">Заявка №<?= (int) $r['converted_order_id'] ?></a>
            <?php else: ?>
              <form method="post" class="inline">
                <?= crm_csrf_field() ?>
                <input type="hidden" name="action" value="convert_order_request">
                <input type="hidden" name="lead_id" value="<?= (int) $r['id'] ?>">
                <button class="btn small" type="submit">Создать заявку в CRM</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Заявки на сотрудничество (B2B) (<?= count($businessRequests) ?>)</h3>
  <?php if (!$businessRequests): ?>
    <div class="empty-state">Пока нет заявок.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Когда</th><th>Организация</th><th>Контакт</th><th>Телефон</th><th>Email</th><th>Объём</th><th>Города</th></tr></thead>
      <tbody>
        <?php foreach ($businessRequests as $r): ?>
        <tr>
          <td><?= crm_date($r['created_at']) ?></td>
          <td><?= e($r['company']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['email'] ?? '—') ?></td>
          <td><?= e($r['volume'] ?? '—') ?></td>
          <td><?= e($r['cities'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
