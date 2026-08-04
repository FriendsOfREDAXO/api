<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `modules/*` — read-only.
 *
 * REDAXO 6 modules are PHP classes discovered via `#[AsModule]`, identified by a string key. There is no
 * `rex_module` table, so the REDAXO 5 addon's write endpoints have no counterpart here; the tests assert
 * that they are genuinely gone rather than silently broken.
 */
final class ModulesApiTest extends ApiTestCase
{
    public function testListReturnsEnvelopeAndKeyBasedFields(): void
    {
        $modules = $this->assertListResponse($this->get('modules'));

        if (0 === count($modules)) {
            self::markTestSkipped('No modules registered in this REDAXO installation.');
        }

        foreach (['key', 'name', 'class'] as $field) {
            $this->assertArrayHasKey($field, $modules[0]);
        }

        $this->assertArrayNotHasKey('id', $modules[0], 'REDAXO 6 modules have no numeric id');
        $this->assertArrayNotHasKey('input', $modules[0], 'The module code is not exposed over the API');
        $this->assertArrayNotHasKey('output', $modules[0]);
        $this->assertNotSame('', (string) $modules[0]['key']);
        $this->assertTrue(class_exists((string) $modules[0]['class']) || '' !== (string) $modules[0]['class']);
    }

    public function testGetByKey(): void
    {
        $key = $this->resolveModuleKey();
        $response = $this->get('modules/' . $key);

        $this->assertSuccess($response);
        $this->assertSame($key, $response['data']['key'] ?? null);
    }

    public function testGetUnknownKeyReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('modules/this-module-does-not-exist'));
    }

    public function testFilterByKey(): void
    {
        $key = $this->resolveModuleKey();
        $filtered = $this->assertListResponse($this->get('modules', ['filter' => ['key' => $key]]));

        $this->assertNotEmpty($filtered);
        $this->assertContains($key, array_column($filtered, 'key'));
    }

    public function testInvalidSortIsRejected(): void
    {
        $this->assertStatus(400, $this->get('modules', ['sort' => 'input:asc']));
    }

    public function testWriteEndpointsDoNotExist(): void
    {
        $key = $this->resolveModuleKey();

        // The collection route only registers GET, so anything else is a method mismatch.
        $this->assertStatus(405, $this->post('modules', ['key' => 'x', 'name' => 'x']));
        $this->assertStatus(405, $this->patch('modules/' . $key, ['name' => 'x']));
        $this->assertStatus(405, $this->delete('modules/' . $key));
    }
}
