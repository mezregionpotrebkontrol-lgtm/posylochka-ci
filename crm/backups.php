<?php
/**
 * Резервные копии базы: список, создание вручную, скачивание.
 * Доступ — только администратор. Скачанную копию стоит хранить ещё и у себя
 * (компьютер, флешка, Яндекс Диск) — копия на том же хостинге не спасёт,
 * если пропадёт сам хостинг.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/backup.php';
$user = crm_require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    [$file, $err] = crm_backup_create();
    if ($err !== null) {
        crm_flash_set($err, 'err');
    } else {
        crm_flash_set('Копия создана: ' . basename($file) . ' (' . crm_human_size((int) filesize($file)) . ').');
    }
    crm_redirect('/crm/backups.php');
}

if (isset($_GET['download'])) {
    $name = basename((string) $_GET['download']);
    $found = null;
    foreach (crm_backup_list() as $b) {
        if ($b['name'] === $name) {
            $found = $b;
            break;
        }
    }
    if (!$found) {
        http_response_code(404);
        die('Файл не найден.');
    }
    crm_audit('backups:download', 'backups', null, $found['name']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $found['name'] . '"');
    header('Content-Length: ' . $found['size']);
    header('Cache-Control: no-store');
    readfile($found['path']);
    exit;
}

$backups = crm_backup_list();
$dir = crm_backup_dir();
$last = $backups[0] ?? null;

$pageTitle = 'Резервные копии базы';
$activeNav = 'backups';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <?php if ($last): ?>
        <?php $ageH = (time() - $last['mtime']) / 3600; ?>
        Последняя копия: <strong><?= date('d.m.Y H:i', $last['mtime']) ?></strong>
        <?php if ($ageH > 36): ?>
          <span class="badge badge-red">больше суток назад — проверьте задание в планировщике</span>
        <?php else: ?>
          <span class="badge badge-green">в порядке</span>
        <?php endif; ?>
      <?php else: ?>
        <span class="badge badge-red">Копий ещё нет</span>
      <?php endif; ?>
      <div class="text-muted" style="font-size:.82rem;margin-top:6px;">
        Хранятся последние <?= crm_backup_config()['keep_days'] ?> дн. Папка: <?= $dir ? e($dir) : 'не создана' ?>
      </div>
    </div>
    <form method="post" class="inline">
      <?= crm_csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <button class="btn" type="submit">Сделать копию сейчас</button>
    </form>
  </div>
</div>

<div class="card">
  <p style="margin-top:0;">Раз в неделю скачивайте свежую копию и храните её у себя (компьютер, Яндекс Диск).
    Восстановление: phpMyAdmin → база <code>u3661226_posylochka_crm</code> → «Импорт» → выбрать файл .sql.gz.</p>
  <?php if (!$backups): ?>
    <div class="empty-state">Копий пока нет. Нажмите «Сделать копию сейчас» и настройте ежедневное задание в планировщике.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>Файл</th><th>Создан</th><th>Размер</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($backups as $b): ?>
      <tr>
        <td><?= e($b['name']) ?></td>
        <td><?= date('d.m.Y H:i', $b['mtime']) ?></td>
        <td><?= crm_human_size((int) $b['size']) ?></td>
        <td><a class="btn small secondary" href="/crm/backups.php?download=<?= urlencode($b['name']) ?>">Скачать</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
