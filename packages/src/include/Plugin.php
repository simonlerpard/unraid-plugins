<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

// TODO: Remove these two lines before release.
error_reporting(E_ALL);
ini_set('display_errors', 'On');
class Plugin {
    private $verifiedOP = "2.24.0";
    private $settings;
    public $config;
    public $installer;
    private $scriptGenerator;

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
        require_once ("{$root}/include/ScriptGenerator.php");
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

    public function getScriptGenerator() {
        return $this->scriptGenerator = $this->scriptGenerator ?? new ScriptGenerator($this);
    }

    public function get ($setting) {
        if (!array_key_exists($setting, $this->settings)) {
            throw new Exception("Invalid setting " . $setting );
        }
        return $this->settings[$setting];
    }

    // Should be run on plugin installation or on boot
    public function install() {
        echo "Installation started.";
        Config::createConfigIfNotExists($this->get('config'));

        echo "Reading config.";
        $config = $this->getConfig();

        if ($config->get("op_cli_version_track") !== "none") {
            echo "Installing the 1Password CLI";
            $opInstallFile = $config->get("op_cli_downloaded_file");

            if (!empty($opInstallFile) && is_file($opInstallFile)) {
                echo "Found local installation files, installing...";
                $this->getInstaller()->setup(true);
            } else {
                echo "Could not find local installation files, download and installation initiated...";
                $this->getInstaller()->setup();
            }
            echo "1Password CLI installation done.";

        } else {
            echo "The 1Password CLI has not been installed before. We'll not install it now.";
        }

        if ($config->get("op_disk_mount") === "enabled") {
            echo "Configuring the automatic fetch during disks mount.";
            $this->getScriptGenerator()->handleAutoMountFile();
        } else {
            echo "Auto fetch keyfile on mount is disabled.";
        }

        if ($config->get("op_disk_delete_keyfile") === "enabled") {
            echo "Configuring automatic removal of keyfile once the disks are mounted.";
            $this->getScriptGenerator()->handleRemoveKeyFile();
        } else {
            echo "Auto removal of the keyfile is disabled.";
        }

        if ($config->get("op_export_token_env") === "enabled") {
            echo "Configuring the automatic export of the service account token.";
            $this->getScriptGenerator()->handleTokenExportFile();
        } else {
            echo "Auto exporting of the service account token is disabled.";
        }

        if (!$config->hasValidToken()) {
            echo "WARNING: You don't have a valid service account token configured.";
        } else if ($config->isOpInstalled() && $config->hasValidToken(true)) {
            echo "Your service account token is valid.";
        }

        echo "1Password cli status: {$config->getInstalledOpVersion()}.";
        if (!$config->isOpInstalled()) {
            echo "Go to the settings page to install the 1Password CLI.";
        }
        echo "Installation script finished.";
    }
}

?>
