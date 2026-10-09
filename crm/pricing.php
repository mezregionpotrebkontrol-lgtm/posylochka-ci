<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pricing-formula.php';
$user = crm_require_role(['admin']);
$pdo = crm_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_formula') {
    crm_csrf_check();
    $defaults = crm_price_config_defaults();
    $stmt = $pdo->prepare('INSERT INTO pricing_config (config_key, config_value, label, group_name, sort_order)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
    // Метки/группы/порядок берём из миграции 005 — здесь достаточно обновить
    // только значения, но на случай отсутствия строки в pricing_config
    // (миграция не выполнена) вставляем её с осмысленными метаданными.
    $meta = [
        'weight_band1_max'    => ['Вес до, кг (1-я ступень)', 'weight', 1],
        'weight_band1_rate'   => ['₽/кг на 1-й ступени (до указанного веса)', 'weight', 2],
        'weight_band2_max'    => ['Вес до, кг (2-я ступень)', 'weight', 3],
        'weight_band2_rate'   => ['₽/кг на 2-й ступени', 'weight', 4],
        'weight_band3_max'    => ['Вес до, кг (3-я ступень)', 'weight', 5],
        'weight_band3_rate'   => ['₽/кг на 3-й ступени', 'weight', 6],
        'weight_band4_rate'   => ['₽/кг свыше 3-й ступени', 'weight', 7],
        'km_mult_base'        => ['Коэффициент расстояния — минимум', 'distance', 8],
        'km_mult_div'         => ['Коэффициент расстояния — делитель (км)', 'distance', 9],
        'min_base_price'      => ['Минимальная базовая цена, ₽', 'distance', 10],
        'pack_none'           => ['Без упаковки, ₽', 'pack', 11],
        'pack_bag'            => ['Фирменный пакет, ₽', 'pack', 12],
        'pack_docs'           => ['Пакет для документов, ₽', 'pack', 13],
        'pack_box_s'          => ['Коробка S, ₽', 'pack', 14],
        'pack_box_m'          => ['Коробка M, ₽', 'pack', 15],
        'pack_box_l'          => ['Коробка L, ₽', 'pack', 16],
        'pack_bubble'         => ['Пузырчатая плёнка, ₽', 'pack', 17],
        'pack_thermobox'      => ['Термобокс, ₽', 'pack', 18],
        'surcharge_fragile'   => ['Наценка «хрупкое», ₽', 'addon', 19],
        'surcharge_inventory' => ['Наценка «опись вложения», ₽', 'addon', 20],
        'surcharge_sms'       => ['Наценка «SMS-уведомления», ₽', 'addon', 21],
        'insurance_rate'      => ['Страхование — ставка от объявленной ценности', 'addon', 22],
        'insurance_min'       => ['Страхование — минимальная сумма, ₽', 'addon', 23],
    ];
    foreach ($defaults as $key => $defaultVal) {
        if (!isset($_POST[$key]) || $_POST[$key] === '') {
            continue;
        }
        $val = (float) str_replace(',', '.', $_POST[$key]);
        [$label, $group, $sort] = $meta[$key];
        $stmt->execute([$key, $val, $label, $group, $sort]);
    }
    crm_flash_set('Тариф калькулятора обновлён.');
    crm_redirect('/crm/pricing.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $from = trim($_POST['from_city'] ?? '');
    $to = trim($_POST['to_city'] ?? '');
    if ($from === '' || $to === '') {
        crm_flash_set('Укажите города маршрута.', 'err');
    } else {
        $stmt = $pdo->prepare('INSERT INTO pricing_rules (from_city, to_city, base_price, price_per_kg, min_price)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), price_per_kg = VALUES(price_per_kg), min_price = VALUES(min_price), active = 1');
        $stmt->execute([
            $from, $to,
            (float) ($_POST['base_price'] ?? 0),
            (float) ($_POST['price_per_kg'] ?? 0),
            $_POST['min_price'] !== '' ? (float) $_POST['min_price'] : null,
        ]);
        crm_flash_set('Маршрут сохранён.');
    }
    crm_redirect('/crm/pricing.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE pricing_rules SET base_price=?, price_per_kg=?, min_price=?, active=? WHERE id=?');
    $stmt->execute([
        (float) ($_POST['base_price'] ?? 0),
        (float) ($_POST['price_per_kg'] ?? 0),
        $_POST['min_price'] !== '' ? (float) $_POST['min_price'] : null,
        isset($_POST['active']) ? 1 : 0,
        $id,
    ]);
    crm_flash_set('Тариф обновлён.');
    crm_redirect('/crm/pricing.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    crm_csrf_check();
    $pdo->prepare('DELETE FROM pricing_rules WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    crm_flash_set('Маршрут удалён.');
    crm_redirect('/crm/pricing.php');
}

$rules = $pdo->query('SELECT * FROM pricing_rules ORDER BY from_city, to_city')->fetchAll();
$formula = crm_price_config();

$pageTitle = 'Цены';
$activeNav = 'pricing';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Тариф калькулятора (вес, расстояние, упаковка, страховка)</h3>
  <p class="text-muted">
    Это настоящая формула, по которой считается стоимость для оплаты онлайн на сайте
    (сервер всегда пересчитывает сумму сам по этим параметрам — цену, присланную из
    браузера, он не принимает). Таблица маршрутов ниже — отдельный, более простой
    справочник, который формула калькулятора не использует.
  </p>
  <p style="background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;padding:10px 14px;">
    ⚠️ Важно: те же цифры продублированы в коде калькулятора на сайте (файл
    <code>site-src/app.html</code>), чтобы посетитель видел предварительную цену
    ещё до оформления заявки. Изменения здесь обновляют только CRM/сервер (то есть
    итоговую сумму, которую реально спишет оплата) — калькулятор на самом сайте
    нужно будет обновить отдельно, иначе показанная клиенту предварительная цена
    может не совпадать с реально списанной суммой. Если нужно — попросите внести
    те же изменения и на сайт.
  </p>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update_formula">

    <h4>Тариф по весу (₽/кг по ступеням)</h4>
    <div class="form-row">
      <label>До, кг <input type="number" step="0.01" name="weight_band1_max" value="<?= e((string) $formula['weight_band1_max']) ?>" style="width:90px;"></label>
      <label>₽/кг <input type="number" step="0.01" name="weight_band1_rate" value="<?= e((string) $formula['weight_band1_rate']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>До, кг <input type="number" step="0.01" name="weight_band2_max" value="<?= e((string) $formula['weight_band2_max']) ?>" style="width:90px;"></label>
      <label>₽/кг <input type="number" step="0.01" name="weight_band2_rate" value="<?= e((string) $formula['weight_band2_rate']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>До, кг <input type="number" step="0.01" name="weight_band3_max" value="<?= e((string) $formula['weight_band3_max']) ?>" style="width:90px;"></label>
      <label>₽/кг <input type="number" step="0.01" name="weight_band3_rate" value="<?= e((string) $formula['weight_band3_rate']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>₽/кг свыше последней ступени <input type="number" step="0.01" name="weight_band4_rate" value="<?= e((string) $formula['weight_band4_rate']) ?>" style="width:90px;"></label>
    </div>

    <h4>Коэффициент расстояния и минимальная цена</h4>
    <p class="text-muted" style="margin-top:-6px;">Коэффициент = минимум + (км маршрута ÷ делитель). Итоговая база = (тариф по весу) × коэффициент, но не меньше минимальной базовой цены.</p>
    <div class="form-row">
      <label>Коэффициент — минимум <input type="number" step="0.0001" name="km_mult_base" value="<?= e((string) $formula['km_mult_base']) ?>" style="width:90px;"></label>
      <label>Коэффициент — делитель, км <input type="number" step="1" name="km_mult_div" value="<?= e((string) $formula['km_mult_div']) ?>" style="width:100px;"></label>
      <label>Минимальная базовая цена, ₽ <input type="number" step="0.01" name="min_base_price" value="<?= e((string) $formula['min_base_price']) ?>" style="width:100px;"></label>
    </div>

    <h4>Упаковка, ₽</h4>
    <div class="form-row">
      <label>Без упаковки <input type="number" step="0.01" name="pack_none" value="<?= e((string) $formula['pack_none']) ?>" style="width:90px;"></label>
      <label>Фирменный пакет <input type="number" step="0.01" name="pack_bag" value="<?= e((string) $formula['pack_bag']) ?>" style="width:90px;"></label>
      <label>Пакет для документов <input type="number" step="0.01" name="pack_docs" value="<?= e((string) $formula['pack_docs']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>Коробка S <input type="number" step="0.01" name="pack_box_s" value="<?= e((string) $formula['pack_box_s']) ?>" style="width:90px;"></label>
      <label>Коробка M <input type="number" step="0.01" name="pack_box_m" value="<?= e((string) $formula['pack_box_m']) ?>" style="width:90px;"></label>
      <label>Коробка L <input type="number" step="0.01" name="pack_box_l" value="<?= e((string) $formula['pack_box_l']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>Пузырчатая плёнка <input type="number" step="0.01" name="pack_bubble" value="<?= e((string) $formula['pack_bubble']) ?>" style="width:90px;"></label>
      <label>Термобокс <input type="number" step="0.01" name="pack_thermobox" value="<?= e((string) $formula['pack_thermobox']) ?>" style="width:90px;"></label>
    </div>

    <h4>Доп. наценки и страхование</h4>
    <div class="form-row">
      <label>«Хрупкое», ₽ <input type="number" step="0.01" name="surcharge_fragile" value="<?= e((string) $formula['surcharge_fragile']) ?>" style="width:90px;"></label>
      <label>«Опись вложения», ₽ <input type="number" step="0.01" name="surcharge_inventory" value="<?= e((string) $formula['surcharge_inventory']) ?>" style="width:90px;"></label>
      <label>SMS-уведомления, ₽ <input type="number" step="0.01" name="surcharge_sms" value="<?= e((string) $formula['surcharge_sms']) ?>" style="width:90px;"></label>
    </div>
    <div class="form-row">
      <label>Страхование — ставка (доля от ценности) <input type="number" step="0.0001" name="insurance_rate" value="<?= e((string) $formula['insurance_rate']) ?>" style="width:100px;"></label>
      <label>Страхование — минимум, ₽ <input type="number" step="0.01" name="insurance_min" value="<?= e((string) $formula['insurance_min']) ?>" style="width:100px;"></label>
    </div>

    <div class="form-actions"><button class="btn" type="submit">Сохранить тариф</button></div>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Тарифы по маршрутам (справочник, не используется калькулятором)</h3>
  <p class="text-muted">Этот список — отдельный, упрощённый справочник маршрутов. Онлайн-калькулятор и оплата на сайте используют формулу выше, а не эту таблицу.</p>
  <?php if (!$rules): ?>
    <div class="empty-state">Маршруты ещё не добавлены.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Откуда</th><th>Куда</th><th>Параметры</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($rules as $r): ?>
        <tr>
          <td><?= e($r['from_city']) ?></td>
          <td><?= e($r['to_city']) ?></td>
          <td>
            <form method="post" class="inline-row">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <label>База <input type="number" step="0.01" name="base_price" value="<?= e((string) $r['base_price']) ?>" style="width:90px;"></label>
              <label>₽/кг <input type="number" step="0.01" name="price_per_kg" value="<?= e((string) $r['price_per_kg']) ?>" style="width:90px;"></label>
              <label>Мин. <input type="number" step="0.01" name="min_price" value="<?= e((string) $r['min_price']) ?>" style="width:90px;" placeholder="—"></label>
              <label><input type="checkbox" name="active" <?= $r['active'] ? 'checked' : '' ?>> вкл</label>
              <button class="btn small" type="submit">Сохранить</button>
            </form>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Удалить маршрут?');">
              <?= crm_csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn small danger" type="submit">Удалить</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="margin-top:0;">Добавить маршрут</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <label>Откуда<input type="text" name="from_city" value="Дербент" required></label>
      <label>Куда<input type="text" name="to_city" value="Санкт-Петербург" required></label>
    </div>
    <div class="form-row">
      <label>База, ₽<input type="number" step="0.01" name="base_price" value="0" required></label>
      <label>₽/кг<input type="number" step="0.01" name="price_per_kg" value="0" required></label>
      <label>Мин. цена, ₽ (необязательно)<input type="number" step="0.01" name="min_price"></label>
    </div>
    <button class="btn" type="submit">Добавить маршрут</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
