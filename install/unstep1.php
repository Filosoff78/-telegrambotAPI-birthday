<?php

use Bitrix\Main\Localization\Loc;
\Bitrix\Main\UI\Extension::load("ui.forms");

global $APPLICATION;
?>
<form method="post" action="<?= $APPLICATION->GetCurPage();?>">
    <?= bitrix_sessid_post();?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="pgk.birthday">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <label class="ui-ctl ui-ctl-checkbox">
        <input type="checkbox" class="ui-ctl-element" name="save_tables" value="Y" checked>
        <div class="ui-ctl-label-text">Сохранить данные</div>
    </label>
    <input type="submit" name="inst" value="Далее">
</form>

<style>
    .ui-ctl + .ui-ctl {
        margin-left: 0;
    }
</style>
