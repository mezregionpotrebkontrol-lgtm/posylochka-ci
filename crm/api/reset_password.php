<?php
require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $payload = capi_json_input();
    $token = trim((string) ($payload['token'] ?? ''));
    $password = (string) ($payload['password'] ?? '');
    if ($token === '' || $password === '') {
        capi_error('Заполните новый пароль.');
    }
    $err = capi_consume_email_reset_token($pdo, $token, $password);
    if ($err !== null) {
        capi_error($err);
    }
    capi_respond(['ok' => true]);
}

// GET — отдаём простую HTML-страницу (на эту ссылку ведёт письмо восстановления).
$token = (string) ($_GET['token'] ?? '');
[, $tokenErr] = $token !== '' ? capi_verify_email_reset_token($pdo, $token) : [null, 'Ссылка неполная.'];

header('Content-Type: text/html; charset=utf-8');
$companyName = e(crm_config()['company_name'] ?? 'Посылочка');
$tokenJson = json_encode($token, JSON_UNESCAPED_UNICODE);
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Новый пароль — <?= $companyName ?></title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f6fb;margin:0;padding:40px 16px;color:#1a2140;}
  .card{max-width:420px;margin:0 auto;background:#fff;border-radius:12px;padding:28px 24px;box-shadow:0 4px 24px rgba(0,0,0,.08);}
  h1{font-size:19px;margin:0 0 16px;}
  label{display:block;font-weight:600;font-size:.85rem;margin-bottom:6px;}
  input{width:100%;padding:10px 12px;border:1px solid #d7dceb;border-radius:8px;font-size:.95rem;box-sizing:border-box;margin-bottom:14px;}
  button{width:100%;padding:11px;border:none;border-radius:8px;background:#1a2140;color:#fff;font-size:.95rem;font-weight:600;cursor:pointer;}
  button:disabled{opacity:.6;cursor:default;}
  .msg{font-size:.85rem;border-radius:8px;padding:10px 12px;margin-bottom:14px;}
  .err{background:#fdeeee;color:#a12f2f;}
  .ok{background:#eafaf0;color:#1f7a45;}
</style>
</head>
<body>
<div class="card">
  <h1><?= $companyName ?> — новый пароль</h1>
  <?php if ($tokenErr): ?>
    <div class="msg err"><?= e($tokenErr) ?></div>
  <?php else: ?>
    <div id="msg" hidden class="msg"></div>
    <form id="f">
      <label for="p">Новый пароль</label>
      <input type="password" id="p" minlength="6" required placeholder="Минимум 6 символов">
      <button type="submit" id="btn">Сохранить пароль</button>
    </form>
  <?php endif; ?>
</div>
<script>
  var form = document.getElementById('f');
  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var msg = document.getElementById('msg');
      var btn = document.getElementById('btn');
      msg.hidden = true;
      btn.disabled = true;
      fetch(location.pathname + location.search, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ token: <?= $tokenJson ?>, password: document.getElementById('p').value })
      }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, json:j}; }); })
        .then(function(res){
          if (!res.ok || !res.json || res.json.error) throw new Error((res.json && res.json.error) || 'Не удалось сохранить пароль');
          form.hidden = true;
          msg.className = 'msg ok';
          msg.textContent = 'Пароль изменён. Теперь можно войти в личный кабинет на сайте.';
          msg.hidden = false;
        }).catch(function(err){
          msg.className = 'msg err';
          msg.textContent = err.message;
          msg.hidden = false;
        }).finally(function(){ btn.disabled = false; });
    });
  }
</script>
</body>
</html>
