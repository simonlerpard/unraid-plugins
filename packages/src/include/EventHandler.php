<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;

class EventHandler {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function trigger($event) {
        switch ($event) {
            case "starting":
                $this->starting();
                break;
            case "disks_mounted":
                $this->disks_mounted();
                break;
            case "op_update":
                $this->op_update();
                break;
            default:
                return false;
        }
    }

    private function starting() {
        // Don't start if we haven't enabled the feature
        if ($this->plugin->getConfig()->get("op_disk_mount") !== "enabled") return;

        $this->fetchKeyFile();
    }

    private function disks_mounted() {
        // Don't delete the keyfile if we haven't enabled disk mount with 1Password
        if ($this->plugin->getConfig()->get("op_disk_mount") !== "enabled") return;

        // Don't delete the file if we've disabled auto delete.
        if ($this->plugin->getConfig()->get("op_disk_delete_keyfile") !== "enabled") return;

        $this->deleteKeyFile();
    }

    private function op_update() {
        // Don't update if we haven't enabled the auto update feature.
        if ($this->plugin->getConfig()->get("op_cli_auto_update") !== "enabled") return;

        $this->plugin->getInstaller()->update();
    }


    public function fetchKeyFile() {
        // Don't start if we don't have a valid vault item
        if (!$this->plugin->getConfig()->hasValidVaultItem()) return;

        // Don't start if we don't have a valid vault token
        if (!$this->plugin->getConfig()->hasValidToken()) return;

        $item = $this->plugin->getConfig()->get("op_vault_item");

        return $this->plugin->getOpHandler()->read($item, $this->plugin->get("keyfile"));
    }

    public function deleteKeyFile() {
        $file = $this->plugin->get("keyfile");
        if (!@unlink($file)) {
            error_log("Failed to remove script file from {$file}");
            return false;
        }

        return true;
    }
};
