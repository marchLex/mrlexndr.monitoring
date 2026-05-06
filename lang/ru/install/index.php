<?php

/**
 * Языковые константы установки и базового описания модуля (`install/index.php`, шаги установки).
 */

$MESS['MRLEXNDR_MONITORING_MODULE_NAME'] = 'Мониторинг базовых показателей';
$MESS['MRLEXNDR_MONITORING_MODULE_DESC'] = 'Локальный мониторинг состояния сервера, инфраструктуры и сайта.';
$MESS['MRLEXNDR_MONITORING_INSTALL_NAME'] = 'Мониторинг базовых показателей';
$MESS['MRLEXNDR_MONITORING_INSTALL_DESCRIPTION'] = 'Локальный мониторинг состояния сервера, инфраструктуры и сайта.';
$MESS['MRLEXNDR_MONITORING_INSTALL_TITLE'] = 'Установка модуля mrlexndr.monitoring';
$MESS['MRLEXNDR_MONITORING_INSTALL_DONE'] = 'Модуль успешно установлен.';
$MESS['MRLEXNDR_MONITORING_UNINSTALL_TITLE'] = 'Удаление модуля mrlexndr.monitoring';
$MESS['MRLEXNDR_MONITORING_INSTALL_MODULE_LOAD'] = 'Не удалось подключить модуль после установки.';
$MESS['MRLEXNDR_MONITORING_INSTALL_PHP_VERSION'] = 'Требуется PHP версии 8.1 или выше.';
$MESS['MRLEXNDR_MONITORING_INSTALL_EXT_CURL'] = 'Требуется расширение PHP curl.';
$MESS['MRLEXNDR_MONITORING_INSTALL_EXT_SOCKETS'] = 'Требуется расширение PHP sockets.';
$MESS['MRLEXNDR_MONITORING_INSTALL_CRON_WARN'] = 'Рекомендуется выполнять агенты на Cron (BX_CRONTAB=true). Иначе проверки могут выполняться на хитах и замедлять сайт.';
$MESS['MRLEXNDR_MONITORING_UNINSTALL_SAVE_TABLE'] = 'Сохранить таблицы базы данных';
$MESS['MRLEXNDR_MONITORING_MAIL_EVENT_NAME'] = 'MrLexndr Monitoring: уведомление о метрике';
$MESS['MRLEXNDR_MONITORING_MAIL_EVENT_DESC'] = 'Отправляется при изменении статусов метрик (через EmailNotifier).';
