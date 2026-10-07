<?php
/**
 * Приём отзыва с сайта (multipart/form-data, может содержать фото).
 * Публикуется СРАЗУ (is_published=1), как на реальном сайте сейчас —
 * без модерации. Скрыть/удалить отзыв можно вручную в CRM (reviews.php).
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$authorName = trim((string) ($_POST['author_name'] ?? ''));
$rating = (int) ($_POST['rating'] ?? 5);
$reviewText = trim((string) ($_POST['review_text'] ?? ''));

if ($authorName === '' || mb_strlen($authorName) > 150) {
    capi_error('Укажите имя (до 150 символов).');
}
if (mb_strlen($reviewText) < 5) {
    capi_error('Напишите отзыв (минимум 5 символов).');
}
if (mb_strlen($reviewText) > 2000) {
    capi_error('Текст отзыва слишком длинный (максимум 2000 символов).');
}
$rating = max(1, min(5, $rating ?: 5));

// Простая защита от спама: не больше одного отзыва с одного IP за 5 минут
// (как на реальном сайте сейчас).
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if ($ip !== '') {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE submitter_ip = ? AND created_at > (NOW() - INTERVAL 5 MINUTE) LIMIT 1");
    $stmt->execute([$ip]);
    if ($stmt->fetch()) {
        capi_error('Вы уже отправляли отзыв недавно. Попробуйте позже.', 429);
    }
}

$photoPath = null;
if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $file = $_FILES['photo'];
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        capi_error('Фото слишком большое (максимум 5 МБ).');
    }
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $mime = @mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        capi_error('Фото должно быть в формате PNG, JPEG или WEBP.');
    }
    $dir = __DIR__ . '/../../uploads/reviews';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $filename = 'rv_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        capi_error('Не удалось сохранить фото. Попробуйте позже.', 500);
    }
    $photoPath = 'uploads/reviews/' . $filename;
}

$stmt = $pdo->prepare('INSERT INTO reviews (author_name, rating, review_text, photo_path, is_published, submitter_ip) VALUES (?,?,?,?,1,?)');
$stmt->execute([$authorName, $rating, $reviewText, $photoPath, $ip ?: null]);

crm_notify_owner("Новый отзыв с сайта от «{$authorName}» ({$rating}/5) опубликован.");

capi_respond(['ok' => true]);
