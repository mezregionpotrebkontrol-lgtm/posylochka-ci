<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin']);
$pdo = crm_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $name = trim($_POST['name'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $login === '' || strlen($password) < 6) {
        crm_flash_set('Заполните имя, логин и пароль (минимум 6 символов).', 'err');
    } elseif (!in_array($role, ['admin', 'operator', 'courier'], true)) {
        crm_flash_set('Некорректная роль.', 'err');
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, login, password_hash, role, phone) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $login, password_hash($password, PASSWORD_BCRYPT), $role, $phone ?: null]);
            crm_flash_set('Сотрудник добавлен.');
        } catch (PDOException $e) {
            crm_flash_set('Такой логин уже используется.', 'err');
        }
    }
    crm_redirect('/crm/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    crm_csrf_check();
    $targetId = (int) ($_POST['user_id'] ?? 0);
    if ($targetId !== (int) $user['id']) {
        $pdo->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$targetId]);
    }
    crm_redirect('/crm/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    crm_csrf_check();
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $newPassword = (string) ($_POST['new_password'] ?? '');
    if (strlen($newPassword) < 6) {
        crm_flash_set('Пароль должен быть не короче 6 символов.', 'err');
    } else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_BCRYPT), $targetId]);
        crm_flash_set('Пароль обновлён.');
    }
    crm_redirect('/crm/users.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY role, name')->fetchAll();

$pageTitle = 'Сотрудники';
$activeNav = 'users';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Новый сотрудник</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div><label>Имя</label><input type="text" name="name" required></div>
      <div><label>Телефон</label><input type="tel" name="phone"></div>
    </div>
    <div class="form-row">
      <div><label>Логин</label><input type="text" name="login" required></div>
      <div><label>Пароль</label><input type="password" name="password" required minlength="6"></div>
    </div>
    <label>Роль</label>
    <select name="role">
      <option value="operator">Оператор</option>
      <option value="courier">Курьер</option>
      <option value="admin">Администратор</option>
    </select>
    <div class="form-actions"><button class="btn" type="submit">Добавить сотрудника</button></div>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>Имя</th><th>Логин</th><th>Роль</th><th>Телефон</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['login']) ?></td>
        <td><?= e(crm_role_label($u['role'])) ?></td>
        <td><?= e($u['phone']) ?></td>
        <td><?= $u['active'] ? '<span class="badge badge-green">Активен</span>' : '<span class="badge badge-red">Отключён</span>' ?></td>
        <td style="white-space:nowrap;">
          <form method="post" class="inline" onsubmit="return promptPassword(this, <?= (int)$u['id'] ?>);">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="new_password" class="np-field">
            <button class="btn small secondary" type="submit">Сменить пароль</button>
          </form>
          <?php if ((int)$u['id'] !== (int)$user['id']): ?>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn small secondary" type="submit"><?= $u['active'] ? 'Отключить' : 'Включить' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function promptPassword(form, userId) {
  var pwd = prompt('Новый пароль для сотрудника (минимум 6 символов):');
  if (!pwd || pwd.length < 6) { return false; }
  form.querySelector('.np-field').value = pwd;
  return true;
}
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
