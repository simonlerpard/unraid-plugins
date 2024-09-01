const setEncryptionStatusMessage = (is1Password) => {
    const defaultStatus = $("#pass").siblings('.red-text');
    const opStatus = $("#pass").siblings('.op-status');
    if (defaultStatus.text() === "Missing key" && is1Password) {
        defaultStatus.hide();
        opStatus.show();
    } else {
        defaultStatus.show();
        opStatus.hide();
    }
}

const opSelectInput = (form) => {
    const opStatus = $("#pass").siblings('.op-status');
    const item = $('input[name="confirmStart"]').length ? $('input[name="confirmStart"]') : $('#cmdStart');

    if (form.input.value === "1password") {
        $('#text').hide();
        $('#copy').hide();
        $('#file').hide();
        $('#pass').hide();
        setEncryptionStatusMessage(true);
        item.prop('disabled', opStatus.hasClass("op-status-error"));
    }
}

const modifyMainPage = (opStatusclass, opStatusMsg) => {
    $("#pass").parent().append(`<span class="op-status ${opStatusclass}">${opStatusMsg}</span>`)

    const form = document.querySelector('form[name="arrayOps"]');
    const selector = form.querySelector('select[name="input"]');
    // Disable the other options, since they don't matter when the 1Password integration has been enabled
    // We'll always try to fetch the secret from the vault during start, regardless of the passphrase or keyfile input.
    [...selector.children].forEach(c => {
        c.setAttribute('disabled', true);
        c.setAttribute('title', 'Disabled by the 1Password settings');
    })

    // Add the 1Password option, to visualize that we're using the 1Password integration.
    const opOption = new Option('1Password', '1password', true);
    selector.insertBefore(opOption, selector.firstChild)
    selector.setAttribute('onchange', "opSelectInput(this.form)");
    selector.selectedIndex = 0;
    opSelectInput(form);

    // Add settings shortcut next to the selector
    $(selector).parent().append('<a href="/Settings/OPSettings" title="Go to the 1Password settings">Settings</a>')
}
