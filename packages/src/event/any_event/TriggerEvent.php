#!/usr/bin/php
<?php

// A list of all events that emhttp can generate
// Source: /usr/local/sbin/emhttp_event
$emhttpEvents = [
    //   Occurs early in emhttp initialization.
    //   Can also occur as a result of init-config and device slot change.
    //   Status information is valid.
    "driver_loaded",

    //   Occurs at beginning of cmdStart execution
    "starting",

    //   Occurs during cmdStart execution.
    //   The 'md' devices are valid.
    "array_started",

    //   Occurs during cmdStart execution.
    //   The disks and user shares (if enabled) are mounted.
    "disks_mounted",

    //   Occurs during cmdStart execution.
    //   Occurs as a result of changing/adding/deleting a share.
    //   The network services are started and may be exporting different share(s).
    "svcs_restarted",

    // TBD: Undocumented
    "svcs_restarting",

    //   Occurs during cmdStart execution.
    //   The docker service is enabled and started.
    "docker_started",

    //   Occurs during cmdStart execution.
    //   The libvirt service is enabled and started.
    "libvirt_started",

    //   Signals end of cmdStart execution.
    "started",

    //   Occurs at beginning of cmdStop execution
    "stopping",

    //   Occurs during cmdStop execution.
    //   About to stop libvirt.
    "stopping_libvirt",

    //   Occurs during cmdStop execution.
    //   About to stop docker.
    "stopping_docker",

    //   Occurs during cmdStop execution.
    //   About to stop network services.
    "stopping_svcs",

    //   Occurs during cmdStop execution.
    //   The network services have been stopped, about to unmount the disks and user shares.
    //   The disks have been spun up and a "sync" executed, but no disks un-mounted yet.
    "unmounting_disks",

    //   Occurs during cmdStop execution.
    //   The disks and user shares have been unmounted, about to stop the array.
    "stopping_array",

    //   Occurs at end of cmdStop execution, or if cmdStart failed.
    //   The array has been stopped.
    "stopped",

    //   Occurs after each time emhttp polls disk SMART data.
    //   Note that if array is not Started, emhttp will not spin down any disk, but emhttp will
    //   still poll SMART data (for spun-up devices) and generate this event.
    "poll_attributes",

    //   Occurs once per second.
    "heartbeat",
];

// The events that we actually use in this plugin:
$supportedEvents = [
    "starting",
    "disks_mounted"
];


function exitWithError($msg) {
    throw new Exception($msg);
    exit(1); // Just to be safe we exit.
}

if (count($argv) < 2) exitWithError("At least one argument is required.");

[ $file, $event ] = $argv;

if (in_array("--help", $argv) || in_array("-h", $argv) || in_array("/?", $argv)) {
    echo "Usage: {$file} <event>\n\n";
    echo "This script will trigger a specific event for the plugin.\n\n";
    echo "Supported events: \n" . implode("", array_map(fn($a) => "  {$a}\n", $supportedEvents));
    exit(0);
}

if (!in_array($event, $supportedEvents)) {
    if (in_array($event, $emhttpEvents)) {
        // It's a valid event, but we don't trigger on it. Just exit successfully.
        // 126 = Command cannot execute. (The command was found, but it could not be executed, possibly due to insufficient permissions or other issues.)
        exit(126);
    }
    exitWithError("Unsupported event type {$event}");
}

function getPluginRoot($d = __DIR__) { if ($d != "/") return is_file("$d/.pluginRoot") ? $d : getPluginRoot(dirname($d)); throw new Exception("Could not find the plugin root directory"); }
$pluginRoot = getPluginRoot();

define("OP_PLUGIN_ROOT", $pluginRoot);
require_once ("{$pluginRoot}/include/Plugin.php");

$plugin = new Plugin($pluginRoot);
$plugin->getEventHandler()->trigger($event);

exit(0);

// $mount = $plugin->getConfig()->get("op_disk_mount") === "enabled";
// $validItem = $plugin->getConfig()->hasValidVaultItem();
// $validToken = $plugin->getConfig()->hasValidToken();

// if ($mount && $validItem && $validToken) {
//     $item = escapeshellarg($plugin->getConfig()->get("op_vault_item"));
//     $token = escapeshellarg($plugin->getConfig()->get("op_cli_service_account_token"));
//     $cmd = "OP_SERVICE_ACCOUNT_TOKEN={$token} /usr/local/bin/op read {$item} --out-file /root/keyfile";
//     echo $cmd;
//     return;
//     shell_exec($cmd);
// }




/**
 * Name: OPHandler
 * Purpose: download/install/uninstall/run op commands, download attachments
 *
 * Commands:
 * op --version
 * op whoami
 * op vault list --format=json
 * op item list --vault=<vault> --format=json
 * op item get <item> --vault=<vault> --format=json
 * op read <item> --out-file <file>
 *
 *
 *
 *
 */
