<?php

/**
 * Оценка времени до первого байта ответа (TTFB) через {@see HttpClient}.
 *
 * Перед запросом ограничиваются редиректы и отключается строгая проверка SSL для устойчивости в dev/stage.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

/**
 * Проверка скорости ответа по HTTP(S) для заданного URL.
 */
final class Ttfb extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'TTFB';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'TTFB главной страницы';
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
            ['url', 'URL для проверки (полный)', '', ['text', 80]],
            ['warn_ms', 'Порог WARNING (мс)', '1500', ['text', 10]],
            ['error_ms', 'Порог ERROR (мс)', '3000', ['text', 10]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $url = isset($settings['url']) ? trim((string)$settings['url']) : '';
        if ($url === '') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            $url = $proto . '://' . $host . '/';
        }

        $warnMs = isset($settings['warn_ms']) ? (float)$settings['warn_ms'] : 1500.0;
        $errorMs = isset($settings['error_ms']) ? (float)$settings['error_ms'] : 3000.0;

        $client = new HttpClient();
        $client->setTimeout(5);
        $client->setStreamTimeout(5);
        $client->setRedirectMax(2);
        $client->disableSslVerification();

        $start = microtime(true);
        $ok = $client->get($url);
        $elapsedMs = (microtime(true) - $start) * 1000.0;

        $statusCode = method_exists($client, 'getStatus') ? (int)$client->getStatus() : 0;

        if (!$ok) {
            $errors = $client->getError();
            return $this->normalizeResult([
                'VALUE' => Json::encode(['url' => $url, 'ms' => round($elapsedMs, 2)], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'ERROR',
                'MESSAGE' => 'HTTP запрос не выполнен: ' . (string)$errors,
            ]);
        }

        if ($statusCode >= 400) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['url' => $url, 'http' => $statusCode, 'ms' => round($elapsedMs, 2)], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'ERROR',
                'MESSAGE' => 'HTTP код ответа: ' . $statusCode,
            ]);
        }

        $payload = [
            'url' => $url,
            'http' => $statusCode,
            'ms' => round($elapsedMs, 2),
        ];

        $status = 'OK';
        $message = 'TTFB ≈ ' . round($elapsedMs, 0) . ' мс';
        if ($elapsedMs >= $errorMs) {
            $status = 'ERROR';
            $message = 'Медленный ответ (TTFB ≈ ' . round($elapsedMs, 0) . ' мс)';
        } elseif ($elapsedMs >= $warnMs) {
            $status = 'WARNING';
            $message = 'Повышенный TTFB (≈ ' . round($elapsedMs, 0) . ' мс)';
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
