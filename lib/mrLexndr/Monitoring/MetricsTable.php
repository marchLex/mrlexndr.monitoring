<?php

/**
 * ORM-сущность таблицы снимков метрик (`mrlexndr_monitoring_metrics`).
 *
 * @package mrLexndr\Monitoring
 */

declare(strict_types=1);

namespace mrLexndr\Monitoring;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\Type\DateTime;

/**
 * Хранилище истории проверок (ORM DataManager).
 */
class MetricsTable extends DataManager
{
    /**
     * Имя таблицы в БД.
     */
    public static function getTableName(): string
    {
        return 'mrlexndr_monitoring_metrics';
    }

    /**
     * Карта полей ORM.
     *
     * @return array<int, \Bitrix\Main\ORM\Fields\Field>
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new DatetimeField('DATE_CHECK', [
                'required' => true,
                'default_value' => static function (): DateTime {
                    return new DateTime();
                },
            ]),
            new StringField('METRIC_CODE', [
                'required' => true,
                'size' => 64,
            ]),
            new TextField('METRIC_VALUE', [
                'required' => true,
            ]),
            new EnumField('STATUS', [
                'values' => ['OK', 'WARNING', 'ERROR'],
                'default_value' => 'OK',
            ]),
            new StringField('MESSAGE', [
                'nullable' => true,
                'size' => 1024,
            ]),
        ];
    }

    /**
     * Описание индексов (фактическое создание — в инсталляторе через SQL).
     *
     * @return array<string, array{fields: array<int, string>, unique?: bool}>
     */
    public static function getIndexesDefinition(): array
    {
        return [
            'IX_MRLEXNDR_METRICS_CODE_DATE' => [
                'fields' => ['METRIC_CODE', 'DATE_CHECK'],
                'unique' => false,
            ],
        ];
    }

    /**
     * Последний снимок по коду метрики.
     *
     * @return array<string, mixed>|null
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getLastByMetricCode(string $metricCode): ?array
    {
        $row = static::getList([
            'filter' => ['=METRIC_CODE' => $metricCode],
            'order' => ['DATE_CHECK' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }
}
