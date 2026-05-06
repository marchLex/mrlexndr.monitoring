<?php

/**
 * AJAX endpoint: принудительный запуск сбора метрик (`Agent::runChecks(true)`).
 *
 * Файл копируется инсталлятором в `/bitrix/admin/mrlexndr_monitoring_ajax_run_checks.php`.
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

header('Content-Type: application/json; charset=UTF-8');

if (!$USER->IsAdmin() && $APPLICATION->GetGroupRight('mrlexndr.monitoring') < 'W') {
    echo Json::encode(['success' => false, 'error' => 'ACCESS_DENIED']);
    die();
}

if (!check_bitrix_sessid()) {
    echo Json::encode(['success' => false, 'error' => 'SESSION_EXPIRED']);
    die();
}

if (!Loader::includeModule('mrlexndr.monitoring')) {
    echo Json::encode(['success' => false, 'error' => 'MODULE_LOAD_FAILED']);
    die();
}

\mrLexndr\Monitoring\Agent::runChecks(true);

echo Json::encode(['success' => true]);
die();
