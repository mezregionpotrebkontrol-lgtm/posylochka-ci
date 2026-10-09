<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (crm_current_user()) {
    crm_redirect('/crm/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_csrf_check();
    $login = trim($_POST['login'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } elseif (crm_login($login, $password)) {
        crm_redirect('/crm/index.php');
    } else {
        $error = 'Неверный логин или пароль.';
    }
}

$companyName = crm_config()['company_name'] ?? 'Посылочка';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Вход — CRM <?= e($companyName) ?></title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="stylesheet" href="/crm/assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div style="text-align:center;margin-bottom:8px;"><img src="/crm/assets/logo.png" alt="<?= e($companyName) ?>" style="width:84px;height:84px;"></div>
    <h1 style="text-align:center;"><?= e($companyName) ?></h1>
    <p style="text-align:center;">Вход в систему управления доставками</p>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= crm_csrf_field() ?>
      <label>Логин</label>
      <input type="text" name="login" required autofocus value="<?= e($_POST['login'] ?? '') ?>">
      <label>Пароль</label>
      <input type="password" name="password" required>
      <div class="form-actions">
        <button type="submit" class="btn" style="width:100%;">Войти</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
