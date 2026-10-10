<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

// Добавление нового клиента
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
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
        $stmt = $pdo->prepare('INSERT INTO clients (name, client_type, phone, email, address, inn, kpp, contact_person, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $name, $clientType,
            $phone ?: null, $email ?: null, $address ?: null,
            $clientType === 'company' ? ($inn ?: null) : null,
            $clientType === 'company' ? ($kpp ?: null) : null,
            $clientType === 'company' ? ($contactPerson ?: null) : null,
            $notes ?: null, $user['id'],
        ]);
        crm_flash_set('Клиент добавлен.');
        crm_redirect('/crm/client.php?id=' . $pdo->lastInsertId());
    }
}

$search = trim($_GET['q'] ?? '');
$typeFilter = in_array($_GET['type'] ?? '', ['individual', 'company'], true) ? $_GET['type'] : '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($typeFilter !== '') {
    $where[] = 'client_type = ?';
    $params[] = $typeFilter;
}
$sql = 'SELECT * FROM clients';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Клиенты';
$activeNav = 'clients';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form method="get" class="filters">
    <div class="field">
      <label>Поиск</label>
      <input type="text" name="q" placeholder="Имя, телефон, email" value="<?= e($search) ?>" style="min-width:260px;">
    </div>
    <div class="field">
      <label>Тип</label>
      <select name="type">
        <option value="">Все</option>
        <option value="individual" <?= $typeFilter === 'individual' ? 'selected' : '' ?>>Физические лица</option>
        <option value="company" <?= $typeFilter === 'company' ? 'selected' : '' ?>>Юридические лица</option>
      </select>
    </div>
    <button class="btn secondary" type="submit">Найти</button>
    <?php if ($search !== '' || $typeFilter !== ''): ?><a class="btn secondary" href="/crm/clients.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Новый клиент</h3>
  <form method="post" id="newClientForm">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div>
        <label>Тип клиента</label>
        <select name="client_type" id="newClientType" onchange="document.getElementById('newClientCompanyFields').style.display = this.value === 'company' ? '' : 'none';">
          <option value="individual">Физическое лицо</option>
          <option value="company">Юридическое лицо</option>
        </select>
      </div>
      <div><label>Имя / название организации</label><input type="text" name="name" required></div>
    </div>
    <div class="form-row">
      <div><label>Телефон</label><input type="tel" name="phone" placeholder="+7..."></div>
      <div><label>Email</label><input type="email" name="email"></div>
    </div>
    <label>Адрес</label>
    <input type="text" name="address">
    <div id="newClientCompanyFields" style="display:none;">
      <div class="form-row">
        <div><label>ИНН</label><input type="text" name="inn"></div>
        <div><label>КПП</label><input type="text" name="kpp"></div>
      </div>
      <label>Контактное лицо</label>
      <input type="text" name="contact_person" placeholder="ФИО контактного лица в организации">
    </div>
    <label>Заметки</label>
    <textarea name="notes"></textarea>
    <div class="form-actions"><button class="btn" type="submit">Добавить клиента</button></div>
  </form>
</div>

<div class="card">
  <?php if (!$clients): ?>
    <div class="empty-state">Клиентов пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Имя</th><th>Тип</th><th>Телефон</th><th>Email</th><th>Добавлен</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><a href="/crm/client.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></td>
        <td><span class="badge <?= ($c['client_type'] ?? 'individual') === 'company' ? 'badge-blue' : 'badge-grey' ?>"><?= e(crm_client_type_label($c['client_type'] ?? 'individual')) ?></span></td>
        <td><?= e($c['phone']) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= crm_date($c['created_at'], 'd.m.Y') ?></td>
        <td><a class="btn small secondary" href="/crm/client.php?id=<?= (int)$c['id'] ?>">Открыть</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
