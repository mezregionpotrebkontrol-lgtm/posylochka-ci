<?php
/**
 * Обновляет sitemap.xml в корне сайта: находит все публичные страницы *.html
 * и записывает их с датой последнего изменения. Запускать раз в неделю
 * (или вручную после изменения сайта):
 *   /opt/php/8.3/bin/php -f /var/www/u3661226/data/www/моя-посылочка.рф/crm/cron/sitemap.php
 */
require __DIR__ . '/_cli.php';

$siteRoot = dirname(__DIR__, 2);
$base = rtrim(crm_config()['site']['base_url'] ?? 'https://моя-посылочка.рф', '/');
// Поисковики ожидают адрес в punycode.
if (function_exists('idn_to_ascii')) {
    $host = parse_url($base, PHP_URL_HOST);
    $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
    if ($ascii) {
        $base = str_replace($host, $ascii, $base);
    }
} else {
    $base = str_replace('моя-посылочка.рф', 'xn----8sb3addmbbgv2e2b8b.xn--p1ai', $base);
}

// Служебные страницы, которые не должны попадать в поиск.
$exclude = '/^(admin|app|reset-password|404|test|offline)/i';

$urls = [];
foreach (glob($siteRoot . '/*.html') ?: [] as $file) {
    $name = basename($file);
    if (preg_match($exclude, $name)) {
        continue;
    }
    $html = (string) @file_get_contents($file, false, null, 0, 20000);
    if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]+noindex/i', $html)) {
        continue;
    }
    $loc = $name === 'index.html' ? $base . '/' : $base . '/' . $name;
    $urls[] = [
        'loc'      => $loc,
        'lastmod'  => date('Y-m-d', filemtime($file)),
        'priority' => $name === 'index.html' ? '1.0' : '0.7',
    ];
}
usort($urls, fn($a, $b) => strcmp($b['priority'], $a['priority']) ?: strcmp($a['loc'], $b['loc']));

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n    <loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n"
        . "    <lastmod>{$u['lastmod']}</lastmod>\n    <priority>{$u['priority']}</priority>\n  </url>\n";
}
$xml .= "</urlset>\n";

if (@file_put_contents($siteRoot . '/sitemap.xml', $xml) === false) {
    cron_log('ОШИБКА: не удалось записать ' . $siteRoot . '/sitemap.xml');
    exit(1);
}
cron_log('sitemap.xml обновлён: ' . count($urls) . ' стр.');
