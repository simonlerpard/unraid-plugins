<?php

class Html {
    public static function getInstalledOpVersion() {
        return !!exec("which op") ? exec("op --version") : "not installed";
    }

    public static function getInstallVersionInput($config) {
        $id = "op_cli_version_track";
        $textField = sprintf('<input type="text" id="%1$s" name="%1$s" value="%2$s" onload="test(this)" onkeyup="setButtonLabel(this)">', $id, $config->get($id));
        $installBtnName = $config->get($id) !== "none" ? _("Install") : _("Uninstall");
        $installBtn = sprintf('<input type="submit" id="install_btn" name="install" value="%s" onclick="setLoadingSpinner(true)">', $installBtnName);
        return $textField . $installBtn;
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
            <input
                type="password"
                name="%s"
                value="%s"
                autocomplete="off"
                data-1p-ignore
                data-bwignore
                data-lpignore="true"
                data-form-type="other"
            />
        ', $id, $config->get($id));
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

    public static function getStyleElement() {
        return sprintf('
            <style>
                select.align{min-width:300px;max-width:300}
                select.hide{display:none}
                .msg-box span{padding:15px}
                div.msg-box{margin-bottom:20px}
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

    public static function getScripts() {
        return sprintf('
            <script>
                const setLoadingSpinner = (spinnerBool) => {
                    document.querySelector(".spinner").style.display = spinnerBool ? "" : "none";
                }
                const setButtonLabel = (event) => {
                    const label = (event.value.toLowerCase() !== "none") ? "%s" : "%s";
                    const btn = document.getElementById("install_btn");
                    btn.value = label;
                    btn.disabled = event.value.length <= 0
                }
                const emptyNoneField = () => {
                    const el = document.getElementById("op_cli_version_track");
                    if (el.value === "none") el.value = "";
                    setButtonLabel(el);
                }

                window.onload = () => {
                    emptyNoneField()
                };
            </script>
        ', _("Install"), _("Uninstall"));
        }
}



?>