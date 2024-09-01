#!/bin/bash

# TODO: Rewrite to php instead

PLUGIN_NAME="simonlerpard.one.password.cli"
CONFIG_FILE_PATH="/boot/config/plugins/${PLUGIN_NAME}/config.json"
FLASH_OP_FILE_PATH="$(cat "${CONFIG_FILE_PATH}" | jq -r .op_cli_downloaded_file | grep ".zip")"
INSTALL_DIR="/usr/local/bin"
INSTALLED_BINARY="${INSTALL_DIR}/op"
INSTALLED_BINARY_SIG="${INSTALL_DIR}/op.sig"
USER_GROUP_NAME="onepassword-cli"

# Function to display usage
usage() {
    echo "Usage: $0 {install|uninstall}"
    exit 1
}

install() {
    echo "Starting the installation process..."

    # 1Password zip file not found on the flash drive, nothing to do. Just exit.
    [ ! -f "${CONFIG_FILE_PATH}" ] && echo "Could not find the config file." && exit 1

    # 1Password zip file not found on the flash drive, nothing to do. Just exit.
    [ -z "${FLASH_OP_FILE_PATH}" ] && echo "Could not read the filepath from config for the zip file." && exit 1
    [ ! -f "${FLASH_OP_FILE_PATH}" ] && echo "Could not find the 1Password cli zip file." && exit 1

    # Check if the usergroup exist
    if ! grep "${USER_GROUP_NAME}:" /etc/group >/dev/null; then
        echo "Usergroup ${USER_GROUP_NAME} does not exist, creating it"
        GID=800
        while ! groupadd -g "${GID}" "${USER_GROUP_NAME}"; do
            echo "Failed to add ${USER_GROUP_NAME} with gid ${GID}"
            [ "$((GID++))" -gt "10025" ] && echo "Tried way too many times, giving up." && exit 1
        done
        echo "Successfully added ${USER_GROUP_NAME} with gid ${GID}"
    fi

    # Check if it's already installed
    if which op &>/dev/null; then
        # Check if the current installation is owned by the onepassword-cli group
        if [[ $(ls -l "${INSTALLED_BINARY}" | awk '{print $4}') != "${USER_GROUP_NAME}" ]]; then
            echo "Found existing op installation, but it wasn't owned by the onepassword-cli group. Aborting."
            exit 1
        fi
        OP_VERSION=$(op --version)
        echo "Found an existing op installation (version: ${OP_VERSION}). Updating it instead."
    else
        echo "Installing the 1Password CLI"
    fi

    # Unzip the file to bin directory
    unzip -od "${INSTALL_DIR}/" "${FLASH_OP_FILE_PATH}" || echo "Failed to unzip the file ${FLASH_OP_FILE_PATH} to ${INSTALL_DIR}/"

    # Change the group ownership of the file to onepassword-cli
    chgrp "${USER_GROUP_NAME}" "${INSTALLED_BINARY}" || echo "Failed to set the group permission for ${INSTALLED_BINARY}"

    # Change the group ownership of the file to onepassword-cli
    chgrp "${USER_GROUP_NAME}" "${INSTALLED_BINARY_SIG}" || echo "Failed to set the group permission for ${INSTALLED_BINARY_SIG}"

    # Apply to all potential sub directories as well
    chmod g+s "${INSTALLED_BINARY}" || echo "Failed to set the permission policy on the directory"

    OP_VERSION=$(op --version)

    echo "1Password CLI version ${OP_VERSION} installed successfully."

    echo "Installation completed."
}

# Function to uninstall the software
uninstall() {
    echo "Starting the uninstallation process..."

    if [[ $(ls -l "${INSTALLED_BINARY}" | awk '{print $4}') == "${USER_GROUP_NAME}" ]]; then
        echo "Removing the binary file"
        rm "${INSTALLED_BINARY}" || echo "Could not remove the binary file ${INSTALLED_BINARY})"
    else
        echo "The file ${INSTALLED_BINARY} does not exist or isn't owned by ${USER_GROUP_NAME}. Ignoring."
    fi

    if [[ $(ls -l "${INSTALLED_BINARY_SIG}" | awk '{print $4}') == "${USER_GROUP_NAME}" ]]; then
        echo "Removing the sig file"
        rm "${INSTALLED_BINARY_SIG}" || echo "Could not remove the sig file (${INSTALLED_BINARY_SIG})"
    else
        echo "The file ${INSTALLED_BINARY_SIG} does not exist or isn't owned by ${USER_GROUP_NAME}. Ignoring."
    fi

    USER_GROUP_ENTRY=$(grep "${USER_GROUP_NAME}:" /etc/group)
    if groupdel "${USER_GROUP_NAME}" &>/dev/null; then
        echo "Removed the usergroup ${USER_GROUP_ENTRY}"
    else
        echo "Could not remove the usergroup ${USER_GROUP_ENTRY}"
    fi

    echo "Uninstallation completed."
}

# Check if the first argument is provided
if [ -z "$1" ]; then
    usage
fi

# Main logic to call the appropriate function
case "$1" in
    install)
        install
        ;;
    uninstall)
        uninstall
        ;;
    *)
        usage
        ;;
esac

exit 0
