<?php

declare(strict_types=1);

/**
 * Bootstrap for the API tests.
 *
 * The tests drive the API over HTTP against a running REDAXO 6 instance, so no REDAXO bootstrap is
 * needed here — only the autoloader, the test config and tests/.env.
 */

// Composer autoloader
$autoloadPaths = [
    // Standalone addon checkout (composer install inside this repo)
    __DIR__ . '/../vendor/autoload.php',
    // Installed as a composer package inside a REDAXO 6 project
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

// Autoloader for the test classes (works without composer install as well)
spl_autoload_register(static function (string $class): void {
    $prefix = 'FriendsOfRedaxo\\Api\\Tests\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load tests/.env (never committed). Tiny parser, no external dependency.
$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = ltrim($line);
        if ('' === $line || '#' === $line[0]) {
            continue;
        }
        $eq = strpos($line, '=');
        if (false === $eq) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        // Quoted values: strip surrounding single/double quotes.
        if (strlen($value) >= 2
            && (('"' === $value[0] && '"' === $value[-1]) || ("'" === $value[0] && "'" === $value[-1]))
        ) {
            $value = substr($value, 1, -1);
        }
        if ('' === $key || false !== getenv($key)) {
            continue; // Don't overwrite values explicitly set in the environment.
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

// Load the test configuration
if (!defined('API_TEST_CONFIG')) {
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('Test configuration not found. Please create tests/config.php.');
    }
    define('API_TEST_CONFIG', require $configPath);
}
