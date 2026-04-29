<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Backend API endpoints.
 *
 * Tests backend routes that use session cookie authentication (BackendUser)
 * instead of Bearer token authentication.
 *
 * Requires two users configured in tests/config.php:
 * - An admin user (full access)
 * - A restricted user (limited permissions)
 */
class BackendApiTest extends TestCase
{
    protected static array $config;
    protected static string $baseUrl;

    /** @var string Admin session cookie string */
    protected static string $adminCookie = '';

    /** @var string Restricted user session cookie string */
    protected static string $restrictedCookie = '';

    /** @var array<string, mixed> Created test resources for cleanup */
    protected array $createdResources = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$config = API_TEST_CONFIG;
        self::$baseUrl = rtrim(self::$config['base_url'], '/');

        $backendConfig = self::$config['backend'] ?? null;
        if (null === $backendConfig) {
            self::markTestSkipped('Backend test config not found in tests/config.php. Add "backend" key.');
        }

        // Login as admin
        self::$adminCookie = self::login(
            $backendConfig['admin_login'],
            $backendConfig['admin_password'],
        );

        if ('' === self::$adminCookie) {
            self::markTestSkipped('Could not login as admin user.');
        }

        // Login as restricted user
        self::$restrictedCookie = self::login(
            $backendConfig['restricted_login'],
            $backendConfig['restricted_password'],
        );

        if ('' === self::$restrictedCookie) {
            self::markTestSkipped('Could not login as restricted user.');
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdResources) as $resource) {
            $this->backendRequest('DELETE', $resource['endpoint'] . '/' . $resource['id'], [], self::$adminCookie);
        }
        $this->createdResources = [];

        parent::tearDown();
    }

    /**
     * Logs into REDAXO backend and returns the session cookie string.
     */
    private static function login(string $login, string $password): string
    {
        $loginUrl = self::$baseUrl . '/redaxo/index.php';

        // Step 1: GET login page to obtain CSRF token + session cookie
        $ch = curl_init();
        $cookieJar = tempnam(sys_get_temp_dir(), 'rex_cookie_');

        curl_setopt_array($ch, [
            CURLOPT_URL => $loginUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_TIMEOUT => 30,
        ]);

        $html = curl_exec($ch);

        if (false === $html) {
            return '';
        }

        // Extract CSRF token
        if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
            return '';
        }
        $csrfToken = $matches[1];

        // Step 2: POST login credentials
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $loginUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_COOKIEJAR => $cookieJar,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'rex_user_login' => $login,
                'rex_user_psw' => $password,
                '_csrf_token' => $csrfToken,
                'javascript' => '0',
            ]),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            @unlink($cookieJar);
            return '';
        }

        // Step 3: Verify login by making a test request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::$baseUrl . '/api/backend/system/clangs',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_TIMEOUT => 30,
        ]);

        curl_exec($ch);
        $verifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // If we get 401, login failed
        if (401 === $verifyCode) {
            @unlink($cookieJar);
            return '';
        }

        // Return the cookie jar path (we use it as our "cookie string")
        return $cookieJar;
    }

    /**
     * Make a backend API request using session cookie.
     */
    protected function backendRequest(string $method, string $endpoint, array $data = [], ?string $cookieJar = null): array
    {
        $cookieJar = $cookieJar ?? self::$adminCookie;
        $url = self::$baseUrl . '/api/backend/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if (false === $response) {
            return ['success' => false, 'status' => 0, 'error' => $error, 'data' => null];
        }

        $decoded = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'data' => $decoded,
            'raw' => $response,
        ];
    }

    protected function adminGet(string $endpoint): array
    {
        return $this->backendRequest('GET', $endpoint, [], self::$adminCookie);
    }

    protected function adminPost(string $endpoint, array $data = []): array
    {
        return $this->backendRequest('POST', $endpoint, $data, self::$adminCookie);
    }

    protected function adminPut(string $endpoint, array $data = []): array
    {
        return $this->backendRequest('PUT', $endpoint, $data, self::$adminCookie);
    }

    protected function adminDelete(string $endpoint): array
    {
        return $this->backendRequest('DELETE', $endpoint, [], self::$adminCookie);
    }

    protected function restrictedGet(string $endpoint): array
    {
        return $this->backendRequest('GET', $endpoint, [], self::$restrictedCookie);
    }

    protected function restrictedPost(string $endpoint, array $data = []): array
    {
        return $this->backendRequest('POST', $endpoint, $data, self::$restrictedCookie);
    }

    protected function restrictedPut(string $endpoint, array $data = []): array
    {
        return $this->backendRequest('PUT', $endpoint, $data, self::$restrictedCookie);
    }

    protected function restrictedDelete(string $endpoint): array
    {
        return $this->backendRequest('DELETE', $endpoint, [], self::$restrictedCookie);
    }

    protected function trackResource(string $endpoint, int|string $id): void
    {
        $this->createdResources[] = ['endpoint' => $endpoint, 'id' => $id];
    }

    // ==================== CLANGS ====================

    public function testAdminCanListClangs(): void
    {
        $response = $this->adminGet('system/clangs');

        $this->assertTrue($response['success'], 'Admin should be able to list clangs');
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertNotEmpty($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testAdminCanGetClang(): void
    {
        $response = $this->adminGet('system/clangs/1');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertArrayHasKey('code', $response['data']);
    }

    public function testRestrictedUserClangListFilteredByPerm(): void
    {
        $response = $this->restrictedGet('system/clangs');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        // Restricted user should only see clangs they have permission for
        // (may be empty if no clang permissions are set)
    }

    public function testRestrictedUserCannotAddClang(): void
    {
        $response = $this->restrictedPost('system/clangs', [
            'code' => 'xx',
            'name' => 'Test Clang',
            'priority' => 99,
            'status' => 0,
        ]);

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    public function testRestrictedUserCannotDeleteClang(): void
    {
        $response = $this->restrictedDelete('system/clangs/1');

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    public function testRestrictedUserCannotUpdateClang(): void
    {
        $response = $this->restrictedPut('system/clangs/1', [
            'name' => 'Hacked',
        ]);

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    // ==================== TEMPLATES (admin-only) ====================

    public function testAdminCanListTemplates(): void
    {
        $response = $this->adminGet('templates');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testRestrictedUserCannotListTemplates(): void
    {
        $response = $this->restrictedGet('templates');

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    public function testRestrictedUserCannotGetTemplate(): void
    {
        $response = $this->restrictedGet('templates/1');

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotAddTemplate(): void
    {
        $response = $this->restrictedPost('templates', [
            'name' => 'Hacked Template',
            'content' => '<p>hack</p>',
        ]);

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotUpdateTemplate(): void
    {
        $response = $this->restrictedPut('templates/1', [
            'name' => 'Hacked',
        ]);

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotDeleteTemplate(): void
    {
        $response = $this->restrictedDelete('templates/1');

        $this->assertSame(403, $response['status']);
    }

    // ==================== USERS (admin-only) ====================

    public function testAdminCanListUsers(): void
    {
        $response = $this->adminGet('users');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertNotEmpty($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testRestrictedUserCannotListUsers(): void
    {
        $response = $this->restrictedGet('users');

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    public function testRestrictedUserCannotGetUser(): void
    {
        $response = $this->restrictedGet('users/1');

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotAddUser(): void
    {
        $response = $this->restrictedPost('users', [
            'name' => 'Hacked User',
            'login' => 'hacked',
            'password' => 'Hacked12345',
        ]);

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotDeleteUser(): void
    {
        $response = $this->restrictedDelete('users/1');

        $this->assertSame(403, $response['status']);
    }

    public function testRestrictedUserCannotListRoles(): void
    {
        $response = $this->restrictedGet('users/roles');

        $this->assertSame(403, $response['status']);
    }

    // ==================== MODULES ====================

    public function testAdminCanListModules(): void
    {
        $response = $this->adminGet('modules');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testRestrictedUserCanListModules(): void
    {
        // All backend users can read modules
        $response = $this->restrictedGet('modules');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
    }

    public function testRestrictedUserCannotAddModule(): void
    {
        $response = $this->restrictedPost('modules', [
            'name' => 'Hacked Module',
            'key' => 'hacked_module',
            'input' => '<p>hack</p>',
            'output' => '<p>hack</p>',
        ]);

        $this->assertSame(403, $response['status']);
        $this->assertSame('Permission denied', $response['data']['error']);
    }

    public function testRestrictedUserCannotDeleteModule(): void
    {
        $response = $this->restrictedDelete('modules/1');

        $this->assertSame(403, $response['status']);
    }

    // ==================== STRUCTURE ====================

    public function testAdminCanListArticles(): void
    {
        $response = $this->adminGet('structure/articles');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testAdminCanGetArticle(): void
    {
        $response = $this->adminGet('structure/articles/1');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testAdminCanCreateAndDeleteArticle(): void
    {
        $name = 'BACKEND_TEST_' . uniqid();
        $response = $this->adminPost('structure/articles', [
            'name' => $name,
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);

        $this->assertSame(201, $response['status'], 'Admin should be able to create articles. Response: ' . json_encode($response['data']));
        $this->assertArrayHasKey('id', $response['data']);

        $articleId = $response['data']['id'];

        // Delete it
        $deleteResponse = $this->adminDelete('structure/articles/' . $articleId);
        $this->assertSame(200, $deleteResponse['status']);
    }

    public function testRestrictedUserStructureAccessDependsOnPerm(): void
    {
        // Restricted user without structure perm should get 403
        $response = $this->restrictedGet('structure/articles/1');

        // Either 200 (has perm) or 403 (no perm)
        $this->assertContains($response['status'], [200, 403]);
    }

    // ==================== MEDIA ====================

    public function testAdminCanListMedia(): void
    {
        $response = $this->adminGet('media');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testAdminCanListMediaCategories(): void
    {
        $response = $this->adminGet('media/category');

        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    // ==================== NO AUTH (should be 401) ====================

    public function testUnauthenticatedRequestReturns401(): void
    {
        // Make request without any cookie
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::$baseUrl . '/api/backend/system/clangs',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertSame(401, $httpCode, 'Unauthenticated request should return 401');
    }

    // ==================== ADMIN CRUD: Clangs ====================

    public function testAdminClangCRUD(): void
    {
        // Create
        $response = $this->adminPost('system/clangs', [
            'code' => 'xx',
            'name' => 'BACKEND_TEST_clang_' . uniqid(),
            'priority' => 99,
            'status' => 0,
        ]);

        $this->assertSame(201, $response['status'], 'Admin should create clang. Response: ' . json_encode($response['data']));
        $clangId = $response['data']['id'];

        // Read
        $getResponse = $this->adminGet('system/clangs/' . $clangId);
        $this->assertSame(200, $getResponse['status']);
        $this->assertSame('xx', $getResponse['data']['code']);

        // Update
        $updateResponse = $this->adminPut('system/clangs/' . $clangId, [
            'name' => 'BACKEND_TEST_updated',
        ]);
        $this->assertSame(200, $updateResponse['status']);

        // Delete
        $deleteResponse = $this->adminDelete('system/clangs/' . $clangId);
        $this->assertSame(200, $deleteResponse['status']);
    }

    // ==================== ADMIN CRUD: Modules ====================

    public function testAdminModuleCRUD(): void
    {
        $name = 'BACKEND_TEST_module_' . uniqid();
        $key = 'backend_test_' . uniqid();

        // Create
        $response = $this->adminPost('modules', [
            'name' => $name,
            'key' => $key,
            'input' => '<p>Backend Test Input</p>',
            'output' => '<p>Backend Test Output</p>',
        ]);

        $this->assertSame(201, $response['status'], 'Admin should create module. Response: ' . json_encode($response['data']));
        $moduleId = $response['data']['id'];

        // Read
        $getResponse = $this->adminGet('modules/' . $moduleId);
        $this->assertSame(200, $getResponse['status']);
        $this->assertSame($name, $getResponse['data']['name']);

        // Update
        $updateResponse = $this->adminPut('modules/' . $moduleId, [
            'name' => $name . '_updated',
        ]);
        $this->assertSame(200, $updateResponse['status']);

        // Delete
        $deleteResponse = $this->adminDelete('modules/' . $moduleId);
        $this->assertSame(200, $deleteResponse['status']);
    }

    // ==================== ADMIN CRUD: Structure ====================

    public function testAdminCanUpdateArticle(): void
    {
        $createResponse = $this->adminPost('structure/articles', [
            'name' => 'BACKEND_TEST_update_' . uniqid(),
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create article. Response: ' . json_encode($createResponse['data']));
        $articleId = $createResponse['data']['id'];

        try {
            $updateResponse = $this->adminPut('structure/articles/' . $articleId, [
                'name' => 'BACKEND_TEST_renamed_' . uniqid(),
            ]);
            $this->assertSame(200, $updateResponse['status'], 'Admin should update article.');
        } finally {
            $this->adminDelete('structure/articles/' . $articleId);
        }
    }

    public function testAdminCategoryCRUD(): void
    {
        $name = 'BACKEND_TEST_cat_' . uniqid();
        $createResponse = $this->adminPost('structure/categories', [
            'name' => $name,
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create category. Response: ' . json_encode($createResponse['data']));
        $categoryId = $createResponse['data']['id'];

        try {
            $updateResponse = $this->adminPut('structure/categories/' . $categoryId, [
                'name' => $name . '_renamed',
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $deleteResponse = $this->adminDelete('structure/categories/' . $categoryId);
            $this->assertSame(200, $deleteResponse['status']);
        }
    }

    public function testAdminSliceCRUD(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $moduleId = self::$config['test_data']['existing_module_id'];
        $clangId = self::$config['test_data']['existing_clang_id'];

        $createResponse = $this->adminPost('structure/articles/' . $articleId . '/slices', [
            'module_id' => $moduleId,
            'clang_id' => $clangId,
            'ctype_id' => 1,
            'value1' => 'BACKEND_TEST_slice_' . uniqid(),
        ]);
        if (201 !== $createResponse['status']) {
            // Template hat das Modul evtl. nicht zugeordnet — wie im Bearer-Pendant tolerieren.
            $this->assertContains($createResponse['status'], [400, 404]);
            $this->markTestSkipped('Slice konnte nicht angelegt werden (Template/Modul-Zuordnung fehlt).');
        }
        $sliceId = $createResponse['data']['slice_id'];

        try {
            $getResponse = $this->adminGet('structure/articles/' . $articleId . '/slices/' . $sliceId);
            $this->assertSame(200, $getResponse['status']);

            $updateResponse = $this->adminPut('structure/articles/' . $articleId . '/slices/' . $sliceId, [
                'value1' => 'BACKEND_TEST_updated_' . uniqid(),
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $this->adminDelete('structure/articles/' . $articleId . '/slices/' . $sliceId);
        }
    }

    // ==================== ADMIN CRUD: Media Category ====================

    public function testAdminMediaCategoryCRUD(): void
    {
        $name = 'BACKEND_TEST_mediacat_' . uniqid();
        $createResponse = $this->adminPost('media/category', [
            'name' => $name,
            'parent_id' => 0,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create media category. Response: ' . json_encode($createResponse['data']));
        $categoryId = $createResponse['data']['id'];

        try {
            $updateResponse = $this->adminPut('media/category/' . $categoryId, [
                'name' => $name . '_renamed',
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $deleteResponse = $this->adminDelete('media/category/' . $categoryId);
            $this->assertSame(200, $deleteResponse['status']);
        }
    }

    // ==================== ADMIN CRUD: Templates ====================

    public function testAdminTemplateCRUD(): void
    {
        $name = 'BACKEND_TEST_template_' . uniqid();
        $createResponse = $this->adminPost('templates', [
            'name' => $name,
            'content' => '<?php echo "Backend Test"; ?>',
            'active' => 0,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create template. Response: ' . json_encode($createResponse['data']));
        $templateId = $createResponse['data']['id'];

        try {
            $getResponse = $this->adminGet('templates/' . $templateId);
            $this->assertSame(200, $getResponse['status']);
            $this->assertSame($name, $getResponse['data']['name']);

            $updateResponse = $this->adminPut('templates/' . $templateId, [
                'name' => $name . '_renamed',
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $deleteResponse = $this->adminDelete('templates/' . $templateId);
            $this->assertSame(200, $deleteResponse['status']);
        }
    }

    // ==================== ADMIN CRUD: Users ====================

    public function testAdminUserCRUD(): void
    {
        $login = strtolower('backend_test_user_' . uniqid());
        $createResponse = $this->adminPost('users', [
            'login' => $login,
            'name' => 'Backend Test User',
            'password' => 'TestPassword123!',
            'email' => $login . '@example.com',
            'status' => 1,
            'admin' => 0,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create user. Response: ' . json_encode($createResponse['data']));
        $userId = $createResponse['data']['id'];

        try {
            $updateResponse = $this->adminPut('users/' . $userId, [
                'name' => 'Backend Test User (renamed)',
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $deleteResponse = $this->adminDelete('users/' . $userId);
            $this->assertSame(200, $deleteResponse['status']);
        }
    }

    // ==================== ADMIN CRUD: Roles ====================

    public function testAdminRoleCRUD(): void
    {
        $name = 'BACKEND_TEST_role_' . uniqid();
        $createResponse = $this->adminPost('users/roles', [
            'name' => $name,
            'description' => 'Backend Test Role',
            'perms' => ['general' => '|structure|'],
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should create role. Response: ' . json_encode($createResponse['data']));
        $roleId = $createResponse['data']['id'];

        try {
            $getResponse = $this->adminGet('users/roles/' . $roleId);
            $this->assertSame(200, $getResponse['status']);

            $updateResponse = $this->adminPut('users/roles/' . $roleId, [
                'description' => 'Backend Test Role (renamed)',
            ]);
            $this->assertSame(200, $updateResponse['status']);
        } finally {
            $deleteResponse = $this->adminDelete('users/roles/' . $roleId);
            $this->assertSame(200, $deleteResponse['status']);
        }
    }
}
