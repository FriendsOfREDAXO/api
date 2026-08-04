<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/** `system/clangs` — the language endpoints. */
final class ClangsApiTest extends ApiTestCase
{
    public function testListReturnsEnvelopeAndFields(): void
    {
        $clangs = $this->assertListResponse($this->get('system/clangs'));

        $this->assertNotEmpty($clangs, 'A REDAXO installation always has at least one language');

        foreach (['id', 'code', 'name', 'priority', 'status'] as $field) {
            $this->assertArrayHasKey($field, $clangs[0]);
        }
    }

    public function testGetReturnsSingleLanguage(): void
    {
        $clangId = (int) self::$config['test_data']['existing_clang_id'];
        $response = $this->get('system/clangs/' . $clangId);

        $this->assertSuccess($response);
        $this->assertSame($clangId, $response['data']['id'] ?? null);
    }

    public function testGetUnknownLanguageReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('system/clangs/99999'));
    }

    public function testFilterByCode(): void
    {
        $all = $this->assertListResponse($this->get('system/clangs'));
        $code = (string) $all[0]['code'];

        $filtered = $this->assertListResponse($this->get('system/clangs', ['filter' => ['code' => $code]]));

        $this->assertNotEmpty($filtered);
        foreach ($filtered as $clang) {
            $this->assertStringContainsStringIgnoringCase($code, (string) $clang['code']);
        }
    }

    public function testInvalidSortFieldIsRejected(): void
    {
        $response = $this->get('system/clangs', ['sort' => 'not_a_column:asc']);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testInvalidSortDirectionIsRejected(): void
    {
        $this->assertStatus(400, $this->get('system/clangs', ['sort' => 'name:sideways']));
    }

    public function testCreateUpdateAndDeleteLanguage(): void
    {
        $code = 'zz';
        $name = $this->generateTestName('lang');

        $created = $this->post('system/clangs', [
            'code' => $code,
            'name' => $name,
            'priority' => 99,
            'status' => 0,
        ]);

        $this->assertStatus(201, $created);
        $this->assertIsArray($created['data']);
        $clangId = (int) $created['data']['id'];

        try {
            $this->assertGreaterThan(0, $clangId);

            $fetched = $this->get('system/clangs/' . $clangId);
            $this->assertSuccess($fetched);
            $this->assertSame($code, $fetched['data']['code'] ?? null);
            $this->assertSame($name, $fetched['data']['name'] ?? null);

            $renamed = $name . '_updated';
            $this->assertSuccess($this->patch('system/clangs/' . $clangId, ['name' => $renamed]));
            $this->assertSame($renamed, $this->get('system/clangs/' . $clangId)['data']['name'] ?? null);
        } finally {
            $this->assertSuccess($this->delete('system/clangs/' . $clangId));
        }

        $this->assertStatus(404, $this->get('system/clangs/' . $clangId));
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $existing = $this->assertListResponse($this->get('system/clangs'))[0];

        $response = $this->post('system/clangs', [
            'code' => (string) $existing['code'],
            'name' => $this->generateTestName('dupe'),
            'priority' => 99,
            'status' => 0,
        ]);

        $this->assertStatus(409, $response);
    }

    public function testMissingRequiredBodyFieldIsRejected(): void
    {
        $response = $this->post('system/clangs', ['name' => $this->generateTestName('nocode')]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testLastLanguageCannotBeDeleted(): void
    {
        $clangs = $this->assertListResponse($this->get('system/clangs'));

        if (count($clangs) > 1) {
            self::markTestSkipped('More than one language exists, so deleting one is legitimate.');
        }

        // Deleting the only language is refused; the start language is refused by core on top of that.
        $this->assertStatus(409, $this->delete('system/clangs/' . $clangs[0]['id']));
    }
}
