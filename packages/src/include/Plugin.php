<?php

// TODO: Remove these two lines before release.
error_reporting(E_ALL);
ini_set('display_errors', 'On');
class Plugin {
    private $verifiedOP = "2.24.0";
    private $settings;
    public $config;
    public $installer;
    private $envManager;

    public function __construct($root = false) {
        global $docroot, $page;
        if (!$root && !(($docroot ?? 0) && ($page ?? 0)))
            throw new Exception("The input argument must include a root or be initialized from a page.");

        $root = $root ?: "{$docroot}/{$page['root']}";
        $name = basename($root);
        $webroot = "/plugins/{$name}";
        $flashroot = "/boot/config/plugins/{$name}";
        $config = "{$flashroot}/config.json";
        $verifiedOP = $this->verifiedOP;

        $this->settings = compact("root", "webroot", "flashroot", "name", "config", "verifiedOP");

        require_once ("{$root}/include/Config.php");
        require_once ("{$root}/include/OPInstaller.php");
        require_once ("{$root}/include/EnvironmentManager.php");
        require_once ("{$root}/include/Html.php");
    }

    // Get current config class or create a new one if it doesn't exist
    public function getConfig() {
        return $this->config = $this->config ?? new Config($this->get('config'));
    }

    // Get current installer class or create a new one if it doesn't exist
    public function getInstaller() {
        return $this->installer = $this->installer ?? new OPInstaller($this);
    }

    public function getEnvironmentManager() {
        return $this->envManager = $this->envManager ?? new EnvironmentManager("/etc/environment", "OP_SERVICE_ACCOUNT_TOKEN");
    }

    public function get ($setting) {
        if (!array_key_exists($setting, $this->settings)) {
            throw new Exception("Invalid setting " . $setting );
        }
        return $this->settings[$setting];
    }
}

?>
