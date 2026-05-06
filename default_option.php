<?php

declare(strict_types=1);

/**
 * Значения опций модуля по умолчанию (файл подключается ядром при первом обращении к настройкам).
 *
 * Хранятся через `\Bitrix\Main\Config\Option` с идентификатором модуля `mrlexndr.monitoring`.
 *
 * @return array<string, string>
 */
return [
    'notify_email' => '',
    'notify_telegram_token' => '',
    'notify_telegram_chat_id' => '',
    'notify_on_error' => 'Y',
    'notify_on_warning' => 'Y',
    'notify_on_ok' => 'Y',
    /** Minimum consecutive ERROR results before sending an ERROR notification (anti-flap). */
    'error_consecutive_threshold' => '2',
    /** JSON map: metricCode => consecutive error count. */
    'consecutive_errors_state' => '{}',
];
