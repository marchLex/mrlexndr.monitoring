<?php

/**
 * Проверка свободного места на диске для указанного пути.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Web\Json;

/**
 * Метрика доли свободного места относительно объёма раздела.
 */
final class DiskSpace extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'DISK_SPACE';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Свободное место на диске';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultInterval(): int
    {
        return 300;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsOptions(): array
    {
        return [
            ['path', 'Путь для проверки', '', ['text', 80]],
            ['min_free_percent', 'Минимум свободного места (%)', '10', ['text', 10]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $path = isset($settings['path']) ? trim((string)$settings['path']) : '';
        if ($path === '') {
            $path = (string)($_SERVER['DOCUMENT_ROOT'] ?: '/');
        }

        $minPercent = isset($settings['min_free_percent']) ? (float)$settings['min_free_percent'] : 10.0;
        if ($minPercent <= 0 || $minPercent > 100) {
            $minPercent = 10.0;
        }

        if (!is_dir($path)) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['path' => $path], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Путь не найден или недоступен.',
            ]);
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free === false || $total === false || $total <= 0) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => 'Не удалось получить данные о диске.',
            ]);
        }

        $freePercent = ($free / $total) * 100.0;

        $payload = [
            'path' => $path,
            'free_bytes' => $free,
            'total_bytes' => $total,
            'free_percent' => round($freePercent, 2),
            'min_free_percent' => $minPercent,
        ];

        $status = 'OK';
        $message = 'Свободно: ' . round($freePercent, 1) . '%';

        if ($freePercent < max(1.0, $minPercent * 0.25)) {
            $status = 'ERROR';
            $message = 'Критически мало места на диске: ' . round($freePercent, 1) . '%';
        } elseif ($freePercent < $minPercent) {
            $status = 'WARNING';
            $message = 'Мало места на диске: ' . round($freePercent, 1) . '%';
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
