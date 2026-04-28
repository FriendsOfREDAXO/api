<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Metainfo API (Feldtypen, Felddefinitionen, Werte).
 *
 * Voraussetzungen am API-Token:
 *   metainfo/types/list,
 *   metainfo/fields/list, metainfo/fields/get, metainfo/fields/add,
 *   metainfo/fields/update, metainfo/fields/delete,
 *   metainfo/articles/values/get, metainfo/articles/values/update,
 *   metainfo/categories/values/get, metainfo/categories/values/update,
 *   metainfo/media/values/get, metainfo/media/values/update,
 *   metainfo/clangs/values/get, metainfo/clangs/values/update
 */
class MetainfoApiTest extends ApiTestCase
{
    // ==================== TYPES ====================

    public function testListTypes(): void
    {
        $response = $this->get('metainfo/types');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']['data']);
        $this->assertGreaterThanOrEqual(13, count($response['data']['data']), 'Mindestens 13 Default-Feldtypen erwartet.');

        $first = $response['data']['data'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('dbtype', $first);
        $this->assertArrayHasKey('dblength', $first);
    }

    // ==================== FIELDS CRUD ====================

    public function testFieldCrudRoundtrip(): void
    {
        $name = 'art_test_' . uniqid();

        $create = $this->post('metainfo/fields', [
            'name' => $name,
            'title' => 'API Test Field',
            'type_id' => 1, // text
            'priority' => 99,
        ]);

        $this->assertStatus(201, $create);
        $this->assertHasField($create, 'id');
        $fieldId = (int) $create['data']['id'];

        $get = $this->get('metainfo/fields/' . $fieldId);
        $this->assertSuccess($get);
        $this->assertSame($name, $get['data']['name']);
        $this->assertSame('art_', $get['data']['prefix']);
        $this->assertSame(1, $get['data']['type_id']);

        $update = $this->patch('metainfo/fields/' . $fieldId, [
            'title' => 'API Test Field (updated)',
            'priority' => 50,
        ]);
        $this->assertSuccess($update);

        $reread = $this->get('metainfo/fields/' . $fieldId);
        $this->assertSame('API Test Field (updated)', $reread['data']['title']);

        $delete = $this->delete('metainfo/fields/' . $fieldId);
        $this->assertSuccess($delete);

        $gone = $this->get('metainfo/fields/' . $fieldId);
        $this->assertStatus(404, $gone);
    }

    public function testFieldAddRejectsInvalidPrefix(): void
    {
        $response = $this->post('metainfo/fields', [
            'name' => 'foo_bar_' . uniqid(),
            'title' => 'Should fail',
            'type_id' => 1,
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testFieldAddRejectsDuplicate(): void
    {
        $name = 'art_test_dup_' . uniqid();

        $first = $this->post('metainfo/fields', [
            'name' => $name,
            'title' => 'Duplicate test',
            'type_id' => 1,
        ]);
        $this->assertStatus(201, $first);
        $fieldId = (int) $first['data']['id'];

        try {
            $second = $this->post('metainfo/fields', [
                'name' => $name,
                'title' => 'Duplicate test',
                'type_id' => 1,
            ]);
            $this->assertStatus(409, $second);
            $this->assertError($second);
        } finally {
            $this->delete('metainfo/fields/' . $fieldId);
        }
    }

    public function testFieldUpdateRejectsRename(): void
    {
        $name = 'art_test_rename_' . uniqid();

        $create = $this->post('metainfo/fields', [
            'name' => $name,
            'title' => 'Rename test',
            'type_id' => 1,
        ]);
        $this->assertStatus(201, $create);
        $fieldId = (int) $create['data']['id'];

        try {
            $update = $this->patch('metainfo/fields/' . $fieldId, [
                'name' => 'art_test_renamed',
            ]);
            $this->assertStatus(422, $update);
        } finally {
            $this->delete('metainfo/fields/' . $fieldId);
        }
    }

    public function testFieldGetNotFound(): void
    {
        $response = $this->get('metainfo/fields/999999');
        $this->assertStatus(404, $response);
    }

    // ==================== VALUES ====================

    public function testArticleValuesRoundtrip(): void
    {
        $name = 'art_test_value_' . uniqid();
        $articleId = self::$config['test_data']['existing_article_id'];
        $clangId = self::$config['test_data']['existing_clang_id'];

        $create = $this->post('metainfo/fields', [
            'name' => $name,
            'title' => 'Value roundtrip',
            'type_id' => 1, // text
        ]);
        $this->assertStatus(201, $create);
        $fieldId = (int) $create['data']['id'];

        try {
            $initial = $this->get('structure/articles/' . $articleId . '/metainfo', ['clang_id' => $clangId]);
            $this->assertSuccess($initial);
            $this->assertArrayHasKey($name, $initial['data']['data']);

            $payload = 'hello-' . uniqid();
            $patch = $this->patch('structure/articles/' . $articleId . '/metainfo?clang_id=' . $clangId, [
                $name => $payload,
            ]);
            $this->assertSuccess($patch);
            $this->assertSame($payload, $patch['data']['data'][$name]);

            $verify = $this->get('structure/articles/' . $articleId . '/metainfo', ['clang_id' => $clangId]);
            $this->assertSame($payload, $verify['data']['data'][$name]);

            // Reset value
            $this->patch('structure/articles/' . $articleId . '/metainfo?clang_id=' . $clangId, [
                $name => '',
            ]);
        } finally {
            $this->delete('metainfo/fields/' . $fieldId);
        }
    }

    public function testArticleValuesPatchRejectsUnknownField(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];

        $response = $this->patch('structure/articles/' . $articleId . '/metainfo', [
            'art_definitely_not_a_real_field_' . uniqid() => 'x',
        ]);

        $this->assertStatus(422, $response);
        $this->assertError($response);
    }

    public function testMediaValuesGetReturnsMap(): void
    {
        $response = $this->get('metainfo/fields', ['filter[prefix]' => 'med_', 'per_page' => 1]);
        $this->assertSuccess($response);

        // Find a media id (we need any existing media for this smoke test).
        $mediaList = $this->get('media', ['per_page' => 1]);
        if (empty($mediaList['data']['data'])) {
            $this->markTestSkipped('Keine Media-Items in der DB für Smoke-Test vorhanden.');
        }
        $mediaId = (int) $mediaList['data']['data'][0]['id'];

        $values = $this->get('media/' . $mediaId . '/metainfo');
        $this->assertSuccess($values);
        $this->assertArrayHasKey('data', $values['data']);
    }

    public function testClangValuesGetReturnsMap(): void
    {
        $clangId = self::$config['test_data']['existing_clang_id'];

        $response = $this->get('system/clangs/' . $clangId . '/metainfo');

        $this->assertSuccess($response);
        $this->assertArrayHasKey('data', $response['data']);
    }

    public function testValuesGetReturnsNotFoundForMissingArticle(): void
    {
        $response = $this->get('structure/articles/999999/metainfo');
        $this->assertStatus(404, $response);
    }
}
