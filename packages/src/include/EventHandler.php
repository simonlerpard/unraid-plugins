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
                $this->mounted();
                break;
            default:
                return false;
        }
    }

    // TODO: Move to somewhere else, should not be a part of the event handler since it's an operation. Probably the op handler or something similar.
    private function starting() {
        $mount = $this->plugin->getConfig()->get("op_disk_mount") === "enabled";
        $validItem = $this->plugin->getConfig()->hasValidVaultItem();
        $validToken = $this->plugin->getConfig()->hasValidToken();

        if ($mount && $validItem && $validToken) {
            $item = escapeshellarg($this->plugin->getConfig()->get("op_vault_item"));
            $token = escapeshellarg($this->plugin->getConfig()->get("op_cli_service_account_token"));
            $cmd = "OP_SERVICE_ACCOUNT_TOKEN={$token} /usr/local/bin/op read {$item} --out-file /root/keyfile --force";
            shell_exec($cmd);
        }
    }
    private function mounted() {
        if ($this->plugin->getConfig()->get("op_disk_delete_keyfile") === "enabled") {
            $file = "/root/keyfile";
            if (@unlink($file)) {
                error_log("Failed to remove script file from {$file}");
            }
        }
    }
};
