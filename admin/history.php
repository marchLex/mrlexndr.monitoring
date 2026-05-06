<?php

/**
 * История снимков метрик из таблицы mrlexndr_monitoring_metrics (постраничный список).
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use mrLexndr\Monitoring\MetricsTable;
use mrLexndr\Monitoring\Registry;

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

if (!$USER->IsAdmin() && $APPLICATION->GetGroupRight('mrlexndr.monitoring') < 'R') {
    $APPLICATION->AuthForm(GetMessage('ACCESS_DENIED'));
}

Loader::includeModule('mrlexndr.monitoring');

IncludeModuleLangFile(__FILE__);

$APPLICATION->SetTitle(GetMessage('MRLEXNDR_MONITORING_HISTORY_TITLE'));

$metricFilter = trim((string)($_REQUEST['metric_code'] ?? ''));
$pageSize = 50;
$page = max(1, (int)($_REQUEST['history_page'] ?? 1));

$filter = [];
if ($metricFilter !== '') {
    $filter['=METRIC_CODE'] = $metricFilter;
}

$total = MetricsTable::getCount($filter);
$totalPages = max(1, (int)ceil($total / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $pageSize;

$query = MetricsTable::getList([
    'filter' => $filter,
    'order' => ['DATE_CHECK' => 'DESC'],
    'limit' => $pageSize,
    'offset' => $offset,
]);

$rows = [];
while ($row = $query->fetch()) {
    $rows[] = $row;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$baseParams = ['lang' => LANGUAGE_ID];
if ($metricFilter !== '') {
    $baseParams['metric_code'] = $metricFilter;
}

$buildPageUrl = static function (int $p) use ($APPLICATION, $baseParams): string {
    $params = array_merge($baseParams, ['history_page' => $p]);

    return $APPLICATION->GetCurPage() . '?' . http_build_query($params);
};
?>

<form method="get" style="margin-bottom:16px;">
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <label>
        <?= GetMessage('MRLEXNDR_MONITORING_HISTORY_FILTER_METRIC') ?>
        <select name="metric_code">
            <option value=""><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_FILTER_ALL') ?></option>
            <?php foreach (Registry::getCheckers() as $checker) {
                $code = $checker->getCode();
                ?>
                <option value="<?= htmlspecialcharsbx($code) ?>" <?= $metricFilter === $code ? 'selected' : '' ?>>
                    <?= htmlspecialcharsbx($checker->getName() . ' (' . $code . ')') ?>
                </option>
            <?php } ?>
        </select>
    </label>
    <button type="submit" class="adm-btn"><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_FILTER_APPLY') ?></button>
</form>

<p class="adm-info-message"><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_HINT', ['#TOTAL#' => (string)$total]) ?></p>

<table class="internal list-table" style="width:100%;">
    <thead>
    <tr class="heading">
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_ID') ?></td>
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_DATE') ?></td>
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_CODE') ?></td>
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_STATUS') ?></td>
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_MESSAGE') ?></td>
        <td><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_COL_VALUE') ?></td>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []) { ?>
        <tr><td colspan="6"><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_EMPTY') ?></td></tr>
    <?php } ?>
    <?php foreach ($rows as $row) {
        $id = (int)($row['ID'] ?? 0);
        $code = (string)($row['METRIC_CODE'] ?? '');
        $status = (string)($row['STATUS'] ?? '');
        $message = (string)($row['MESSAGE'] ?? '');
        $value = (string)($row['METRIC_VALUE'] ?? '');
        $dateStr = '';
        $dt = $row['DATE_CHECK'] ?? null;
        if ($dt instanceof DateTime) {
            $dateStr = $dt->toString();
        } elseif ($dt !== null) {
            $dateStr = (string)$dt;
        }

        $messageShort = mb_strlen($message) > 200 ? mb_substr($message, 0, 200) . '…' : $message;
        $valueShort = mb_strlen($value) > 120 ? mb_substr($value, 0, 120) . '…' : $value;
        ?>
        <tr>
            <td><?= $id ?></td>
            <td><?= htmlspecialcharsbx($dateStr) ?></td>
            <td><?= htmlspecialcharsbx($code) ?></td>
            <td><?= htmlspecialcharsbx($status) ?></td>
            <td title="<?= htmlspecialcharsbx($message) ?>"><?= htmlspecialcharsbx($messageShort) ?></td>
            <td title="<?= htmlspecialcharsbx($value) ?>"><pre style="margin:0;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialcharsbx($valueShort) ?></pre></td>
        </tr>
    <?php } ?>
    </tbody>
</table>

<?php if ($totalPages > 1) { ?>
    <div style="margin-top:12px;">
        <?php if ($page > 1) { ?>
            <a href="<?= htmlspecialcharsbx($buildPageUrl($page - 1)) ?>"><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_PAGE_PREV') ?></a>
        <?php } ?>
        <span style="margin:0 8px;">
            <?= GetMessage('MRLEXNDR_MONITORING_HISTORY_PAGE_OF', ['#PAGE#' => (string)$page, '#TOTAL#' => (string)$totalPages]) ?>
        </span>
        <?php if ($page < $totalPages) { ?>
            <a href="<?= htmlspecialcharsbx($buildPageUrl($page + 1)) ?>"><?= GetMessage('MRLEXNDR_MONITORING_HISTORY_PAGE_NEXT') ?></a>
        <?php } ?>
    </div>
<?php } ?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
