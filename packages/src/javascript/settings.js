window.onload = () => {
    handleTrackSelectChange(true);
};

const setLoadingSpinner = (spinnerBool) => {
    document.querySelector(".spinner").style.display = spinnerBool ? "" : "none";
}

const handleTrackSelectChange = (init) => {
    const trackSelector = document.getElementById("trackSelection");
    const textInput = document.getElementById("op_cli_version_track");
    const options = [...trackSelector].map(o => o.value);
    if (init) {
        if (options.includes(textInput.value)) {
            trackSelector.value = textInput.value;
            trackSelector.setAttribute("custom-value", "");
        } else {
            trackSelector.value = "custom";
        }
    }
    if (["latest", "stable", "none"].includes(trackSelector.value)) {
        textInput.value = trackSelector.value;
        textInput.style.display = "none";
    } else {
        textInput.value = trackSelector.getAttribute("custom-value") ?? "";
        textInput.style.display = "inline-block";
    }
    handleInstallButton();
}
const handleCustomTrackInput = (textInput) => {
    const trackSelector = document.getElementById("trackSelection");
    trackSelector.setAttribute("custom-value", textInput.value);
    document.getElementById("install_btn").disabled = !textInput.value;
    handleInstallButton();
}
const handleInstallButton = () => {
    const track = document.getElementById("trackSelection").value;
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
