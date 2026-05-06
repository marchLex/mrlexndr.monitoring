<?php

/**
 * Реестр встроенных чекеров и каналов уведомлений с расширением через события ядра.
 *
 * События:
 * - `mrlexndr.monitoring` / `OnRegisterCheckers`
 * - `mrlexndr.monitoring` / `OnRegisterNotifiers`
 *
 * Обработчики могут вернуть {@see EventResult} с параметрами `checkers` / `notifiers` (массивы объектов).
 *
 * @package mrLexndr\Monitoring
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use mrLexndr\Monitoring\Checkers\Backups;
use mrLexndr\Monitoring\Checkers\CheckerInterface;
use mrLexndr\Monitoring\Checkers\DiskSpace;
use mrLexndr\Monitoring\Checkers\DomainExpire;
use mrLexndr\Monitoring\Checkers\HttpCodes;
use mrLexndr\Monitoring\Checkers\LicenseExpire;
use mrLexndr\Monitoring\Checkers\SslExpire;
use mrLexndr\Monitoring\Checkers\Ttfb;
use mrLexndr\Monitoring\Notifiers\EmailNotifier;
use mrLexndr\Monitoring\Notifiers\NotifierInterface;
use mrLexndr\Monitoring\Notifiers\TelegramNotifier;

/**
 * Фабрика списков чекеров и уведомителей с учётом событий расширения.
 */
final class Registry
{
    /**
     * @return array<int, CheckerInterface>
     */
    public static function getCheckers(): array
    {
        $checkers = [
            new DomainExpire(),
            new LicenseExpire(),
            new SslExpire(),
            new Backups(),
            new DiskSpace(),
            new Ttfb(),
            new HttpCodes(),
        ];

        $event = new Event('mrlexndr.monitoring', 'OnRegisterCheckers', [
            'checkers' => $checkers,
        ]);
        $event->send();

        $merged = $checkers;
        foreach ($event->getResults() as $result) {
            if (!$result instanceof EventResult) {
                continue;
            }

            $params = $result->getParameters();
            if (!empty($params['checkers']) && is_array($params['checkers'])) {
                foreach ($params['checkers'] as $checker) {
                    $merged[] = $checker;
                }
            }
        }

        return self::filterCheckers($merged);
    }

    /**
     * @return array<int, NotifierInterface>
     */
    public static function getNotifiers(): array
    {
        $notifiers = [
            new EmailNotifier(),
            new TelegramNotifier(),
        ];

        $event = new Event('mrlexndr.monitoring', 'OnRegisterNotifiers', [
            'notifiers' => $notifiers,
        ]);
        $event->send();

        $merged = $notifiers;
        foreach ($event->getResults() as $result) {
            if (!$result instanceof EventResult) {
                continue;
            }

            $params = $result->getParameters();
            if (!empty($params['notifiers']) && is_array($params['notifiers'])) {
                foreach ($params['notifiers'] as $notifier) {
                    $merged[] = $notifier;
                }
            }
        }

        return self::filterNotifiers($merged);
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return array<int, CheckerInterface>
     */
    private static function filterCheckers(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if ($item instanceof CheckerInterface) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return array<int, NotifierInterface>
     */
    private static function filterNotifiers(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if ($item instanceof NotifierInterface) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
