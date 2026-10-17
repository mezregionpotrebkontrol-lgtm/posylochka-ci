<?php
/**
 * Промокоды и скидки: создание, отключение, статистика использования.
 * Доступ — только администратор.
 */
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin']);
$pdo = crm_db();

$tableReady = true;
try {
    $pdo->query('SELECT 1 FROM promo_codes LIMIT 1');
} catch (Throwable $e) {
    $tableReady = false;
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    crm_csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = crm_promo_normalize($_POST['code'] ?? '');
        $type = ($_POST['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value = (float) str_replace(',', '.', (string) ($_POST['discount_value'] ?? '0'));
        $num = function (string $key): ?float {
            $v = trim((string) ($_POST[$key] ?? ''));
            return $v === '' ? null : (float) str_replace(',', '.', $v);
        };
        $date = function (string $key): ?string {
            $v = trim((string) ($_POST[$key] ?? ''));
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        };
        $maxUses = trim((string) ($_POST['max_uses'] ?? ''));

        if (!preg_match('/^[A-ZА-ЯЁ0-9_-]{3,40}$/u', $code)) {
            crm_flash_set('Промокод: 3–40 символов, буквы, цифры, «-» или «_», без пробелов.', 'err');
        } elseif ($value <= 0 || ($type === 'percent' && $value > 100)) {
            crm_flash_set('Укажите размер скидки (процент — от 1 до 100).', 'err');
        } else {
            try {
                $pdo->prepare('INSERT INTO promo_codes
                    (code, description, discount_type, discount_value, max_discount, min_order_amount, valid_from, valid_to, max_uses, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([
                        $code,
                        trim((string) ($_POST['description'] ?? '')) ?: null,
                        $type, $value,
                        $type === 'percent' ? $num('max_discount') : null,
                        $num('min_order_amount'),
                        $date('valid_from'), $date('valid_to'),
                        ctype_digit($maxUses) && (int) $maxUses > 0 ? (int) $maxUses : null,
                        $user['id'],
                    ]);
                crm_flash_set('Промокод ' . $code . ' создан.');
            } catch (PDOException $e) {
                crm_flash_set('Такой промокод уже есть.', 'err');
            }
        }
        crm_redirect('/crm/promo.php');
    }

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE promo_codes SET active = 1 - active WHERE id = ?')->execute([(int) ($_POST['promo_id'] ?? 0)]);
        crm_redirect('/crm/promo.php');
    }
}

$promos = [];
$stats = [];
if ($tableReady) {
    $promos = $pdo->query('SELECT * FROM promo_codes ORDER BY active DESC, created_at DESC')->fetchAll();
    try {
        foreach ($pdo->query('SELECT promo_code, COUNT(*) AS cnt, COALESCE(SUM(discount_amount),0) AS disc, COALESCE(SUM(price),0) AS revenue
            FROM orders WHERE promo_code IS NOT NULL AND status <> \'cancelled\' GROUP BY promo_code') as $row) {
            $stats[$row['promo_code']] = $row;
        }
    } catch (Throwable $e) {
    }
}

$pageTitle = 'Промокоды и скидки';
$activeNav = 'promo';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if (!$tableReady): ?>
<div class="card">
  <div class="empty-state">
    Промокоды ещё не подключены к базе. Импортируйте файл
    <code>install/migration-013-audit-promo-consent.sql</code> через phpMyAdmin
    (ispmanager → Базы данных → u3661226_posylochka_crm → SQL) и обновите страницу.
  </div>
</div>
<?php else: ?>

<div class="card">
  <h3 style="margin-top:0;">Новый промокод</h3>
  <form method="post">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div><label>Промокод</label><input type="text" name="code" required placeholder="НАПРИМЕР: ДЕРБЕНТ10" style="text-transform:uppercase;"></div>
      <div><label>Описание (для себя)</label><input type="text" name="description" placeholder="Акция к открытию ПВЗ"></div>
    </div>
    <div class="form-row">
      <div>
        <label>Тип скидки</label>
        <select name="discount_type">
          <option value="percent">Процент от суммы, %</option>
          <option value="fixed">Фиксированная сумма, ₽</option>
        </select>
      </div>
      <div><label>Размер скидки</label><input type="number" step="0.01" min="0.01" name="discount_value" required></div>
    </div>
    <div class="form-row">
      <div><label>Скидка не больше, ₽ (для процента, необязательно)</label><input type="number" step="0.01" name="max_discount"></div>
      <div><label>Минимальная сумма заказа, ₽ (необязательно)</label><input type="number" step="0.01" name="min_order_amount"></div>
    </div>
    <div class="form-row">
      <div><label>Действует с</label><input type="date" name="valid_from"></div>
      <div><label>Действует по</label><input type="date" name="valid_to"></div>
    </div>
    <label>Сколько раз можно использовать (пусто — без ограничений)</label>
    <input type="number" min="1" name="max_uses">
    <div class="form-actions"><button class="btn" type="submit">Создать промокод</button></div>
  </form>
</div>

<div class="card">
  <?php if (!$promos): ?>
    <div class="empty-state">Промокодов пока нет.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Код</th><th>Скидка</th><th>Условия</th><th>Срок</th><th>Использован</th><th>Заказы / выручка</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($promos as $p): $st = $stats[$p['code']] ?? null; ?>
      <tr<?= (int) $p['active'] ? '' : ' style="opacity:.55;"' ?>>
        <td><strong><?= e($p['code']) ?></strong><?php if ($p['description']): ?><br><span class="text-muted"><?= e($p['description']) ?></span><?php endif; ?></td>
        <td><?= e(crm_promo_value_label($p)) ?><?php if ($p['max_discount']): ?><br><span class="text-muted">до <?= crm_money((float) $p['max_discount']) ?></span><?php endif; ?></td>
        <td><?= $p['min_order_amount'] ? 'от ' . crm_money((float) $p['min_order_amount']) : '—' ?></td>
        <td><?= $p['valid_from'] ? crm_date($p['valid_from'], 'd.m.Y') : '…' ?> – <?= $p['valid_to'] ? crm_date($p['valid_to'], 'd.m.Y') : '…' ?></td>
        <td><?= (int) $p['used_count'] ?><?= $p['max_uses'] !== null ? ' из ' . (int) $p['max_uses'] : '' ?></td>
        <td><?= $st ? (int) $st['cnt'] . ' шт. · скидки ' . crm_money((float) $st['disc']) . ' · выручка ' . crm_money((float) $st['revenue']) : '—' ?></td>
        <td>
          <form method="post" class="inline">
            <?= crm_csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="promo_id" value="<?= (int) $p['id'] ?>">
            <button class="btn small secondary" type="submit"><?= (int) $p['active'] ? 'Отключить' : 'Включить' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
