<?php

/**
 * Проверка срока действия TLS-сертификата через `stream_socket_client`.
 *
 * Используется SNI (`SNI_server_name`) для корректной работы на виртуальных хостах.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Web\Json;

/**
 * Проверка истечения SSL-сертификата удалённого хоста.
 */
final class SslExpire extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'SSL_EXPIRE';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Истечение SSL-сертификата';
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultInterval(): int
    {
        return 86400;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsOptions(): array
    {
        return [
            ['host', 'Хост (например, example.com)', '', ['text', 60]],
            ['port', 'Порт', '443', ['text', 10]],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $host = isset($settings['host']) ? trim((string)$settings['host']) : '';
        if ($host === '') {
            $host = (string)($_SERVER['HTTP_HOST'] ?? '');
            $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        }

        if ($host === '') {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не задан хост для проверки SSL.',
            ]);
        }

        $port = isset($settings['port']) ? (int)$settings['port'] : 443;
        if ($port <= 0) {
            $port = 443;
        }

        $ctx = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_server_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'ssl://' . $host . ':' . $port,
            $errno,
            $errstr,
            3,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!is_resource($socket)) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['host' => $host, 'port' => $port], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'ERROR',
                'MESSAGE' => 'SSL подключение не удалось: ' . ($errstr !== '' ? $errstr : (string)$errno),
            ]);
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($cert === null) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => 'Не удалось получить peer certificate.',
            ]);
        }

        $parsed = false;
        if ($cert instanceof \OpenSSLCertificate) {
            $pem = '';
            if (openssl_x509_export($cert, $pem)) {
                $parsed = openssl_x509_parse($pem);
            }
        } else {
            $parsed = @openssl_x509_parse($cert);
        }

        if ($parsed === false || empty($parsed['validTo_time_t'])) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => 'Не удалось распарсить сертификат.',
            ]);
        }

        $expiryTs = (int)$parsed['validTo_time_t'];
        $now = time();
        $daysLeft = (int)floor(($expiryTs - $now) / 86400);

        $payload = [
            'host' => $host,
            'port' => $port,
            'expires_ts' => $expiryTs,
            'expires_iso' => gmdate('c', $expiryTs),
            'days_left' => $daysLeft,
            'subject' => (string)($parsed['subject']['CN'] ?? ''),
        ];

        $status = 'OK';
        $message = 'Сертификат валиден. Осталось дней: ' . $daysLeft;
        if ($daysLeft < 0) {
            $status = 'ERROR';
            $message = 'Сертификат просрочен.';
        } elseif ($daysLeft <= 14) {
            $status = 'WARNING';
            $message = 'Скоро истечение сертификата. Осталось дней: ' . $daysLeft;
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
