<?php

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
    public static function getInstalledOpVersion() {
        $version = !!exec("which op") ? exec("op --version") : "not installed";
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
                <input type="button" name="check_for_updates" value="%3$s"/>
            </div>
        ', $dropdown, $installBtnName, _("Check for updates"));
    }

    // public static function getExportTokenInput($config) {
    //     $id = "op_export_token_env";
    //     $options = [
    //         "none" => _("None"),
    //         "user" => _("Specific user(s)"),
    //         "users" => _("All users"),
    //         "environment" => _("Entire system"),
    //     ];
    //     $dropdown = Html::getDropdownWithCustom($id, $options, "user", $config->get($id));

    //     return $dropdown;
    // }

    public static function getExportToken($config) {
        $id = "op_export_token_env";
        $enabled = $config->get($id) === "enabled";
        $selectEnabled = $enabled ? "selected" : "";
        $selectDisabled = !$enabled ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s">
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

    public static function getSubTitle($title, $icon = "") {
        return sprintf('
            <table class="tablesorter shift ups"><thead><tr><th>
                %s %s
            </th></tr></thead></table>', Html::getIcon($icon), Html::getTitleSpan($title)
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

    public static function getKeyFileOptions($config) {
        $id = "op_cli_disk_auto_mount";
        $diskEncryptionFile = $config->get($id);
        $yesSelect = $diskEncryptionFile === "yes" ? "selected" : "";
        $noSelect = $diskEncryptionFile === "no" ? "selected" : "";
        return sprintf('
            <select id="%1$s" name="%1$s" class="align">
                <option value="yes" %2$s>Yes</option>
                <option value="no" %3$s>No</option>
            </select>
        ', $id, $yesSelect, $noSelect);
    }

    public static function getGenericTextInput($id, $config) {
        return sprintf('
            <input type="text" name="%1$s" id="%1$s" value="%2$s">
            ', $id, $config->get($id)
        );
    }

    public static function getErrorBox($messages) {
        $spans = array_map(function($msg) { return sprintf('<span class="error error-msg">Error: %s</span>', $msg); }, $messages);

        return sprintf('<div class="msg-box">%s</div>', implode("", $spans));

    }

    public static function getStyleElement() {
        return sprintf('
            <style>
                input.small, .small{width:150px;margin-right:20px;display:inline-block;}
                select.align{min-width:200px;max-width:300}
                select.hide{display:none}
                .msg-box span{padding:15px}
                div.msg-box{margin-bottom:20px}
                i.pwd-toggle { cursor: pointer; }
            </style>
        ');
        }

        /*
        Replace the previous state if we did a POST, avoiding to resend the data on a refresh
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
}



?>