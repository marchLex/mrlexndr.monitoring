<?php

/**
 * Матрица прав доступа к функционалу модуля в административной части.
 */

declare(strict_types=1);

/**
 * Module access matrix for administrative UI.
 *
 * - D: denied
 * - R: view dashboard
 * - W: manage module settings
 *
 * @return array<string, array<string, string>>
 */
return [
    [
        'ENTITY_ID' => 'mrlexndr.monitoring',
        'ACTION' => [
            'dashboard' => 'R',
            'settings' => 'W',
        ],
    ],
];
