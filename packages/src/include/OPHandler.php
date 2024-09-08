<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;

class OPHandler {
    private $plugin;
    private $op;

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->op = $this->plugin->getInstaller()->getInstalledFile();
    }

    public function hasOp() {
        $op = $this->op;
        return !!exec(escapeshellcmd("which \"{$op}\""));
    }

    public function version($getOutput = true) {
        return $this->cmd("--version", $getOutput, false);
    }
    public function whoami($getOutput = true) {
        return $this->cmd("whoami", $getOutput);
    }

    public function listVaults() {
        return $this->cmd("vault list");
    }

    public function listItemsInVault($vault) {
        return $this->cmd("item list --vault=\"{$vault}\"");
    }

    public function listFieldsInItem($vault, $itemId) {
        return $this->cmd("item get \"{$itemId}\" --vault=\"{$vault}\"");
    }

    public function read($item, $file, $getOutput = false, $overwrite = true) {
        $force = $overwrite ? "--force" : "";
        return $this->cmd(trim("read \"{$item}\" --out-file \"{$file}\" {$force}"), $getOutput);
    }

    private function cmd($args, $getOutput = true, $formatJson = true) {
        $opToken = $this->plugin->getConfig()->get("op_cli_service_account_token");
        $opCache = $this->plugin->getConfig()->get("op_cli_use_cache");
        $opFormat = $formatJson ? "json" : "human-readable";
        $op = $this->op;
        $cmd = escapeshellcmd("OP_SERVICE_ACCOUNT_TOKEN=\"{$opToken}\" OP_FORMAT=\"{$opFormat}\" OP_CACHE=\"{$opCache}\" \"{$op}\" {$args}");
        $output = shell_exec($cmd) ?: false;

        // If no output, return boolean
        // Corner case, but this will return false if the command doesn't produce any output (but still is successful).
        // All of the current commands currently always produce some output. But should probably be fixed in the future.
        if (!$getOutput) return !!$output;

        // If format json, return assoc array
        if ($formatJson) return json_decode($output, true);

        // Else, return output
        return $output;
    }
}
