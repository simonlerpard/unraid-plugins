<?php

class Html {
    public static function loadSettingsJavaScript($plugin) {
        return sprintf('
            <script src="/plugins/%s/javascript/settings.js" type="text/javascript"></script>
        ', $plugin->get('name'));
    }
    public static function getInstalledOpVersion() {
        $version = !!exec("which op") ? exec("op --version") : "not installed";
        return sprintf('
            <strong><span class="small">Installed: <span id="installed_version">%s</span></span></strong>
        ', $version);
    }

    public static function getInstallVersionInput($config) {
        $id = "op_cli_version_track";
        $installBtnName = $config->get($id) !== "none" ? _("Install") : _("Uninstall");
        $select = sprintf('
            <div>
                <select id="trackSelection" name="track_selector" custom-value="%6$s" onchange="handleTrackSelectChange()">
                    <option value="stable">%1$s</option>
                    <option value="latest">%2$s</option>
                    <option value="custom">%3$s</option>
                    <option value="none">%4$s</option>
                </select>
                <input
                    type="text"
                    class="small"
                    style="display:none;"
                    id="%5$s"
                    name="%5$s"
                    value="%6$s"
                    onkeyup="handleCustomTrackInput(this, \'prev\')"
                    />
                <input type="submit" id="install_btn" name="install" value="%8$s" onclick="setLoadingSpinner(true)">
                <input type="button" name="check_for_updates" value="%7$s"/>
            </div>
        ', _("Stable"), _("Latest"), _("Custom"), _("None"), $id, $config->get($id), _("Check for updates"), $installBtnName);
        return $select;
    }

    public static function getSpinner() {
        return '<div class="spinner" style="display:none"></div>';
    }

    public static function getTitle($title, $icon = "") {
        $titleIconMap = [
            'cloud-download' => 'fa fa-cloud-download',
            'cogs' => 'fa fa-cogs',
            'db' => 'fa fa-database',
        ];
        $iconClasses = $icon ? $titleIconMap[strtolower($icon)] : '';
        return sprintf('
            <div class="title">
                <i class="%s title" aria-hidden="true"></i>
                <span class="left">%s</span>
            </div>', $iconClasses, $title
        );
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