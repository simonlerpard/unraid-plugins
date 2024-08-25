<?php

class Plugin {
    private $settings;
    public $config;
    public $installer;

    public function __construct($name, $root) {
        $flash = "/boot/config/plugins/$name";
        $config = "$flash/config.json";
        $verifiedOP = "2.24.0";
        $this->settings = compact("name", "root", "flash", "config", "verifiedOP");

        require_once ("$root/include/Config.php");
        require_once ("$root/include/OPInstaller.php");
        require_once ("$root/include/Html.php");
    }

    // Get current config class or create a new one if it doesn't exist
    public function getConfig() {
        return $this->config = $this->config ?? new Config($this->get('config'));
    }

    // Get current installer class or create a new one if it doesn't exist
    public function getInstaller() {
        return $this->installer = $this->installer ?? new OPInstaller($this);
    }

    public function get ($setting) {
        if (!array_key_exists($setting, $this->settings)) {
            throw new Exception("Invalid setting " . $setting );
        }
        return $this->settings[$setting];
    }
}

?>
