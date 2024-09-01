<?php

if (!isset($_POST['dir'])) return;
$dir = $_POST['dir'];

$pluginRoot = $pluginRoot ?? dirname(dirname(__FILE__));
define("OP_PLUGIN_ROOT", $pluginRoot);

require_once ("$pluginRoot/include/Plugin.php");
require_once ("$pluginRoot/include/TreeFunctions.php");

$plugin = new Plugin($pluginRoot);
$token = $plugin->getConfig()->get("op_cli_service_account_token");

if (!$plugin->getConfig()->hasValidToken()) {
    http_response_code(401);
    echo "Invalid service account token. Save the new token and then try again.";
    exit;
}

if (!$plugin->getConfig()->isOpInstalled()) {
    http_response_code(403);
    echo "The 1Password CLI must first be installed.";
    exit;
}


if ($dir == '/') {
    // List all vaults
    $vaults = listVaults($token);
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
        $items = listItemsInVault($vaultName, $token);
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
        $vaultName = $parts[0];
        $itemId = $parts[1];
        $itemWithFields = listFieldsInItem($vaultName, $itemId, $token);
        echo generateTree($vaultName, $itemId, $itemWithFields); // Generate the tree structure for the fields
    }
}

?>
