<?php
/**
 * Постраничный просмотр списков и выгрузка в Excel (CSV).
 *
 * Использование на странице списка:
 *   $pg = crm_paginate($pdo, 'SELECT COUNT(*) FROM ... WHERE ...', $params);
 *   $sql .= ' LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'];
 *   ...
 *   <?= crm_pagination_html($pg) ?>
 *
 * Выгрузка: если в адресе есть ?export=csv — страница отдаёт файл со ВСЕМИ
 * строками под текущими фильтрами (без разбиения на страницы).
 */

const CRM_PER_PAGE = 50;

/** Пользователь запросил выгрузку текущего списка в Excel. */
function crm_is_export(): bool
{
    return ($_GET['export'] ?? '') === 'csv';
}

/**
 * Считает общее число строк и вычисляет текущую страницу.
 * @return array{page:int,per:int,offset:int,total:int,pages:int}
 */
function crm_paginate(PDO $pdo, string $countSql, array $params = [], int $per = CRM_PER_PAGE, string $pageParam = 'page'): array
{
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    $page = (int) ($_GET[$pageParam] ?? 1);
    $page = max(1, min($page, $pages));
    return [
        'page'   => $page,
        'per'    => $per,
        'offset' => ($page - 1) * $per,
        'total'  => $total,
        'pages'  => $pages,
        'param'  => $pageParam,
    ];
}

/** SQL-хвост LIMIT/OFFSET для текущей страницы (или пусто при выгрузке). */
function crm_limit_sql(array $pg): string
{
    if (crm_is_export()) {
        return '';
    }
    return ' LIMIT ' . (int) $pg['per'] . ' OFFSET ' . (int) $pg['offset'];
}

/** Ссылка на текущую страницу с изменёнными GET-параметрами (фильтры сохраняются). */
function crm_url_with(array $changes): string
{
    $query = array_merge($_GET, $changes);
    foreach ($query as $k => $v) {
        if ($v === null || $v === '') {
            unset($query[$k]);
        }
    }
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    return $path . ($query ? '?' . http_build_query($query) : '');
}

/** Блок «Показано 51–100 из 340 · ‹ 1 2 3 … 7 ›». */
function crm_pagination_html(array $pg): string
{
    $total = $pg['total'];
    if ($total === 0) {
        return '';
    }
    $from = $pg['offset'] + 1;
    $to = min($pg['offset'] + $pg['per'], $total);
    $html = '<div class="pagination" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-top:14px;">';
    $html .= '<span class="text-muted" style="margin-right:8px;">Показано ' . $from . '–' . $to . ' из ' . $total . '</span>';
    if ($pg['pages'] > 1) {
        $param = $pg['param'];
        $cur = $pg['page'];
        $link = function (int $p, string $label, bool $active = false) use ($param): string {
            $cls = $active ? 'btn small' : 'btn small secondary';
            return '<a class="' . $cls . '" href="' . e(crm_url_with([$param => $p > 1 ? $p : null])) . '">' . $label . '</a>';
        };
        if ($cur > 1) {
            $html .= $link($cur - 1, '‹');
        }
        $shown = [];
        foreach ([1, 2, $cur - 2, $cur - 1, $cur, $cur + 1, $cur + 2, $pg['pages'] - 1, $pg['pages']] as $p) {
            if ($p >= 1 && $p <= $pg['pages']) {
                $shown[$p] = true;
            }
        }
        ksort($shown);
        $prev = 0;
        foreach (array_keys($shown) as $p) {
            if ($prev && $p > $prev + 1) {
                $html .= '<span class="text-muted">…</span>';
            }
            $html .= $link($p, (string) $p, $p === $cur);
            $prev = $p;
        }
        if ($cur < $pg['pages']) {
            $html .= $link($cur + 1, '›');
        }
    }
    $html .= '</div>';
    return $html;
}

/** Кнопка «Скачать в Excel» для текущего списка с текущими фильтрами. */
function crm_export_button(string $label = 'Скачать в Excel'): string
{
    return '<a class="btn secondary" href="' . e(crm_url_with(['export' => 'csv', 'page' => null])) . '">⬇ ' . e($label) . '</a>';
}

/**
 * Отдаёт CSV, который Excel открывает без «кракозябр» (UTF-8 с BOM, разделитель «;»),
 * и завершает выполнение страницы.
 * @param string[] $headers
 * @param iterable<array> $rows Каждая строка — массив значений в порядке заголовков.
 */
function crm_csv_download(string $filename, array $headers, iterable $rows): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';', '"', '\\');
    foreach ($rows as $row) {
        $clean = [];
        foreach ($row as $value) {
            $value = (string) ($value ?? '');
            // Защита от «формульных» инъекций в Excel (=, +, -, @ в начале ячейки).
            if ($value !== '' && strpbrk($value[0], '=+-@') !== false && !is_numeric($value)) {
                $value = "'" . $value;
            }
            $clean[] = $value;
        }
        fputcsv($out, $clean, ';', '"', '\\');
    }
    fclose($out);
    if (function_exists('crm_audit')) {
        crm_audit('export', $filename, null, count($headers) . ' столбцов');
    }
    exit;
}

/** Число для CSV в «русском» виде (запятая), чтобы Excel считал его числом. */
function crm_csv_num($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return str_replace('.', ',', (string) (float) $value);
}
