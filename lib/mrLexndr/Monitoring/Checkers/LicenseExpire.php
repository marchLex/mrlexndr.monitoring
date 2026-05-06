<?php

/**
 * Проверка даты окончания лицензии 1С-Битрикс через API ядра (если доступно).
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;

/**
 * Метрика срока действия лицензии продукта.
 */
final class LicenseExpire extends AbstractChecker
{
    /**
     * {@inheritDoc}
     */
    public function getCode(): string
    {
        return 'LICENSE_EXPIRE';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Истечение лицензии 1С-Битрикс';
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
            ['note_license', 'Используются встроенные средства ядра (если доступны).', '', ['note']],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): array
    {
        try {
            $license = Application::getInstance()->getLicense();
        } catch (\Throwable $e) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Лицензия недоступна: ' . $e->getMessage(),
            ]);
        }

        if ($license === null) {
            return $this->normalizeResult([
                'VALUE' => '',
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Объект лицензии не получен.',
            ]);
        }

        $expire = null;
        if (method_exists($license, 'getExpireDate')) {
            /** @var mixed $expire */
            $expire = $license->getExpireDate();
        }

        if ($expire === null || (is_string($expire) && trim($expire) === '')) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['license' => 'unknown'], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не удалось получить дату окончания лицензии через API ядра.',
            ]);
        }

        $ts = false;
        if ($expire instanceof \Bitrix\Main\Type\DateTime) {
            $ts = $expire->getTimestamp();
        } elseif (is_object($expire) && method_exists($expire, 'getTimestamp')) {
            $ts = (int)$expire->getTimestamp();
        } else {
            $ts = strtotime((string)$expire);
        }

        if ($ts === false) {
            return $this->normalizeResult([
                'VALUE' => Json::encode(['raw' => (string)$expire], JSON_UNESCAPED_UNICODE),
                'STATUS' => 'WARNING',
                'MESSAGE' => 'Не удалось распарсить дату лицензии.',
            ]);
        }

        $now = time();
        $daysLeft = (int)floor(($ts - $now) / 86400);

        $payload = [
            'expires_ts' => $ts,
            'expires_iso' => gmdate('c', $ts),
            'days_left' => $daysLeft,
        ];

        $status = 'OK';
        $message = 'Лицензия активна. Осталось дней: ' . $daysLeft;
        if ($daysLeft < 0) {
            $status = 'ERROR';
            $message = 'Лицензия истекла.';
        } elseif ($daysLeft <= 30) {
            $status = 'WARNING';
            $message = 'Скоро истечение лицензии. Осталось дней: ' . $daysLeft;
        }

        return $this->normalizeResult([
            'VALUE' => Json::encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => $status,
            'MESSAGE' => $message,
        ]);
    }
}
