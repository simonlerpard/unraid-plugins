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
            default:
                return false;
        }
    }

    private function starting() {
        $mount = $this->plugin->getConfig()->get("op_disk_mount") === "enabled";
        $validItem = $this->plugin->getConfig()->hasValidVaultItem();
        $validToken = $this->plugin->getConfig()->hasValidToken();

        if ($mount && $validItem && $validToken) {
            $item = $this->plugin->getConfig()->get("op_vault_item");
            $this->plugin->getOpHandler()->read($item, $this->plugin->get("keyfile"));
        }
    }

    private function disks_mounted() {
        if ($this->plugin->getConfig()->get("op_disk_delete_keyfile") === "enabled") {
            $file = $this->plugin->get("keyfile");
            if (@unlink($file)) {
                error_log("Failed to remove script file from {$file}");
            }
        }
    }
};
