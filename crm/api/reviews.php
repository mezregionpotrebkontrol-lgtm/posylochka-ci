<?php
/** Публичный список одобренных отзывов для сайта. GET /api/reviews.php */
require_once __DIR__ . '/_bootstrap.php';

$stmt = $pdo->query("SELECT author_name, rating, review_text, photo_path, created_at
    FROM reviews WHERE is_published = 1 ORDER BY created_at DESC LIMIT 100");
$rows = $stmt->fetchAll();

$cfg = crm_config();
$baseUrl = rtrim($cfg['site']['base_url'] ?? '', '/');

$reviews = array_map(function (array $r) use ($baseUrl) {
    return [
        'author_name' => $r['author_name'],
        'rating'      => (int) $r['rating'],
        'review_text' => $r['review_text'],
        'photo_url'   => $r['photo_path'] ? $baseUrl . '/' . $r['photo_path'] : null,
        'created_at'  => $r['created_at'],
    ];
}, $rows);

capi_respond(['ok' => true, 'reviews' => $reviews]);
