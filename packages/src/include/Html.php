<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class Html {
    public static function getJavaScript($plugin, $filename) {
        return sprintf('
            <script src="%s/javascript/%s" type="text/javascript"></script>
        ', $plugin->get("webroot"), $filename);
    }
    public static function getStyle($plugin, $filename) {
        return sprintf('
            <link rel="stylesheet" href="%s/style/%s">
        ', $plugin->get("webroot"), $filename);
    }
    public static function getInstalledOpVersion($config) {
        $version = $config->getInstalledOpVersion();
        return sprintf('
            <strong><span class="small">Installed: <span id="installed_version">%s</span></span></strong>
        ', $version);
    }

    public static function getDropdownWithCustom(
        $id,
        $options = ["none" => "None"],
        $customKey = "",
        $initValue = "",
        $jsSelectCallback = "",
        $jsKeyUpCallback = ""
    ) {
        $jsSelectChange = sprintf(
            "handleSelectChange(false, '%s', '%s', handleInstallButton)",
            $id,
            $customKey,
            $jsSelectCallback
        );
        $jsKeyUpFunction = "handleCustomInput(this, {$jsKeyUpCallback})";
        $options = array_map(fn($k, $v) => sprintf('<option value="%s">%s</option>', $k, $v), array_keys($options), array_values($options));
        return sprintf('
            <select
                id="select_%1$s"
                name="select_%1$s"
                custom-value="%2$s"
                onchange="%3$s"
                class="align"
                >%4$s</select>
            <input
                type="text"
                class="small"
                style="display:none;"
                id="%1$s"
                name="%1$s"
                value="%2$s"
                onkeyup="%5$s"
                />
        ', $id, $initValue, $jsSelectChange, implode("", $options), $jsKeyUpFunction);
    }

    public static function getInstallVersionInput($config) {
        $id = "op_cli_version_track";
        $installBtnName = $config->get($id) !== "none" ? _("Install") : _("Uninstall");
        $options = [
            "stable" => _("Stable"),
            "latest" => _("Latest"),
            "custom" => _("Custom"),
            "none" => _("None"),
        ];
        $dropdown = Html::getDropdownWithCustom($id, $options, "custom", $config->get($id), "handleInstallButton", "handleInstallButton");
        return sprintf('
            <div>
                %1$s
                <input type="submit" id="install_btn" name="install" value="%2$s" onclick="setLoadingSpinner(true)">
                <input type="button" name="check_for_updates" value="%3$s" onclick="checkForUpdates(this)"/>
            </div>
        ', $dropdown, $installBtnName, _("Check for updates"));
    }

    public static function getVaultItemInput($config) {
        $id = "op_vault_item";
        $initValue = $config->get($id);
        $msg = "";
        $disabled = false;
        if (!$config->hasValidToken()) {
            $disabled = true;
            $msg .= _("Missing or invalid service account token, cannot browse. Update the token, save and then try again.");
        }
        if (!$config->isOpInstalled()) {
            $disabled = true;
            $msg .= _("You must install the 1Password CLI first before we can browse your items.");
        }

        $disabled = $disabled ? "disabled" : "";
        $msg = _("Missing or invalid service account token, cannot browse. Update the token, save and then try again.");
        return sprintf('
            <div id="vaultItemWrapper">
                <div id="fileTreeDemo"></div>
                <input id="%1$s" name="%1$s" type="text" value="%2$s">
                <input type="button" value="Text input" onclick="toggleVaultItemInputs()" %3$s title="%4$s">
            </div>
        ', $id, $initValue, $disabled, $msg);
    }

    public static function getExportToken($config) {
        $id = "op_export_token_env";
        $enabled = $config->get($id) === "enabled";
        $selectEnabled = $enabled ? "selected" : "";
        $selectDisabled = !$enabled ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s" class="align">
                <option value="disabled" %2$s>Disabled</option>
                <option value="enabled" %3$s>Enabled</option>
            </select>
        ', $id, $selectDisabled, $selectEnabled);
    }

    public static function getSpinner() {
        return '<div class="spinner" style="display:none"></div>';
    }

    public static function getTitle($title, $icon = "") {
        return sprintf('
            <div class="title">
                %s %s
            </div>', Html::getIcon($icon), Html::getTitleSpan($title)
        );
    }

    public static function getSubTitle($title, $icon = "", $margin = false) {
        $margin = $margin ? "margin-top:30px;" : "";
        return sprintf('
            <table class="tablesorter shift ups" style="%s"><thead><tr><th>
                %s %s
            </th></tr></thead></table>', $margin, Html::getIcon($icon), Html::getTitleSpan($title)
        );

    }

    public static function getIcon($icon, $classes = "") {
        $titleIconMap = [
            'cloud-download' => 'fa fa-cloud-download',
            'cogs' => 'fa fa-cogs',
            'db' => 'fa fa-database',
        ];
        $iconClasses = $icon ? $titleIconMap[strtolower($icon)] : '';
        return sprintf('<i class="%s %s" aria-hidden="true"></i>', $iconClasses, $classes);
    }

    public static function getTitleSpan($text) {
        return sprintf('<span class="left">%s</span>', $text);
    }

    public static function getServiceAccountTokenInput($config) {
        $id = "op_cli_service_account_token";
        return sprintf('
        <div id="service_account_input_wrapper">
            <input
                type="password"
                name="%s"
                value="%s"
                autocomplete="off"
                data-1p-ignore
                data-bwignore
                data-lpignore="true"
                data-form-type="other"
                style="min-width:35%%"
            />
            <i class="fa fa-eye pwd-toggle" aria-hidden="true" onclick="toggleEye(this)" title="%s"></i>
        </diV>
        ', $id, $config->get($id), _("Show value"));
    }

    public static function getMountOptions($config) {
        $id = "op_disk_mount";
        $diskEncryptionFile = $config->get($id);
        $enabledSelected = $diskEncryptionFile === "enabled" ? "selected" : "";
        $disabledSelected = $diskEncryptionFile === "disabled" ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s" class="align">
                <option value="enabled" %2$s>Enabled</option>
                <option value="disabled" %3$s>Disabled</option>
            </select>
        ', $id, $enabledSelected, $disabledSelected);
    }

    public static function getAlertOptions($config) {
        $id = "op_disk_alert_level";
        $alertLevel = $config->get($id);
        $alertSelected = $alertLevel === "alert" ? "selected" : "";
        $warningSelected = $alertLevel === "warning" ? "selected" : "";
        $noticeSelected = $alertLevel === "notice" ? "selected" : "";
        $nothingSelected = $alertLevel === "none" ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s">
                <option value="alert" %2$s>Alert</option>
                <option value="warning" %3$s>Warning</option>
                <option value="notice" %4$s>Notice</option>
                <option value="none" %5$s>Nothing</option>
            </select>
        ', $id, $alertSelected, $warningSelected, $noticeSelected, $nothingSelected);
    }

    public static function getDeleteKeyfileOptions($config) {
        $id = "op_disk_delete_keyfile";
        $deleteKeyfile = $config->get($id);
        $enabledSelected = $deleteKeyfile === "enabled" ? "selected" : "";
        $disabledSelected = $deleteKeyfile === "disabled" ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s" class="align">
                <option value="enabled" %2$s>Enabled</option>
                <option value="disabled" %3$s>Disabled</option>
            </select>
        ', $id, $enabledSelected, $disabledSelected);
    }

    public static function getErrorBox($messages) {
        $spans = array_map(function($msg) { return sprintf('<span class="error error-msg">Error: %s</span>', $msg); }, $messages);

        return sprintf('<div class="msg-box">%s</div>', implode("", $spans));

    }

    public static function getStyleElement() {
        return sprintf('
            <style>
                input.small, .small{width:150px;margin-right:20px;display:inline-block;}
                select.align{min-width:200px;max-width:300px;}
                select.hide{display:none}
                .msg-box span{padding:15px}
                div.msg-box{margin-bottom:20px}
                i.pwd-toggle { cursor: pointer; }
            </style>
        ');
        }

    /*
    Replaces the previous state if we did a POST, avoiding to resend the data on a refresh
    For example if we pressed the install button and then did a refresh, we don't re-send
    the POST request.
    */
    public static function resetPostPageScript() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return sprintf('
                <script>
                    if ( window.history.replaceState ) {
                        window.history.replaceState( null, null, window.location.href );
                    }
                </script>
            ');
        }
    }

    // TODO: REMOVE
    // public static function getGenericTextInput($id, $config) {
    //     return sprintf('
    //         <input type="text" name="%1$s" id="%1$s" value="%2$s" class="align">
    //         ', $id, $config->get($id)
    //     );
    // }
}

?>
