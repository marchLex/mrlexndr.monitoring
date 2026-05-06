<?php

/**
 * Инсталлятор и деинсталлятор модуля `mrlexndr.monitoring`.
 *
 * Создаёт таблицу метрик, регистрирует почтовое событие, копирует административные файлы,
 * добавляет периодические агенты и выполняет очистку при удалении.
 *
 * @package mrlexndr.monitoring
 */

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Internal\EventMessageTable;
use Bitrix\Main\Mail\Internal\EventTypeTable;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\SiteTable;
use mrLexndr\Monitoring\MetricsTable;

/**
 * Класс установки модуля в административном интерфейсе Битрикс.
 */
class mrlexndr_monitoring extends CModule
{
    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_ID = 'mrlexndr.monitoring';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = GetMessage('MRLEXNDR_MONITORING_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('MRLEXNDR_MONITORING_MODULE_DESC');
        $this->PARTNER_NAME = 'mrlexndr';
        $this->PARTNER_URI = 'https://mrlexndr.local';
    }

    /**
     * Установка модуля: проверки окружения, БД, события, файлы, агенты.
     */
    public function DoInstall(): bool
    {
        global $APPLICATION;

        if (!$this->checkEnvironment()) {
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        if (!Loader::includeModule($this->MODULE_ID)) {
            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->ThrowException(GetMessage('MRLEXNDR_MONITORING_INSTALL_MODULE_LOAD'));
            return false;
        }

        if (!$this->InstallDB()) {
            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->ThrowException(GetMessage('MRLEXNDR_MONITORING_INSTALL_MODULE_LOAD'));
            return false;
        }

        $this->InstallEvents();
        $this->InstallFiles();

        $this->InstallAgents();

        $APPLICATION->IncludeAdminFile(
            GetMessage('MRLEXNDR_MONITORING_INSTALL_TITLE'),
            __DIR__ . '/step1.php'
        );

        return true;
    }

    /**
     * Удаление модуля в два шага (подтверждение сохранения таблиц).
     */
    public function DoUninstall(): void
    {
        global $APPLICATION;

        $step = (int)($_REQUEST['step'] ?? 0);
        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                GetMessage('MRLEXNDR_MONITORING_UNINSTALL_TITLE'),
                __DIR__ . '/unstep1.php'
            );

            return;
        }

        $saveTables = $_REQUEST['savedata'] ?? '';
        if ($saveTables !== 'Y') {
            $this->UnInstallDB();
        }

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallAgents();

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    /**
     * Создание таблицы и индекса метрик.
     */
    public function InstallDB(): bool
    {
        $this->loadMetricsTableClass();

        if (!Loader::includeModule($this->MODULE_ID)) {
            return false;
        }

        $connection = Application::getConnection();

        if (!$connection->isTableExists(MetricsTable::getTableName())) {
            MetricsTable::getEntity()->createDbTable();
        }

        $table = MetricsTable::getTableName();

        try {
            $connection->queryExecute(
                'CREATE INDEX IX_MRLEXNDR_METRICS_CODE_DATE ON `'
                . str_replace('`', '``', $table)
                . '` (`METRIC_CODE`, `DATE_CHECK`)'
            );
        } catch (\Throwable) {
            // Индекс уже может существовать при переустановке.
        }

        return true;
    }

    /**
     * Удаление таблицы метрик и опций модуля (если таблица существует).
     */
    public function UnInstallDB(): bool
    {
        $this->loadMetricsTableClass();

        if (!Loader::includeModule($this->MODULE_ID)) {
            return false;
        }

        $connection = Application::getConnection();
        $tableName = MetricsTable::getTableName();

        if ($connection->isTableExists($tableName)) {
            $connection->dropTable($tableName);
        }

        Option::deleteForModule($this->MODULE_ID);

        return true;
    }

    /**
     * Регистрация типа почтового события и шаблона сообщения.
     */
    public function InstallEvents(): bool
    {
        if (!Loader::includeModule('main')) {
            return false;
        }

        $exists = EventTypeTable::getList([
            'filter' => ['=EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT'],
            'limit' => 1,
        ])->fetch();

        if (!$exists) {
            EventTypeTable::add([
                'LID' => 'ru',
                'EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT',
                'NAME' => GetMessage('MRLEXNDR_MONITORING_MAIL_EVENT_NAME'),
                'DESCRIPTION' => GetMessage('MRLEXNDR_MONITORING_MAIL_EVENT_DESC'),
                'SORT' => 100,
                'EVENT_TYPE' => 'email',
            ]);
        }

        $msgExists = EventMessageTable::getList([
            'filter' => ['=EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT'],
            'limit' => 1,
        ])->fetch();

        if (!$msgExists) {
            $lid = 's1';
            $defSite = SiteTable::getList([
                'filter' => ['=DEF' => 'Y', '=ACTIVE' => 'Y'],
                'limit' => 1,
                'select' => ['LID'],
            ])->fetch();
            if (is_array($defSite) && !empty($defSite['LID'])) {
                $lid = (string)$defSite['LID'];
            }

            EventMessageTable::add([
                'EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT',
                'LID' => $lid,
                'ACTIVE' => 'Y',
                'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                'EMAIL_TO' => '#EMAIL_TO#',
                'SUBJECT' => '#CHECK_CODE#: #STATUS#',
                'MESSAGE' => "#CHECK_NAME# (#CHECK_CODE#)\nStatus: #STATUS#\nMessage: #MESSAGE#\n\nValue:\n#VALUE#\n",
                'BODY_TYPE' => 'text',
            ]);
        }

        return true;
    }

    /**
     * Удаление почтовых шаблонов и типов событий модуля.
     */
    public function UnInstallEvents(): bool
    {
        if (!Loader::includeModule('main')) {
            return false;
        }

        $messages = EventMessageTable::getList([
            'filter' => ['=EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT'],
            'select' => ['ID'],
        ]);
        while ($row = $messages->fetch()) {
            EventMessageTable::delete((int)$row['ID']);
        }

        $types = EventTypeTable::getList([
            'filter' => ['=EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT'],
            'select' => ['ID'],
        ]);
        while ($row = $types->fetch()) {
            EventTypeTable::delete((int)$row['ID']);
        }

        return true;
    }

    /**
     * Копирование административных файлов в `/bitrix/admin/`.
     */
    public function InstallFiles(): bool
    {
        CopyDirFiles(__DIR__ . '/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/', true, true);

        return true;
    }

    /**
     * Удаление административных файлов из `/bitrix/admin/` по эталону из установки.
     */
    public function UnInstallFiles(): bool
    {
        DeleteDirFiles(__DIR__ . '/admin/', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/');

        return true;
    }

    /**
     * Регистрация периодических агентов с корректными строками возврата (Patch 1).
     */
    public function InstallAgents(): void
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);

        CAgent::AddAgent(
            '\\mrLexndr\\Monitoring\\Agent::runChecks();',
            $this->MODULE_ID,
            'Y',
            180,
            '',
            'Y',
            '',
            100
        );

        CAgent::AddAgent(
            '\\mrLexndr\\Monitoring\\Agent::clearOldMetrics();',
            $this->MODULE_ID,
            'Y',
            86400,
            '',
            'Y',
            '',
            110
        );
    }

    /**
     * Удаление всех агентов модуля.
     */
    public function UnInstallAgents(): void
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
    }

    /**
     * Подключение файла ORM до вызовов MetricsTable: при Install/Uninstall include.php может быть выполнен без полной регистрации классов из карты автозагрузки.
     */
    private function loadMetricsTableClass(): void
    {
        require_once dirname(__DIR__) . '/lib/mrLexndr/Monitoring/MetricsTable.php';
    }

    /**
     * Проверка версии PHP и обязательных расширений.
     *
     * @global CMain $APPLICATION
     */
    private function checkEnvironment(): bool
    {
        global $APPLICATION;

        if (PHP_VERSION_ID < 80100) {
            $APPLICATION->ThrowException(GetMessage('MRLEXNDR_MONITORING_INSTALL_PHP_VERSION'));

            return false;
        }

        if (!extension_loaded('curl')) {
            $APPLICATION->ThrowException(GetMessage('MRLEXNDR_MONITORING_INSTALL_EXT_CURL'));

            return false;
        }

        if (!extension_loaded('sockets')) {
            $APPLICATION->ThrowException(GetMessage('MRLEXNDR_MONITORING_INSTALL_EXT_SOCKETS'));

            return false;
        }

        return true;
    }
}
