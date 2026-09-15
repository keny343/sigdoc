<?php
/**
 * SIGDoc — configuration loader.
 *
 * Copy `config.example.php` to `config.local.php` and fill real values.
 * `config.local.php` must NEVER be committed (see .gitignore).
 */
declare(strict_types=1);

$configFile = __DIR__ . '/config.local.php';
$exampleFile = __DIR__ . '/config.example.php';

if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Missing includes/config.local.php — copy from config.example.php\n");
    }
    http_response_code(500);
    exit('SIGDoc: missing includes/config.local.php. Copy config.example.php and configure secrets locally.');
}

/** @var array $config */
$config = require $configFile;

$defaults = is_file($exampleFile) ? (require $exampleFile) : [];
$config = array_replace_recursive($defaults, $config);

if (!function_exists('sigdoc_config')) {
    function sigdoc_config(?string $key = null, $default = null)
    {
        global $config;
        if ($key === null) {
            return $config;
        }
        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

return $config;
