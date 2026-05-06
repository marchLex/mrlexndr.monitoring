<?php

/**
 * Отправка сообщений в Telegram Bot API через {@see HttpClient}.
 *
 * Токен бота не должен кодироваться в URL — двоеточие является частью корректного токена.
 *
 * @package mrLexndr\Monitoring\Notifiers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Notifiers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;
use mrLexndr\Monitoring\Checkers\CheckerInterface;

/**
 * Реализация канала Telegram для оповещений о метриках.
 */
final class TelegramNotifier implements NotifierInterface
{
    /** Таймаут HTTP для запроса к API Telegram (секунды). */
    private const API_TIMEOUT = 3;

    /**
     * {@inheritDoc}
     */
    public function send(CheckerInterface $checker, array $result): void
    {
        if (!Loader::includeModule('mrlexndr.monitoring')) {
            return;
        }

        $token = trim((string)Option::get('mrlexndr.monitoring', 'notify_telegram_token', ''));
        $chatId = trim((string)Option::get('mrlexndr.monitoring', 'notify_telegram_chat_id', ''));
        if ($token === '' || $chatId === '') {
            return;
        }

        $text = $checker->getCode() . ' — ' . $result['STATUS'] . "\n"
            . $checker->getName() . "\n"
            . $result['MESSAGE'];

        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';

        $client = new HttpClient();
        $client->setTimeout(self::API_TIMEOUT);
        $client->setStreamTimeout(self::API_TIMEOUT);
        $client->post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => 'true',
        ]);
    }
}
