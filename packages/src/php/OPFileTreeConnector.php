<?php

if (!isset($_POST['dir'])) return;
$dir = $_POST['dir'];

$pluginRoot = $pluginRoot ?? dirname(dirname(__FILE__));
define("OP_PLUGIN_ROOT", $pluginRoot);

require_once ("$pluginRoot/include/Plugin.php");
require_once ("$pluginRoot/include/TreeFunctions.php");

$plugin = new Plugin($pluginRoot);
$token = $plugin->getConfig()->get("op_cli_service_account_token");
$op = $plugin->getOpHandler();

function exitWithError($msg) {
    echo "Error: {$msg}";
    exit;
}

if (!$plugin->getConfig()->hasValidToken()) {
    exitWithError("Invalid service account token. Save the new token, reload and then try again.");
}

if (!$plugin->getConfig()->isOpInstalled()) {
    exitWithError("The 1Password CLI must first be installed.");
}

if ($dir == '/') {
    // List all vaults
    $vaults = $op->listVaults();
    if (!$vaults) {
        exitWithError("Failed to retrieve any vaults from 1Password.");
    }
    echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    foreach ($vaults as $vault) {
        $vaultName = htmlspecialchars($vault['name']);
        echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"/$vaultName/\">$vaultName</a></li>";
    }
    echo "</ul>";
} else {
    $parts = explode('/', trim($dir, '/'));
    if (count($parts) == 1) {
        // List items in the specified vault
        $vaultName = $parts[0];
        $items = $op->listItemsInVault($vaultName);
        if (!$items) {
            exitWithError("Failed to retrieve any items from 1Password.");
        }
        echo "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
        usort($items, "order");
        foreach ($items as $item) {
            $itemId = htmlspecialchars($item['id']);
            $itemTitle = htmlspecialchars($item['title']);
            echo "<li class=\"directory collapsed\"><a href=\"#\" rel=\"/$vaultName/$itemId/\">$itemTitle</a></li>";
        }
        echo "</ul>";
    } elseif (count($parts) == 2) {
        // List fields and files in the specified item
        [$vaultName, $itemId] = $parts;
        $itemWithFields = $op->listFieldsInItem($vaultName, $itemId);
        if (!$itemWithFields) {
            exitWithError("Failed to retrieve any field items from 1Password.");
        }
        echo generateTree($vaultName, $itemId, $itemWithFields); // Generate the tree structure for the fields
    }
}

?>
