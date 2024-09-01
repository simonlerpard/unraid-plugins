window.addEventListener('DOMContentLoaded', (event) => {
    handleSelectChange(true, "op_cli_version_track", "custom", handleInstallButton)
    // handleSelectChange(true, "op_export_token_env", "user")
});

const setLoadingSpinner = (spinnerBool) => {
    document.querySelector(".spinner").style.display = spinnerBool ? "" : "none";
}

const handleSelectChange = (init, id, customKey, callback = false) => {
    const trackSelector = document.getElementById(`select_${id}`);
    const textInput = document.getElementById(id);
    const options = [...trackSelector].map(o => o.value);
    if (init) {
        if (options.includes(textInput.value)) {
            trackSelector.value = textInput.value;
            trackSelector.setAttribute("custom-value", "");
        } else {
            trackSelector.value = customKey;
        }
    }
    if (customKey !== trackSelector.value) {
        textInput.value = trackSelector.value;
        textInput.style.display = "none";
    } else {
        textInput.value = trackSelector.getAttribute("custom-value") ?? "";
        textInput.style.display = "inline-block";
    }

    if (typeof callback === 'function') callback();
}

const handleCustomInput = (textInput, callback = false) => {
    const trackSelector = document.getElementById(`select_${textInput.id}`);
    trackSelector.setAttribute("custom-value", textInput.value);
    if (textInput.id === "op_cli_version_track") {
        document.getElementById("install_btn").disabled = !textInput.value;
    }
    if (typeof callback === 'function') callback();
}

const handleInstallButton = () => {
    const track = document.getElementById("select_op_cli_version_track").value;
    const text = document.getElementById("op_cli_version_track").value;
    const btn = document.getElementById("install_btn");
    const notInstalled = document.getElementById("installed_version").innerText === "not installed";
    btn.value = track === "none" ? "Uninstall" : "Install";

    if (track === "custom" && !text.length) {
        btn.disabled = true;
        btn.title = "Input field must contain at least one character."
    } else if (track === "none" && notInstalled) {
        btn.disabled = true;
        btn.title = "The 1Password CLI is not installed."
    } else {
        btn.disabled = false;
        btn.title = ""
    }
}

const toggleEye = (element) => {
    if (!element) return;
    element.classList.toggle("fa-eye");
    element.classList.toggle("fa-eye-slash");
    const showPassword = element.classList.contains("fa-eye-slash");
    element.parentElement.querySelector("input").type = showPassword ? "text" : "password";
    element.title = showPassword ? "Show value" : "Hide value";
}
