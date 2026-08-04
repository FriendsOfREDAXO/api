<?php

declare(strict_types=1);

/**
 * API test configuration.
 *
 * Values come from tests/.env (local, never committed). The defaults below are placeholders only, so the
 * suite skips with a clear message instead of dying when no .env exists.
 */

$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);

    return false === $value || '' === $value ? $default : $value;
};

$envBool = static function (string $key, bool $default) use ($env): bool {
    return in_array(strtolower($env($key, $default ? '1' : '0')), ['1', 'true', 'yes', 'on'], true);
};

$envInt = static function (string $key, int $default) use ($env): int {
    $value = $env($key, (string) $default);

    return is_numeric($value) ? (int) $value : $default;
};

return [
    'base_url' => $env('API_TEST_BASE_URL'),
    'api_prefix' => $env('API_TEST_API_PREFIX', '/api'),
    'api_token' => $env('API_TEST_TOKEN'),
    'restricted_token' => $env('API_TEST_RESTRICTED_TOKEN'),
    'restricted_token_allowed_path' => $env('API_TEST_RESTRICTED_TOKEN_ALLOWED_PATH', 'structure/articles'),
    'restricted_token_denied_path' => $env('API_TEST_RESTRICTED_TOKEN_DENIED_PATH', 'users'),
    'timeout' => $envInt('API_TEST_TIMEOUT', 30),
    'verify_ssl' => $envBool('API_TEST_VERIFY_SSL', false),
    'debug' => $envBool('API_TEST_DEBUG', true),

    'test_data' => [
        'existing_article_id' => $envInt('API_TEST_EXISTING_ARTICLE_ID', 1),
        'existing_category_id' => $envInt('API_TEST_EXISTING_CATEGORY_ID', 1),
        'existing_clang_id' => $envInt('API_TEST_EXISTING_CLANG_ID', 1),

        // REDAXO 6 identifies templates and modules by string key, not by numeric id.
        'existing_template_key' => $env('API_TEST_EXISTING_TEMPLATE_KEY', 'default'),
        'existing_module_key' => $env('API_TEST_EXISTING_MODULE_KEY', ''),

        // Column name of an art_* metainfo field defined by the project's meta schema. Empty means the
        // metainfo value tests are skipped (a fresh REDAXO 6 has no meta schema at all).
        'metainfo_article_field' => $env('API_TEST_METAINFO_ARTICLE_FIELD', ''),

        'test_prefix' => 'API_TEST_',
    ],

    'backend' => [
        'admin_login' => $env('API_TEST_ADMIN_LOGIN', 'admin'),
        'admin_password' => $env('API_TEST_ADMIN_PASSWORD'),
    ],
];
