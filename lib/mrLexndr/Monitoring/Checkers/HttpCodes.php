<?php

/**
 * Проверка HTTP-кодов ответа для списка URL (правила в JSON).
 *
 * Перед запросами ограничиваются редиректы и отключается строгая проверка SSL (см. Patch 1).
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

/**
 * Сверка фактического HTTP-статуса с ожидаемым для набора URL.
 */
final class HttpCodes extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'HTTP_CODES';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'HTTP коды страниц';
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
            [
                'rules_json',
                'Правила JSON: [{"url":"https://site/","code":200}]',
                '[{"url":"https://example.com/","code":200}]',
                ['textarea', 10, 60],
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        $settings = $this->getCheckerSettings();
        $raw = isset($settings['rules_json']) ? (string)$settings['rules_json'] : '';
        if (trim($raw) === '') {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не заданы правила проверки HTTP (rules_json).',
            ]);
        }

        try {
            $rules = Json::decode($raw);
        } catch (\Throwable $e) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'ERROR',
                'MESSAGE' => 'Некорректный JSON правил: ' . $e->getMessage(),
            ]);
        }

        if (!is_array($rules) || $rules === []) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Пустой список правил.',
            ]);
        }

        $client = new HttpClient();
        $client->setTimeout(5);
        $client->setStreamTimeout(5);
        $client->setRedirectMax(2);
        $client->disableSslVerification();

        $results = [];
        $worst = 'OK';

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $url = isset($rule['url']) ? trim((string)$rule['url']) : '';
            $expected = isset($rule['code']) ? (int)$rule['code'] : 200;
            if ($url === '') {
                continue;
            }

            $ok = $client->get($url);
            $http = method_exists($client, 'getStatus') ? (int)$client->getStatus() : 0;

            $match = $ok && $http === $expected;
            $row = [
                'url' => $url,
                'expected' => $expected,
                'actual' => $http,
                'ok' => $match,
            ];
            $results[] = $row;

            if (!$match) {
                $worst = 'ERROR';
            }
        }

        if ($results === []) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Нет валидных правил для проверки.',
            ]);
        }

        $status = $worst === 'OK' ? 'OK' : 'ERROR';
        $message = $status === 'OK'
            ? 'Все HTTP проверки успешны.'
            : 'Есть несовпадения HTTP кодов.';

        return $this->normalizeResult([
            'VALUE' => Json::encode(['checks' => $results], JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
