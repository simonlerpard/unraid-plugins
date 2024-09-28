<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class ConfigException extends Exception {};

class Config {
    private static $defaultConfig = [
        // Default config
        "op_cli_latest_version_available" => "N/A",
        "op_cli_version_track" => "none", // latest, stable, <version>, none
        "op_cli_auto_update" => "enabled", // enabled/disabled

        "op_cli_service_account_token" => "",
        "op_cli_use_cache" => "true", // true/false
        "op_export_token" => "true", // true/false
        "op_export_cache" => "true", // true/false
        "op_export_env" => "disabled", // enabled/disabled

        "op_disk_mount" => "disabled", // enabled/disabled
        "op_disk_delete_keyfile" => "disabled", // enabled/disabled
        "op_vault_item" => "",
        "op_cli_remove_dbus_files_in_tmp" => "enabled" // enabled/disabled - Temporary workaround to handle dbus files in /tmp issue
    ];
    private $plugin;
    private $config;
    private $configFromFile =[];
    private $file;
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

    /**
     * Saves one or multiple configuration parameters to the config file on the persistent disk.
     *
     * @param [string] ...$params Save specific params to file. If no params are specified entire config is saved.
     * @return [] Returns the changed values as a diff
     */
    public function save(...$params) {
        if (!$this->isModified()) return [];

        ksort($this->config);
        $newConfig = $this->config;
        $diff = $this->getConfigDiff();

        // Only save specific parameters from the "in-memory" config
        if (count($params) > 0) {
            $configToSave = array_combine($params, array_map([$this, "get"], $params));
            $newConfig = [
                ...$this->configFromFile,
                ...$configToSave
            ];

            // Filter the diff to only include the specific params instead
            $diff = array_intersect_key($diff, array_flip($params));
        }

        $success = file_put_contents($this->file, json_encode($newConfig, JSON_PRETTY_PRINT));

        if ($success === false) throw new ConfigException("Failed to save config file.");

        $this->configFromFile = $newConfig;

        return $diff;
    }

    // Get one or multiple parameters, accepts multiple input arguments, and if the last
    // argument is set to true, it will return as an associative array. Otherwise just the values.
    public function get(...$params) {
        $assoc = is_bool(end($params)) ? array_pop($params) : false;

        if (count($params) === 0) throw new Exception("Invalid input arguments, must contain at least one config parameter");
        if (count($params) > 1) {
            $params = array_unique($params);
            $result = array_combine($params, array_map([$this, "get"], $params));

            return $assoc ? $result : array_values($result);
        }

        $this->load();
        $k = array_shift($params);
        $config = $this->config;

        if (!array_key_exists($k, $config)) {
            // Try to fetch from the nonConfig if we can't find the parameter in the config
            $config = $this->getNonConfig();
            if (!array_key_exists($k, $config))
                throw new ConfigException("Could not find the config parameter " . $k);
        }

        $v = $config[$k];

        return $assoc ? [$k => $v] : $v;
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

    public function getDefaultConfig() {
        return Config::$defaultConfig;
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

    public function isModified() {
        return count($this->getConfigDiff()) > 0;
    }

    public function set($param, $newValue) {
        $current = $this->config[$param] ?? null;
        $this->config[$param] = $newValue;
    }

    public function deleteConfig($param) {
        unset($this->config[$param]);
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
        return !empty($array["signInAddress"] ?? "") &&
            !empty($array["email"] ?? "") &&
            !empty($array["secretKey"] ?? "") &&
            !empty($array["deviceUuid"] ?? "");
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
