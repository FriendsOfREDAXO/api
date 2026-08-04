<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use CURLFile;
use PHPUnit\Framework\TestCase;

use function count;
use function is_array;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_COOKIEFILE;
use const CURLOPT_COOKIEJAR;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYHOST;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const JSON_PRETTY_PRINT;

/**
 * Base class for the API tests.
 *
 * The tests exercise the addon the way a client does: over HTTP against a running REDAXO 6 instance
 * configured in tests/.env. That is deliberate — the interesting behaviour (routing, auth, the request
 * lifecycle hook, JSON shapes, status codes) only exists in a real request.
 *
 * @phpstan-type TResponse array{success: bool, status: int, data: mixed, raw?: string, error?: string}
 */
abstract class ApiTestCase extends TestCase
{
    /** @var array<string, mixed> */
    protected static array $config;
    protected static string $baseUrl;
    protected static string $apiToken;

    /** Cookie jar for backend-session requests; created lazily by {@see self::loginBackend()}. */
    private static ?string $cookieFile = null;

    /** @var list<array{endpoint: string, id: int|string}> Created resources, removed again in tearDown() */
    protected array $createdResources = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$config = API_TEST_CONFIG;

        if ('' === self::$config['base_url']) {
            self::markTestSkipped('Set API_TEST_BASE_URL in tests/.env (copy tests/.env.example).');
        }

        self::$baseUrl = rtrim((string) self::$config['base_url'], '/') . self::$config['api_prefix'];
        self::$apiToken = (string) self::$config['api_token'];

        if ('' === self::$apiToken) {
            self::markTestSkipped('Set API_TEST_TOKEN in tests/.env.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$cookieFile && file_exists(self::$cookieFile)) {
            unlink(self::$cookieFile);
        }
        self::$cookieFile = null;

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        // Remove what the test created, newest first
        foreach (array_reverse($this->createdResources) as $resource) {
            $this->delete($resource['endpoint'] . '/' . $resource['id']);
        }
        $this->createdResources = [];

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $query
     * @return TResponse
     */
    protected function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TResponse
     */
    protected function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TResponse
     */
    protected function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TResponse
     */
    protected function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /** @return TResponse */
    protected function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Multipart POST, for file uploads.
     *
     * @param array<string, scalar> $data
     * @param array<string, string> $files field name => local file path
     * @return TResponse
     */
    protected function postMultipart(string $endpoint, array $data = [], array $files = []): array
    {
        return $this->request('POST', $endpoint, [
            'multipart' => true,
            'data' => $data,
            'files' => $files,
        ]);
    }

    /**
     * Logs into the REDAXO backend and returns whether a session is available.
     *
     * Used by the tests for the `/api/backend/...` mirror, which authenticates via the backend session
     * cookie instead of a bearer token.
     */
    protected function loginBackend(): bool
    {
        if (null !== self::$cookieFile) {
            return true;
        }

        $password = (string) self::$config['backend']['admin_password'];
        if ('' === $password) {
            return false;
        }

        $cookieFile = (string) tempnam(sys_get_temp_dir(), 'rex_api_test_cookies_');
        $loginUrl = rtrim((string) self::$config['base_url'], '/') . '/redaxo/index.php';

        $form = $this->rawRequest('GET', $loginUrl, [], $cookieFile);
        if (!preg_match('/name="_csrf_token" value="([^"]+)"/', $form['body'], $matches)) {
            unlink($cookieFile);

            return false;
        }

        $this->rawRequest('POST', $loginUrl, [
            CURLOPT_POSTFIELDS => http_build_query([
                'rex_user_login' => (string) self::$config['backend']['admin_login'],
                'rex_user_psw' => $password,
                '_csrf_token' => $matches[1],
            ]),
        ], $cookieFile);

        // A logged-in session reaches a real backend page instead of being bounced to the login form.
        $check = $this->rawRequest('GET', $loginUrl . '?page=api/token', [], $cookieFile);

        if (!str_contains($check['body'], 'rex-page-header')) {
            unlink($cookieFile);

            return false;
        }

        return null !== (self::$cookieFile = $cookieFile);
    }

    /**
     * Request against the `/api/backend/...` mirror, authenticated by the backend session.
     *
     * @param array<string, mixed> $query
     * @return TResponse
     */
    protected function getAsBackendUser(string $endpoint, array $query = []): array
    {
        return $this->request('GET', 'backend/' . ltrim($endpoint, '/'), ['query' => $query, 'session' => true]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TResponse
     */
    protected function postAsBackendUser(string $endpoint, array $data = []): array
    {
        return $this->request('POST', 'backend/' . ltrim($endpoint, '/'), ['json' => $data, 'session' => true]);
    }

    /**
     * @param array<string, mixed> $data
     * @return TResponse
     */
    protected function patchAsBackendUser(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', 'backend/' . ltrim($endpoint, '/'), ['json' => $data, 'session' => true]);
    }

    /** @return TResponse */
    protected function deleteAsBackendUser(string $endpoint): array
    {
        return $this->request('DELETE', 'backend/' . ltrim($endpoint, '/'), ['session' => true]);
    }

    /**
     * @param array<string, mixed> $options
     * @return TResponse
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = self::$baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }

        $useSession = (bool) ($options['session'] ?? false);
        $headers = ['Accept: application/json'];

        if (!$useSession) {
            $headers[] = 'Authorization: Bearer ' . ($options['token'] ?? self::$apiToken);
        }

        $curlOptions = [CURLOPT_CUSTOMREQUEST => $method];

        if (isset($options['json'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($options['json']);
            $headers[] = 'Content-Type: application/json';
        } elseif ($options['multipart'] ?? false) {
            $postData = $options['data'] ?? [];

            foreach ($options['files'] ?? [] as $fieldName => $filePath) {
                if (file_exists($filePath)) {
                    $postData[$fieldName] = new CURLFile($filePath);
                }
            }

            $curlOptions[CURLOPT_POSTFIELDS] = $postData;
        }

        $curlOptions[CURLOPT_HTTPHEADER] = $headers;

        $result = $this->rawRequest($method, $url, $curlOptions, $useSession ? self::$cookieFile : null);

        if (null !== $result['error']) {
            return ['success' => false, 'status' => 0, 'error' => $result['error'], 'data' => null];
        }

        return [
            'success' => $result['status'] >= 200 && $result['status'] < 300,
            'status' => $result['status'],
            'data' => json_decode($result['body'], true),
            'raw' => $result['body'],
        ];
    }

    /**
     * @param array<int, mixed> $curlOptions
     * @return array{status: int, body: string, error: string|null}
     */
    private function rawRequest(string $method, string $url, array $curlOptions = [], ?string $cookieFile = null): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$config['timeout'],
            CURLOPT_SSL_VERIFYPEER => self::$config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => self::$config['verify_ssl'] ? 2 : 0,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if (null !== $cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        }

        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        return [
            'status' => $status,
            'body' => false === $body ? '' : (string) $body,
            'error' => false === $body ? ($error ?: 'unknown curl error') : null,
        ];
    }

    /** Registers a created resource so tearDown() removes it again. */
    protected function trackResource(string $endpoint, int|string $id): void
    {
        $this->createdResources[] = ['endpoint' => $endpoint, 'id' => $id];
    }

    /** @param TResponse $response */
    protected function assertSuccess(array $response, string $message = ''): void
    {
        $this->assertTrue($response['success'], $message . $this->describe($response));
    }

    /** @param TResponse $response */
    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertSame($expected, $response['status'], $message . $this->describe($response));
    }

    /** @param TResponse $response */
    protected function assertError(array $response, string $message = ''): void
    {
        $this->assertFalse($response['success'], $message . $this->describe($response));
        $this->assertIsArray($response['data'], $message);
        $this->assertArrayHasKey('error', $response['data'], $message);
    }

    /** @param TResponse $response */
    protected function assertHasField(array $response, string $field, string $message = ''): void
    {
        $this->assertIsArray($response['data'], $message . $this->describe($response));
        $this->assertArrayHasKey($field, $response['data'], $message . $this->describe($response));
    }

    /**
     * Asserts the unified list envelope (`{data: [...], meta: {...}}`) and returns the rows.
     *
     * @param TResponse $response
     * @return list<array<string, mixed>>
     */
    protected function assertListResponse(array $response, string $message = ''): array
    {
        $this->assertSuccess($response, $message);
        $this->assertIsArray($response['data'], $message);
        $this->assertArrayHasKey('data', $response['data'], $message);
        $this->assertArrayHasKey('meta', $response['data'], $message);

        foreach (['page', 'per_page', 'total', 'total_pages'] as $key) {
            $this->assertArrayHasKey($key, $response['data']['meta'], $message);
        }

        $this->assertIsArray($response['data']['data'], $message);

        return $response['data']['data'];
    }

    /** @param TResponse $response */
    private function describe(array $response): string
    {
        if (!self::$config['debug']) {
            return '';
        }

        return "\nStatus: " . $response['status']
            . "\nData: " . json_encode($response['data'], JSON_PRETTY_PRINT)
            . (isset($response['error']) ? "\nError: " . $response['error'] : '');
    }

    protected function getTestPrefix(): string
    {
        return (string) self::$config['test_data']['test_prefix'];
    }

    protected function generateTestName(string $suffix = ''): string
    {
        return $this->getTestPrefix() . uniqid() . ('' !== $suffix ? '_' . $suffix : '');
    }

    /** Creates an article at root level and registers it for cleanup. */
    protected function createArticle(string $name = '', int $categoryId = 0, int $status = 0): int
    {
        $response = $this->post('structure/articles', [
            'name' => '' !== $name ? $name : $this->generateTestName('article'),
            'category_id' => $categoryId,
            'priority' => 1,
            'status' => $status,
        ]);

        $this->assertStatus(201, $response, 'Article creation failed');
        $this->assertIsArray($response['data']);

        $id = (int) $response['data']['id'];
        $this->trackResource('structure/articles', $id);

        return $id;
    }

    /** Creates a category at root level and registers it for cleanup. */
    protected function createCategory(string $name = '', int $categoryId = 0, int $status = 0): int
    {
        $response = $this->post('structure/categories', [
            'name' => '' !== $name ? $name : $this->generateTestName('category'),
            'category_id' => $categoryId,
            'priority' => 1,
            'status' => $status,
        ]);

        $this->assertStatus(201, $response, 'Category creation failed');
        $this->assertIsArray($response['data']);

        $id = (int) $response['data']['id'];
        $this->trackResource('structure/categories', $id);

        return $id;
    }

    /** The module key to place test slices with: configured, or the first one the API reports. */
    protected function resolveModuleKey(): string
    {
        $configured = (string) self::$config['test_data']['existing_module_key'];

        if ('' !== $configured) {
            return $configured;
        }

        $modules = $this->assertListResponse($this->get('modules'));

        if (0 === count($modules)) {
            self::markTestSkipped('No modules registered in this REDAXO installation.');
        }

        return (string) $modules[0]['key'];
    }

    /**
     * Writes a tiny valid PNG to a temp file and returns its path.
     *
     * Generated rather than committed as a fixture so the repo stays free of binary blobs.
     */
    protected function createTempPng(int $size = 8): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        $raw = '';
        for ($y = 0; $y < $size; ++$y) {
            $raw .= "\x00" . str_repeat("\xff\x00\x00", $size);
        }

        $png = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0))
            . $chunk('IDAT', (string) gzcompress($raw))
            . $chunk('IEND', '');

        $path = (string) tempnam(sys_get_temp_dir(), 'rex_api_test_') . '.png';
        file_put_contents($path, $png);

        return $path;
    }
}
