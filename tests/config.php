<?php

declare(strict_types=1);

/**
 * API Test Configuration.
 *
 * Diese Konfiguration wird für die API-Tests verwendet.
 * Passe die Werte an deine lokale Umgebung an.
 */
return [
    // Basis-URL der REDAXO-Installation
    'base_url' => 'https://redaxo.localhost',

    // API-Endpunkt-Prefix
    'api_prefix' => '/api',

    // API-Token für Authentifizierung
    // Erstelle einen Token im Backend unter "API" -> "Token"
    'api_token' => '5c32e210baa8fe7e27c8df02c30d206e',

    // Timeout für HTTP-Requests in Sekunden
    'timeout' => 30,

    // SSL-Zertifikat verifizieren (für lokale Entwicklung oft false)
    'verify_ssl' => false,

    // Test-Daten IDs (werden bei Tests verwendet/erstellt)
    'test_data' => [
        // Existierende IDs für Read-Tests (müssen in der DB vorhanden sein)
        'existing_article_id' => 1,
        'existing_category_id' => 1,
        'existing_clang_id' => 1,
        'existing_template_id' => 1,
        'existing_module_id' => 1,

        // Prefix für Test-Daten (zur einfachen Identifizierung)
        'test_prefix' => 'API_TEST_',
    ],

    // Debug-Modus (mehr Ausgaben bei Fehlern)
    'debug' => true,

    // Backend-Authentifizierung (Session-Cookie basiert)
    'backend' => [
        'admin_login' => 'admin',
        'admin_password' => 'admin',
        'restricted_login' => 'jan',
        'restricted_password' => 'jan',
    ],
];
