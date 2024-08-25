<?php


class OPInstaller {
    private $config;
    private $plugin;

    public function __construct($config, $plugin) {
        $this->config = $config;
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

    function setup() {
        $track = $this->config->get('op_cli_version_track');
        $arg = $track !== "none" ? "install" : "uninstall";
        $version = $this->getVersionFromTrack($track);
        if ($arg === "install") {
            $this->downloadOnePasswordCli($version);
        }
        $cmdCode;
        $cmdOutput = [];
        $cmd = sprintf('%s/scripts/setup_op_cli_with_unraid.sh %s', $this->plugin->get('httpDir'), $arg);
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
                return $this->config->get('op_cli_stable_version');
            case "none":
                return "";
            default:
                return $track;
        }
    }

    private function fetchLatestVersion($save = false) {
        $resp = @file_get_contents("https://app-updates.agilebits.com/check/1/0/CLI2/en/2.0.0/N");
        if ($resp === FALSE) throw new Exception("Could not fetch the latest version from 1Password");

        $obj = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
        if (!array_key_exists("version", $obj)) {
            error_log("Missing version attribute from lPassword");
            error_log(print_r($obj));
            throw new Exception("The version attibute didn't exist on the response from 1Password");
        }
        $version = $obj["version"];
        $this->config->set('op_cli_latest_version_available', $version);
        $this->config->set('op_cli_latest_version_checked', time());

        if ($save) $this->config->save();

        return $version;
    }

    private function getDownloadFilePath() {
        $file = sprintf('%s/downloaded-op.zip', $this->plugin->get('flashDir'));
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

// function has_cache() {
//     if (!isset($_SESSION)) return false;
//     if (!array_key_exists("op_cli_plugin_cache", $_SESSION)) return false;
//     if ($_SESSION["op_cli_plugin_cache"]["cacheExpires"] < time()) return false;
//     return true;
// }

// function get_latest_version_from_cache() {
//     return has_cache() ? $_SESSION["op_cli_plugin_cache"]["latestVersion"] : false;
// }

// function get_downloaded_version_from_cache() {
//     return has_cache() ? $_SESSION["op_cli_plugin_cache"]["latestDownloadedVersion"] : false;
// }

// function set_latest_version_to_cache($version) {
//     if (!isset($_SESSION["op_cli_plugin_cache"])) $_SESSION["op_cli_plugin_cache"] = [];
//     $_SESSION["op_cli_plugin_cache"]["latestVersion"] = $version;
//     $_SESSION["op_cli_plugin_cache"]["cacheExpires"] = time() + 7200;

//     return $version;
// }

// function set_downloaded_version_to_cache($version) {
//     if (!isset($_SESSION["op_cli_plugin_cache"])) $_SESSION["op_cli_plugin_cache"] = [];
//     $_SESSION["op_cli_plugin_cache"]["latestDownloadedVersion"] = $version;
//     $_SESSION["op_cli_plugin_cache"]["cacheExpires"] = time() + 7200;

//     return $version;
// }

// function get_latest_one_password_cli_version() {
//     try {
//         if ($cachedVersion = get_latest_version_from_cache()) return $cachedVersion;
//         $resp = @file_get_contents("https://app-updates.agilebits.com/check/1/0/CLI2/en/2.0.0/N");
//         if ($resp === FALSE) throw new Exception();

//         $obj = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
//         if (!array_key_exists("version", $obj)) throw new Exception();

//         return set_latest_version_to_cache($obj["version"]);
//     } catch (Exception $e) {
//         return "Failed to fetch latest version from 1Password";
//     }
// }

// function download_one_password_cli($version, $file) {
//     if (get_downloaded_version_from_cache() === $version) {
//         // File was already downloaded within the cache period. Skipping the download part since it should be on the flash drive.
//         error_log("Found 1Password cli from session cache. Skipping to re-download the file until cache expires.");
//         return;
//     };
//     $os = "linux";
//     $arch = "amd64";
//     $url = sprintf('https://cache.agilebits.com/dist/1P/op2/pkg/v%3$s/op_%1$s_%2$s_v%3$s.zip', $os, $arch, $version);
//     $success = file_put_contents($file, @file_get_contents($url));
//     if (!$success) {
//         error_log(sprintf('Failed to download zip file from %s', $url));
//         throw new Exception("Failed to download 1Password cli from 1Password.");
//     }
//     set_downloaded_version_to_cache($version);
// }

// function setup_one_password_cli($plugin, $version) {
//     $cmd = !empty($version) ? "install" : "uninstall";
//     if ($cmd === "install") {
//         $file = sprintf('%s/downloaded-op.zip', $plugin->get('flashDir'));
//         $success = download_one_password_cli($version, $file);
//     }
//     $cmdOutput = [];
//     $cmdCode;
//     exec(sprintf('%s/scripts/setup_op_cli_with_unraid.sh %s', $plugin->get('httpDir'), $cmd), $cmdOutput, $cmdCode);
//     if ($cmdCode !== 0) {
//         error_log(sprintf("Failed to %s the 1Password cli. Script output:", $cmd));
//         error_log(print_r($cmdOutput, TRUE));
//         throw new Exception("Setup script error when doing %s of the 1Password cli. More details in the php log. (/var/log/phplog)");
//     }

//     return true;
// }

// function handle_post_requests($plugin, $config) {
//     if ($opVersion = $_POST["install_op_version"] ?? false) {
//         // switch(strtolower($opVersion)) {
//         //     case "latest":
//         //         return setup_one_password_cli($plugin, get_latest_one_password_cli_version());
//         //     case "stable":
//         //         return setup_one_password_cli($plugin, "2.20.0"); //  TODO set this somewhere else
//         //     case "none":
//         //         return setup_one_password_cli($plugin, "");
//         //     default:
//         //         return setup_one_password_cli($plugin, $opVersion);
//         // }
//     }

//     return false;
// }

?>