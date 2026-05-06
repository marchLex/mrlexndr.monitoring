<?php

/**
 * Контракт канала уведомлений о результатах проверки метрики.
 *
 * @package mrLexndr\Monitoring\Notifiers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Notifiers;

use mrLexndr\Monitoring\Checkers\CheckerInterface;

/**
 * Отправка сообщения администратору при срабатывании правил уведомлений.
 */
interface NotifierInterface
{
    /**
     * Отправить уведомление по результату проверки.
     *
     * @param array{VALUE: string, STATUS: string, MESSAGE: string} $result
     */
    public function send(CheckerInterface $checker, array $result): void;
}
