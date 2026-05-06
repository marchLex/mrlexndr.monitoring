<?php

/**
 * Контракт одной метрики (проверки) модуля мониторинга.
 *
 * @package mrLexndr\Monitoring\Checkers
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring\Checkers;

/**
 * Реализация проверки: код, интервал, настройки и выполнение.
 */
interface CheckerInterface
{
    /**
     * Уникальный код метрики (поле `METRIC_CODE` в БД).
     */
    public function getCode(): string;

    /**
     * Заголовок для административного интерфейса.
     */
    public function getName(): string;

    /**
     * Интервал запуска по умолчанию (секунды), если не переопределён в настройках модуля.
     */
    public function getDefaultInterval(): int;

    /**
     * Описание полей настроек для генерации формы в `options.php`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSettingsOptions(): array;

    /**
     * Выполнение проверки.
     *
     * @return array{VALUE: string, STATUS: string, MESSAGE: string}
     */
    public function run(): array;
}
