// Constants defined outside of this script:
// OP_PLUGIN_PATH  - The webroot path to the plugin directory
// OP_CONFIG    - The config object, containing the current configuration for the plugin

window.addEventListener('DOMContentLoaded', () => {
    spinner(true);
    setConsole();
    addTopMenuButtons();
    addJQueryListeners();
    loadConfigValues({
        ...OP_CONFIG
    }, true);

    // Remove all config placeholder elements
    // They are only there to have a default gui value before loading config
    removeConfigPlaceholders();
    loadFileTree();
    spinner(false);
});

const getApiEndpoint = () => {
    if (!OP_PLUGIN_PATH.length) throw new Error("Missing the OP_PLUGIN_PATH variable.");
    return `${OP_PLUGIN_PATH}/php/Api.php`;
}

const loadFileTree = () => {
    // Skip loading the tree if the button is disabled. Which indicates invalid token.
    if ($("#vault_item_wrapper input[type='button']").is(":disabled")) return;
    $('#op_file_tree_wrapper').fileTree({
        root: '/',
        script: `${OP_PLUGIN_PATH}/php/OPFileTreeConnector.php`,
        expandSpeed: 200,
        collapseSpeed: 200,
        multiFolder: false,
    }, function(file) {
        $("#op_vault_item").val(file);
        // Manually enable the Apply button, since we didn't do any keydown presses for our input.
        // $("#op_vault_item").parents("form").find(':input[type="submit"]').prop('disabled', false);
        $("#op_vault_item").trigger("input");
        toggleVaultItemInputs();
    });
    // Show the initial view (text or tree)
    $("#op_vault_item").val()?.length ? toggleVaultItemInputs(true) : toggleVaultItemInputs(false);
}

const addTopMenuButtons = () => {
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
        <span class="clearFloat"></span>
    `);

    // Determine if we want to show the info initially or not. (Remembers the last state)
    if (localStorage.getItem("op_info") === "show" || !localStorage.getItem("op_info")) {
        document.querySelector("blockquote").style.display = "block";
    }
}

// Temporary workaround to restore the console since console.log has been removed by vite...
// https://forums.unraid.net/bug-reports/stable-releases/61210-javascript-consoledebug-and-consolelog-disabled-due-to-vue-i18n-r3084/
const setConsole = () => {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);
    console = iframe.contentWindow.console;
    window.console = console;
}

// A fixed spinner is already loaded from the WebGUI with the class .spinner and .fixed. Use it.
// Also block clicks inside the wrapper when spinner is shown, to avoid multiple clicks on the same thing.
const spinner = (show) => {
    if (show) {
        $(".spinner").show()
        $(".op-settings-wrapper").addClass("block-clicks");
        return;
    }
    $(".spinner").hide()
    $(".op-settings-wrapper").removeClass("block-clicks");
}

const removeConfigPlaceholders = () => $(".config-placeholder").remove();

/**
 * Loading each key-value pair from the config into the DOM
 * Each key must be an id of the element to load the value into
 */
const loadConfigValues = (config) => {
    config = convertDiffToConfig(config);
    changedElements = [];
    // Set values to all elements
    for ([key, val] of Object.entries(config)) {
        const jqElement = $(`#${key}`);
        let changed = false;
        // Set "text" value
        if (jqElement.is("span")) {
            changed = jqElement.text() !== val;
            jqElement.text(val);
        }
        // Set input value
        else if (jqElement.is("input")) {
            switch (jqElement.attr("type")) {
                case "text":
                case "password":
                case "hidden":
                    changed = jqElement.val() !== val;
                    jqElement.val(val)
                    break;
                case "checkbox":
                    const checked = val === "true";
                    changed = jqElement.is(":checked") !== checked;
                    jqElement.prop("checked", checked)
                    break;
                default:
                    console.warn(" Setting values: Unsupported input type " + jqElement.attr("type"));
                    break;
            }
        }
        // Set select value
        else if (jqElement.is("select")) {
            changed = jqElement.val() !== val;
            jqElement.val(val);
        }

        if (changed) {
            changedElements.push(jqElement);
        }
    }

    // Trigger the change event if changed
    changedElements.forEach(el => {
        el.trigger("input");
        el.trigger("change");
    });
}

const saveConfigDiffLocally = (diff) => {
    config = convertDiffToConfig(diff);
    Object.entries(config).forEach(([k, v]) => {
        OP_CONFIG[k] = v;
        $(`#${k}`).trigger("input");
        $(`#${k}`).trigger("change");
    });
}

// Convert diff to normal config, if the new key doesn't exist, assume it's a normal config.
// Convert { key: {prev: "oldValue", new: "newValue"}, ...} to {key: "newValue", ...}
const convertDiffToConfig = (diff) => {
    if (!diff) return {};
    return Object.fromEntries(
        Object.entries(diff).map(([key, value]) => [key, value.new ?? value])
    );
}

// Parse and get the config from the input fields
// Input either a config or an array of config keys.
const getLocalConfig = (configOrKeys) => {
    const config = Array.isArray(configOrKeys) ?
        Object.fromEntries(configOrKeys.map(k => [k])) :
        configOrKeys;
    const entries = Object.entries(config).map(([key, value]) => {
        const jqElement = $(`#${key}`);

        if (jqElement.is("span")) return [key, jqElement.text()];
        if (jqElement.is("select")) return [key, jqElement.val()];
        if (jqElement.is(`input[type="text"]`)) return [key, jqElement.val()];
        if (jqElement.is(`input[type="password"]`)) return [key, jqElement.val()];
        if (jqElement.is(`input[type="hidden"]`)) return [key, jqElement.val()];
        if (jqElement.is(`input[type="checkbox"]`)) return [key, jqElement.is(":checked") ? "true" : "false"];

        console.warn(`Could not find element with id ${key} when fetching local config`);

        return [];
    }).filter(a => a.length);

    return Object.fromEntries(entries);
}

const getConfig = async (btn) => {
    spinner(true);
    try {
        const response = await fetch(getApiEndpoint(), {
            method: 'post',
            body: new URLSearchParams({
                "get_config": true,
                csrf_token
            })
        });
    } catch (err) {
        console.error(`Error: ${err}`);
    } finally {
        spinner(false);
    }
}

const sendApiRequest = async (params = {}) => {
    let configDiff = {};
    try {
        spinner(true);
        params = {
            ...params,
            csrf_token
        };
        console.log("Sending API request: ", params);
        const response = await fetch(getApiEndpoint(), {
            method: 'post',
            body: new URLSearchParams(params)
        });
        console.log('Completed!', response);
        configDiff = await response.json();
    } catch (err) {
        console.error(`Error: ${err}`);
    } finally {
        spinner(false);
    }

    return configDiff;
}

const addJQueryListeners = () => {
    // Handle selects with custom text inputs
    $(".select-with-custom-text-input").on("change keyup", (event) => {
        const jqTarget = $(event.target);
        const jqSelect = $($(event.currentTarget).children("select").get(0));
        const jqText = $($(event.currentTarget).children("input:text").get(0));
        // Can't use :hidden, because then it will catch everything that's hidden (including the text input if it happens to have display:none)
        const jqHidden = $($(event.currentTarget).children(`input[type="hidden"]`).get(0));
        const options = jqSelect.children("option")
            .map((i, o) => o.value)
            .toArray()
            .filter(value => value !== "custom");
        const isValidOption = options.includes(jqTarget.val());

        if (jqTarget.is("select")) {
            if (isValidOption) {
                jqText.hide();
                jqHidden.val(jqTarget.val()).change();
            } else {
                jqText.show();
            }
        } else if (jqTarget.is("input:text")) {
            jqHidden.val(jqTarget.val()).change();
        } else if (jqTarget.is(`input[type="hidden"]`)) {
            if (jqTarget.val() !== jqSelect.val()) {
                jqSelect.val(isValidOption ? jqTarget.val() : "custom").change()
            }
            if (jqTarget.val() !== jqText.val() && !isValidOption) {
                jqText.val(jqTarget.val()).change();
            }
        }
    });

    // Handle install button
    // Enable/Disable the button based on text field
    $(".select-with-custom-text-input:has(>#op_cli_version_track)").on("change keyup", (event) => {
        // Only disable the button if:
        // We've selected "custom" and the text input is empty
        // or
        // We've selected "none" when we don't have 1Password cli installed
        const jqText = $($(event.currentTarget).children("input:text").get(0));
        const jqSelect = $($(event.currentTarget).children("select").get(0));
        const opNotInstalled = $("#op_cli_latest_installed_version").text() === "not installed";
        const noneSelected = jqSelect.val() === "none";
        const customSelected = jqSelect.val() === "custom";
        const customWithNoText = customSelected && jqText.val().length === 0;
        const regex = /^\d{1,2}\.\d{1,2}\.\d{1,2}(-beta\.\d{2})?$/;
        const isAlreadyUninstalled = noneSelected && opNotInstalled;
        const doesNotMatchRegex = !regex.test(jqText.val());
        const disabled = isAlreadyUninstalled || customWithNoText;
        $("#install_btn").prop("disabled", disabled);
        if (isAlreadyUninstalled) {
            $("#install_btn").prop("disabled", true);
            $("#install_btn").prop("title", "The 1Password cli is already uninstalled");
        } else if (customWithNoText) {
            $("#install_btn").prop("disabled", true);
            $("#install_btn").prop("title", "You must input a valid version");
        } else if (doesNotMatchRegex && customSelected) {
            $("#install_btn").prop("disabled", false); // Allow anyway, in case 1Password changes pattern. But set the title at least.
            $("#install_btn").prop("title", "Version format should be X.Y.Z or X.Y.Z-beta.XX where X and Y are digits and XX is a two-digit number");
        } else {
            $("#install_btn").prop("disabled", false);
            $("#install_btn").prop("title", "");
        }
    });
    // Rename button based on selection (Install/Uninstall)
    $(".select-with-custom-text-input:has(>#op_cli_version_track)").on("change keyup", (event) => {
        const jqSelect = $($(event.currentTarget).children("select").get(0));
        const label = jqSelect.val() !== "none" ? "Install" : "Uninstall";
        $("#install_btn").val(label);
    });
    // Handle click events
    $("#install_btn").on("click", handleInstallation)
    $("#check_for_updates").on("click", handleCheckForUpdates)
    $("#apply_btn").on("click", handleConfigUpdate);
    $("#default_btn").on("click", handleDefaultConfig)
    $("#restore_btn").on("click", handleRestoreConfig)

    // Toggle password icon and the password input type
    $(".pwd-toggle").on("click", (event) => {
        const jqTarget = $(event.currentTarget);
        jqTarget.toggleClass("fa-eye fa-eye-slash");

        const showPassword = jqTarget.hasClass("fa-eye-slash");
        const type = showPassword ? "text" : "password";
        const title = showPassword ? "Hide value" : "Show value";

        jqTarget.parent().children("input").prop("type", type)
        jqTarget.prop("title", title);
    })

    // Listen for all config inputs/changes. Display apply button if there's a diff.
    const configIdsSelector = Object.keys(OP_CONFIG).map(k => `#${k}`).join(", ");
    $(configIdsSelector).on("input", () => {
        const local = getLocalConfig(OP_CONFIG);
        const modifiedConfig = Object.fromEntries(Object.entries(local).filter(([k, v]) => {
            return !(k in OP_CONFIG) || OP_CONFIG[k] !== v;
        }));
        const disableBtn = $.isEmptyObject(modifiedConfig);
        const title = disableBtn ? "No changes has been made" : "";

        $("#apply_btn").prop("disabled", disableBtn);
        $("#apply_btn").prop("title", title);
        $("#restore_btn").prop("disabled", disableBtn);
        $("#restore_btn").prop("title", disableBtn ? title : "Restore to previous settings");
    })

    $("#op_export_env").on("input", (event) => {
        const exportEnv = $(".export_env");
        if (event.target.value === "enabled") {
            exportEnv.show();
        } else {
            exportEnv.hide();
        }
    });
}

const handleInstallation = async () => {
    const op_cli_version_track = $("#op_cli_version_track").val();
    const diff = await sendApiRequest({
        install_cli: true,
        op_cli_version_track
    });
    loadConfigValues(diff);
    saveConfigDiffLocally(diff);
}

const handleCheckForUpdates = async () => {
    const diff = await sendApiRequest({
        check_for_updates: true,
    });
    loadConfigValues(diff);
    saveConfigDiffLocally(diff);
}

const handleConfigUpdate = async () => {
    const config = getLocalConfig(OP_CONFIG);
    const diff = await sendApiRequest({
        ...config,
        update_config: true,
        save: true,
    });
    loadConfigValues(diff);
    saveConfigDiffLocally(diff);
}

const handleDefaultConfig = async () => {
    const track = OP_CONFIG.op_cli_version_track;
    const diff = await sendApiRequest({
        get_default_config: true,
    });
    spinner(true);
    loadConfigValues(diff);

    $confirmMsg = "Do you wish to save & apply the default configuration right now?";
    if (track !== "none" && getLocalConfig(OP_CONFIG).op_cli_version_track === "none") {
        $confirmMsg += "\n\nNote: This will also uninstall the 1Password CLI.";
    }

    // Sleep a short while to give the triggers time to update the UI.
    // Maybe not the best solution, but it's a simple one
    await sleep(250);
    spinner(false);
    if (confirm($confirmMsg)) {
        await handleConfigUpdate();
        spinner(true);
        await handleInstallation();
        spinner(true);
    }
}

const handleRestoreConfig = () => {
    loadConfigValues(OP_CONFIG);
    saveConfigDiffLocally(OP_CONFIG);
}



const toggleVaultItemInputs = (showText = undefined) => {
    const btn = $("#vault_item_wrapper input[type='button']");
    const btnDisabled = btn.is(":disabled");
    let nextBtnValue = $("#op_vault_item").is(":visible") ? "Text" : "Browse";
    text = "Text";
    browse = "Browse";
    if (showText === true || btnDisabled) {
        $("#op_vault_item").show();
        $('#op_file_tree_wrapper').hide();
        nextBtnValue = browse;
    } else if (showText === false) {
        $("#op_vault_item").hide();
        $('#op_file_tree_wrapper').show();
        nextBtnValue = text;
    } else {
        $("#op_vault_item").toggle();
        $('#op_file_tree_wrapper').toggle();
    }
    btn.val(nextBtnValue);
}

const toggleAllInfo = (btn) => {
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

const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));