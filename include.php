<?php

/**
 * Точка подключения модуля `mrlexndr.monitoring`.
 *
 * Автозагрузка классов через классическую регистрацию Bitrix (`Loader::registerAutoLoadClasses`).
 */

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

\Bitrix\Main\Loader::registerAutoLoadClasses(
    'mrlexndr.monitoring',
    [
        'mrLexndr\\Monitoring\\Agent' => 'lib/mrLexndr/Monitoring/Agent.php',
        'mrLexndr\\Monitoring\\Checkers\\AbstractChecker' => 'lib/mrLexndr/Monitoring/Checkers/AbstractChecker.php',
        'mrLexndr\\Monitoring\\Checkers\\Backups' => 'lib/mrLexndr/Monitoring/Checkers/Backups.php',
        'mrLexndr\\Monitoring\\Checkers\\CheckerInterface' => 'lib/mrLexndr/Monitoring/Checkers/CheckerInterface.php',
        'mrLexndr\\Monitoring\\Checkers\\DiskSpace' => 'lib/mrLexndr/Monitoring/Checkers/DiskSpace.php',
        'mrLexndr\\Monitoring\\Checkers\\DomainExpire' => 'lib/mrLexndr/Monitoring/Checkers/DomainExpire.php',
        'mrLexndr\\Monitoring\\Checkers\\HttpCodes' => 'lib/mrLexndr/Monitoring/Checkers/HttpCodes.php',
        'mrLexndr\\Monitoring\\Checkers\\LicenseExpire' => 'lib/mrLexndr/Monitoring/Checkers/LicenseExpire.php',
        'mrLexndr\\Monitoring\\Checkers\\SslExpire' => 'lib/mrLexndr/Monitoring/Checkers/SslExpire.php',
        'mrLexndr\\Monitoring\\Checkers\\Ttfb' => 'lib/mrLexndr/Monitoring/Checkers/Ttfb.php',
        'mrLexndr\\Monitoring\\MetricsTable' => 'lib/mrLexndr/Monitoring/MetricsTable.php',
        'mrLexndr\\Monitoring\\Notifiers\\EmailNotifier' => 'lib/mrLexndr/Monitoring/Notifiers/EmailNotifier.php',
        'mrLexndr\\Monitoring\\Notifiers\\NotifierInterface' => 'lib/mrLexndr/Monitoring/Notifiers/NotifierInterface.php',
        'mrLexndr\\Monitoring\\Notifiers\\TelegramNotifier' => 'lib/mrLexndr/Monitoring/Notifiers/TelegramNotifier.php',
        'mrLexndr\\Monitoring\\Registry' => 'lib/mrLexndr/Monitoring/Registry.php',
    ]
);
