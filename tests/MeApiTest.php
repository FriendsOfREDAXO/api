<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests für die Selbstauskunft /api/me.
 *
 * Eigener cURL-Helper statt ApiTestCase, weil hier pro Request ein anderes
 * Token gesendet werden muss (voll, eingeschränkt, ungültig).
 */
class MeApiTest extends TestCase
{
    private static array $config;
    private static string $baseUrl;
    private static string $token;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$config = API_TEST_CONFIG;
        self::$baseUrl = rtrim(self::$config['base_url'], '/') . self::$config['api_prefix'];
        self::$token = self::$config['api_token'];

        if ('' === self::$token) {
            self::markTestSkipped('Kein API-Token in tests/.env konfiguriert.');
        }
    }

    public function testCompactIsDefaultFormat(): void
    {
        $response = $this->doRequest(self::$token);

        $this->assertSame(200, $response['status']);
        $this->assertSame('bearer', $response['data']['meta']['auth']['type'] ?? null);
        $this->assertArrayHasKey('endpoints', $response['data']);
        $this->assertNotEmpty($response['data']['endpoints']);
        $this->assertArrayNotHasKey('openapi', $response['data'], 'Default-Format muss kompakt sein, nicht OpenAPI.');
        $this->assertSame(
            count($response['data']['endpoints']),
            $response['data']['meta']['endpoint_count'] ?? null,
        );
    }

    public function testEndpointEntriesAreSelfDescribing(): void
    {
        $response = $this->doRequest(self::$token);
        $this->assertSame(200, $response['status']);

        foreach ($response['data']['endpoints'] as $endpoint) {
            $this->assertArrayHasKey('scope', $endpoint);
            $this->assertArrayHasKey('methods', $endpoint);
            $this->assertArrayHasKey('path', $endpoint);
            $this->assertArrayHasKey('description', $endpoint);
            $this->assertNotEmpty($endpoint['methods']);
            $this->assertStringStartsWith(self::$config['api_prefix'] . '/', $endpoint['path']);
        }
    }

    public function testMeListsItselfWithoutOwnScope(): void
    {
        // Der Endpunkt darf keinen eigenen Scope brauchen — sonst fehlt er
        // genau bei den Tokens, bei denen der Scope vergessen wurde.
        $response = $this->doRequest(self::$token);
        $scopes = array_column($response['data']['endpoints'], 'scope');

        $this->assertContains('me', $scopes);
        $this->assertNotContains('me', $response['data']['meta']['auth']['scopes'] ?? []);
    }

    public function testOnlyGrantedScopesAreListed(): void
    {
        $response = $this->doRequest(self::$token);
        $granted = $response['data']['meta']['auth']['scopes'] ?? [];

        foreach ($response['data']['endpoints'] as $endpoint) {
            if ('me' === $endpoint['scope']) {
                continue;
            }
            $this->assertContains(
                $endpoint['scope'],
                $granted,
                'Gelistet wurde ein Scope, den das Token nicht hat: ' . $endpoint['scope'],
            );
        }
    }

    public function testBackendRoutesAreNotListedForBearerToken(): void
    {
        $response = $this->doRequest(self::$token);

        foreach ($response['data']['endpoints'] as $endpoint) {
            $this->assertStringStartsNotWith('backend/', $endpoint['scope']);
        }
    }

    public function testRestrictedTokenSeesFewerEndpoints(): void
    {
        $restricted = self::$config['restricted_token'] ?? '';
        if ('' === $restricted) {
            self::markTestSkipped('Kein Restricted-Token in tests/.env (API_TEST_RESTRICTED_TOKEN).');
        }

        $full = $this->doRequest(self::$token);
        $limited = $this->doRequest($restricted);

        $this->assertSame(200, $limited['status'], 'Auch ein eingeschränktes Token muss /me nutzen dürfen.');
        $this->assertLessThan(
            count($full['data']['endpoints']),
            count($limited['data']['endpoints']),
        );

        $paths = array_column($limited['data']['endpoints'], 'path');
        $allowed = self::$config['api_prefix'] . '/' . ltrim(self::$config['restricted_token_allowed_path'], '/');
        $denied = self::$config['api_prefix'] . '/' . ltrim(self::$config['restricted_token_denied_path'], '/');

        $this->assertContains($allowed, $paths);
        $this->assertNotContains($denied, $paths);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $response = $this->doRequest('thisisnotavalidtoken_' . bin2hex(random_bytes(8)));

        $this->assertSame(401, $response['status']);
        $this->assertArrayNotHasKey('required_scope', $response['data'] ?? []);
    }

    public function testMissingScopeNamesTheRequiredScope(): void
    {
        $restricted = self::$config['restricted_token'] ?? '';
        if ('' === $restricted) {
            self::markTestSkipped('Kein Restricted-Token in tests/.env (API_TEST_RESTRICTED_TOKEN).');
        }

        $response = $this->doRequest($restricted, self::$config['restricted_token_denied_path']);

        $this->assertSame(401, $response['status']);
        $this->assertSame('Authorization failed', $response['data']['error'] ?? null);
        $this->assertNotEmpty(
            $response['data']['required_scope'] ?? '',
            'Bei gültigem Token und fehlendem Scope muss der benötigte Scope benannt werden.',
        );
    }

    public function testOpenApiFormatReturnsSpecForGrantedRoutesOnly(): void
    {
        $compact = $this->doRequest(self::$token);
        $spec = $this->doRequest(self::$token, 'me', ['format' => 'openapi']);

        $this->assertSame(200, $spec['status']);
        $this->assertSame('3.0.0', $spec['data']['openapi'] ?? null);
        $this->assertNotEmpty($spec['data']['paths'] ?? []);

        $prefix = self::$config['api_prefix'];
        foreach (array_keys($spec['data']['paths']) as $path) {
            // Regression: handle() darf die registrierten Routen nicht mutieren,
            // sonst steht das Prefix doppelt in der Spec.
            $this->assertStringStartsNotWith($prefix . '/', $path, 'Pfad enthält das API-Prefix doppelt: ' . $path);
        }

        $specPaths = array_keys($spec['data']['paths']);
        foreach ($compact['data']['endpoints'] as $endpoint) {
            $expected = substr($endpoint['path'], strlen($prefix));
            $this->assertContains($expected, $specPaths);
        }
    }

    public function testOpenApiTagsAreAList(): void
    {
        // OpenAPI verlangt für "tags" ein Array; ein Objekt (String-Keys) macht
        // das Dokument für getypte Parser unlesbar.
        $spec = $this->doRequest(self::$token, 'me', ['format' => 'openapi']);

        $this->assertSame(200, $spec['status']);
        $this->assertIsList($spec['data']['tags'] ?? null);
        $this->assertNotEmpty($spec['data']['tags']);

        foreach ($spec['data']['tags'] as $tag) {
            $this->assertArrayHasKey('name', $tag);
            $this->assertArrayHasKey('description', $tag);
            $this->assertStringNotContainsString('[translate:', $tag['description'], 'Fehlender Sprachschlüssel für Tag ' . $tag['name']);
        }
    }

    public function testUnknownFormatIsRejected(): void
    {
        $response = $this->doRequest(self::$token, 'me', ['format' => 'yaml']);

        $this->assertSame(400, $response['status']);
        $this->assertArrayHasKey('error', $response['data'] ?? []);
    }

    /**
     * @param array<string, string> $query
     * @return array{status: int, data: ?array}
     */
    private function doRequest(string $token, string $endpoint = 'me', array $query = []): array
    {
        $url = self::$baseUrl . '/' . ltrim($endpoint, '/');
        if ([] !== $query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$config['timeout'],
            CURLOPT_SSL_VERIFYPEER => self::$config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => self::$config['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status' => $status,
            'data' => is_string($body) ? json_decode($body, true) : null,
        ];
    }
}
