<?php

$pluginRoot = $pluginRoot ?? dirname(dirname(__FILE__));
define("OP_PLUGIN_ROOT", $pluginRoot);

require_once ("$pluginRoot/include/Plugin.php");
$plugin = new Plugin($pluginRoot);
$installer = $plugin->getInstaller();

if (!empty($_POST["check_for_updates"] ?? false)) {
    $installer->checkForUpdates();
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($plugin->getConfig()->getConfigDiff());

exit;
