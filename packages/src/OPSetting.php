Menu="Utilities"
Title="1Password Integration"
Icon="main-circle-small-icon.png"
Tag="key"
---
<?php
$pluginRoot = $pluginRoot ?? "{$docroot}/{$page['root']}";
define("OP_PLUGIN_ROOT", $pluginRoot);

require_once ("$pluginRoot/include/Plugin.php");

$plugin = new Plugin();
echo Html::getStyleElement(); //  Styling and reset is needed in case of Exception.

try {
$config = $plugin->getConfig();
$installer = $plugin->getInstaller();
$latestOpVersion = $config->get("op_cli_latest_version_available");//"2.30.0";// $installer->getLatestVersion();
$config->handlePostData();

// Install/uninstall 1Password CLI if the install button is pressed.
if (!empty($_POST["install"] ?? false)) {
    $installer->setup();
}

if ($config->hasChanged("op_export_token_env")) {
    $plugin->getScriptGenerator()->handleTokenExportFile();
}

// No critical errors occurred, now it's safe to save the config file to disk.
$config->save();

echo Html::resetPostPageScript();

?>
<!-- LOAD JAVASCRIPT -->
<?= Html::getJavaScript($plugin, "settings.js"); ?>

<blockquote class="inline_help" style="margin:0 25px 50px;">
    <h2 >Instructions</h2>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus et neque sed libero pellentesque fringilla.Nam lacinia urna dolor, vel sollicitudin dui pulvinar id. Nulla volutpat vitae neque quis aliquet.Quisque luctus tortor tristique orci elementum, vel eleifend lectus pellentesque. Curabitur commodo congue magna in tincidunt.Aliquam cursus lobortis purus ac condimentum. Vestibulum in maximus tortor. Nullam a blandit lectus, a gravida lacus.</p>
    <p>Aenean sit amet mauris luctus, imperdiet quam id,euismod lacus. Nunc condimentum, massa nec interdum efficitur, augue metus semper velit, in egestas dolor metus et metus. Aliquam fringilla consectetur eros in elementum. Praesent sit amet urna viverra, tempus quam quis, blandit leo. Mauris accumsan leo at leo volutpat tristique. Sed mollis odio ut ante elementum posuere. Cras luctus massa id odio euismod, sit amet aliquet libero sollicitudin.</p>
    <p>Curabitur eleifend mauris eget sapien bibendum, hendrerit elementum diam aliquam. Donec vel magna in tellus varius ullamcorper. Praesent viverra elit nulla, id tincidunt ante tempor ut. Donec congue purus vitae nibh bibendum, et posuere nisl cursus. Maecenas vitae varius erat. Nunc sit amet ex pulvinar, lacinia metus id, laoreet eros. Aliquam erat volutpat. Praesent eu ullamcorper lorem. Nullam elementum feugiat velit dignissim dignissim. Mauris malesuada, ante condimentum consequat ornare, tellus elit luctus elit, sed viverra tortor ipsum sed nisl. Nunc bibendum mi at sem ultricies rhoncus. Nam in velit erat. Phasellus viverra et orci fringilla sodales.</p>
</blockquote>

<!-- SECTION: DOWNLOAD AND INSTALL THE CLI -->
<? //Html::getTitle("Download and install the cli", "cloud-download"); ?>

<?= Html::getSubTitle("Download and install the 1Password cli", "cloud-download");?>

**1Password cli version**:
: <?= Html::getInstalledOpVersion($config); ?> <span class="small">Latest: <span id="latestOpVersion"><?= $latestOpVersion; ?></span></span><span class="small">Stable: <?= $plugin->get('stableOPVersion'); ?></span>

:info_plug:
> The currently installed, latest and stable 1Password cli version.
:end

<form markdown="1" method="POST">
_(Choose version to install)_:
: <?= Html::getInstallVersionInput($config) . Html::getSpinner(); ?>

:info_plug:
> <strong>stable</strong> (recommended): Will install the latest verified version. Verified compatibility with the plugin by the plugin author (version: <?= $plugin->get('stableOPVersion'); ?>).
>
> <strong>latest</strong>: Will install the latest available release from AgileBits, 1Password (version: <?= $latestOpVersion; ?>).
>
> <strong>version number</strong>: Input the version number to install (e.g. version: 2.7.0 or 2.23.0-beta.01 or 2.30.0). This might break the plugin.
>
> <strong>none</strong>: Set to none to uninstall the 1Password CLI from unRAID again.
>
> You can find all versions and the release notes for each version on
> <a href="https://app-updates.agilebits.com/product_history/CLI2" target="_blank">1Passwords (AgileBits) website</a>
:end

Update automatically:
: <label style="margin-right:20px;"><input type="checkbox" checked /> On boot</label> <label><input type="checkbox" checked /> On plugin update</label>

:info_plug:
> Automatically install new versions of the 1Password cli. Either when booting the system or upgrading the plugin.
> Remember, automatic updates against the latest track might break the plugin. It's recommended to use the stable track!
>
> If **latest** is selected the latest available update will be installed.
>
> If **stable** is selected the stable version specified by the plugin author will get installed.
>
> *Post a comment in the forum if you'd like more options, like installing updates when available instead of the current events.*
:end
</form>


<!-- SECTION: 1Password CLI SETTINGS -->
<?= Html::getSubTitle("General settings", "cogs", true);?>

<form markdown="1" method="POST">
_(Service account token)_:
: <?= Html::getServiceAccountTokenInput($config); ?>

:op_cli_service_account_token_plug:
> **Note**: This token will be stored on the flash drive in plain text.
>
> Make sure it only has limited access to your vaults.
> If the flash drive gets compromised you should invalidate the token immediately.
>
> More info about how to setup 1Password with a service account can be found at the
> <a href="https://developer.1password.com/docs/service-accounts/get-started/" target="_blank">1Password's website</a>
:end

Export the service account token in the terminal
: <?= Html::getExportToken($config); ?>

:info_plug:
> When this is enabled you will automatically get the environment variable OP_SERVICE_ACCOUNT_TOKEN exported
> with the configured service account token for your 1Password service account.
> This will enable you to run *op* commands directly by just opening up a terminal without any further
> login hassle. run **op --help** or visit <a href="https://developer.1password.com/docs/cli/reference" target="_blank">1Password's website</a> for more details.
>
> Example commands:
>
> - **op whoami** (describes the current logged in user)
> - **op item list** (lists all available items for the logged in user)
> - **op --help** (displays information about available commands and flags)
> - **printenv** (displays all exported environment variables, including OP_SERVICE_ACCOUNT_TOKEN if it has been enabled)
>
> **Note**: Switchihng state for this setting will start/stop the export to any new session. But it will not handle any of the current terminal sessions. So you might need to restart the terminal for this setting to take effect.
:end

Mount encrypted disks with 1Password:
: <?= Html::getMountOptions($config); ?>

:info_plug:
> This will enable you to mount your disks from a 1Password vault item. Either manually or automatically on boot.
> If this setting is enabled all  other decryption methods will be disabled. This is because we'll hook into the starting event
> of the array and replace the keyfile with the 1Password item.
>
> If you changed your mind and don't want to use the 1Password vault item, you can easily revert this setting. Just set it to disabed
> and you should be back to normal. You can also uninstall the entire plugin, but that's a bit overkill.
>
> When this setting is enabled we will hook into the "starting" event hook. Fetch your 1Password item in the vault and copy it
> into the default keyfile location (which is in RAM), if there already is a local keyfile it will be replaced.
>
> If you have your keyfile/passphrase uploaded to your 1Password vault, the item is referenced correctly to your vault and you have an active internet connection
> you should be able to decrypt your drive using 1Password.
>
> **Internet access is required**, if you don't have any internet access from your server during the startup of the array
> the startup will fail and you must manually start it again later or disable this setting and manually start the array with the decryption
> keyfile/passphrase from a nother source.
:end

<!-- SECTION: SETTINGS FOR SETTING UP THE KEYFILE USAGE -->
<?= Html::getSubTitle("Disk decryption", "db", true);?>

<? if (isset($var) && strtolower($var["startArray"] ?? "") !== "yes"): ?>
<dl>
<dt>You don't have auto start enabled, do you wish to auto start on boot?</dt>
<dd>"Enable auto start" in <a href="/Settings/DiskSettings">Disk Settings</a></dd>
</dl>
<? endif; ?>

Send notifications if any error occur during startup
: <div class="alertContainer"><span>Select alert level:</span> <?= Html::getAlertOptions($config); ?></div>

:info_plug:
> This setting is mainly for you to decide how important the notification is for you. Some wish to have a simple notice, other wants to have big alarms
> if the array doesn't start correctly. We recommend to turn it on, but which level is simply up to you and your other <a href="/Settings/Notifications">notification settings</a>.
:end

Delete local keyfile/passphrase when the disks are mounted
: <?= Html::getDeleteKeyfileOptions($config); ?>

:info_plug:
> The keyfile is temporary stored in /root/keyfile during the time of mounting all disks.
> Enable this setting to automatically delete it once the mounting is completed.
>
> It's recommended to enable this feature to avoid leaking the key accidentally.
> As long as we have an internet connection we can always re-fetch it from the 1Password vault when we need it.
:end

Choose your vault item:
: <?= Html::getVaultItemInput($config); ?>

:info_plug:
> Choose the vault item you used to encrypt your disks. It's very important to be the exact same item,
> otherwise the decryption won't work.
>
> You can choose to either input the 1Password reference (starting with `op://`) as a text or selecting
> the item in the tree view ("Browse"). It's recommended to use the "Browse" feature to make sure the
> reference value is the correct one. The reference value usually follows these structures (square brackets indicates optional parameter):
>
> - `op://<vaultId>/<itemId>/<fieldId>[?specialOption=value]`
> - `op://<vaultId>/<itemId>/[<section>/]<fileId>`
>
:end

Validate selected key:
: <input type="button" value="Test the decryption key">

:info_plug:
> This will validate if the selected key actually can decrypt the disks or not.
>
> If the validation fails we will print some debug information for you.
>
:end

<!-- BOTTOM PAGE BUTTONS -->
&nbsp;
: <input type="submit" value="_(Apply)_"><input type="button" value="_(Close)_" onclick="done()">

</form>

<?php

// Print the error message if we catch any.
} catch (Exception $e) {
    echo Html::getErrorBox([$e->getMessage()]);
    echo '<button id="goBackBtn" onclick="history.back()">Go Back</button>';
}

// TODO: Move these styles and scripts to separate files or into th Html class
?>

<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.filetree.css?v=1700086926">
<script src="/webGui/javascript/jquery.filetree.js?v=1700086926" charset="utf-8"></script>

<style>
    .jqueryFileTree LI.attachment{
        background: url(/webGui/images/disk-image.png) left top no-repeat;
        background-size: 14px;
        margin-left: 2px;
    }
    input.mainInfoBtn{
        font-size: 8pt;
        cursor: help;
        float: right;
        display: block;
        margin: 0 5px 0 25px; /* top, right, bottom, left */
        max-height: fit-content;
    }
    input.expandAll{
        font-size: 8pt;
        cursor: help;
        float: right;
        display: block;
        margin: 0 25px 0 5px; /* top, right, bottom, left */
        max-height: fit-content;
    }
    #vaultItemWrapper input[type="button"] {
        vertical-align: top;
        margin-top: 0;
    }
    #vaultItemWrapper input[type="text"] {
        display: none;
        min-width: 35%;
    }
    #vaultItemWrapper div {
        display: inline-block;
        min-width: 35%;
        margin: 0 20px 0 0;
        padding-top:10px;
        border-top: 1px solid lightgray;
    }
    #goBackBtn {
        margin:10px;
    }
    dt {
        max-width: 420px;
    }
    .alertContainer {
        min-width: 200px;
        max-width: 300px;
        display: inline-block;
    }
    .alertContainer select {
        box-sizing: border-box;
        padding: 0 20px 0 5px;
        min-width: 95px;
        max-width: 95px;
    }
</style>

<script>
    $(document).ready(function() {
        // Skip loading the tree if the button is disabled. Which indicates invalid token.
        if ($("#vaultItemWrapper input[type='button']").is(":disabled")) return;
        $('#fileTreeDemo').fileTree({
            root: '/',
            script: '<?= $plugin->get("webroot"); ?>/php/OPFileTreeConnector.php',
            expandSpeed: 200,
            collapseSpeed: 200,
            multiFolder: false,
        }, function(file) {
            $("#op_vault_item").val(file);
            // Manually enable the Apply button, since we dind't do any keydown presses for our input.
            $("#op_vault_item").parents("form").find(':input[type="submit"]').prop('disabled', false);
            toggleVaultItemInputs();
        });
    });
    window.addEventListener('DOMContentLoaded', (event) => {
        $('#plugin_tree').fileTree({root:'/boot/',top:'/boot/',filter:'plg'}, function(file) {$('#plugin_file').val(file);});
        $(".title").first().append(`
            <input type="button"
            id="expandAll"
            class="expandAll"
            onclick="toggleAllInfo(this)"
            value="Toggle">

            <input type="button"
            class="mainInfoBtn"
            onclick="toggleInfo()"
            value="Info">

            <span style="float:none;clear:both;display:block;"></span>`)
        if (localStorage.getItem("op_info") === "show" || !localStorage.getItem("op_info")) {
            document.querySelector("blockquote").style.display = "block";
        }
        $("#op_vault_item").val()?.length ? toggleVaultItemInputs(true) : toggleVaultItemInputs(false);
    });
    const toggleVaultItemInputs = (showText = undefined) => {
        const btn = $("#vaultItemWrapper input[type='button']");
        const btnDisabled = btn.is(":disabled");
        let nextBtnValue = $("#op_vault_item").is(":visible") ? "Text" : "Browse";
        text = "Text";
        browse = "Browse";
        if (showText === true || btnDisabled) {
            $("#op_vault_item").show();
            $('#fileTreeDemo').hide();
            nextBtnValue = browse;
        } else if (showText === false) {
            $("#op_vault_item").hide();
            $('#fileTreeDemo').show();
            nextBtnValue = text;
        } else {
            $("#op_vault_item").toggle();
            $('#fileTreeDemo').toggle();
        }
        btn.val(nextBtnValue);
    }
    const toggleAllInfo =(btn) => {
        const items = $("blockquote.inline_help");
        const visible = items.filter(":visible");
        const hidden = items.filter(":hidden");
        visible.length > hidden.length ? items.hide("slow") : items.show("slow")
    }
    const toggleInfo = () => {
        const newStatus = !$("#helpinfo0").is(":visible");
        $("#helpinfo0").toggle("slow");
        localStorage.setItem("op_info", newStatus ? "show" : "hide");
    }
    const checkForUpdates = async (btn) => {
        setLoadingSpinner(true);
        try {
            const response = await fetch('<?= $plugin->get("webroot"); ?>/php//Api.php', {
                method: 'post',
                body: new URLSearchParams({
                    [btn.name] : true,
                    csrf_token
                })
            });
            const newVersion = (await response.json())?.op_cli_latest_version_available?.new;
            if (newVersion) {
                $("#latestOpVersion").text(newVersion);
            }
            console.log('Completed!', response);
        } catch(err) {
            console.error(`Error: ${err}`);
        } finally {
            setLoadingSpinner(false);
        }
    }
</script>
