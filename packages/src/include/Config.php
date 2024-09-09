<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class ConfigException extends Exception {};

class Config {
    private static $defaultConfig = [
        // Default config
        "op_cli_version_track" => "none", // latest, stable, <version>, none
        "op_cli_latest_version_available" => "",
        "op_cli_auto_update_boot" => "true",
        "op_cli_auto_update_plugin" => "true",

        "op_cli_service_account_token" => "",
        "op_cli_use_cache" => "enabled", // TODO: enabled/disabled
        "op_export_token_env" => "", // system, users, <comma separated users>

        "op_disk_mount" => "disabled", // enabled/disabled
        "op_disk_delete_keyfile" => "disabled", // enabled/disabled
        "op_vault_item" => "",

        "op_disk_alert_level" => "notice",
    ];
    private $plugin;
    private $config;
    private $configFromFile =[];
    private $file;
    private $modified = false;
    private $loaded = false;
    private $installedOpVersion;

    public static function createConfigIfNotExists($file) {
        echo "Looking for config file.\n";
        if (file_exists($file)) {
            echo "Found config file.\n";
            return;
        }
        echo "No config file found, creating file with default config {$file}\n";
        $success = @file_put_contents($file, json_encode(self::$defaultConfig, JSON_PRETTY_PRINT));
        if (!$success) {
            echo "Received error creating the config file.\n";
            return;
        }
        echo "Config file was create successfully.\n";
    }

    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->config = Config::$defaultConfig;
        $this->file = $this->plugin->get('config');
        $this->load();
    }

    private function checkIfFileExists() {
        if (!file_exists($this->file)) throw new ConfigException("Config file does not exist.");
    }

    private function load($force = false) {
        if ($this->loaded && !$force) return;

        $this->checkIfFileExists();
        $this->configFromFile = json_decode(@file_get_contents($this->file), true);
        $this->config = [
            ...$this->config,
            ...$this->configFromFile
        ];
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

    public function getAll($includeNonConfigValues = false) {
        return $includeNonConfigValues
            ? [
                ...$this->config,
                ...$this->getNonConfig()
            ]
            : $this->config;
    }

    // Not really part of config since they are fetched from other
    // sources than the config file. And should never be stored inside
    // the config file either.
    public function getNonConfig($force = false) {
        return [
            "op_cli_latest_installed_version" => $this->getInstalledOpVersion($force),
            "op_cli_latest_stable_version_available" => $this->plugin->get("stableOPVersion"),
        ];
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

    public function getInstalledOpVersion($force = false) {
        $op = $this->plugin->getOpHandler();
        if ($force && $this->installedOpVersion) {
            unset($this->installedOpVersion);
        }
        return $this->installedOpVersion ?? $this->installedOpVersion = $op->hasOp()
            ? $op->version()
            : "not installed";
    }

    public function isOpInstalled() {
        return $this->getInstalledOpVersion() !== "not installed";
    }

    public function hasValidToken($testCmd = false) {
        $t = $this->get("op_cli_service_account_token");

        $split = explode("_", $t);
        if (count($split) !== 2) return false;

        $base64 = $split[1];
        if (strlen($base64) <= 0) return false;

        $json = base64_decode($split[1]);
        if (!$json || strlen($json) < 100) return false;

        $array = json_decode($json, true);
        if (!is_array($array ?? "") || empty($array)) return false;

        if ($testCmd) {
            if (!$this->isOpInstalled()) return false;
            return $this->plugin->getOpHandler()->whoami(false);
        }

        // Check if any of these attribute exists in the token and is not empty
        return !empty($array["signInAddress"] ?? "") > 0 &&
            !empty($array["email"] ?? "") > 0 &&
            !empty($array["secretKey"] ?? "") > 0 &&
            !empty($array["deviceUuid"] ?? "") > 0;
    }

    public function hasValidVaultItem($testCmd = false) {
        $i = $this->get("op_vault_item");
        return $testCmd
            ? $this->plugin->getOpHandler()->read($i, "/dev/null")
            : str_starts_with($i, "op://") || substr_count($i, "/") > 2;
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
