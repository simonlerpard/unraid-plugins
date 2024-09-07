<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class OPInstaller {
    private $plugin;
    private $storageDir; // persistent storage
    private $installDir; // binary location
    private $tmpDir;     // temporary working directory
    private $opFileName = "op"; // The op cli name inside the downloaded archive
    private $groupName = "onepassword-cli"; // The group to assign the op cli
    private $persistentFile;
    private $installFile;

    public function __construct($plugin){
        $this->plugin = $plugin;
        $name = $this->plugin->get("name");
        $this->storageDir = $this->plugin->get("flashroot") . "/downloads";
        $this->installDir = "/usr/local/bin";
        $this->tmpDir = "/tmp/{$name}";
        $this->persistentFile = dirname($this->storageDir) . "/" . $this->getFileName();
        $this->installFile = $this->installDir . "/" . $this->opFileName;
    }

    public function setup() {
        $track = $this->plugin->getConfig()->get("op_cli_version_track");
        if ($track === "none") {
            return $this->uninstall();
        }
        $version = $track === "latest"
            ? $this->fetchLatestVersion()
            : ($track === "stable"
                ? $this->plugin->get("stableOPVersion")
                : $track);

        // If the versioned file doesn't exist, download and move it to persistent storage
        $versionedFile = $this->storageDir . "/" . $this->getFileName($version);
        if (!file_exists($versionedFile)) {
            $tmpDir = $this->download($version);
            $this->moveToPersistentStorage($tmpDir, $version);
        } else {
            $this->copyToPersistentStorage($versionedFile);
        }

        return $this->install();
    }

    public function checkForUpdates() {
        $version = $this->fetchLatestVersion();
        $this->plugin->getConfig()->set("op_cli_latest_version_available", $version);
        $this->plugin->getConfig()->save();
    }


    private function fetchLatestVersion() {
        // Info: https://1password.community/discussion/129022/how-to-programatically-fetch-the-latest-versions#Comment_637882
        // format: Mmmppbb (M = Major, m = minor, p = patch, b = build)
        $buildNumber = implode([
            "major" => "2",  // M
            "minor" => "00", // mm
            "patch" => "00", // pp
            "build" => "00"  // bb
        ]);
        $includeBeta = "N"; // Y or N
        $url = "https://app-updates.agilebits.com/check/1/0/CLI2/en/{$buildNumber}/{$includeBeta}";
        $resp = @file_get_contents($url);
        if ($resp === FALSE) throw new Exception("Could not fetch the latest version from 1Password, url: " . $url);

        $obj = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
        if (!array_key_exists("version", $obj)) {
            error_log("Missing version attribute from lPassword");
            error_log(print_r($obj));
            throw new Exception("The version attribute didn't exist on the response from 1Password, url: " . $url);
        }

        return $obj["version"];
    }

    private function download($version, $os = "linux", $arch = "amd64") {
        $url = sprintf(
            'https://cache.agilebits.com/dist/1P/op2/pkg/v%3$s/op_%1$s_%2$s_v%3$s.zip',
            $os, $arch, $version
        );
        $filename = $this->getFileName($version);
        $tmpDir = $this->tmpDir;
        $filePath = "{$tmpDir}/{$filename}";

        // Create dir
        if (!$this->createDir($tmpDir)) {
            error_log("Failed to create tmp directory. ({$tmpDir})");
            throw new Exception("Failed to create tmp directory. ({$tmpDir})");
        }

        // Download file
        $success = file_put_contents($filePath, @file_get_contents($url));
        if (!$success) {
            error_log(sprintf('Failed to download the 1Password cli from %s', $url));
            throw new Exception("Failed to download the 1Password cli from 1Password.");
        }

        // Verify download (unfortunately GnuPG isn't included in Unraid so we can't verify the signature)
        // But we'll unzip, execute and verify the version before storing it persistently
        $this->verifyDownloadedFile($filePath, $version);

        return $filePath;
    }

    private function moveToPersistentStorage($fromFilePath, $version) {
        $storageFilePath = $this->storageDir . "/" . $this->getFileName($version);

        // Should not happen, but if the storage directory doesn't exists, create it.
        if (!is_dir(dirname($storageFilePath))) mkdir(dirname($storageFilePath));

        // Only move the archive to flash if we've verified it to only store "clean" archives
        if (!rename($fromFilePath, $storageFilePath)) {
            error_log("Could not move the downloaded file to the storage location ({$storageFilePath})");
            throw new Exception("Could not move the downloaded file to the storage location ({$storageFilePath})");
        }

        $this->copyToPersistentStorage($storageFilePath);

        return true;
    }

    private function copyToPersistentStorage($storageFilePath) {
        if (file_exists($storageFilePath) && file_exists($this->persistentFile)) {
            // Don't copy the file if we already have the current file as a persistentFile
            if (md5_file($storageFilePath) === md5_file($this->persistentFile)) return;
        }

        // Create a copy without a version number
        // just so we know what to use on boot, without storing a version
        // number in the config file.
        if(!copy($storageFilePath, $this->persistentFile)) {
            error_log("Failed to create a copy as a non-versioned file");
            throw new Exception("Failed to create a copy as a non-versioned file");
        }
    }

    public function install() {
        if (!file_exists($this->persistentFile)) {
            error_log("Could not fine the persistent 1Password cli archive");
            throw new Exception("Could not fine the persistent 1Password cli archive");
        }
        if (!is_dir($this->installDir)) {
            error_log("Could not find the installation directory");
            throw new Exception("Could not find the installation directory");
        }

        $zip = new ZipArchive();

        try {
            if (!$zip->open($this->persistentFile))
                throw new Exception("Could not open the persistent zip archive");
            if (!$zip->extractTo($this->tmpDir, $this->opFileName))
                throw new Exception("Could not extract the 1Password cli file from archive");
            $zip->close();

            $tmpFilePath = $this->tmpDir . "/" . $this->opFileName;
            $newFilePath = $this->installDir . "/" . $this->opFileName;

            // Change the group of the file (create it if it doesn't exist)
            $this->createGroup($this->groupName);
            chgrp($tmpFilePath, $this->groupName);
            chmod($tmpFilePath, 02755); // Read/Write/Exec for owner, Read/Exec for everyone else. 2 = g+s (set SGID)

            $tmpOp = escapeshellarg($tmpFilePath);
            exec("{$tmpOp} --version", $output, $code);
            if ($code !== 0)
                throw new Exception("Failed to verify the binary file before finalizing the installation");

            // Delete the file to make sure it's not busy, otherwise the overwrite seems to fail
            if (file_exists($newFilePath)) @unlink($newFilePath);

            if (!rename($tmpFilePath, $newFilePath))
                throw new Exception("Failed to move the 1Password cli ({$tmpFilePath}) to the installation directory");

            return true;

        } catch (Exception $e) {
            if ($zip->status > 0) $zip->close();       // Close zip file if opened
            error_log($e->getMessage());               // Log the error message
            throw $e;
        }
    }

    public function uninstall() {
        $file = $this->installFile;
        if (!file_exists($file)) {
            $op = escapeshellarg(basename($file));
            exec("which {$op}", $output, $code);

            if ($code === 0)
                throw new Exception("The 1Password cli isn't installed where it's suppose to be. Please remove it manually.");

            // The file doesn't exist and it's not in the path. Consider it uninstalled already.
            $this->deleteGroup();
            return true;
        }

        if (!@unlink($file))
            throw new Exception("Failed to remove the file $file");

        $this->deleteGroup();
        return true;
    }

    private function createGroup($groupName, $gid = 800) {
        $groupName = escapeshellarg($groupName);
        $gid = escapeshellarg($gid);
        // Ok if groupName already exists, allocate automatic system gid if gid already is used
        exec("groupadd --gid $gid $groupName --force --system", $output, $code);
        if ($code !== 0)
            throw new Exception("Failed to create group {$groupName} with gid {$gid} (exit code: {$code}), output: " . implode("\n", $output));

        return true;
    }

    private function deleteGroup($groupName = "onepassword-cli") {
        $groupName = escapeshellarg($groupName);
        exec("groupdel $groupName", $output, $code);
        if ($code !== 0 && $code !== 6 ) // 0 = ok, 6 = group doesn't exist
            throw new Exception("Failed to delete the user group {$groupName} (exit code {$code}), output: ", implode("\n", $output));

        return true;
    }

    /**
     * Unzips the file to a temporary directory, verifies different parameters
     * and then remove the temporary directory again.
     *
     * @param [type] $filepath where the downloaded file is
     * @param [type] $expectedVersion version to be expected
     * @return boolean if the downloaded version matched the expected
     */
    private function verifyDownloadedFile($filepath, $expectedVersion) {
        $filename = $this->opFileName;

        if (!file_exists($filepath)) {
            error_log("Could not find the downloaded 1Password file {$filepath}.");
            throw new Exception("Could not find the downloaded 1Password file {$filepath}.");
        }

        $tmpDir = dirname($this->tmpDir) . "/" . uniqid(); // Temporary unique directory to remove once done.
        mkdir($tmpDir);
        $tmpFilePath = "{$tmpDir}/{$filename}";
        $zip = new ZipArchive();

        try {
            $openZip = $zip->open($filepath);
            if ($openZip !== true)
                throw new Exception("Failed to open zip archive");

            $info = $zip->statName($filename);

            if (false === $info)
                throw new Exception("The zip archive does not contain any {$filename} file");
            if (!array_key_exists("size", $info))
                throw new Exception("The {$filename} file does not have any size attribute (inside the zip archive)");
            if (!array_key_exists("name", $info))
                throw new Exception("The {$filename} file does not have any name attribute (inside the zip archive)");
            if ($info["size"] <= 0)
                throw new Exception("The size of the {$filename} file inside the archive is 0 bytes or smaller");
            if ($info["name"] !== $filename)
                throw new Exception("The {$filename} filename inside the archive does not match with the expected filename");
            if (!$zip->extractTo($tmpDir, $filename))
                throw new Exception("Failed to extract {$filename} to {$tmpDir}");
            if (!$zip->close())
                throw new Exception("Failed to close the zip file {$filepath}");
            if (!chmod($tmpFilePath, 0700))
                throw new Exception("Failed to chmod the extracted file ({$tmpFilePath})");

            $version = shell_exec(sprintf("%s --version", escapeshellcmd($tmpFilePath)));

            if (!$version)
                throw new Exception("Failed to run {$tmpFilePath}");

            @unlink($tmpFilePath);
            rmdir($tmpDir);
            return trim($version) === $expectedVersion;

        } catch (Exception $e) {
            if ($zip->status > 0) $zip->close();       // Close zip file if opened
            if (file_exists($tmpFilePath)) @unlink($tmpFilePath);
            if (is_dir($tmpDir)) rmdir($tmpDir);     // Remove temp dir if exists
            error_log($e->getMessage());               // Log the error message
            throw $e;
        }
    }

    private function getFileName($version = "") {
        $base = "1password-cli";
        $ext = "zip";
        return !empty($version) ? "{$base}-v{$version}.{$ext}": "{$base}.{$ext}";
    }

    private function createDir($path) {
        return is_dir($path) || mkdir($path, 0755, true);
    }

}
