<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = crm_require_role(['admin', 'operator']);
$pdo = crm_db();

$id = (int) ($_GET['id'] ?? 0);
$claim = null;
$order = null;

if ($id) {
    $stmt = $pdo->prepare('SELECT cl.*, o.from_city, o.to_city, o.client_id FROM claims cl JOIN orders o ON o.id = cl.order_id WHERE cl.id = ?');
    $stmt->execute([$id]);
    $claim = $stmt->fetch();
    if (!$claim) {
        http_response_code(404);
        die('Претензия не найдена.');
    }
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $orderStmt->execute([$claim['order_id']]);
    $order = $orderStmt->fetch();
} else {
    $orderId = (int) ($_GET['order_id'] ?? 0);
    if (!$orderId) {
        http_response_code(404);
        die('Не указана заявка, по которой оформляется претензия.');
    }
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) {
        http_response_code(404);
        die('Заявка не найдена.');
    }
}

$client = null;
if ($order) {
    $clStmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $clStmt->execute([$order['client_id']]);
    $client = $clStmt->fetch();
}

// Создание претензии
if (!$claim && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    crm_csrf_check();
    $reason = in_array($_POST['reason'] ?? '', ['defect', 'loss', 'return', 'other'], true) ? $_POST['reason'] : 'other';
    $description = trim($_POST['description'] ?? '');
    $compensation = $_POST['compensation_amount'] !== '' ? (float) str_replace(',', '.', $_POST['compensation_amount']) : null;
    $dueDate = $_POST['response_due_date'] !== '' ? $_POST['response_due_date'] : null;

    $photoPath = null;
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $file = $_FILES['photo'];
        $maxBytes = 8 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            crm_flash_set('Фото слишком большое (максимум 8 МБ).', 'err');
            crm_redirect('/crm/claim.php?order_id=' . (int) $order['id']);
        }
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = @mime_content_type($file['tmp_name']) ?: '';
        if (!isset($allowed[$mime])) {
            crm_flash_set('Фото должно быть в формате PNG, JPEG или WEBP.', 'err');
            crm_redirect('/crm/claim.php?order_id=' . (int) $order['id']);
        }
        $dir = __DIR__ . '/../uploads/claims';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filename = 'claim_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (@move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            $photoPath = 'uploads/claims/' . $filename;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO claims (order_id, reason, description, photo_path, compensation_amount, response_due_date, created_by)
        VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $order['id'], $reason, $description ?: null, $photoPath, $compensation, $dueDate, $user['id'],
    ]);
    $newId = (int) $pdo->lastInsertId();
    crm_flash_set('Претензия №' . $newId . ' создана.');
    crm_redirect('/crm/claim.php?id=' . $newId);
}

// Обновление статуса/суммы/срока
if ($claim && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    crm_csrf_check();
    $reason = in_array($_POST['reason'] ?? '', ['defect', 'loss', 'return', 'other'], true) ? $_POST['reason'] : $claim['reason'];
    $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved', 'rejected'], true) ? $_POST['status'] : $claim['status'];
    $description = trim($_POST['description'] ?? '');
    $compensation = $_POST['compensation_amount'] !== '' ? (float) str_replace(',', '.', $_POST['compensation_amount']) : null;
    $dueDate = $_POST['response_due_date'] !== '' ? $_POST['response_due_date'] : null;

    $photoPath = $claim['photo_path'];
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $mime = @mime_content_type($file['tmp_name']) ?: '';
        if ($file['size'] <= 8 * 1024 * 1024 && isset($allowed[$mime])) {
            $dir = __DIR__ . '/../uploads/claims';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $filename = 'claim_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
            if (@move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
                $photoPath = 'uploads/claims/' . $filename;
            }
        }
    }

    $resolvedAt = $claim['resolved_at'];
    if (in_array($status, ['resolved', 'rejected'], true) && !in_array($claim['status'], ['resolved', 'rejected'], true)) {
        $resolvedAt = date('Y-m-d H:i:s');
    } elseif (!in_array($status, ['resolved', 'rejected'], true)) {
        $resolvedAt = null;
    }

    $stmt = $pdo->prepare('UPDATE claims SET reason=?, status=?, description=?, photo_path=?, compensation_amount=?, response_due_date=?, resolved_at=? WHERE id=?');
    $stmt->execute([$reason, $status, $description ?: null, $photoPath, $compensation, $dueDate, $resolvedAt, $id]);
    crm_flash_set('Претензия обновлена.');
    crm_redirect('/crm/claim.php?id=' . $id);
}

$pageTitle = $claim ? ('Претензия №' . $claim['id']) : ('Новая претензия — заявка #' . $order['id']);
$activeNav = 'claims';
require __DIR__ . '/includes/layout_top.php';
?>

<p><a href="/crm/claims.php">← Все претензии</a> &nbsp;|&nbsp; <a href="/crm/order.php?id=<?= (int) $order['id'] ?>">Заявка #<?= (int) $order['id'] ?></a></p>

<div class="card">
  <h3 style="margin-top:0;">
    Заявка #<?= (int) $order['id'] ?>: <?= e($order['from_city']) ?> → <?= e($order['to_city']) ?>
    <?php if ($client): ?> — <a href="/crm/client.php?id=<?= (int) $client['id'] ?>"><?= e($client['name']) ?></a><?php endif; ?>
  </h3>

  <?php if (!$claim): ?>
  <form method="post" enctype="multipart/form-data">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div>
        <label>Причина</label>
        <select name="reason" required>
          <?php foreach (['defect', 'loss', 'return', 'other'] as $r): ?>
            <option value="<?= $r ?>"><?= e(crm_claim_reason_label($r)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Сумма компенсации клиенту, ₽ (необязательно)</label><input type="number" step="0.01" name="compensation_amount"></div>
    </div>
    <div class="form-row">
      <div><label>Срок ответа клиенту</label><input type="date" name="response_due_date"></div>
      <div><label>Фото повреждения (необязательно)</label><input type="file" name="photo" accept="image/png,image/jpeg,image/webp"></div>
    </div>
    <label>Описание</label>
    <textarea name="description" placeholder="Что произошло, что повреждено/утеряно, обстоятельства..."></textarea>
    <div class="form-actions"><button class="btn" type="submit">Создать претензию</button></div>
  </form>

  <?php else: ?>
  <form method="post" enctype="multipart/form-data">
    <?= crm_csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="form-row">
      <div>
        <label>Причина</label>
        <select name="reason">
          <?php foreach (['defect', 'loss', 'return', 'other'] as $r): ?>
            <option value="<?= $r ?>" <?= $claim['reason'] === $r ? 'selected' : '' ?>><?= e(crm_claim_reason_label($r)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Статус</label>
        <select name="status">
          <?php foreach (['open', 'in_progress', 'resolved', 'rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $claim['status'] === $s ? 'selected' : '' ?>><?= e(crm_claim_status_label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div><label>Сумма компенсации клиенту, ₽</label><input type="number" step="0.01" name="compensation_amount" value="<?= e($claim['compensation_amount'] ?? '') ?>"></div>
      <div><label>Срок ответа клиенту</label><input type="date" name="response_due_date" value="<?= e($claim['response_due_date'] ?? '') ?>"></div>
    </div>
    <label>Описание</label>
    <textarea name="description"><?= e($claim['description']) ?></textarea>
    <?php if ($claim['photo_path']): ?>
      <label>Текущее фото</label><br>
      <a href="/<?= e($claim['photo_path']) ?>" target="_blank"><img src="/<?= e($claim['photo_path']) ?>" alt="Фото повреждения" style="max-width:260px;border-radius:8px;margin-bottom:10px;"></a>
    <?php endif; ?>
    <label>Заменить фото (необязательно)</label>
    <input type="file" name="photo" accept="image/png,image/jpeg,image/webp">
    <div class="form-actions"><button class="btn" type="submit">Сохранить</button></div>
  </form>

  <p class="text-muted" style="margin-top:14px;">
    Создана <?= crm_date($claim['created_at']) ?><?php if ($claim['resolved_at']): ?>, закрыта <?= crm_date($claim['resolved_at']) ?><?php endif; ?>.
  </p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
