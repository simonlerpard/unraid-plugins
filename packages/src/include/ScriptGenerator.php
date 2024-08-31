<?php

class ScriptGenerator {
    private $plugin;
    private $envScriptFile = "/etc/profile.d/set_op_env";

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    // TODO: Improve error handling
    public function handleEnvBashFile() {
        $configFile = $this->plugin->get('config');
        $export = $this->plugin->getConfig()->get("op_export_token_env") === "enabled";

        if(!$export) {
            if(!@unlink($this->envScriptFile)) {
                error_log("Failed to remove script file from {$this->envScriptFile}");
            }
            return;
        };

        $script = <<<EOL
#!/bin/bash

# Export the OP_SERVICE_ACCOUNT_TOKEN for the root user
[ "$(whoami)" == "root" ] && export OP_SERVICE_ACCOUNT_TOKEN="$(jq -r .'op_cli_service_account_token' '$configFile')"

return $?
EOL;

        if (!@file_put_contents($this->envScriptFile, $script)) {
            error_log("Failed to write script file to {$this->envScriptFile}");
            return;
        }

        if (!chmod($this->envScriptFile, 0755)) {
            error_log("Failed to make the script file executable, {$this->envScriptFile}");
            return;
        }

        return;
    }
}



