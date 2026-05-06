<?php

/**
 * Базовый класс проверок: чтение JSON-настроек и интервалов из опций модуля.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\Json;

/**
 * Общая логика доступа к настройкам чекера в `Option` модуля `mrlexndr.monitoring`.
 */
abstract class AbstractChecker implements CheckerInterface
{
    /**
     * Эффективный интервал запуска с учётом опции `checker_interval_<CODE>`.
     */
    public function getEffectiveIntervalSeconds(): int
    {
        return $this->getIntervalOption();
    }

    /**
     * Идентификатор модуля для Option API.
     */
    protected function getModuleId(): string
    {
        return 'mrlexndr.monitoring';
    }

    /**
     * Декодированные настройки чекера из опции `checker_settings_<CODE>` (JSON).
     *
     * @return array<string, mixed>
     */
    protected function getCheckerSettings(): array
    {
        $raw = Option::get($this->getModuleId(), 'checker_settings_' . $this->getCode(), '');
        if ($raw === '' || $raw === '0') {
            return [];
        }

        try {
            $decoded = Json::decode($raw);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Интервал из опции или значение по умолчанию из чекера.
     */
    protected function getIntervalOption(): int
    {
        $raw = Option::get($this->getModuleId(), 'checker_interval_' . $this->getCode(), '');
        $raw = trim((string)$raw);
        if ($raw === '') {
            return $this->getDefaultInterval();
        }

        $int = (int)$raw;
        return $int > 0 ? $int : $this->getDefaultInterval();
    }

    /**
     * Приводит статус к допустимому перечислению.
     *
     * @param array{VALUE: string, STATUS: string, MESSAGE: string} $result
     *
     * @return array{VALUE: string, STATUS: string, MESSAGE: string}
     */
    protected function normalizeResult(array $result): array
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
}
