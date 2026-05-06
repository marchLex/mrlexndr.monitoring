<?php

/**
 * Проверка актуальности файлов резервного копирования в каталоге `/bitrix/backup/`.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Web\Json;

/**
 * Метрика «наличие свежей резервной копии» по времени модификации файлов.
 */
final class Backups extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'BACKUPS';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Резервные копии (/bitrix/backup/)';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultInterval(): int
    {
        return 3600;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsOptions(): array
    {
        return [
            ['path', 'Каталог резервных копий (абсолютный путь)', '', ['text', 80]],
            ['max_age_hours', 'Предупреждение если старше (часов)', '48', ['text', 10]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
        $path = isset($settings['path']) ? trim((string)$settings['path']) : '';
        if ($path === '') {
            $path = rtrim($docRoot, '/\\') . '/bitrix/backup';
        }

        $maxAgeHours = isset($settings['max_age_hours']) ? (float)$settings['max_age_hours'] : 48.0;
        if ($maxAgeHours <= 0) {
            $maxAgeHours = 48.0;
        }

        if (!is_dir($path)) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['path' => $path], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Каталог резервных копий не найден: ' . $path,
            ]);
        }

        $files = [];
        foreach (['*.tar', '*.tar.gz', '*.tgz', '*.zip', '*.gz'] as $pattern) {
            foreach ((array)glob(rtrim($path, '/\\') . '/' . $pattern) as $f) {
                if (is_file($f)) {
                    $files[] = $f;
                }
            }
        }

        if ($files === []) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['path' => $path, 'files' => 0], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'ERROR',
                'MESSAGE' => 'Файлы резервных копий не найдены.',
            ]);
        }

        $latestMtime = 0;
        $latestFile = '';
        foreach ($files as $file) {
            $mtime = (int)filemtime($file);
            if ($mtime > $latestMtime) {
                $latestMtime = $mtime;
                $latestFile = $file;
            }
        }

        $ageHours = (time() - $latestMtime) / 3600;

        $payload = [
            'path' => $path,
            'latest_file' => basename($latestFile),
            'latest_mtime' => $latestMtime,
            'latest_iso' => gmdate('c', $latestMtime),
            'age_hours' => round($ageHours, 2),
        ];

        $status = 'OK';
        $message = 'Последняя резервная копия свежая (≈ ' . round($ageHours, 1) . ' ч назад).';
        if ($ageHours > $maxAgeHours) {
            $status = 'WARNING';
            $message = 'Давно не было новых резервных копий (≈ ' . round($ageHours, 1) . ' ч).';
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
