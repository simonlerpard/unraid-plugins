<?php

$pluginRoot = $pluginRoot ?? dirname(dirname(__FILE__));
define("OP_PLUGIN_ROOT", $pluginRoot);

require_once ("$pluginRoot/include/Plugin.php");
$plugin = new Plugin($pluginRoot);
$installer = $plugin->getInstaller();
$config = $plugin->getConfig();

function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$install = !empty($_POST["install_cli"] ?? false) && !empty($_POST["op_cli_version_track"]);

if ($install) {
    $config->set("op_cli_version_track", $_POST["op_cli_version_track"]);
    $installer->setup();
    $diff = $config->save("op_cli_version_track");
    $nonConfig = $config->getNonConfig(true);
    jsonResponse([...$nonConfig, ...$diff]);
}

if (!empty($_POST["check_for_updates"] ?? false)) {
    $diff = $installer->checkForUpdates();
    if (empty($diff)) {
        jsonResponse($config->get("op_cli_latest_version_available", "op_cli_latest_stable_version_available", true));
    }
    jsonResponse($diff);
}

if (!empty($_POST["get_config"])) {
    jsonResponse($plugin->getConfig()->getAll());
}

if (!empty($_POST["get_default_config"])) {
    jsonResponse($plugin->getConfig()->getDefaultConfig());
}

if ($_POST["update_config"] ?? false) {
    $config->handlePostData();

    if ($_POST["save"] ?? false) {
        if ($config->hasChanged("op_export_env")) {
            $plugin->getScriptGenerator()->handleTokenExportFile();
        }
        if ($config->hasChanged("op_cli_auto_update")) {
            $plugin->getScriptGenerator()->handleAutoUpdateCronFile();
        }
        if ($config->hasChanged("op_cli_remove_dbus_files_in_tmp")) {
            $plugin->getScriptGenerator()->handleDBusInTmpDir();
        }

        jsonResponse($config->save());
    }

    jsonResponse($plugin->getConfig()->getConfigDiff());
}

if ($_POST["action_fetch_key"] ?? false) {
    $status = $plugin->getEventHandler()->fetchKeyFile();
    jsonResponse(["action_fetch_key" => $status]);
}

if ($_POST["action_delete_key"] ?? false) {
    $status = $plugin->getEventHandler()->deleteKeyFile();
    jsonResponse(["action_delete_key" => $status]);
}


http_response_code(400);
jsonResponse(["error" => [
    "message" => "Bad request",
]]);

exit;

?>
