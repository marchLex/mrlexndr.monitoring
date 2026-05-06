<?php

/**
 * Административный дашборд мониторинга: последние статусы метрик и принудительный запуск проверок.
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;
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

$APPLICATION->SetTitle(GetMessage('MRLEXNDR_MONITORING_DASHBOARD_TITLE'));

Extension::load(['ui.buttons', 'ui.alerts', 'ui.notification']);

Asset::getInstance()->addString(
    '<script>BX.message(' . Json::encode([
        'MRLEXNDR_MONITORING_FORCE_OK' => GetMessage('MRLEXNDR_MONITORING_FORCE_OK'),
        'MRLEXNDR_MONITORING_FORCE_FAIL' => GetMessage('MRLEXNDR_MONITORING_FORCE_FAIL'),
    ], JSON_UNESCAPED_UNICODE) . ');</script>'
);

Asset::getInstance()->addString(
    <<<'JS'
<script>
BX.ready(function () {
    var btn = BX('mrlexndr-monitoring-force-run');
    if (!btn) {
        return;
    }

    BX.bind(btn, 'click', function () {
        btn.disabled = true;

        BX.ajax({
            url: '/bitrix/admin/mrlexndr_monitoring_ajax_run_checks.php',
            method: 'POST',
            dataType: 'json',
            data: {
                sessid: BX.bitrix_sessid()
            },
            onsuccess: function (data) {
                btn.disabled = false;
                if (data && data.success) {
                    BX.UI.Notification.Center.notify({
                        content: BX.message('MRLEXNDR_MONITORING_FORCE_OK') || 'OK'
                    });
                    window.location.reload();
                    return;
                }

                BX.UI.Notification.Center.notify({
                    content: (data && data.error) ? data.error : (BX.message('MRLEXNDR_MONITORING_FORCE_FAIL') || 'Error'),
                    autoHideDelay: 5000
                });
            },
            onfailure: function () {
                btn.disabled = false;
                BX.UI.Notification.Center.notify({
                    content: BX.message('MRLEXNDR_MONITORING_FORCE_FAIL') || 'Error',
                    autoHideDelay: 5000
                });
            }
        });
    });
});
</script>
JS
);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$cronOk = defined('BX_CRONTAB') && BX_CRONTAB === true;
if (!$cronOk) {
    CAdminMessage::ShowMessage([
        'TYPE' => 'ERROR',
        'MESSAGE' => GetMessage('MRLEXNDR_MONITORING_DASHBOARD_CRON_ALERT'),
        'DETAILS' => '',
        'HTML' => true,
    ]);
}

$canForce = $USER->IsAdmin() || $APPLICATION->GetGroupRight('mrlexndr.monitoring') >= 'W';
?>

<div style="padding: 12px 0 18px;">
    <?php if ($canForce) { ?>
        <button class="ui-btn ui-btn-primary" type="button" id="mrlexndr-monitoring-force-run">
            <?= GetMessage('MRLEXNDR_MONITORING_DASHBOARD_FORCE_RUN') ?>
        </button>
    <?php } ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;">
    <?php
    foreach (Registry::getCheckers() as $checker) {
        $code = $checker->getCode();
        $row = MetricsTable::getLastByMetricCode($code);

        $status = is_array($row) ? (string)($row['STATUS'] ?? 'OK') : '—';
        $message = is_array($row) ? (string)($row['MESSAGE'] ?? '') : GetMessage('MRLEXNDR_MONITORING_DASHBOARD_NO_DATA');
        $value = is_array($row) ? (string)($row['METRIC_VALUE'] ?? '') : '';
        $date = '';
        if (is_array($row) && !empty($row['DATE_CHECK'])) {
            $dt = $row['DATE_CHECK'];
            if ($dt instanceof \Bitrix\Main\Type\DateTime) {
                $date = $dt->toString();
            } else {
                $date = (string)$dt;
            }
        }

        $border = '#2fc6f6';
        if ($status === 'OK') {
            $border = '#47d18c';
        } elseif ($status === 'WARNING') {
            $border = '#ffa900';
        } elseif ($status === 'ERROR') {
            $border = '#ff5752';
        }
        ?>
        <div class="ui-card" style="border-left:4px solid <?= htmlspecialcharsbx($border) ?>;padding:16px;background:#fff;">
            <div style="font-weight:600;margin-bottom:10px;">
                <?= htmlspecialcharsbx($checker->getName()) ?>
                <span style="opacity:.7;font-size:12px;margin-left:8px;"><?= htmlspecialcharsbx($code) ?></span>
            </div>
            <div style="margin-bottom:8px;">
                <b><?= GetMessage('MRLEXNDR_MONITORING_DASHBOARD_STATUS') ?>:</b>
                <?= htmlspecialcharsbx($status) ?>
            </div>
            <div style="margin-bottom:8px;">
                <b><?= GetMessage('MRLEXNDR_MONITORING_DASHBOARD_LAST_CHECK') ?>:</b>
                <?= htmlspecialcharsbx($date !== '' ? $date : '—') ?>
            </div>
            <div style="margin-bottom:8px;">
                <b><?= GetMessage('MRLEXNDR_MONITORING_DASHBOARD_MESSAGE') ?>:</b><br>
                <?= htmlspecialcharsbx($message) ?>
            </div>
            <details style="margin-top:8px;">
                <summary><?= GetMessage('MRLEXNDR_MONITORING_DASHBOARD_VALUE') ?></summary>
                <pre style="white-space:pre-wrap;word-break:break-word;margin-top:8px;"><?= htmlspecialcharsbx($value) ?></pre>
            </details>
        </div>
        <?php
    }
    ?>
</div>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
