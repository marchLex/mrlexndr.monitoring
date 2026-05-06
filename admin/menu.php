<?php

/**
 * Регистрация пункта меню административной части для раздела мониторинга.
 */

declare(strict_types=1);

global $APPLICATION;

IncludeModuleLangFile(__FILE__);

if ($APPLICATION->GetGroupRight('mrlexndr.monitoring') < 'R') {
    return [];
}

$aMenu = [
    'parent_menu' => 'global_menu_services',
    'sort' => 900,
    'text' => GetMessage('MRLEXNDR_MONITORING_MENU_TEXT'),
    'title' => GetMessage('MRLEXNDR_MONITORING_MENU_TEXT'),
    'url' => 'mrlexndr_monitoring_dashboard.php?lang=' . LANGUAGE_ID,
    'icon' => 'sys_menu_icon',
    'page_icon' => 'sys_menu_icon',
    'items_id' => 'menu_mrlexndr_monitoring',
    'items' => [
        [
            'text' => GetMessage('MRLEXNDR_MONITORING_MENU_DASHBOARD'),
            'title' => GetMessage('MRLEXNDR_MONITORING_MENU_DASHBOARD'),
            'url' => 'mrlexndr_monitoring_dashboard.php?lang=' . LANGUAGE_ID,
        ],
        [
            'text' => GetMessage('MRLEXNDR_MONITORING_MENU_HISTORY'),
            'title' => GetMessage('MRLEXNDR_MONITORING_MENU_HISTORY'),
            'url' => 'mrlexndr_monitoring_history.php?lang=' . LANGUAGE_ID,
        ],
    ],
];

return [$aMenu];
