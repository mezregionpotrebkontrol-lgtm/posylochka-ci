<?php
/**
 * Печатная накладная на отправление + квитанция клиента, автозаполняется
 * данными конкретной заявки (orders + clients). Открывается из карточки
 * заявки (order.php) кнопкой «Печать накладной», сама печать — штатным
 * window.print() браузера, отдельный PDF-файл генерировать не нужно.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT o.*, c.name AS client_name, c.phone AS client_phone
    FROM orders o JOIN clients c ON c.id = o.client_id WHERE o.id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    die('Заявка не найдена.');
}

$trackLabel = $order['track_code'] ?: ('№' . $order['id']);
$service = $order['service'] ?? '';

function wb_check(bool $on): string
{
    return $on ? '☑' : '☐';
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Накладная — заявка №<?= (int) $order['id'] ?></title>
<style>
  :root{--brand:#0a2472;}
  *{box-sizing:border-box;}
  body{font-family:Arial,Helvetica,sans-serif;color:#1c2438;max-width:780px;margin:24px auto;padding:0 16px;font-size:.88rem;}
  .no-print{margin-bottom:16px;}
  .no-print button{background:var(--brand);color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:.9rem;font-weight:700;cursor:pointer;}
  .sheet{border:1px solid #c9d0e3;border-radius:10px;padding:18px 20px;margin-bottom:22px;}
  .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--brand);padding-bottom:10px;margin-bottom:10px;}
  .head .brand{font-size:1.15rem;font-weight:800;color:var(--brand);}
  .head .tagline{font-size:.78rem;color:#6b7490;}
  .head .contacts{text-align:right;font-size:.8rem;color:#444;}
  .title-row{display:flex;justify-content:space-between;align-items:center;margin:10px 0 14px;}
  .title-row h1{font-size:1.05rem;margin:0;}
  .track-box{border:2px solid var(--brand);border-radius:6px;padding:6px 14px;text-align:center;}
  .track-box .lbl{font-size:.68rem;color:#6b7490;text-transform:uppercase;letter-spacing:.04em;}
  .track-box .code{font-size:1.1rem;font-weight:800;color:var(--brand);}
  .track-box svg{display:block;margin-top:4px;}
  .meta-row{display:flex;gap:24px;margin-bottom:14px;font-size:.84rem;}
  .meta-row div{flex:1;}
  .meta-row .lbl{color:#6b7490;font-size:.74rem;}
  .two-col{display:flex;gap:24px;margin-bottom:14px;}
  .two-col .col{flex:1;border:1px solid #e3e7f1;border-radius:8px;padding:10px 12px;}
  .two-col .col h4{margin:0 0 8px;font-size:.8rem;color:var(--brand);text-transform:uppercase;letter-spacing:.03em;}
  .two-col .field{margin-bottom:8px;}
  .two-col .field .lbl{font-size:.72rem;color:#6b7490;}
  .two-col .field .val{font-size:.9rem;border-bottom:1px solid #c9d0e3;min-height:1.3em;padding-bottom:2px;}
  .cargo-types{display:flex;flex-wrap:wrap;gap:14px;margin:10px 0;font-size:.85rem;}
  .cargo-grid{display:grid;grid-template-columns:1fr 1fr 2fr;gap:14px;margin-bottom:14px;}
  .cargo-grid .box{border:1px solid #e3e7f1;border-radius:8px;padding:10px 12px;}
  .cargo-grid .lbl{font-size:.72rem;color:#6b7490;}
  .cargo-grid .val{font-size:1rem;font-weight:700;}
  .addons{font-size:.8rem;margin-bottom:14px;line-height:1.7;}
  .cost-table{width:100%;border-collapse:collapse;margin-bottom:12px;}
  .cost-table td{padding:6px 4px;font-size:.88rem;border-bottom:1px dashed #d7dcea;}
  .cost-table td:last-child{text-align:right;font-weight:700;width:140px;}
  .cost-table tr.total td{font-size:1rem;border-bottom:none;border-top:2px solid var(--brand);padding-top:8px;}
  .pay-row{font-size:.85rem;margin-bottom:16px;}
  .sign-row{display:flex;justify-content:space-between;margin-top:26px;font-size:.78rem;color:#444;}
  .sign-row div{flex:1;border-top:1px solid #999;padding-top:4px;margin-right:16px;}
  .sign-row div:last-child{margin-right:0;}
  .cut{text-align:center;color:#999;font-size:.75rem;margin:4px 0 18px;border-top:1px dashed #bbb;padding-top:6px;}
  .receipt{font-size:.84rem;}
  .receipt .grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin:10px 0;}
  .receipt .lbl{color:#6b7490;font-size:.72rem;}
  .receipt .val{border-bottom:1px solid #c9d0e3;min-height:1.3em;}
  .receipt .note{font-size:.78rem;color:#555;margin-top:10px;line-height:1.5;}
  .footer-note{text-align:center;font-size:.72rem;color:#8a92ab;margin-top:6px;}
  @media print{
    .no-print{display:none;}
    body{margin:0;max-width:none;}
    .sheet{border:none;border-radius:0;page-break-inside:avoid;}
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">🖨 Печать</button>
  <a href="/crm/order.php?id=<?= (int) $order['id'] ?>" style="margin-left:12px;">← Назад к заявке</a>
</div>

<div class="sheet">
  <div class="head">
    <div>
      <div class="brand">ПОСЫЛОЧКА</div>
      <div class="tagline">Доставка с температурным режимом · Дербент — Санкт-Петербург, Москва, вся Россия</div>
    </div>
    <div class="contacts">
      ИП Казаченко Наталия Николаевна<br>
      Тел.: +7 (812) 711-18-19<br>
      моя-посылочка.рф
    </div>
  </div>

  <div class="title-row">
    <h1>НАКЛАДНАЯ НА ОТПРАВЛЕНИЕ</h1>
    <div class="track-box">
      <div class="lbl">Трек-номер</div>
      <div class="code"><?= e($trackLabel) ?></div>
      <svg class="barcode" data-code="<?= e($trackLabel) ?>"></svg>
    </div>
  </div>

  <div class="meta-row">
    <div><div class="lbl">Дата приёма</div><?= crm_date($order['created_at'], 'd.m.Y') ?></div>
    <div><div class="lbl">Пункт приёма</div>&nbsp;</div>
    <div><div class="lbl">Принял (сотрудник)</div><?= e($user['name']) ?></div>
  </div>

  <div class="two-col">
    <div class="col">
      <h4>Отправитель</h4>
      <div class="field"><div class="lbl">ФИО</div><div class="val"><?= e($order['client_name']) ?></div></div>
      <div class="field"><div class="lbl">Телефон</div><div class="val"><?= e($order['client_phone']) ?></div></div>
      <div class="field"><div class="lbl">Город отправления</div><div class="val"><?= e($order['from_city']) ?><?= $order['from_address'] ? ', ' . e($order['from_address']) : '' ?></div></div>
    </div>
    <div class="col">
      <h4>Получатель</h4>
      <div class="field"><div class="lbl">ФИО</div><div class="val">&nbsp;</div></div>
      <div class="field"><div class="lbl">Телефон</div><div class="val">&nbsp;</div></div>
      <div class="field"><div class="lbl">Город / адрес доставки</div><div class="val"><?= e($order['to_city']) ?><?= $order['to_address'] ? ', ' . e($order['to_address']) : '' ?></div></div>
    </div>
  </div>

  <div class="cargo-types">
    <span><?= wb_check($service === '') ?> Физическое лицо</span>
    <span><?= wb_check($service === 'Посылка') ?> Посылка</span>
    <span><?= wb_check($service === 'Продукты') ?> Продукты питания</span>
    <span><?= wb_check($service === 'B2B') ?> Для юр. лиц (B2B)</span>
  </div>

  <div class="cargo-grid">
    <div class="box"><div class="lbl">Вес, кг</div><div class="val"><?= $order['weight_kg'] !== null ? e(rtrim(rtrim(number_format((float)$order['weight_kg'], 2, '.', ''), '0'), '.')) : '—' ?></div></div>
    <div class="box"><div class="lbl">Объявленная ценность, ₽</div><div class="val"><?= $order['declared_value'] !== null ? crm_money((float) $order['declared_value']) : '—' ?></div></div>
    <div class="box"><div class="lbl">Описание содержимого</div><div class="val" style="font-weight:400;font-size:.88rem;"><?= e($order['cargo_description']) ?: '&nbsp;' ?></div></div>
  </div>

  <div class="addons">
    ☐ Термоупаковка / термобокс &nbsp;&nbsp; ☐ Хрупкий груз &nbsp;&nbsp; ☐ Опись документов / вложений<br>
    ☐ SMS-уведомления о статусе &nbsp;&nbsp; ☐ Страхование груза
  </div>

  <table class="cost-table">
    <tr><td>Стоимость доставки</td><td><?= $order['price'] !== null ? crm_money((float) $order['price']) : '______________ ₽' ?></td></tr>
    <tr><td>Дополнительные услуги</td><td>______________ ₽</td></tr>
    <tr class="total"><td>Итого к оплате</td><td><?= $order['price'] !== null ? crm_money((float) $order['price']) : '______________ ₽' ?></td></tr>
  </table>

  <div class="pay-row">
    <?= wb_check($order['payment_method'] === 'cash') ?> Наличный расчёт &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'terminal') ?> Оплата через терминал &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'invoice') ?> Оплата по счёту &nbsp;&nbsp;
    <?= wb_check($order['payment_method'] === 'online') ?> Онлайн на сайте
  </div>

  <div class="sign-row">
    <div>Подпись отправителя — груз и условия доставки подтверждаю</div>
    <div>Подпись сотрудника пункта приёма / печать</div>
  </div>
</div>

<div class="cut">✂ линия отрыва — квитанция клиента ниже</div>

<div class="sheet receipt">
  <div class="title-row">
    <h1 style="font-size:.95rem;">КВИТАНЦИЯ О ПРИЁМЕ ГРУЗА</h1>
    <div class="track-box">
      <div class="lbl">Трек-номер</div>
      <div class="code"><?= e($trackLabel) ?></div>
      <svg class="barcode" data-code="<?= e($trackLabel) ?>"></svg>
    </div>
  </div>
  <div class="grid">
    <div><div class="lbl">Дата приёма</div><div class="val"><?= crm_date($order['created_at'], 'd.m.Y') ?></div></div>
    <div><div class="lbl">Маршрут</div><div class="val"><?= e($order['from_city']) ?> → <?= e($order['to_city']) ?></div></div>
    <div><div class="lbl">Отправитель</div><div class="val"><?= e($order['client_name']) ?></div></div>
    <div><div class="lbl">Вес, кг</div><div class="val"><?= $order['weight_kg'] !== null ? e($order['weight_kg']) : '—' ?></div></div>
    <div><div class="lbl">Телефон</div><div class="val"><?= e($order['client_phone']) ?></div></div>
    <div><div class="lbl">Итого к оплате</div><div class="val"><?= $order['price'] !== null ? crm_money((float) $order['price']) : '—' ?></div></div>
  </div>
  <div class="note">
    Сохраните эту квитанцию до получения груза. Отследить статус отправления — на сайте моя-посылочка.рф в разделе «Отследить», указав трек-номер, либо в мобильном приложении «Посылочка». Вопросы по доставке — по телефону +7 (812) 711-18-19.
  </div>
</div>

<div class="footer-note">ПОСЫЛОЧКА · ИП Казаченко Наталия Николаевна · моя-посылочка.рф · Документ является подтверждением приёма груза к перевозке</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.querySelectorAll('svg.barcode').forEach(function (el) {
  var code = el.getAttribute('data-code') || '';
  if (!code || typeof JsBarcode === 'undefined') return;
  try {
    JsBarcode(el, code, {
      format: 'CODE128',
      width: 1.6,
      height: 36,
      displayValue: false,
      margin: 0
    });
  } catch (e) { /* если код содержит символы, не подходящие CODE128, просто не рисуем штрихкод */ }
});
</script>

</body>
</html>
