<?php

declare(strict_types=1);

/**
 * API Test Configuration.
 *
 * Werte werden aus tests/.env gelesen (Geheimnisse, lokal). Die Defaults
 * unten sind nur Platzhalter, damit Tests bei fehlender .env mit klarer
 * Meldung skippen statt mit Fehler zu sterben.
 */

$env = static function (string $key, string $default = ''): string {
    $val = getenv($key);
    if (false === $val || '' === $val) {
        return $default;
    }
    return $val;
};

$envBool = static function (string $key, bool $default) use ($env): bool {
    $val = $env($key, $default ? '1' : '0');
    return in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
};

$envInt = static function (string $key, int $default) use ($env): int {
    $val = $env($key, (string) $default);
    return is_numeric($val) ? (int) $val : $default;
};

return [
    'base_url' => $env('API_TEST_BASE_URL', 'https://redaxo.localhost'),
    'api_prefix' => $env('API_TEST_API_PREFIX', '/api'),
    'api_token' => $env('API_TEST_TOKEN'),
    'timeout' => $envInt('API_TEST_TIMEOUT', 30),
    'verify_ssl' => $envBool('API_TEST_VERIFY_SSL', false),
    'debug' => $envBool('API_TEST_DEBUG', true),

    'test_data' => [
        'existing_article_id' => $envInt('API_TEST_EXISTING_ARTICLE_ID', 1),
        'existing_category_id' => $envInt('API_TEST_EXISTING_CATEGORY_ID', 1),
        'existing_clang_id' => $envInt('API_TEST_EXISTING_CLANG_ID', 1),
        'existing_template_id' => $envInt('API_TEST_EXISTING_TEMPLATE_ID', 1),
        'existing_module_id' => $envInt('API_TEST_EXISTING_MODULE_ID', 1),

        'test_prefix' => 'API_TEST_',
    ],

    'backend' => [
        'admin_login' => $env('API_TEST_ADMIN_LOGIN', 'admin'),
        'admin_password' => $env('API_TEST_ADMIN_PASSWORD'),
        'restricted_login' => $env('API_TEST_RESTRICTED_LOGIN', 'apitest_restricted'),
        'restricted_password' => $env('API_TEST_RESTRICTED_PASSWORD'),
    ],
];
