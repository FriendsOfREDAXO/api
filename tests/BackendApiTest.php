<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `/api/backend/...` — the mirror authenticated by the REDAXO backend session instead of a bearer token.
 *
 * Needs API_TEST_ADMIN_PASSWORD in tests/.env; without it the whole class skips.
 */
final class BackendApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->loginBackend()) {
            self::markTestSkipped('Backend login failed — set API_TEST_ADMIN_LOGIN/API_TEST_ADMIN_PASSWORD in tests/.env.');
        }
    }

    public function testSessionAuthIsAccepted(): void
    {
        $this->assertSuccess($this->getAsBackendUser('system/clangs'));
    }

    public function testWithoutSessionTheMirrorIsRejected(): void
    {
        // No cookie and no bearer token: the mirror must not fall back to anything.
        $response = $this->request('GET', 'backend/system/clangs', ['token' => '']);

        $this->assertStatus(401, $response);
    }

    public function testBearerTokenDoesNotUnlockTheBackendMirror(): void
    {
        // The mirrored routes are session-authenticated; a token, however broadly scoped, is not enough.
        $response = $this->get('backend/system/clangs');

        $this->assertStatus(401, $response);
    }

    public function testMirrorReturnsTheSameShapeAsTheTokenRoute(): void
    {
        $viaToken = $this->get('system/clangs');
        $viaSession = $this->getAsBackendUser('system/clangs');

        $this->assertSuccess($viaToken);
        $this->assertSuccess($viaSession);
        $this->assertSame($viaToken['data'], $viaSession['data']);
    }

    public function testStructureIsReachable(): void
    {
        $articles = $this->assertListResponse($this->getAsBackendUser('structure/articles'));

        $this->assertNotEmpty($articles);
    }

    public function testUsersAreReachable(): void
    {
        $users = $this->assertListResponse($this->getAsBackendUser('users'));

        $this->assertNotEmpty($users);
    }

    public function testMediaIsReachable(): void
    {
        $this->assertListResponse($this->getAsBackendUser('media'));
        $this->assertListResponse($this->getAsBackendUser('media/category'));
    }

    public function testModulesAndTemplatesAreReachable(): void
    {
        $this->assertListResponse($this->getAsBackendUser('modules'));
        $this->assertListResponse($this->getAsBackendUser('templates'));
    }

    public function testMetainfoValuesAreMirroredButDefinitionsAreNot(): void
    {
        $clangId = (int) self::$config['test_data']['existing_clang_id'];

        $this->assertSuccess($this->getAsBackendUser('system/clangs/' . $clangId . '/metainfo'));

        // Field definitions stay bearer-only on purpose — see the Backend\Metainfo mirror.
        $this->assertStatus(404, $this->getAsBackendUser('metainfo/fields'));
    }

    public function testWriteThroughTheMirror(): void
    {
        $name = $this->generateTestName('be_article');

        $created = $this->postAsBackendUser('structure/articles', [
            'name' => $name,
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);

        $this->assertStatus(201, $created);
        $this->assertIsArray($created['data']);
        $articleId = (int) $created['data']['id'];

        try {
            $fetched = $this->getAsBackendUser('structure/articles/' . $articleId);
            $this->assertSame($name, $fetched['data']['name'] ?? null);

            $this->assertSuccess($this->patchAsBackendUser('structure/articles/' . $articleId, ['name' => $name . '_updated']));
            $this->assertSame($name . '_updated', $this->getAsBackendUser('structure/articles/' . $articleId)['data']['name'] ?? null);
        } finally {
            $this->assertSuccess($this->deleteAsBackendUser('structure/articles/' . $articleId));
        }
    }

    public function testMirrorHasTheSameNumberOfRoutesAsItMirrors(): void
    {
        // Every mirrored route reuses its source controller, so any mismatch would mean a package was
        // registered without its mirror (or the other way round).
        $modules = $this->assertListResponse($this->get('modules'));
        $mirrored = $this->assertListResponse($this->getAsBackendUser('modules'));

        $this->assertSame(count($modules), count($mirrored));
    }
}
