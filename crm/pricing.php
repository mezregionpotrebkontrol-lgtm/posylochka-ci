<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin']);
$pdo = crm_db();

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

$pageTitle = 'Цены';
$activeNav = 'pricing';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Тарифы по маршрутам</h3>
  <p class="text-muted">Эти цены использует и калькулятор на сайте, и CRM при создании заявки — единый источник, менять нужно только здесь. Цена = база + (цена за кг × вес), но не меньше минимальной.</p>
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
