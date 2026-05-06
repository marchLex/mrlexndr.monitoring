<?php

/**
 * Отправка писем через почтовое событие Битрикс (`Event::sendImmediate` / `Event::send`).
 *
 * Требует настройки шаблона события `MRLEXNDR_MONITORING_ALERT` и поля получателя в опциях модуля.
 *
 * @package mrLexndr\Monitoring\Notifiers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Notifiers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\SiteTable;
use mrLexndr\Monitoring\Checkers\CheckerInterface;

/**
 * Реализация канала Email для оповещений о метриках.
 */
final class EmailNotifier implements NotifierInterface
{
    /**
     * {@inheritDoc}
     */
    public function send(CheckerInterface $checker, array $result): void
    {
        if (!Loader::includeModule('mrlexndr.monitoring')) {
            return;
        }

        $to = trim((string)Option::get('mrlexndr.monitoring', 'notify_email', ''));
        if ($to === '') {
            return;
        }

        $lid = self::resolveSiteId();

        $fields = [
            'EVENT_NAME' => 'MRLEXNDR_MONITORING_ALERT',
            'LID' => $lid,
            'DUPLICATE_CHECK' => 'N',
            'C_FIELDS' => [
                'EMAIL_TO' => $to,
                'CHECK_CODE' => $checker->getCode(),
                'CHECK_NAME' => $checker->getName(),
                'STATUS' => $result['STATUS'],
                'MESSAGE' => $result['MESSAGE'],
                'VALUE' => $result['VALUE'],
            ],
        ];

        if (method_exists(Event::class, 'sendImmediate')) {
            Event::sendImmediate($fields);
            return;
        }

        Event::send($fields);
    }

    /**
     * Определяет `LID` сайта для почтового события.
     */
    private static function resolveSiteId(): string
    {
        if (defined('SITE_ID') && is_string(SITE_ID) && SITE_ID !== '') {
            return SITE_ID;
        }

        if (!Loader::includeModule('main')) {
            return 's1';
        }

        $def = SiteTable::getList([
            'filter' => ['=DEF' => 'Y', '=ACTIVE' => 'Y'],
            'limit' => 1,
            'select' => ['LID'],
        ])->fetch();

        return is_array($def) && !empty($def['LID']) ? (string)$def['LID'] : 's1';
    }
}
