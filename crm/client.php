<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) {
    http_response_code(404);
    die('Клиент не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $clientType = in_array($_POST['client_type'] ?? '', ['individual', 'company'], true) ? $_POST['client_type'] : 'individual';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $inn = trim($_POST['inn'] ?? '');
    $kpp = trim($_POST['kpp'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        crm_flash_set('Укажите имя или название клиента.', 'err');
    } else {
        $stmt = $pdo->prepare('UPDATE clients SET name=?, client_type=?, phone=?, email=?, address=?, inn=?, kpp=?, contact_person=?, notes=? WHERE id=?');
        $stmt->execute([
            $name, $clientType,
            $phone ?: null, $email ?: null, $address ?: null,
            $clientType === 'company' ? ($inn ?: null) : null,
            $clientType === 'company' ? ($kpp ?: null) : null,
            $clientType === 'company' ? ($contactPerson ?: null) : null,
            $notes ?: null, $id,
        ]);
        crm_flash_set('Данные клиента обновлены.');
        crm_redirect('/crm/client.php?id=' . $id);
    }
}

$ordersStmt = $pdo->prepare('SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC');
$ordersStmt->execute([$id]);
$orders = $ordersStmt->fetchAll();

$claimsStmt = $pdo->prepare('SELECT cl.*, o.from_city, o.to_city FROM claims cl JOIN orders o ON o.id = cl.order_id WHERE o.client_id = ? ORDER BY cl.created_at DESC');
$claimsStmt->execute([$id]);
$clientClaims = $claimsStmt->fetchAll();

// Данные личного кабинета клиента на сайте (регистрация): email, паспорт,
// адрес регистрации — заполняются самим клиентом при регистрации/заказе
// с онлайн-оплатой. Таблица отдельная (client_accounts), один-к-одному с clients.
$accStmt = $pdo->prepare('SELECT * FROM client_accounts WHERE client_id = ? LIMIT 1');
$accStmt->execute([$id]);
$account = $accStmt->fetch();

$pageTitle = $client['name'];
$activeNav = 'clients';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/clients.php">← Все клиенты</a></p>

<div class="card">
  <h3 style="margin-top:0;">Данные клиента
    <span class="badge <?= ($client['client_type'] ?? 'individual') === 'company' ? 'badge-blue' : 'badge-grey' ?>" style="font-size:.8rem;"><?= e(crm_client_type_label($client['client_type'] ?? 'individual')) ?></span>
  </h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-row">
      <div>
        <label>Тип клиента</label>
        <select name="client_type" onchange="document.getElementById('clientCompanyFields').style.display = this.value === 'company' ? '' : 'none';">
          <option value="individual" <?= ($client['client_type'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Физическое лицо</option>
          <option value="company" <?= ($client['client_type'] ?? 'individual') === 'company' ? 'selected' : '' ?>>Юридическое лицо</option>
        </select>
      </div>
      <div><label>Имя / название</label><input type="text" name="name" required value="<?= e($client['name']) ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Телефон</label><input type="tel" name="phone" value="<?= e($client['phone']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" value="<?= e($client['email']) ?>"></div>
    </div>
    <label>Адрес</label>
    <input type="text" name="address" value="<?= e($client['address']) ?>">
    <div id="clientCompanyFields" style="<?= ($client['client_type'] ?? 'individual') === 'company' ? '' : 'display:none;' ?>">
      <div class="form-row">
        <div><label>ИНН</label><input type="text" name="inn" value="<?= e($client['inn'] ?? '') ?>"></div>
        <div><label>КПП</label><input type="text" name="kpp" value="<?= e($client['kpp'] ?? '') ?>"></div>
      </div>
      <label>Контактное лицо</label>
      <input type="text" name="contact_person" value="<?= e($client['contact_person'] ?? '') ?>" placeholder="ФИО контактного лица в организации">
    </div>
    <label>Заметки</label>
    <textarea name="notes"><?= e($client['notes']) ?></textarea>
    <div class="form-actions"><button class="btn" type="submit">Сохранить</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Данные с регистрации на сайте (личный кабинет)</h3>
  <?php if (!$account): ?>
    <div class="empty-state">Клиент не регистрировал личный кабинет на сайте (пока нет логина/пароля) — эти данные появятся, как только он зарегистрируется.</div>
  <?php else: ?>
    <table>
      <tbody>
        <tr><th style="width:220px;">Телефон (логин)</th><td><?= e($account['phone']) ?></td></tr>
        <tr><th>Email</th><td><?= e($account['email'] ?? '') ?: '—' ?></td></tr>
        <tr><th>Дата рождения</th><td><?= $account['birth_date'] ? crm_date($account['birth_date'], 'd.m.Y') : '—' ?></td></tr>
        <tr><th>Паспорт: серия и номер</th><td><?= e(trim(($account['passport_series'] ?? '') . ' ' . ($account['passport_number'] ?? ''))) ?: '—' ?></td></tr>
        <tr><th>Кем выдан</th><td><?= e($account['passport_issued_by'] ?? '') ?: '—' ?></td></tr>
        <tr><th>Код подразделения</th><td><?= e($account['passport_issued_code'] ?? '') ?: '—' ?></td></tr>
        <tr><th>Дата выдачи</th><td><?= $account['passport_issue_date'] ? crm_date($account['passport_issue_date'], 'd.m.Y') : '—' ?></td></tr>
        <tr><th>Адрес регистрации</th>
          <td><?php
            $regParts = array_filter([
                $account['reg_city'] ?? '',
                $account['reg_street'] ?? '',
                $account['reg_house'] ?? '' ? 'д. ' . $account['reg_house'] : '',
                $account['reg_apartment'] ?? '' ? 'кв. ' . $account['reg_apartment'] : '',
                $account['reg_postcode'] ?? '' ? 'индекс ' . $account['reg_postcode'] : '',
            ]);
            echo $regParts ? e(implode(', ', $regParts)) : '—';
          ?></td>
        </tr>
        <tr><th>Согласие на обработку ПДн</th><td><?= $account['consent_at'] ? 'Дано ' . crm_date($account['consent_at']) : '—' ?></td></tr>
        <tr><th>Зарегистрирован</th><td><?= crm_date($account['created_at']) ?></td></tr>
      </tbody>
    </table>
    <p class="text-muted" style="margin-top:12px;">Эти данные клиент указал сам при регистрации личного кабинета на сайте — здесь только для просмотра (чтобы изменить, попросите клиента обновить их в своём личном кабинете).</p>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">
    История заказов
    <a class="btn small" style="float:right;" href="/crm/order.php?client_id=<?= (int)$client['id'] ?>">+ Новая заявка</a>
  </h3>
  <?php if (!$orders): ?>
    <div class="empty-state">Заказов пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№</th><th>Маршрут</th><th>Статус</th><th>Оплата</th><th>Сумма</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/crm/order.php?id=<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a></td>
        <td><?= e($o['from_city']) ?> → <?= e($o['to_city']) ?></td>
        <td><span class="badge <?= crm_order_status_class($o['status']) ?>"><?= e(crm_order_status_label($o['status'])) ?></span></td>
        <td>
          <span class="badge <?= crm_payment_status_class($o['payment_status']) ?>"><?= e(crm_payment_status_label($o['payment_status'])) ?></span>
          <?php if ($o['payment_status'] === 'paid' || $o['payment_method']): ?>
            <br><span class="text-muted" style="font-size:.78rem;"><?= e(crm_payment_method_label($o['payment_method'])) ?></span>
          <?php endif; ?>
        </td>
        <td><?= crm_money($o['price'] !== null ? (float)$o['price'] : null) ?></td>
        <td><?= crm_date($o['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Претензии и возвраты</h3>
  <?php if (!$clientClaims): ?>
    <div class="empty-state">Претензий нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>№ заявки</th><th>Маршрут</th><th>Причина</th><th>Статус</th><th>Компенсация</th><th>Создана</th></tr></thead>
    <tbody>
      <?php foreach ($clientClaims as $cl): ?>
      <tr>
        <td><a href="/crm/claim.php?id=<?= (int)$cl['id'] ?>">№<?= (int)$cl['id'] ?> (заявка #<?= (int)$cl['order_id'] ?>)</a></td>
        <td><?= e($cl['from_city']) ?> → <?= e($cl['to_city']) ?></td>
        <td><?= e(crm_claim_reason_label($cl['reason'])) ?></td>
        <td><span class="badge <?= crm_claim_status_class($cl['status']) ?>"><?= e(crm_claim_status_label($cl['status'])) ?></span></td>
        <td><?= crm_money($cl['compensation_amount'] !== null ? (float)$cl['compensation_amount'] : null) ?></td>
        <td><?= crm_date($cl['created_at'], 'd.m.Y') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
