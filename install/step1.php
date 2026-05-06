<?php

/**
 * Завершающий шаг установки: информационные сообщения об успехе и предупреждение про Cron.
 */

declare(strict_types=1);

if (!check_bitrix_sessid()) {
    return;
}

global $APPLICATION;

IncludeModuleLangFile(__DIR__ . '/index.php');

echo CAdminMessage::ShowNote(GetMessage('MRLEXNDR_MONITORING_INSTALL_DONE'));

if (!defined('BX_CRONTAB') || BX_CRONTAB !== true) {
    echo CAdminMessage::ShowMessage([
        'TYPE' => 'ERROR',
        'MESSAGE' => GetMessage('MRLEXNDR_MONITORING_INSTALL_CRON_WARN'),
        'HTML' => true,
    ]);
}
