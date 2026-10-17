<?php
/**
 * Ежедневная резервная копия базы CRM.
 *
 * Добавьте задание в ispmanager -> Планировщик (CRON), раз в сутки, ночью:
 *   /opt/php/8.3/bin/php -f /var/www/u3661226/data/www/моя-посылочка.рф/crm/cron/backup.php
 * (путь к php и к сайту уточните в ispmanager — см. INSTRUKCIYA.md в корне репозитория).
 */
require __DIR__ . '/_cli.php';
require_once __DIR__ . '/../includes/backup.php';

[$file, $err] = crm_backup_create();
if ($err !== null) {
    cron_log('ОШИБКА: ' . $err);
    crm_notify_owner('⚠️ Не удалось сделать резервную копию базы CRM: ' . $err);
    exit(1);
}
crm_audit('backup', 'backup', null, basename($file) . ', ' . crm_human_size((int) filesize($file)), null);
cron_log('Копия создана: ' . $file . ' (' . crm_human_size((int) filesize($file)) . ')');
