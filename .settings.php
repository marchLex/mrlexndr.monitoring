<?php

/**
 * Служебная конфигурация модуля для ядра Битрикс.
 *
 * Явно задаёт PSR-4 автозагрузку для пространства имён `mrLexndr\Monitoring`,
 * так как файл `include.php` сознательно не содержит ручной карты классов (Patch 1).
 */

declare(strict_types=1);

return [
    'autoload' => [
        'psr-4' => [
            'mrLexndr\\Monitoring\\' => 'lib/mrLexndr/Monitoring/',
        ],
    ],
];
