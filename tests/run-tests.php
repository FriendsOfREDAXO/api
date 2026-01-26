#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * API Test Runner.
 *
 * Dieses Script führt die API-Tests aus.
 *
 * Verwendung:
 *   php tests/run-tests.php              # Alle Tests
 *   php tests/run-tests.php Structure    # Nur Structure-Tests
 *   php tests/run-tests.php --list       # Verfügbare Test-Klassen auflisten
 */

$addonDir = dirname(__DIR__);
$vendorDir = $addonDir . '/vendor';
$rootVendorDir = dirname($addonDir, 4) . '/vendor';

// PHPUnit finden
$phpunitPaths = [
    $vendorDir . '/bin/phpunit',
    $rootVendorDir . '/bin/phpunit',
    $vendorDir . '/phpunit/phpunit/phpunit',
    $rootVendorDir . '/phpunit/phpunit/phpunit',
];

$phpunit = null;
foreach ($phpunitPaths as $path) {
    if (file_exists($path)) {
        $phpunit = $path;
        break;
    }
}

if (!$phpunit) {
    echo "PHPUnit nicht gefunden!\n";
    echo "Bitte PHPUnit installieren:\n";
    echo "  composer require --dev phpunit/phpunit\n";
    exit(1);
}

// Argumente verarbeiten
$args = array_slice($argv, 1);

if (in_array('--list', $args)) {
    echo "Verfügbare Test-Klassen:\n";
    foreach (glob($addonDir . '/tests/*ApiTest.php') as $file) {
        echo '  - ' . basename($file, '.php') . "\n";
    }
    exit(0);
}

// PHPUnit-Befehl zusammenbauen
$cmd = [
    PHP_BINARY,
    $phpunit,
    '--configuration', $addonDir . '/phpunit.xml',
    '--colors=always',
];

// Filter für bestimmte Test-Klasse
if (!empty($args) && !str_starts_with($args[0], '-')) {
    $filter = $args[0];
    if (!str_ends_with($filter, 'Test')) {
        $filter .= 'ApiTest';
    }
    $cmd[] = '--filter';
    $cmd[] = $filter;
}

// Weitere Argumente durchreichen
foreach ($args as $arg) {
    if (str_starts_with($arg, '-')) {
        $cmd[] = $arg;
    }
}

// Befehl ausführen
$cmdString = implode(' ', array_map('escapeshellarg', $cmd));
echo "Führe aus: $cmdString\n\n";

passthru($cmdString, $exitCode);
exit($exitCode);
