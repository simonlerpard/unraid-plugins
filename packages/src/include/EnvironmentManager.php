<?php if (!defined("OP_PLUGIN_ROOT")) http_response_code(403) && exit;
// If this file is called directly we just instantly exit with forbidden.

class EnvironmentManager {
    private $file;
    private $variableName;

    public function __construct($file, $variableName) {
        $this->file = $file;
        $this->variableName = $variableName;
    }

    /**
     * Update or add a variable and value to the file.
     *
     * @param string $value Variable value
     */
    public function updateVariable($value) {
        $lines = $this->readFile();
        $variableExists = false;

        // Check if the variable already exists and update it if so
        foreach ($lines as &$line) {
            if (strpos($line, "{$this->variableName}=") === 0) {
                $line = "{$this->variableName}=\"$value\"";
                $variableExists = true;
                break;
            }
        }

        // If the variable does not exist, add it
        if (!$variableExists) {
            $lines[] = "{$this->variableName}=\"$value\"";
        }

        file_put_contents($this->file, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * Remove a specific variable from the file.
     */
    public function removeVariable() {
        $lines = $this->readFile();
        $initialCount = count($lines);

        // Filter out the line containing the key to be removed
        $filteredLines = array_filter($lines, function($line) {
            return strpos($line, "{$this->variableName}=") !== 0;
        });

        // Check if any line was removed
        if (count($filteredLines) === $initialCount) {
            echo "Variable {$this->variableName} does not exist in the file.\n";
            return;
        }

        file_put_contents($this->file, implode(PHP_EOL, $filteredLines) . PHP_EOL);
    }

    /**
     * Read the file content into an array.
     *
     * @return array File content line by line.
     */
    private function readFile() {
        return file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
}
?>
