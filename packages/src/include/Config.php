<?php

class ConfigException extends Exception {};

class Config {
    private static $defaultConfig = [
        // Default config
        "op_cli_version_track" => "stable", // latest, stable, <version>, none
        "op_cli_downloaded_file" => "",
        "op_cli_downloaded_version" => "",
        "op_cli_downloaded_timestamp" => "",
        "op_cli_latest_version_available" => "",
        "op_cli_latest_version_checked" => 0,
        "op_cli_service_account_token" => "",
        "op_cli_disk_auto_mount" => "none", // passphrase, keyfile, none
        "op_vault_name" => "",
        "op_vault_item" => "",
    ];
    private $config;
    private $file;
    private $modified = false;
    private $loaded = false;

    public static function getDefailtConfig() {
        return Config::$defaultConfig;
    }

    public function __construct($file) {
        $this->config = Config::$defaultConfig;
        $this->file = $file;
        $this->load();
    }

    private function checkIfFileExists() {
        if (!file_exists($this->file)) throw new ConfigException("Config file does not exist.");
    }

    private function load($force = false) {
        if ($this->loaded && !$force) return;

        $this->checkIfFileExists();
        $fromFile = json_decode(@file_get_contents($this->file), true);
        $this->config = array_merge($this->config, $fromFile);
        $this->loaded = true;
    }

    public function save() {
        if (!$this->modified) return;
        ksort($this->config);
        $success = file_put_contents($this->file, json_encode($this->config, JSON_PRETTY_PRINT));

        if ($success === false) throw new ConfigException("Failed to save config file.");

        $this->modified = false;
    }

    public function get($param) {
        $this->load();
        if (!array_key_exists($param, $this->config)) throw new ConfigException("Could not find the config parameter " . $param);

        return $this->config[$param];
    }

    public function set($param, $newValue) {
        $current = $this->config[$param] ?? null;
        $this->modified = $current !== $newValue || $this->modified;
        $this->config[$param] = $newValue;
    }

    public function deleteConfig($param) {
        unset($this->config[$param]);
        $this->modified = true;
    }

    public function getPluginSettings () {
        return $this->getPluginSettings;
    }

    public function handlePostData() {
        if (empty($_POST)) return;
        // Save input data to config if a config parameter exists
        foreach ($_POST as $key => $value) {
            $lowerKey = strtolower($key);
            if (array_key_exists($lowerKey, $this->config)) {
                $this->set($lowerKey, $value);
            }
        }
    }
}

?>