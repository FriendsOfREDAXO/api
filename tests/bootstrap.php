<?php

declare(strict_types=1);

/**
 * Bootstrap für API-Tests.
 */

// Composer Autoloader laden (falls vorhanden)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

// Autoloader für Test-Klassen
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

// tests/.env laden (nicht ins Repo committen). Tiny parser, keine externe Dependency.
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

// Test-Konfiguration laden
if (!defined('API_TEST_CONFIG')) {
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('Test-Konfiguration nicht gefunden. Bitte tests/config.php erstellen.');
    }
    define('API_TEST_CONFIG', require $configPath);
}
