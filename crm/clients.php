<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

// Добавление нового клиента
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        crm_flash_set('Укажите имя или название клиента.', 'err');
    } else {
        $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $phone ?: null, $email ?: null, $address ?: null, $notes ?: null, $user['id']]);
        crm_flash_set('Клиент добавлен.');
        crm_redirect('/crm/client.php?id=' . $pdo->lastInsertId());
    }
}

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 200");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM clients ORDER BY created_at DESC LIMIT 200');
}
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
    <button class="btn secondary" type="submit">Найти</button>
    <?php if ($search !== ''): ?><a class="btn secondary" href="/crm/clients.php">Сбросить</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Новый клиент</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div><label>Имя / название</label><input type="text" name="name" required></div>
      <div><label>Телефон</label><input type="tel" name="phone" placeholder="+7..."></div>
    </div>
    <div class="form-row">
      <div><label>Email</label><input type="email" name="email"></div>
      <div><label>Адрес</label><input type="text" name="address"></div>
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
    <thead><tr><th>Имя</th><th>Телефон</th><th>Email</th><th>Добавлен</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><a href="/crm/client.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></td>
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
