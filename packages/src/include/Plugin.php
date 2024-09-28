<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class Plugin {
    private $settings;
    private $config;
    private $installer;
    private $scriptGenerator;
    private $eventHandler;
    private $opHandler;

    public function __construct($root = false) {
        global $docroot, $page;
        if (!$root && !(($docroot ?? 0) && ($page ?? 0)))
            throw new Exception("The input argument must include a root or be initialized from a page.");

        // Plugin settings:
        $root = $root ?: "{$docroot}/{$page['root']}";
        $name = basename($root);
        $webroot = "/plugins/{$name}";
        $flashroot = "/boot/config/plugins/{$name}";
        $config = "{$flashroot}/config.json";
        $stableOPVersion = "2.30.0"; // The latest 1Password cli version we've verified this plugin version towards
        $keyfile = "/root/keyfile";
        $triggerEventFile = "{$root}/event/any_event/TriggerEvent.php";

        $this->settings = compact(
            "root",
            "webroot",
            "flashroot",
            "name",
            "config",
            "stableOPVersion",
            "keyfile",
            "triggerEventFile"
        );

        require_once ("{$root}/include/Config.php");
        require_once ("{$root}/include/OPInstaller.php");
        require_once ("{$root}/include/OPHandler.php");
        require_once ("{$root}/include/ScriptGenerator.php");
        require_once ("{$root}/include/EventHandler.php");
    }

    // Get current config class or create a new one if it doesn't exist
    public function getConfig() {
        return $this->config = $this->config ?? new Config($this);
    }

    // Get current installer class or create a new one if it doesn't exist
    public function getInstaller() {
        return $this->installer = $this->installer ?? new OPInstaller($this);
    }

    public function getOpHandler() {
        return $this->opHandler = $this->opHandler ?? new OPHandler($this);
    }

    public function getScriptGenerator() {
        return $this->scriptGenerator = $this->scriptGenerator ?? new ScriptGenerator($this);
    }

    public function getEventHandler() {
        return $this->eventHandler = $this->eventHandler ?? new EventHandler($this);
    }

    public function get ($setting) {
        if (!array_key_exists($setting, $this->settings)) {
            throw new Exception("Invalid setting " . $setting );
        }
        return $this->settings[$setting];
    }

    // Should be run on plugin installation or on boot
    public function install() {
        echo "Installation started.\n";
        Config::createConfigIfNotExists($this->get('config'));

        echo "Reading config.\n";
        $config = $this->getConfig();

        if ($config->get("op_cli_version_track") !== "none") {
            echo "Installing the 1Password CLI\n";
            try {
                $this->getInstaller()->install();
                echo "1Password CLI installation done.\n";
            } catch (Exception $e) {
                echo "An error occurred during the installation of the 1Password CLI.\n";
                echo $e->getMessage() . "\n";
            }
        } else {
            echo "The 1Password CLI should not be installed now.\n";
        }

        if ($config->get("op_export_env") === "enabled") {
            echo "Configuring the automatic export of the service account token.\n";
            $this->getScriptGenerator()->handleTokenExportFile();
        } else {
            echo "Auto exporting of the service account token is disabled.\n";
        }

        if ($config->get("op_cli_auto_update") === "enabled") {
            echo "Configuring the automatic update schedule for the 1Password CLI.\n";
            $this->getScriptGenerator()->handleAutoUpdateCronFile();
            echo "Updating the 1Password CLI if installed and an update is available.\n";
            $this->getInstaller()->update();
        } else {
            echo "Automatic updates of the 1Password CLI is disabled..\n";
        }

        if ($config->get("op_cli_remove_dbus_files_in_tmp") === "enabled") {
            echo "Configuring automatic schedule to remove DBus files in /tmp.\n";
            $this->getScriptGenerator()->handleDBusInTmpDir();
        } else {
            echo "Skip removal of DBus files in /tmp.\n";
        }

        if (!$config->hasValidToken()) {
            echo "WARNING: You don't have a valid service account token configured.\n";
        } else if ($config->isOpInstalled() && $config->hasValidToken(true)) {
            echo "Your service account token is valid.\n";
        }

        echo "1Password cli status: {$config->getInstalledOpVersion()}.\n";
        if (!$config->isOpInstalled()) {
            echo "Go to the settings page to install the 1Password CLI.\n";
        }
        echo "Installation script finished.\n";
    }

    public function uninstall() {
        echo "Uninstallation initializing, starting cleanup process...\n";
        $this->getScriptGenerator()->handleTokenExportFile(true);
        $this->getScriptGenerator()->handleAutoUpdateCronFile(true);
        $this->getInstaller()->uninstall();
        // The cleanup of the web plugin directory are done with the package manager outside of this process.
        // The cleanup of the usb plugin directory are done with the package manager outside of this process.
        echo "Cleanup complete\n";
    }

    public function getStyle($nameOrPath) {
        $path = str_contains($nameOrPath, "/")
            ? $nameOrPath
            : $this->get("webroot") . "/style/{$nameOrPath}.css";
        return sprintf('
            <link rel="stylesheet" href="%s">
        ', $path);
    }

    public function getJavaScript($nameOrPath) {
        $path = str_contains($nameOrPath, "/")
            ? $nameOrPath
            : $this->get("webroot") . "/javascript/{$nameOrPath}.js";
        return sprintf('
            <script src="%s" type="text/javascript"></script>
        ', $path);
    }
}

?>
