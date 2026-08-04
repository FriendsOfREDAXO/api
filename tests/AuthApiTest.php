<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/** Authentication, scope enforcement and the generic routing responses. */
final class AuthApiTest extends ApiTestCase
{
    public function testValidTokenIsAccepted(): void
    {
        $this->assertSuccess($this->get('system/clangs'));
    }

    public function testMissingTokenIsRejected(): void
    {
        $response = $this->request('GET', 'system/clangs', ['token' => '']);

        $this->assertStatus(401, $response);
        $this->assertError($response);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $response = $this->request('GET', 'system/clangs', ['token' => 'definitely-not-a-valid-token']);

        $this->assertStatus(401, $response);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        $response = $this->get('this/route/does/not/exist');

        $this->assertStatus(404, $response);
        $this->assertSame('Route not found', $response['data']['error'] ?? null);
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        // /modules is GET-only: REDAXO 6 modules are PHP classes, so there is nothing to POST.
        $response = $this->post('modules', []);

        $this->assertStatus(405, $response);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('allowed', $response['data']);
        $this->assertContains('GET', $response['data']['allowed']);
    }

    public function testScopesAreEnforced(): void
    {
        $restrictedToken = (string) self::$config['restricted_token'];

        if ('' === $restrictedToken) {
            self::markTestSkipped('Set API_TEST_RESTRICTED_TOKEN in tests/.env to test scope enforcement.');
        }

        $allowed = $this->request('GET', (string) self::$config['restricted_token_allowed_path'], ['token' => $restrictedToken]);
        $denied = $this->request('GET', (string) self::$config['restricted_token_denied_path'], ['token' => $restrictedToken]);

        $this->assertSuccess($allowed, 'The restricted token should have access to its allowed scope');
        $this->assertStatus(401, $denied, 'The restricted token must not reach a scope it was not granted');
    }

    public function testPreflightRequestIsAnswered(): void
    {
        $response = $this->request('OPTIONS', 'system/clangs');

        $this->assertStatus(204, $response);
    }
}
