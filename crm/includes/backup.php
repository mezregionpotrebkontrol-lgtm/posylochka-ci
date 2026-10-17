<?php
/**
 * Резервные копии базы CRM.
 *
 * Делает полный SQL-дамп (структура + данные) средствами PHP — не нужен
 * mysqldump и доступ по SSH. Файл сжимается (.sql.gz) и кладётся в папку
 * ВНЕ сайта (по умолчанию — рядом с папкой сайта: ../posylochka-backups),
 * чтобы его нельзя было скачать по ссылке. Старые копии удаляются
 * автоматически (по умолчанию хранятся 14 последних дней).
 *
 * Восстановление: phpMyAdmin -> база -> «Импорт» -> выбрать .sql.gz.
 */

function crm_backup_config(): array
{
    $cfg = crm_config()['backup'] ?? [];
    return [
        'dir'       => $cfg['dir'] ?? null,
        'keep_days' => max(1, (int) ($cfg['keep_days'] ?? 14)),
    ];
}

/** Папка для копий; создаётся при необходимости. Возвращает путь или null. */
function crm_backup_dir(): ?string
{
    $cfg = crm_backup_config();
    $candidates = [];
    if (!empty($cfg['dir'])) {
        $candidates[] = $cfg['dir'];
    }
    // Папка сайта = на уровень выше crm/. Кладём копии рядом с ней, вне веб-доступа.
    $candidates[] = dirname(__DIR__, 3) . '/posylochka-backups';
    // Запасной вариант — внутри crm/, но с запретом доступа через .htaccess.
    $candidates[] = dirname(__DIR__) . '/backups';

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            if (!file_exists($dir . '/.htaccess')) {
                @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            }
            if (!file_exists($dir . '/index.html')) {
                @file_put_contents($dir . '/index.html', '');
            }
            return rtrim($dir, '/');
        }
    }
    return null;
}

/**
 * Создаёт копию базы. Возвращает [путь к файлу|null, ошибка|null].
 * @return array{0:?string,1:?string}
 */
function crm_backup_create(): array
{
    @set_time_limit(600);
    $dir = crm_backup_dir();
    if ($dir === null) {
        return [null, 'Нет папки для резервных копий с правом записи.'];
    }
    $pdo = crm_db();
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $file = $dir . '/crm_' . date('Y-m-d_H-i-s') . '.sql.gz';
    $gz = @gzopen($file, 'wb6');
    if (!$gz) {
        return [null, 'Не удалось создать файл копии.'];
    }

    try {
        gzwrite($gz, "-- Резервная копия CRM «Посылочка»\n-- База: {$dbName}\n-- Создана: " . date('d.m.Y H:i:s') . "\n\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        $raw = new PDO(
            'mysql:host=' . crm_config()['db']['host'] . ';dbname=' . crm_config()['db']['name'] . ';charset=utf8mb4',
            crm_config()['db']['user'],
            crm_config()['db']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]
        );

        foreach ($tables as [$table]) {
            $q = '`' . str_replace('`', '``', $table) . '`';
            $create = $pdo->query('SHOW CREATE TABLE ' . $q)->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS {$q};\n{$create};\n\n");

            $stmt = $raw->query('SELECT * FROM ' . $q);
            $batch = [];
            $cols = null;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($cols === null) {
                    $cols = '(' . implode(',', array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                }
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 200) {
                    gzwrite($gz, "INSERT INTO {$q} {$cols} VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                gzwrite($gz, "INSERT INTO {$q} {$cols} VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            $stmt->closeCursor();
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n-- Конец копии\n");
        gzclose($gz);
    } catch (Throwable $e) {
        @gzclose($gz);
        @unlink($file);
        return [null, 'Ошибка при создании копии: ' . $e->getMessage()];
    }

    @chmod($file, 0600);
    crm_backup_rotate();
    return [$file, null];
}

/** Удаляет копии старше keep_days, но всегда оставляет минимум 3 последние. */
function crm_backup_rotate(): int
{
    $keepDays = crm_backup_config()['keep_days'];
    $list = crm_backup_list();
    $removed = 0;
    foreach (array_slice($list, 3) as $b) {
        if ($b['mtime'] < time() - $keepDays * 86400) {
            if (@unlink($b['path'])) {
                $removed++;
            }
        }
    }
    return $removed;
}

/** Список копий, новые сверху. */
function crm_backup_list(): array
{
    $dir = crm_backup_dir();
    if ($dir === null) {
        return [];
    }
    $out = [];
    foreach (glob($dir . '/crm_*.sql.gz') ?: [] as $path) {
        $out[] = ['name' => basename($path), 'path' => $path, 'size' => filesize($path), 'mtime' => filemtime($path)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

function crm_human_size(int $bytes): string
{
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' КБ';
    }
    return round($bytes / 1024 / 1024, 1) . ' МБ';
}
