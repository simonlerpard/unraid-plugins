<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class ScriptGenerator {
    private $plugin;
    private $envScriptFile = "/etc/profile.d/op_set_env.sh";
    private $autoUpdateCronFile = "/etc/cron.daily/auto_update_op.sh";
    private $removeDbusFilesInTmpCronFile = "/etc/cron.daily/remove_op_dbus_files_in_tmp.sh";

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    // TODO: Improve error handling
    public function handleTokenExportFile($uninstall = false) {
        $enabled = $this->plugin->getConfig()->get("op_export_env") === "enabled";
        if ($enabled && !$uninstall) {
            return $this->createScriptFile($this->envScriptFile, false, $this->getEnvScript());
        };
        return $this->removeScriptFile($this->envScriptFile);
    }

    public function handleAutoUpdateCronFile($uninstall = false) {
        $enabled = $this->plugin->getConfig()->get("op_cli_auto_update") === "enabled";
        if ($enabled && !$uninstall) {
            return $this->createScriptFile($this->autoUpdateCronFile, true, $this->getTriggerUpdateCronScript());
        };
        return $this->removeScriptFile($this->autoUpdateCronFile);
    }

    public function handleDBusInTmpDir($uninstall = false) {
        $enabled = $this->plugin->getConfig()->get("op_cli_remove_dbus_files_in_tmp") === "enabled";
        if ($enabled && !$uninstall) {
            return $this->createScriptFile($this->removeDbusFilesInTmpCronFile, true, $this->getFixDBusFilesInTmpScript());
        };
        return $this->removeScriptFile($this->removeDbusFilesInTmpCronFile);
    }

    private function createScriptFile($file, $createDir, $script, $permission = 0755) {
        // Should not happen, but better to be safe than sorry.
        if (str_starts_with($file, "/boot/"))
            throw new Exception("We do not allow writing to the boot device!");

        $dir = dirname($file);
        if (!is_dir($dir)) {
            if ($createDir && !mkdir($dir, $permission, true)) {
                error_log("Failed to create directory {$dir}");
                return false;
            }
        }
        if (!@file_put_contents($file, $script)) {
            error_log("Failed to write file to {$file}");
            return false;
        }
        if (!chmod($file, $permission)) {
            error_log("Failed to make the script file executable, {$file}");
            return false;
        }

        return true;
    }

    private function removeScriptFile($file) {
        // Should not happen, but better to be safe than sorry.
        if (str_starts_with($file, "/boot/"))
            throw new Exception("We do not allow deleting files from the boot device!");

        if(!@unlink($file)) {
            error_log("Failed to remove script file from {$file}");
            return false;
        }

        return true;
    }

    private function getEnvScript() {
    $configFile = $this->plugin->get('config');
    return <<<EOL
#!/bin/bash

if [ "$(whoami)" == "root" ]; then
    # Export the OP_SERVICE_ACCOUNT_TOKEN and OP_CACHE for the root user
    EXPORT_OP_CACHE="$(jq -r .'op_export_cache' '$configFile')"
    EXPORT_OP_TOKEN="$(jq -r .'op_export_token' '$configFile')"
    [ "\${EXPORT_OP_CACHE}" == "true" ] && export OP_CACHE="$(jq -r .'op_cli_use_cache' '$configFile')"
    [ "\${EXPORT_OP_TOKEN}" == "true" ] && export OP_SERVICE_ACCOUNT_TOKEN="$(jq -r .'op_cli_service_account_token' '$configFile')"

    # Enable op auto completion in bash if op exists in the path
    which op >/dev/null 2>&1 && source <(op completion bash)
fi

return $?
EOL;
    }

    private function getTriggerUpdateCronScript() {
    $triggerEventFile = $this->plugin->get('triggerEventFile');
    return <<<EOL
#!/bin/bash

# Update 1Password cli if the trigger event file exists
[ -f "{$triggerEventFile}" ] && "{$triggerEventFile}" "op_update"

EOL;
    }

    private function getFixDBusFilesInTmpScript() {
    return <<<EOL
#!/bin/bash

# It seems like 1Password CLI generates a lot of dbus- files in the /tmp dir for some unknown reason. It's most likely a bug or something is missing.
# It might be related to these:
# - https://1password.community/discussion/145620/ssh-integration-causing-dbus-crashes
# - https://1password.community/discussion/140538/gnome-shell-crashing-on-fedora-38-linux-after-100s-of-ssh-sessions-through-keyring
#
find "/tmp" -type s -group onepassword-cli -name 'dbus-*' -exec rm {} +

EOL;
    }
}
