<?php
require_once __DIR__ . '/includes/bootstrap.php';
crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT i.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address
    FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    die('Счёт не найден.');
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<title>Счёт <?= e($invoice['number']) ?></title>
<style>
  body{font-family:Arial,sans-serif;color:#1c2438;max-width:720px;margin:40px auto;padding:0 20px;}
  h1{font-size:1.3rem;border-bottom:2px solid #0a2472;padding-bottom:10px;}
  table{width:100%;border-collapse:collapse;margin:20px 0;}
  td,th{border:1px solid #d7dcea;padding:8px 10px;text-align:left;font-size:.92rem;}
  .req{margin-top:30px;font-size:.85rem;color:#444;line-height:1.6;}
  .total{font-size:1.1rem;font-weight:700;}
  @media print{ .no-print{display:none;} body{margin:0;} }
</style>
</head>
<body>
  <button class="no-print" onclick="window.print()" style="float:right;padding:8px 16px;">Печать</button>
  <h1>Счёт на оплату № <?= e($invoice['number']) ?> от <?= crm_date($invoice['created_at'], 'd.m.Y') ?></h1>

  <p><strong>Исполнитель:</strong> Индивидуальный предприниматель Казаченко Наталия Николаевна<br>
  ИНН 781017623573, ОГРНИП 324470400129493<br>
  Адрес регистрации: 188680, Ленинградская область, Всеволожский р-н, д. Кальтино, Колтушское ш., д. 19, к. 2<br>
  Телефон: +7 (812) 711-18-19 · Email: my@lk-posylochka.ru</p>

  <p><strong>Заказчик:</strong> <?= e($invoice['client_name']) ?><?= $invoice['client_phone'] ? ', тел. ' . e($invoice['client_phone']) : '' ?><?= $invoice['client_address'] ? ', ' . e($invoice['client_address']) : '' ?></p>

  <table>
    <thead><tr><th>№</th><th>Наименование услуги</th><th>Сумма</th></tr></thead>
    <tbody>
      <tr>
        <td>1</td>
        <td>Услуги по транспортно-экспедиционному обслуживанию<?= $invoice['order_id'] ? ' (заявка №' . (int)$invoice['order_id'] . ')' : '' ?></td>
        <td><?= crm_money((float)$invoice['amount']) ?></td>
      </tr>
    </tbody>
  </table>

  <p class="total">Итого к оплате: <?= crm_money((float)$invoice['amount']) ?></p>

  <div class="req">
    Оплата данного счёта означает согласие с условиями публичной оферты, размещённой на сайте моя-посылочка.рф.<br>
    Статус счёта: <?= e(crm_invoice_status_label($invoice['status'])) ?><?= $invoice['paid_at'] ? ', оплачен ' . crm_date($invoice['paid_at'], 'd.m.Y') : '' ?>.
  </div>
</body>
</html>
