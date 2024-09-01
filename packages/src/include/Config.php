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
        "op_disk_mount" => "disabled", // enabled/disabled
        "op_disk_alert_level" => "notice",
        "op_disk_delete_keyfile" => "disabled", // enabled/disabled
        "op_vault_name" => "", // REMOVE
        "op_vault_item" => "",
        "op_export_token_env" => "", // system, users, <comma separated users>
    ];
    private $config;
    private $configFromFile =[];
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
        $this->configFromFile = json_decode(@file_get_contents($this->file), true);
        $this->config = array_merge($this->config, $this->configFromFile);
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

    public function getConfigDiff() {
        $differences = [];
        $prev = $this->configFromFile;
        $new = $this->config;

        // Check for differences in $prev compared to $new
        foreach ($prev as $key => $value) {
            if (!array_key_exists($key, $new)) {
                $differences[$key] = ['prev' => $value, 'new' => null];
            } elseif ($new[$key] !== $value) {
                $differences[$key] = ['prev' => $value, 'new' => $new[$key]];
            }
        }

        // Check for keys in $new that are not in $prev
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $prev)) {
                $differences[$key] = ['prev' => null, 'new' => $value];
            }
        }

        return $differences;
    }

    public function hasChanged($var) {
        $diff = $this->getConfigDiff();
        return in_array($var, array_keys($diff));
    }

    public function getChange($var) {
        return $this->getConfigDiff()[$var] ?? [];
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

    // public function getPluginSettings () {
    //     return $this->getPluginSettings;
    // }

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