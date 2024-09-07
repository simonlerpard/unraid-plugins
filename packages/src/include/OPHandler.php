<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;

class OPHandler {
    private $plugin;
    private $op;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->op = $this->plugin->getInstaller()->getInstalledFile();
    }

    private function cmd($args, $getOutput = true) {
        $opToken = $this->plugin->getConfig()->get("op_cli_service_account_token");
        $opCache = $this->plugin->getConfig()->get("op_cli_use_cache");
        $op = $this->op;
        $cmd = escapeshellcmd("OP_SERVICE_ACCOUNT_TOKEN=\"{$opToken}\" OP_FORMAT=\"json\" OP_CACHE=\"{$opCache}\" \"{$op}\" {$args}");
        $output = shell_exec($cmd) ?: false;

        // If we expect an output, decode it and return. Otherwise return boolean.
        return $getOutput ? json_decode($output, true) : !!$output;
    }

    public function version($getOutput = true) {
        return $this->cmd("--version", $getOutput);
    }
    public function whoami($getOutput = true) {
        return $this->cmd("whoami", $getOutput);
    }

    public function listVaults() {
        return $this->cmd("vault list");
    }

    public function listItemsInVault($vault) {
        $vault = escapeshellarg($vault);
        return $this->cmd("item list --vault=\"{$vault}\"");
    }

    public function listFieldsInItem($vault, $itemId) {
        $vault = escapeshellarg($vault);
        $itemId = escapeshellarg($itemId);
        return $this->cmd("item get \"{$itemId}\" --vault=\"{$vault}\"");
    }

    public function read($item, $file, $overwrite = true) {
        $force = $overwrite ? "--force" : "";
        return $this->cmd(trim("read \"{$item}\" --out-file \"{$file}\" {$force}"));
    }
}
