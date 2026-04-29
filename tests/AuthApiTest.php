<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests für die Bearer-Token-Authentifizierung.
 *
 * Deckt explizit die Auth-Failure-Pfade ab, die in den anderen Test-Suites
 * nicht getroffen werden, weil dort immer ein gültiger Token mitgesendet wird.
 */
class AuthApiTest extends TestCase
{
    private static array $config;
    private static string $baseUrl;
    private static string $validToken;

    /** Beliebiger geschützter Endpoint, dessen 200-Response wir als Baseline brauchen. */
    private const PROTECTED_ENDPOINT = 'structure/articles';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$config = API_TEST_CONFIG;
        self::$baseUrl = rtrim(self::$config['base_url'], '/') . self::$config['api_prefix'];
        self::$validToken = self::$config['api_token'];

        if ('' === self::$validToken) {
            self::markTestSkipped('Kein API-Token in tests/.env konfiguriert.');
        }
    }

    public function testValidTokenReturns200(): void
    {
        // Sanity-Check: Ohne diesen Baseline-Test sind alle 401-Asserts wertlos,
        // weil eine fehlerhafte Test-Umgebung sonst auch valide Tokens als 401 abweist.
        $response = $this->doRequest(['Authorization: Bearer ' . self::$validToken]);
        $this->assertSame(200, $response['status'], 'Valider Token muss 200 liefern (Test-Setup-Check).');
    }

    public function testMissingAuthorizationHeader(): void
    {
        $response = $this->doRequest([]);
        $this->assertSame(401, $response['status']);
        $this->assertSame('Authorization failed', $response['data']['error'] ?? null);
    }

    public function testEmptyBearerToken(): void
    {
        $response = $this->doRequest(['Authorization: Bearer ']);
        $this->assertSame(401, $response['status']);
    }

    public function testWrongBearerToken(): void
    {
        $response = $this->doRequest(['Authorization: Bearer thisisnotavalidtoken_' . bin2hex(random_bytes(8))]);
        $this->assertSame(401, $response['status']);
    }

    public function testMalformedAuthorizationHeader(): void
    {
        // Kein "Bearer "-Prefix — Token::getFromBearerToken() kann nichts extrahieren.
        $response = $this->doRequest(['Authorization: Basic ' . base64_encode('user:pass')]);
        $this->assertSame(401, $response['status']);
    }

    public function testTokenWithWrongLastChar(): void
    {
        // Stellt sicher, dass leicht abweichende Tokens (z. B. abgeschnitten/verändert)
        // nicht akzeptiert werden — fängt Off-by-One-Bugs in der Token-Vergleichslogik ab.
        $tampered = self::$validToken;
        $last = $tampered[strlen($tampered) - 1];
        $tampered[strlen($tampered) - 1] = ('A' === $last) ? 'B' : 'A';
        $response = $this->doRequest(['Authorization: Bearer ' . $tampered]);
        $this->assertSame(401, $response['status']);
    }

    public function testRestrictedTokenAllowedScope(): void
    {
        $restricted = self::$config['restricted_token'] ?? '';
        if ('' === $restricted) {
            self::markTestSkipped('Kein Restricted-Token in tests/.env (API_TEST_RESTRICTED_TOKEN).');
        }

        $allowedPath = self::$config['restricted_token_allowed_path'];
        $response = $this->doRequest(['Authorization: Bearer ' . $restricted], $allowedPath);

        $this->assertSame(200, $response['status'], 'Restricted Token sollte für gewährten Scope durchkommen.');
    }

    public function testRestrictedTokenDeniedScope(): void
    {
        $restricted = self::$config['restricted_token'] ?? '';
        if ('' === $restricted) {
            self::markTestSkipped('Kein Restricted-Token in tests/.env (API_TEST_RESTRICTED_TOKEN).');
        }

        $deniedPath = self::$config['restricted_token_denied_path'];
        $response = $this->doRequest(['Authorization: Bearer ' . $restricted], $deniedPath);

        $this->assertSame(401, $response['status'], 'Restricted Token muss für nicht gewährten Scope mit 401 abgewiesen werden.');
        $this->assertSame('Authorization failed', $response['data']['error'] ?? null);
    }

    /**
     * @param array<string> $headers Zusätzliche Header (jeweils "Name: Value").
     * @return array{status: int, data: ?array}
     */
    private function doRequest(array $headers, ?string $endpoint = null): array
    {
        $url = self::$baseUrl . '/' . ($endpoint ?? self::PROTECTED_ENDPOINT);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$config['timeout'],
            CURLOPT_SSL_VERIFYPEER => self::$config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => self::$config['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status' => $status,
            'data' => is_string($body) ? json_decode($body, true) : null,
        ];
    }
}
