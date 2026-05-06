<?php

/**
 * Периодические агенты модуля: сбор метрик и обслуживание хранилища снимков.
 *
 * Методы `runChecks()` и `clearOldMetrics()` вызываются из таблицы агентов Битрикс.
 * Важно: для периодических агентов возвращаемое значение должно быть строкой PHP-кода
 * следующего запуска той же функции — иначе агент может быть удалён после первого выполнения.
 *
 * @package mrLexndr\Monitoring
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\Json;
use mrLexndr\Monitoring\Checkers\AbstractChecker;
use mrLexndr\Monitoring\Checkers\CheckerInterface;

/**
 * Управление фоновыми задачами модуля мониторинга.
 */
final class Agent
{
    /** Строка возврата для периодического агента сбора метрик. */
    private const AGENT_CALLBACK_RUN_CHECKS = '\\mrLexndr\\Monitoring\\Agent::runChecks();';

    /** Строка возврата для периодического агента очистки истории. */
    private const AGENT_CALLBACK_CLEAR_OLD = '\\mrLexndr\\Monitoring\\Agent::clearOldMetrics();';

    /**
     * Сбор метрик по расписанию и отправка уведомлений при смене статусов.
     *
     * @param bool $force Принудительный запуск без учёта интервала последней проверки.
     *
     * @return non-empty-string PHP-код следующего запуска агента (обязательно для периодики).
     */
    public static function runChecks(bool $force = false): string
    {
        if (!Loader::includeModule('mrlexndr.monitoring')) {
            return self::AGENT_CALLBACK_RUN_CHECKS;
        }

        foreach (Registry::getCheckers() as $checker) {
            self::processChecker($checker, $force);
        }

        return self::AGENT_CALLBACK_RUN_CHECKS;
    }

    /**
     * Удаление записей метрик старше 30 суток.
     *
     * @return non-empty-string PHP-код следующего запуска агента (обязательно для периодики).
     */
    public static function clearOldMetrics(): string
    {
        if (!Loader::includeModule('mrlexndr.monitoring')) {
            return self::AGENT_CALLBACK_CLEAR_OLD;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $table = MetricsTable::getTableName();

        $border = DateTime::createFromPhp(new \DateTimeImmutable('-30 days'));
        $borderSql = $helper->convertToDbDateTime($border);

        $connection->queryExecute(
            'DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE `DATE_CHECK` < ' . $borderSql
        );

        return self::AGENT_CALLBACK_CLEAR_OLD;
    }

    /**
     * Выполняет один чекер: интервал, запись результата, при необходимости — уведомления.
     *
     * Исключения чекеров перехватываются и сохраняются как результат метрики со статусом ERROR.
     */
    private static function processChecker(CheckerInterface $checker, bool $force): void
    {
        $code = $checker->getCode();

        $interval = $checker instanceof AbstractChecker
            ? $checker->getEffectiveIntervalSeconds()
            : $checker->getDefaultInterval();

        if (!$force && !self::shouldRunNow($code, $interval)) {
            return;
        }

        $prevRow = MetricsTable::getLastByMetricCode($code);
        $prevStatus = isset($prevRow['STATUS']) ? (string)$prevRow['STATUS'] : null;

        try {
            $result = $checker->run();
        } catch (\Throwable $e) {
            $result = [
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => $e->getMessage(),
            ];
        }

        $result = self::normalizeResultArray($result);

        $threshold = max(1, (int)Option::get('mrlexndr.monitoring', 'error_consecutive_threshold', '2'));

        $stateRaw = Option::get('mrlexndr.monitoring', 'consecutive_errors_state', '{}');
        try {
            /** @var array<string, int> $state */
            $state = Json::decode($stateRaw);
        } catch (\Throwable) {
            $state = [];
        }
        if (!is_array($state)) {
            $state = [];
        }

        $count = (int)($state[$code] ?? 0);
        if ($result['STATUS'] === 'ERROR') {
            $count++;
        } else {
            $count = 0;
        }
        $state[$code] = $count;
        Option::set('mrlexndr.monitoring', 'consecutive_errors_state', Json::encode($state, JSON_UNESCAPED_UNICODE));

        $shouldNotify = self::shouldNotify($prevStatus, $result['STATUS'], $count, $threshold);

        try {
            MetricsTable::add([
                'DATE_CHECK' => new DateTime(),
                'METRIC_CODE' => $code,
                'METRIC_VALUE' => $result['VALUE'],
                'STATUS' => $result['STATUS'],
                'MESSAGE' => mb_substr($result['MESSAGE'], 0, 1024),
            ]);
        } catch (\Throwable) {
            return;
        }

        if (!$shouldNotify) {
            return;
        }

        foreach (Registry::getNotifiers() as $notifier) {
            try {
                $notifier->send($checker, $result);
            } catch (\Throwable $e) {
                \CEventLog::Add([
                    'SEVERITY' => 'WARNING',
                    'AUDIT_TYPE_ID' => 'MRLEXNDR_MONITORING_NOTIFIER_ERROR',
                    'MODULE_ID' => 'mrlexndr.monitoring',
                    'ITEM_ID' => \get_class($notifier),
                    'DESCRIPTION' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Определяет, прошёл ли интервал с последней успешной записи метрики.
     */
    private static function shouldRunNow(string $metricCode, int $intervalSeconds): bool
    {
        $last = MetricsTable::getLastByMetricCode($metricCode);
        if ($last === null) {
            return true;
        }

        /** @var mixed $dt */
        $dt = $last['DATE_CHECK'] ?? null;
        if ($dt instanceof DateTime) {
            $lastTs = $dt->getTimestamp();
        } else {
            $lastTs = strtotime((string)$dt);
        }

        if ($lastTs <= 0) {
            return true;
        }

        return (time() - $lastTs) >= $intervalSeconds;
    }

    /**
     * Нормализует результат чекера перед записью в БД.
     *
     * @param array<mixed> $result Сырой результат.
     *
     * @return array{VALUE: string, STATUS: string, MESSAGE: string}
     */
    private static function normalizeResultArray(array $result): array
    {
        $status = strtoupper((string)($result['STATUS'] ?? 'ERROR'));
        if (!in_array($status, ['OK', 'WARNING', 'ERROR'], true)) {
            $status = 'ERROR';
        }

        return [
            'VALUE' => (string)($result['VALUE'] ?? ''),
            'STATUS' => $status,
            'MESSAGE' => (string)($result['MESSAGE'] ?? ''),
        ];
    }

    /**
     * Решает, нужно ли отправлять уведомление с учётом настроек и антифлапа по ERROR.
     */
    private static function shouldNotify(?string $prevStatus, string $newStatus, int $consecutiveErrors, int $threshold): bool
    {
        $notifyError = Option::get('mrlexndr.monitoring', 'notify_on_error', 'Y') === 'Y';
        $notifyWarning = Option::get('mrlexndr.monitoring', 'notify_on_warning', 'Y') === 'Y';
        $notifyOk = Option::get('mrlexndr.monitoring', 'notify_on_ok', 'Y') === 'Y';

        if ($newStatus === 'OK' && ($prevStatus === 'ERROR' || $prevStatus === 'WARNING')) {
            return $notifyOk;
        }

        if ($newStatus === 'WARNING' && $prevStatus !== null && $prevStatus !== 'WARNING') {
            return $notifyWarning;
        }

        if ($newStatus === 'ERROR' && $notifyError) {
            return $consecutiveErrors === $threshold;
        }

        return false;
    }
}
