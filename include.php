<?php

/**
 * Точка подключения модуля `mrlexndr.monitoring`.
 *
 * Автозагрузка классов выполняется ядром Битрикс по правилам PSR-4 из каталога `lib/`.
 * В этом файле остаётся только защита от прямого доступа по HTTP.
 */

declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
