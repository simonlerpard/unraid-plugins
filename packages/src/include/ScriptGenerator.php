<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

// TODO: Rename to FileManager
class ScriptGenerator {
    private $plugin;
    private $keyFile = "/root/keyfile";
    private $envScriptFile = "/etc/profile.d/op_set_env.sh";
    private $mountScriptFile = "/usr/local/emhttp/webGui/event/starting/op_auto_mount.sh";
    private $removeKeyFileScriptFile ="/usr/local/emhttp/webGui/event/disks_mounted/op_auto_delete_keyfile.sh";

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    // TODO: Improve error handling
    public function handleTokenExportFile($uninstall = false) {
        $enabled = $this->plugin->getConfig()->get("op_export_token_env") === "enabled";
        if ($enabled && !$uninstall) {
            return $this->createScriptFile($this->envScriptFile, false, $this->getEnvScript());
        };
        return $this->removeScriptFile($this->envScriptFile);
    }

    public function handleAutoMountFile($uninstall = false) {
        $enabled = $this->plugin->getConfig()->get("op_disk_mount") === "enabled";
        if ($enabled && !$uninstall) {
            return  $this->createScriptFile($this->mountScriptFile, true, $this->getFetchKeyScript());
        }
        return $this->removeScriptFile($this->mountScriptFile);
    }

    public function handleRemoveKeyFile($uninstall = false) {
        $enabled =  $this->plugin->getConfig()->get("op_disk_delete_keyfile") === "enabled";
        if ($enabled && !$uninstall) {
            return  $this->createScriptFile($this->removeKeyFileScriptFile, true, $this->getDeleteKeyScript());
        }
        return $this->removeScriptFile($this->removeKeyFileScriptFile);
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

# Export the OP_SERVICE_ACCOUNT_TOKEN for the root user
[ "$(whoami)" == "root" ] && export OP_SERVICE_ACCOUNT_TOKEN="$(jq -r .'op_cli_service_account_token' '$configFile')"

return $?
EOL;
}

    private function getFetchKeyScript() {
        $configFile = $this->plugin->get('config');
        $keyFile = $this->keyFile;
        return <<<EOL
#!/bin/bash

OP_SERVICE_ACCOUNT_TOKEN="$(jq -r .'op_cli_service_account_token' '$configFile')"
OP_VAULT_ITEM="$(jq -r .'op_vault_item' '$configFile')"

OP_SERVICE_ACCOUNT_TOKEN="\${OP_SERVICE_ACCOUNT_TOKEN}" /usr/local/bin/op read "\${OP_VAULT_ITEM}" --out-file "$keyFile" --force

exit $?
EOL;
    }

    private function getDeleteKeyScript() {
        $keyFile = $this->keyFile;
        return <<<EOL
#!/bin/bash

rm -f "$keyFile"

exit $?
EOL;
    }
}
