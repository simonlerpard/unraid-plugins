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
    jsonResponse($installer->checkForUpdates());
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
        if ($config->hasChanged("op_export_token_env")) {
            $plugin->getScriptGenerator()->handleTokenExportFile();
        }
        if ($config->hasChanged("op_cli_auto_update")) {
            $plugin->getScriptGenerator()->handleAutoUpdateCronFile();
        }

        jsonResponse($config->save());
    }

    jsonResponse($plugin->getConfig()->getConfigDiff());
}


http_response_code(400);
jsonResponse(["error" => [
    "message" => "Bad request",
]]);

exit;
