<?php
    style("drawio", "settings");
    \OCP\Util::addScript("drawio", "personalSettings");
?>
<div id="drawio" class="section section-drawio">
    <h2>Diagramming</h2>

    <div class="drawio-setting">
        <label for="theme"><?php p($l->t("Theme")) ?></label>
        <select id="theme">
            <option value="default"<?php if ($_["drawioTheme"] === "default") echo ' selected'; ?>><?php p($l->t("Auto")) ?></option>
            <option value="kennedy"<?php if ($_["drawioTheme"] === "kennedy") echo ' selected'; ?>><?php p($l->t("Classic")) ?></option>
            <option value="simple"<?php if ($_["drawioTheme"] === "simple") echo ' selected'; ?>><?php p($l->t("Modern")) ?></option>
            <option value="min"<?php if ($_["drawioTheme"] === "min") echo ' selected'; ?>><?php p($l->t("Minimal")) ?></option>
            <option value="atlas"<?php if ($_["drawioTheme"] === "atlas") echo ' selected'; ?>><?php p($l->t("Atlas")) ?></option>
        </select>
    </div>

    <div class="drawio-setting">
        <label for="darkMode"><?php p($l->t("Dark")) ?></label>
        <select id="darkMode">
            <option value="auto"<?php if ($_["drawioDarkMode"] === "auto") echo ' selected'; ?>><?php p($l->t("Auto")) ?></option>
            <option value="on"<?php if ($_["drawioDarkMode"] === "on") echo ' selected'; ?>><?php p($l->t("Yes")) ?></option>
            <option value="off"<?php if ($_["drawioDarkMode"] === "off") echo ' selected'; ?>><?php p($l->t("No")) ?></option>
        </select>
    </div>

    <div class="drawio-setting">
        <label for="lang"><?php p($l->t("Language")) ?></label>
        <select id="lang"></select>
        <input type="hidden" id="curLang" value="<?php p($_["drawioLang"]) ?>">
    </div>

    <a id="drawioSave" class="button"><?php p($l->t("Save")) ?></a>
</div>
