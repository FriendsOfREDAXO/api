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

// Test-Konfiguration laden
if (!defined('API_TEST_CONFIG')) {
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        throw new RuntimeException('Test-Konfiguration nicht gefunden. Bitte tests/config.php erstellen.');
    }
    define('API_TEST_CONFIG', require $configPath);
}
