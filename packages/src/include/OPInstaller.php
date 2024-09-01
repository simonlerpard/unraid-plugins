<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

// TODO: Improve the installation of the CLI. Possibly re-write the entire thing.
class OPInstaller {
    private $config;
    private $plugin;

    public function __construct($plugin) {
        $this->config = $plugin->getConfig();
        $this->plugin = $plugin;
    }

    public function getLatestVersion($force = false) {
        $lastFetched = intval($this->config->get('op_cli_latest_version_checked'));
        if ($lastFetched + 3600 * 24 > time() && !$force) {
            // It's been less than 24 hours since we last fetched it, fetching from config instead.
            return $this->config->get('op_cli_latest_version_available');
        }

        // Since this is a public function we don't know who's responsible for
        // saving the config. Therefor we save the config.
        return $this->fetchLatestVersion(true);
    }

    function setup($useLocalFile = false) {
        $track = $this->config->get('op_cli_version_track');
        $arg = $track !== "none" ? "install" : "uninstall";
        $version = $this->getVersionFromTrack($track);
        if ($arg === "install" && !$useLocalFile) {
            $this->downloadOnePasswordCli($version);
        }
        // $cmdCode;
        $cmdOutput = [];
        $cmd = sprintf('%s/scripts/setup_op_cli_with_unraid.sh %s', $this->plugin->get("root"), $arg);
        exec($cmd, $cmdOutput, $cmdCode);

        if ($cmdCode !== 0) {
            error_log(sprintf("Failed to %s the 1Password cli. Script output:", $arg));
            error_log(print_r($cmdOutput, TRUE));
            throw new Exception(sprintf("Setup script error when doing %s of the 1Password cli. More details in the php log. (/var/log/phplog)", $arg));
        }
    }

    private function getVersionFromTrack($track) {
        switch($track) {
            case "latest":
                return $this->getLatestVersion();
            case "stable":
                return $this->plugin->get('verifiedOP');
            case "none":
                return "";
            default:
                return $track;
        }
    }

    private function fetchLatestVersion($save = false) {
        $url = "https://app-updates.agilebits.com/check/1/0/CLI2/en/2.0.0/N";
        $resp = @file_get_contents($url);
        if ($resp === FALSE) throw new Exception("Could not fetch the latest version from 1Password, url: " . $url);

        $obj = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
        if (!array_key_exists("version", $obj)) {
            error_log("Missing version attribute from lPassword");
            error_log(print_r($obj));
            throw new Exception("The version attibute didn't exist on the response from 1Password, url: " . $url);
        }
        $version = $obj["version"];
        $this->config->set('op_cli_latest_version_available', $version);
        $this->config->set('op_cli_latest_version_checked', time());

        if ($save) $this->config->save();

        return $version;
    }

    private function getDownloadFilePath() {
        $file = sprintf('%s/downloaded-op.zip', $this->plugin->get('flashroot'));
        if (!is_dir(dirname($file))) {
            throw new Exception("The directory for the downloaded file path does not exist.");
        }
        return  $file;
    }

    private function downloadOnePasswordCli($version, $os = "linux", $arch = "amd64") {
        $url = sprintf('https://cache.agilebits.com/dist/1P/op2/pkg/v%3$s/op_%1$s_%2$s_v%3$s.zip', $os, $arch, $version);
        $file = $this->getDownloadFilePath();
        $success = file_put_contents($file, @file_get_contents($url));
        if (!$success) {
            error_log(sprintf('Failed to download zip file from %s', $url));
            throw new Exception("Failed to download 1Password cli from 1Password.");
        }
        $this->config->set('op_cli_downloaded_file', $file);
        $this->config->set('op_cli_downloaded_version', $version);
        $this->config->set('op_cli_downloaded_timestamp', time());
        // Must also save to be synced with the downloaded file that's already been written to disk
        $this->config->save();
    }
}

?>
