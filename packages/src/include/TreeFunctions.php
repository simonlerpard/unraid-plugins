<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

/**
 * Convert a size in bytes to a human-readable format (KB, MB, GB, etc.).
 *
 * @param int $bytes The size in bytes.
 * @return string The human-readable size.
 */
function bytesToHumanReadable($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    if ($bytes < 0) {
        throw new InvalidArgumentException('Bytes cannot be negative');
    }

    if ($bytes === 0) {
        return '0 B';
    }

    // Calculate the appropriate unit index
    $i = floor(log($bytes, 1024));
    $size = $bytes / pow(1024, $i);

    // Return the formatted string
    return sprintf('%.2f %s', $size, $units[$i]);
}

function getReference($field) {
    if (!is_array($field)) return;
    if (!array_key_exists("reference", $field)) return false;
    if (!is_string($field["reference"])) return false;
    if (!str_starts_with($field["reference"], "op://")) return false;
    return $field["reference"];
}

function order ($a, $b) {
    $k = array_key_exists("name", $a) ? "name" : "label";
    return strcasecmp($a[$k] ?? "", $b[$k] ?? "");
}

function generateTree($vaultName, $itemId, $itemWithFields) {
    $html = "<ul class=\"jqueryFileTree\" style=\"display: none;\">";
    $fields = $itemWithFields["fields"] ?? [];
    $files = $itemWithFields["files"] ?? [];

    // Sort and show attachment first (since that's usually not very common, but can definitely be in our case)
    usort($files, "order");
    foreach ($files as $file) {
        $fileId = htmlspecialchars($file['id']);
        $fileName = htmlspecialchars($file['name']);
        $fileSize = htmlspecialchars($file['size']);
        $sectionId = htmlspecialchars($file['section']['id'] ?? ""); // Is for example included for attachments but not for documents.
        $humanFileSize = bytesToHumanReadable($fileSize);
        $parts = array_filter([$vaultName, $itemId, $sectionId, $fileId], "strlen"); // Remove empty values (for example the section if it's empty)
        $opUrl = "op://" . implode("/", $parts);
        $html .= "<li class=\"attachment\"><a href=\"$opUrl\" rel=\"$opUrl\">$fileName (size: $humanFileSize)</a></li>";
    }

    // Sort and show the rest of the item fields.
    usort($fields, "order");
    foreach ($fields as $field) {
        $fieldId = htmlspecialchars($field['id']);
        $fieldLabel = htmlspecialchars($field['label']);

        $opUrl = getReference($field) ?: "op://$vaultName/$itemId/$fieldId";
        $html .= "<li class=\"file\"><a href=\"$opUrl\" rel=\"$opUrl\">$fieldLabel</a></li>";

        // Handle special formats (ssh keys in this case). Ssh keys can be common since it's built into 1Password..
        if (array_key_exists("ssh_formats", $field)) {
            foreach (array_keys($field["ssh_formats"]) as $key) {
                $sshOpUrl = getReference($field["ssh_formats"][$key]) ?: $opUrl;
                $html .= "<li class=\"file\"><a href=\"$sshOpUrl\" rel=\"$sshOpUrl\">$fieldLabel ($key format)</a></li>";
            }
        }
    }

    $html .= "</ul>";
    return $html;
}
