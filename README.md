# mrlexndr.monitoring

Модуль **1С-Битрикс**: локальный мониторинг базовых показателей (метрики, история в БД, агенты, уведомления).

## Пространство имён

Классы модуля находятся в пространстве имён `mrLexndr\Monitoring` и расположены по PSR-4 в каталоге `lib/mrLexndr/Monitoring/`.

Автозагрузка задаётся в [`.settings.php`](.settings.php). Файл [`include.php`](include.php) содержит только проверку `B_PROLOG_INCLUDED` (Patch 1).

## Установка

Разместите модуль в `/local/modules/mrlexndr.monitoring/` и установите через административную панель.

## Patch 1 (кратко)

- Периодические агенты возвращают строку следующего вызова (`Agent::runChecks` / `clearOldMetrics`).
- Telegram: URL без `rawurlencode` токена.
- Деинсталляция: удаление таблицы только если она существует.
- SSL checker: SNI; HTTP checkers: ограничение редиректов и отключение строгой проверки SSL для устойчивости в dev.
- Ошибки уведомителей пишутся в журнал событий (`MRLEXNDR_MONITORING_NOTIFIER_ERROR`).
