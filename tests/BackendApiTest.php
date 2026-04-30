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

    /** Pfad zum Test-Bild für Media-Upload-Tests. */
    private static string $testImagePath = '';

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

        // Test-Bild für Media-Upload-Tests anlegen.
        self::$testImagePath = sys_get_temp_dir() . '/api_backend_test.png';
        $im = imagecreatetruecolor(50, 50);
        imagepng($im, self::$testImagePath);
        // imagedestroy() ist seit PHP 8.0 wirkungslos und in 8.5 deprecated — weglassen.
    }

    public static function tearDownAfterClass(): void
    {
        if ('' !== self::$testImagePath && file_exists(self::$testImagePath)) {
            @unlink(self::$testImagePath);
        }
        parent::tearDownAfterClass();
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

    /**
     * Multipart-POST mit Backend-Cookie. Setzt KEINEN Content-Type-Header — den
     * generiert curl mit der korrekten Boundary, sobald POSTFIELDS ein Array ist.
     *
     * @param array<string, mixed>  $data  Form-Felder
     * @param array<string, string> $files Map fieldName => filePath
     */
    protected function backendMultipart(string $endpoint, array $data, array $files, string $cookieJar): array
    {
        $url = self::$baseUrl . '/api/backend/' . ltrim($endpoint, '/');

        foreach ($files as $fieldName => $filePath) {
            if (file_exists($filePath)) {
                $data[$fieldName] = new \CURLFile($filePath);
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_COOKIEFILE => $cookieJar,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'data' => is_string($response) ? json_decode($response, true) : null,
            'raw' => $response,
        ];
    }

    protected function adminMultipartPost(string $endpoint, array $data, array $files): array
    {
        return $this->backendMultipart($endpoint, $data, $files, self::$adminCookie);
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

    public function testAdminCanListSlices(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $response = $this->adminGet('structure/articles/' . $articleId . '/slices');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['data']['data']);
        $this->assertArrayHasKey('meta', $response['data']);
    }

    public function testAdminCanDuplicateRole(): void
    {
        $name = 'BACKEND_TEST_role_dup_' . uniqid();
        $createResponse = $this->adminPost('users/roles', [
            'name' => $name,
            'description' => 'Source role',
            'perms' => ['general' => '|structure|'],
        ]);
        $this->assertSame(201, $createResponse['status']);
        $sourceId = $createResponse['data']['id'];

        $duplicateId = null;
        try {
            $duplicateName = $name . '_copy';
            $dupResponse = $this->adminPost('users/roles/' . $sourceId . '/duplicate', [
                'name' => $duplicateName,
            ]);
            $this->assertSame(201, $dupResponse['status'], 'Admin should duplicate role. Response: ' . json_encode($dupResponse['data']));
            $this->assertArrayHasKey('id', $dupResponse['data']);
            $duplicateId = $dupResponse['data']['id'];
            $this->assertNotSame($sourceId, $duplicateId);
        } finally {
            if (null !== $duplicateId) {
                $this->adminDelete('users/roles/' . $duplicateId);
            }
            $this->adminDelete('users/roles/' . $sourceId);
        }
    }

    // ==================== ADMIN: Media (file lifecycle) ====================

    public function testAdminMediaCRUD(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild nicht vorhanden.');
        }

        $createResponse = $this->adminMultipartPost('media', [
            'category_id' => 0,
            'title' => 'BACKEND_TEST_media_' . uniqid(),
        ], [
            'file' => self::$testImagePath,
        ]);
        $this->assertSame(201, $createResponse['status'], 'Admin should upload media. Response: ' . json_encode($createResponse['data']));
        $this->assertArrayHasKey('filename', $createResponse['data']);
        $filename = $createResponse['data']['filename'];

        try {
            $infoResponse = $this->adminGet('media/' . $filename . '/info');
            $this->assertSame(200, $infoResponse['status']);
            $this->assertSame($filename, $infoResponse['data']['filename']);

            $newTitle = 'BACKEND_TEST_renamed_' . uniqid();
            $updateResponse = $this->adminPut('media/' . $filename . '/update', [
                'title' => $newTitle,
            ]);
            $this->assertSame(200, $updateResponse['status']);

            $verifyResponse = $this->adminGet('media/' . $filename . '/info');
            $this->assertSame($newTitle, $verifyResponse['data']['title']);
        } finally {
            $deleteResponse = $this->adminDelete('media/' . $filename . '/delete');
            $this->assertSame(200, $deleteResponse['status']);
        }
    }

    public function testAdminCanGetMediaFile(): void
    {
        if (!file_exists(self::$testImagePath)) {
            $this->markTestSkipped('Test-Bild nicht vorhanden.');
        }

        $createResponse = $this->adminMultipartPost('media', [
            'category_id' => 0,
            'title' => 'BACKEND_TEST_filefetch_' . uniqid(),
        ], [
            'file' => self::$testImagePath,
        ]);
        $this->assertSame(201, $createResponse['status']);
        $filename = $createResponse['data']['filename'];

        try {
            $fileResponse = $this->adminGet('media/' . $filename . '/file');
            $this->assertSame(200, $fileResponse['status']);
            // Raw response: PNG-Magic-Bytes statt JSON.
            $this->assertNotEmpty($fileResponse['raw']);
            $this->assertStringStartsWith("\x89PNG", (string) $fileResponse['raw'], 'Erwartet PNG-Bytes als Raw-Response.');
        } finally {
            $this->adminDelete('media/' . $filename . '/delete');
        }
    }

    // ==================== ADMIN: Metainfo (Werte an Article/Category/Media) ====================

    public function testAdminCanReadAndWriteArticleMetainfo(): void
    {
        // Eigenen Nicht-Start-Artikel anlegen (Metainfo-Handler lehnt Start-Artikel ab —
        // deren Werte gehören zur Kategorie). Existierende article_id=1 ist meist Start-Artikel.
        $clangId = self::$config['test_data']['existing_clang_id'];
        $createResponse = $this->adminPost('structure/articles', [
            'name' => 'BACKEND_TEST_metainfo_' . uniqid(),
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);
        $this->assertSame(201, $createResponse['status']);
        $articleId = $createResponse['data']['id'];

        $fieldName = 'art_backend_test_' . uniqid();
        $bearerToken = self::$config['api_token'];
        $fieldId = $this->createMetainfoFieldViaBearer($fieldName, $bearerToken);

        try {
            $get = $this->adminGet('structure/articles/' . $articleId . '/metainfo?clang_id=' . $clangId);
            $this->assertSame(200, $get['status'], 'Admin should read article metainfo. Response: ' . json_encode($get['data']));
            $this->assertArrayHasKey($fieldName, $get['data']['data']);

            $payload = 'admin-set-' . uniqid();
            $put = $this->adminPut('structure/articles/' . $articleId . '/metainfo?clang_id=' . $clangId, [
                $fieldName => $payload,
            ]);
            $this->assertSame(200, $put['status']);
            $this->assertSame($payload, $put['data']['data'][$fieldName]);
        } finally {
            $this->deleteMetainfoFieldViaBearer($fieldId, $bearerToken);
            $this->adminDelete('structure/articles/' . $articleId);
        }
    }

    public function testAdminCanReadAndWriteCategoryMetainfo(): void
    {
        $fieldName = 'cat_backend_test_' . uniqid();
        $bearerToken = self::$config['api_token'];
        $fieldId = $this->createMetainfoFieldViaBearer($fieldName, $bearerToken);

        try {
            $categoryId = self::$config['test_data']['existing_category_id'];
            $clangId = self::$config['test_data']['existing_clang_id'];

            $get = $this->adminGet('structure/categories/' . $categoryId . '/metainfo?clang_id=' . $clangId);
            $this->assertSame(200, $get['status']);
            $this->assertArrayHasKey($fieldName, $get['data']['data']);

            $payload = 'cat-admin-' . uniqid();
            $put = $this->adminPut('structure/categories/' . $categoryId . '/metainfo?clang_id=' . $clangId, [
                $fieldName => $payload,
            ]);
            $this->assertSame(200, $put['status']);
            $this->assertSame($payload, $put['data']['data'][$fieldName]);

            $this->adminPut('structure/categories/' . $categoryId . '/metainfo?clang_id=' . $clangId, [$fieldName => '']);
        } finally {
            $this->deleteMetainfoFieldViaBearer($fieldId, $bearerToken);
        }
    }

    public function testAdminCanReadAndWriteMediaMetainfo(): void
    {
        $fieldName = 'med_backend_test_' . uniqid();
        $bearerToken = self::$config['api_token'];
        $fieldId = $this->createMetainfoFieldViaBearer($fieldName, $bearerToken);

        try {
            // Existierendes Medium suchen.
            $list = $this->adminGet('media?per_page=1');
            if (200 !== $list['status'] || empty($list['data']['data'])) {
                $this->markTestSkipped('Keine Media-Items in der DB für Backend-Metainfo-Test.');
            }
            $filename = $list['data']['data'][0]['filename'];

            $get = $this->adminGet('media/' . $filename . '/metainfo');
            $this->assertSame(200, $get['status']);
            $this->assertArrayHasKey($fieldName, $get['data']['data']);

            $payload = 'media-admin-' . uniqid();
            $put = $this->adminPut('media/' . $filename . '/metainfo', [$fieldName => $payload]);
            $this->assertSame(200, $put['status']);
            $this->assertSame($payload, $put['data']['data'][$fieldName]);

            $this->adminPut('media/' . $filename . '/metainfo', [$fieldName => '']);
        } finally {
            $this->deleteMetainfoFieldViaBearer($fieldId, $bearerToken);
        }
    }

    public function testMetainfoFieldsCrudIsNotMirroredToBackend(): void
    {
        // Bewusste Architektur-Entscheidung: Field-Management bleibt Bearer-only.
        $response = $this->adminGet('metainfo/types');
        $this->assertSame(404, $response['status'], 'metainfo/types darf NICHT als /backend/-Variante existieren.');
    }

    /**
     * Legt ein Metainfo-Feld via Bearer an. Backend-Metainfo-Routen mirrorn Field-CRUD nicht,
     * also nutzen wir den Bearer-Pfad für Setup/Teardown.
     */
    private function createMetainfoFieldViaBearer(string $name, string $token): int
    {
        $url = self::$baseUrl . '/api/metainfo/fields';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['name' => $name, 'title' => 'Backend-Metainfo Test', 'type_id' => 1]),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $data = json_decode((string) $body, true);
        $this->assertSame(201, $code, 'Setup: Metainfo-Feld konnte nicht angelegt werden. Response: ' . (string) $body);
        return (int) $data['id'];
    }

    private function deleteMetainfoFieldViaBearer(int $fieldId, string $token): void
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::$baseUrl . '/api/metainfo/fields/' . $fieldId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
        ]);
        curl_exec($ch);
    }

    public function testAdminCanAssignAndRemoveUserRole(): void
    {
        // Eigenen User + Rolle anlegen, Zuweisung + Entfernen testen.
        $login = strtolower('backend_test_userrole_' . uniqid());
        $userResponse = $this->adminPost('users', [
            'login' => $login,
            'name' => 'Backend RoleAssign User',
            'password' => 'TestPassword123!',
            'email' => $login . '@example.com',
            'status' => 1,
            'admin' => 0,
        ]);
        $this->assertSame(201, $userResponse['status']);
        $userId = $userResponse['data']['id'];

        $roleResponse = $this->adminPost('users/roles', [
            'name' => 'BACKEND_TEST_assignrole_' . uniqid(),
            'description' => 'For role assignment test',
            'perms' => ['general' => '|structure|'],
        ]);
        $this->assertSame(201, $roleResponse['status']);
        $roleId = $roleResponse['data']['id'];

        try {
            $assign = $this->adminPost('users/' . $userId . '/role/' . $roleId);
            $this->assertSame(200, $assign['status'], 'Admin should assign role. Response: ' . json_encode($assign['data']));
            $this->assertContains($roleId, $assign['data']['roles']);

            $list = $this->adminGet('users/' . $userId . '/role');
            $this->assertSame(200, $list['status']);
            $this->assertCount(1, $list['data']['data']);
            $this->assertSame($roleId, $list['data']['data'][0]['id']);

            $remove = $this->adminDelete('users/' . $userId . '/role/' . $roleId);
            $this->assertSame(200, $remove['status']);
            $this->assertNotContains($roleId, $remove['data']['roles']);
        } finally {
            $this->adminDelete('users/roles/' . $roleId);
            $this->adminDelete('users/' . $userId);
        }
    }

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
