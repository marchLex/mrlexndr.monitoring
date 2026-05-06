<?php

/**
 * Первый шаг удаления модуля: подтверждение и опция сохранения таблиц БД.
 *
 * Языковые сообщения подгружаются из `install/index.php`, где определены общие константы установки.
 */

declare(strict_types=1);

if (!check_bitrix_sessid()) {
    return;
}

global $APPLICATION;

IncludeModuleLangFile(__DIR__ . '/index.php');

?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="get">
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <input type="hidden" name="id" value="mrlexndr.monitoring">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <?= bitrix_sessid_post() ?>

    <p>
        <label>
            <input type="checkbox" name="savedata" value="Y">
            <?= GetMessage('MRLEXNDR_MONITORING_UNINSTALL_SAVE_TABLE') ?>
        </label>
    </p>

    <input type="submit" value="<?= htmlspecialcharsbx(GetMessage('MOD_UNINST_DEL')) ?>">
</form>
