<?php

/**
 * Обертка в `/bitrix/admin/`: страница истории метрик из каталога модуля.
 */

declare(strict_types=1);

$mrlexndrMonitoringModuleRoot = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/mrlexndr.monitoring';
if (!is_file($mrlexndrMonitoringModuleRoot . '/include.php')) {
    $mrlexndrMonitoringModuleRoot = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/mrlexndr.monitoring';
}

require $mrlexndrMonitoringModuleRoot . '/admin/history.php';
