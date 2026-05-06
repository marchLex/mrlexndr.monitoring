<?php

/**
 * Страница настроек модуля в административном разделе (`Настройки → Настройки продукта → Модули`).
 *
 * Генерирует вкладки «Метрики» и «Уведомления», сохраняет опции и JSON-настройки чекеров.
 */

declare(strict_types=1);

defined('B_PROLOG_INCLUDED') || die();

use Bitrix\Main\Config\Option;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\Json;
use mrLexndr\Monitoring\Registry;

/**
 * @global CMain $APPLICATION
 * @global CUser $USER
 */

Loc::loadMessages(__FILE__);

if ($APPLICATION->GetGroupRight('mrlexndr.monitoring') < 'W') {
    return;
}

Loader::includeModule('mrlexndr.monitoring');

$moduleId = 'mrlexndr.monitoring';
$request = HttpApplication::getInstance()->getContext()->getRequest();

$aTabs = [
    [
        'DIV' => 'mrlexndr_monitoring_metrics',
        'TAB' => Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_TAB_METRICS'),
        'ICON' => '',
        'TITLE' => Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_TAB_METRICS'),
    ],
    [
        'DIV' => 'mrlexndr_monitoring_notify',
        'TAB' => Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_TAB_NOTIFY'),
        'ICON' => '',
        'TITLE' => Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_TAB_NOTIFY'),
    ],
];

$tabControl = new CAdminTabControl('tabControl', $aTabs);

if ($request->isPost() && check_bitrix_sessid() && (string)$request->getPost('save') !== '') {
    Option::set($moduleId, 'notify_email', trim((string)$request->getPost('notify_email')));
    Option::set($moduleId, 'notify_telegram_token', trim((string)$request->getPost('notify_telegram_token')));
    Option::set($moduleId, 'notify_telegram_chat_id', trim((string)$request->getPost('notify_telegram_chat_id')));
    Option::set($moduleId, 'notify_on_error', $request->getPost('notify_on_error') === 'Y' ? 'Y' : 'N');
    Option::set($moduleId, 'notify_on_warning', $request->getPost('notify_on_warning') === 'Y' ? 'Y' : 'N');
    Option::set($moduleId, 'notify_on_ok', $request->getPost('notify_on_ok') === 'Y' ? 'Y' : 'N');

    $threshold = (int)$request->getPost('error_consecutive_threshold');
    if ($threshold <= 0) {
        $threshold = 2;
    }
    Option::set($moduleId, 'error_consecutive_threshold', (string)$threshold);

    foreach (Registry::getCheckers() as $checker) {
        $code = $checker->getCode();

        $intervalRaw = trim((string)$request->getPost('interval_' . $code));
        Option::set($moduleId, 'checker_interval_' . $code, $intervalRaw);

        $settings = [];
        foreach ($checker->getSettingsOptions() as $fieldDef) {
            if (!is_array($fieldDef) || count($fieldDef) < 4) {
                continue;
            }

            $fieldId = (string)$fieldDef[0];
            /** @var array<int, mixed> $typeInfo */
            $typeInfo = is_array($fieldDef[3]) ? $fieldDef[3] : [$fieldDef[3]];
            $fieldType = (string)($typeInfo[0] ?? 'text');

            if ($fieldId === '' || str_starts_with($fieldId, 'note') || $fieldType === 'note') {
                continue;
            }

            $settings[$fieldId] = (string)$request->getPost($code . '_' . $fieldId);
        }

        Option::set($moduleId, 'checker_settings_' . $code, Json::encode($settings, JSON_UNESCAPED_UNICODE));
    }

    LocalRedirect(
        $APPLICATION->GetCurPage()
        . '?mid=' . urlencode($moduleId)
        . '&lang=' . LANGUAGE_ID
        . '&' . $tabControl->ActiveTabParam()
    );
}

$tabControl->Begin();

$tabControl->BeginNextTab();

foreach (Registry::getCheckers() as $checker) {
    $code = $checker->getCode();

    echo '<tr class="heading"><td colspan="2">' . htmlspecialcharsbx($checker->getName()) . ' <span style="opacity:.6">(' . htmlspecialcharsbx($code) . ')</span></td></tr>';

    $intervalValue = Option::get($moduleId, 'checker_interval_' . $code, '');
    ?>
    <tr>
        <td width="40%"><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_INTERVAL_SUFFIX') ?></td>
        <td width="60%">
            <input type="number" min="1" name="<?= 'interval_' . htmlspecialcharsbx($code) ?>"
                   value="<?= htmlspecialcharsbx((string)$intervalValue) ?>"
                   placeholder="<?= (int)$checker->getDefaultInterval() ?>">
            <div class="adm-info-message-wrap">
                <span class="adm-info-message"><?= Loc::getMessage(
                    'MRLEXNDR_MONITORING_OPTIONS_INTERVAL_HINT',
                    ['#DEFAULT#' => (string)(int)$checker->getDefaultInterval()]
                ) ?></span>
            </div>
        </td>
    </tr>
    <?php

    foreach ($checker->getSettingsOptions() as $fieldDef) {
        if (!is_array($fieldDef) || count($fieldDef) < 4) {
            continue;
        }

        $fieldId = (string)$fieldDef[0];
        $title = (string)$fieldDef[1];
        $default = (string)$fieldDef[2];
        /** @var array<int, mixed> $type */
        $type = is_array($fieldDef[3]) ? $fieldDef[3] : [$fieldDef[3]];
        $fieldType = (string)($type[0] ?? 'text');

        if ($fieldType === 'note') {
            echo '<tr><td colspan="2">' . htmlspecialcharsbx($title) . '</td></tr>';
            continue;
        }

        $savedRaw = Option::get($moduleId, 'checker_settings_' . $code, '');
        try {
            $saved = Json::decode($savedRaw);
        } catch (\Throwable) {
            $saved = [];
        }
        $value = is_array($saved) && array_key_exists($fieldId, $saved) ? (string)$saved[$fieldId] : $default;

        $inputName = htmlspecialcharsbx($code . '_' . $fieldId);

        echo '<tr>';
        echo '<td>' . htmlspecialcharsbx($title) . '</td>';
        echo '<td>';

        if ($fieldType === 'textarea') {
            $rows = (int)($type[1] ?? 5);
            $cols = (int)($type[2] ?? 60);
            echo '<textarea name="' . $inputName . '" rows="' . $rows . '" cols="' . $cols . '">' . htmlspecialcharsbx($value) . '</textarea>';
        } else {
            $width = (int)($type[1] ?? 40);
            echo '<input type="text" name="' . $inputName . '" value="' . htmlspecialcharsbx($value) . '" size="' . $width . '">';
        }

        echo '</td>';
        echo '</tr>';
    }
}

$tabControl->EndTab();

$tabControl->BeginNextTab();

$notifyEmail = Option::get($moduleId, 'notify_email', '');
$tgToken = Option::get($moduleId, 'notify_telegram_token', '');
$tgChat = Option::get($moduleId, 'notify_telegram_chat_id', '');
$notifyError = Option::get($moduleId, 'notify_on_error', 'Y');
$notifyWarning = Option::get($moduleId, 'notify_on_warning', 'Y');
$notifyOk = Option::get($moduleId, 'notify_on_ok', 'Y');
$threshold = (int)Option::get($moduleId, 'error_consecutive_threshold', '2');
if ($threshold <= 0) {
    $threshold = 2;
}

?>
<tr>
    <td width="40%"><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_EMAIL') ?></td>
    <td width="60%"><input type="text" name="notify_email" value="<?= htmlspecialcharsbx($notifyEmail) ?>" size="50"></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_TG_TOKEN') ?></td>
    <td><input type="text" name="notify_telegram_token" value="<?= htmlspecialcharsbx($tgToken) ?>" size="50"></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_TG_CHAT') ?></td>
    <td><input type="text" name="notify_telegram_chat_id" value="<?= htmlspecialcharsbx($tgChat) ?>" size="50"></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_ERROR') ?></td>
    <td><input type="checkbox" name="notify_on_error" value="Y" <?= $notifyError === 'Y' ? 'checked' : '' ?>></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_WARNING') ?></td>
    <td><input type="checkbox" name="notify_on_warning" value="Y" <?= $notifyWarning === 'Y' ? 'checked' : '' ?>></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_NOTIFY_OK') ?></td>
    <td><input type="checkbox" name="notify_on_ok" value="Y" <?= $notifyOk === 'Y' ? 'checked' : '' ?>></td>
</tr>
<tr>
    <td><?= Loc::getMessage('MRLEXNDR_MONITORING_OPTIONS_THROTTLE') ?></td>
    <td><input type="number" min="1" name="error_consecutive_threshold" value="<?= (int)$threshold ?>"></td>
</tr>
<?php

$tabControl->Buttons(['btn_apply' => false]);

$tabControl->End();
