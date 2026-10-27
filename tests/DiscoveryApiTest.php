<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `me` — the self-description of the calling token.
 * @internal
 */
final class DiscoveryApiTest extends ApiTestCase
{
    public function testListsTheEndpointsOfTheToken(): void
    {
        $response = $this->get('me');

        $this->assertSuccess($response);
        self::assertSame('bearer', $response['data']['meta']['auth']['type'] ?? null);
        self::assertSame(count($response['data']['endpoints']), $response['data']['meta']['endpoint_count']);

        $scopes = array_column($response['data']['endpoints'], 'scope');
        self::assertContains('structure/articles/list', $scopes);
        // the steps of the chunked upload share one scope
        self::assertNotContains('media/upload/init', $scopes);
    }

    public function testOpenApiFormat(): void
    {
        $response = $this->get('me', ['format' => 'openapi']);

        $this->assertSuccess($response);
        self::assertSame('3.0.0', $response['data']['openapi'] ?? null);
        self::assertArrayHasKey('/structure/articles', $response['data']['paths']);
        self::assertSame('structure/articles/list', $response['data']['paths']['/structure/articles']['get']['x-required-scope'] ?? null);
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->assertStatus(400, $this->get('me', ['format' => 'xml']));
    }

    public function testRequiresAValidToken(): void
    {
        $this->assertStatus(401, $this->request('GET', 'me', ['token' => 'definitely-not-a-valid-token']));
    }
}
