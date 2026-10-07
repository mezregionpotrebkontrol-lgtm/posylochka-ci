<?php
/**
 * Приём отзыва с сайта (multipart/form-data, может содержать фото).
 * Отзыв сохраняется со статусом pending — публикуется только после
 * модерации в CRM (раздел «Отзывы», crm/reviews.php).
 */
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    capi_error('Метод не поддерживается.', 405);
}

$authorName = trim((string) ($_POST['author_name'] ?? ''));
$rating = (int) ($_POST['rating'] ?? 5);
$reviewText = trim((string) ($_POST['review_text'] ?? ''));

if ($authorName === '') {
    capi_error('Укажите ваше имя.');
}
if (mb_strlen($reviewText) < 5) {
    capi_error('Напишите отзыв (минимум 5 символов).');
}
$rating = max(1, min(5, $rating ?: 5));

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

$stmt = $pdo->prepare('INSERT INTO reviews (author_name, rating, review_text, photo_path, status) VALUES (?,?,?,?,\'pending\')');
$stmt->execute([$authorName, $rating, $reviewText, $photoPath]);

crm_notify_owner("Новый отзыв с сайта от «{$authorName}» ({$rating}/5) — ждёт модерации в CRM.");

capi_respond(['ok' => true]);
