<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Basis-Testklasse für API-Tests.
 *
 * Stellt HTTP-Client und Hilfsmethoden für API-Aufrufe bereit.
 */
abstract class ApiTestCase extends TestCase
{
    protected static array $config;
    protected static string $baseUrl;
    protected static string $apiToken;

    /** @var array<string, mixed> Erstellte Test-Ressourcen zum Aufräumen */
    protected array $createdResources = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$config = API_TEST_CONFIG;
        self::$baseUrl = rtrim(self::$config['base_url'], '/') . self::$config['api_prefix'];
        self::$apiToken = self::$config['api_token'];

        if ('DEIN_API_TOKEN_HIER' === self::$apiToken || empty(self::$apiToken)) {
            self::markTestSkipped('Bitte API-Token in tests/config.php konfigurieren.');
        }
    }

    protected function tearDown(): void
    {
        // Aufräumen: Erstellte Ressourcen löschen
        foreach (array_reverse($this->createdResources) as $resource) {
            $this->deleteResource($resource['endpoint'], $resource['id']);
        }
        $this->createdResources = [];

        parent::tearDown();
    }

    /**
     * Führt einen GET-Request aus.
     */
    protected function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Führt einen POST-Request aus.
     */
    protected function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Führt einen PUT-Request aus.
     */
    protected function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Führt einen PATCH-Request aus.
     */
    protected function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /**
     * Führt einen DELETE-Request aus.
     */
    protected function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Führt einen Multipart-POST-Request aus (für Datei-Uploads).
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
     * Führt einen HTTP-Request aus.
     */
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $url = self::$baseUrl . '/' . ltrim($endpoint, '/');

        // Query-Parameter anhängen
        if (!empty($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$config['timeout'],
            CURLOPT_SSL_VERIFYPEER => self::$config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => self::$config['verify_ssl'] ? 2 : 0,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::$apiToken,
                'Accept: application/json',
            ],
        ]);

        // Request-Body
        if (isset($options['json'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . self::$apiToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ]);
        } elseif (isset($options['multipart']) && $options['multipart']) {
            $postData = $options['data'] ?? [];

            // Dateien hinzufügen
            if (!empty($options['files'])) {
                foreach ($options['files'] as $fieldName => $filePath) {
                    if (file_exists($filePath)) {
                        $postData[$fieldName] = new \CURLFile($filePath);
                    }
                }
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . self::$apiToken,
                'Accept: application/json',
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if (false === $response) {
            return [
                'success' => false,
                'status' => 0,
                'error' => $error,
                'data' => null,
            ];
        }

        $data = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'status' => $httpCode,
            'data' => $data,
            'raw' => $response,
        ];
    }

    /**
     * Registriert eine erstellte Ressource zum späteren Aufräumen.
     */
    protected function trackResource(string $endpoint, int|string $id): void
    {
        $this->createdResources[] = [
            'endpoint' => $endpoint,
            'id' => $id,
        ];
    }

    /**
     * Löscht eine Ressource (für Aufräumen nach Tests).
     */
    protected function deleteResource(string $endpoint, int|string $id): void
    {
        $this->delete($endpoint . '/' . $id);
    }

    /**
     * Assertion: Response war erfolgreich.
     */
    protected function assertSuccess(array $response, string $message = ''): void
    {
        $debugInfo = '';
        if (self::$config['debug'] && !$response['success']) {
            $debugInfo = "\nStatus: " . $response['status'];
            $debugInfo .= "\nData: " . json_encode($response['data'], JSON_PRETTY_PRINT);
        }

        $this->assertTrue($response['success'], $message . $debugInfo);
    }

    /**
     * Assertion: Response hat bestimmten HTTP-Status.
     */
    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $debugInfo = '';
        if (self::$config['debug'] && $expected !== $response['status']) {
            $debugInfo = "\nData: " . json_encode($response['data'], JSON_PRETTY_PRINT);
        }

        $this->assertSame($expected, $response['status'], $message . $debugInfo);
    }

    /**
     * Assertion: Response enthält Fehler.
     */
    protected function assertError(array $response, string $message = ''): void
    {
        $this->assertFalse($response['success'], $message);
        $this->assertArrayHasKey('error', $response['data'] ?? [], $message);
    }

    /**
     * Assertion: Response-Daten enthalten bestimmtes Feld.
     */
    protected function assertHasField(array $response, string $field, string $message = ''): void
    {
        $this->assertArrayHasKey($field, $response['data'] ?? [], $message);
    }

    /**
     * Gibt Test-Prefix zurück.
     */
    protected function getTestPrefix(): string
    {
        return self::$config['test_data']['test_prefix'];
    }

    /**
     * Generiert einen eindeutigen Test-Namen.
     */
    protected function generateTestName(string $suffix = ''): string
    {
        return $this->getTestPrefix() . uniqid() . ($suffix ? '_' . $suffix : '');
    }
}
