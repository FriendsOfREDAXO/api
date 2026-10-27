<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `metainfo/*` — field definitions (read-only) and values (read/write).
 *
 * The definitions come from the project's code-declared meta schemas, so the field-CRUD endpoints of the
 * REDAXO 5 addon do not exist. The value tests need at least one article field, configured through
 * API_TEST_METAINFO_ARTICLE_FIELD (its key, without the `meta_` prefix); without it they skip, because a fresh REDAXO 6 ships no meta schema.
 */
final class MetainfoApiTest extends ApiTestCase
{
    public function testFieldListReturnsEnvelopeAndTypeInsteadOfTypeId(): void
    {
        $fields = $this->assertListResponse($this->get('metainfo/fields'));

        if (0 === count($fields)) {
            self::markTestSkipped('This installation declares no meta schema.');
        }

        foreach (['name', 'key', 'label', 'entity', 'priority', 'type', 'required', 'translated', 'table'] as $key) {
            $this->assertArrayHasKey($key, $fields[0]);
        }

        $this->assertArrayNotHasKey('type_id', $fields[0], 'The field class is the type in REDAXO 6');
        $this->assertContains($fields[0]['entity'], ['article', 'category', 'media', 'language']);
        $this->assertSame('meta_' . $fields[0]['key'], $fields[0]['name']);
    }

    public function testFieldListCanBeFilteredByPrefix(): void
    {
        // `prefix` is the REDAXO 5 name of the entity filter
        foreach (['entity' => 'article', 'prefix' => 'art_'] as $filter => $value) {
            $fields = $this->assertListResponse($this->get('metainfo/fields', ['filter' => [$filter => $value]]));

            foreach ($fields as $field) {
                $this->assertSame('article', $field['entity']);
            }
        }
    }

    public function testInvalidPrefixIsRejected(): void
    {
        $response = $this->get('metainfo/fields', ['filter' => ['prefix' => 'nope_']]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testFieldGetByName(): void
    {
        $fieldName = $this->resolveArticleFieldName();

        $response = $this->get('metainfo/fields/' . $fieldName);

        $this->assertSuccess($response);
        $this->assertSame($fieldName, $response['data']['name'] ?? null);
        $this->assertSame('article', $response['data']['entity'] ?? null);
    }

    public function testFieldGetUnknownNameReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('metainfo/fields/meta_definitely_not_a_field'));
    }

    public function testFieldWriteEndpointsDoNotExist(): void
    {
        $this->assertStatus(405, $this->post('metainfo/fields', ['name' => 'meta_x', 'title' => 'x']));
        $this->assertStatus(405, $this->patch('metainfo/fields/meta_x', ['title' => 'x']));
        $this->assertStatus(405, $this->delete('metainfo/fields/meta_x'));
    }

    public function testArticleValuesRoundTrip(): void
    {
        $fieldName = $this->resolveArticleFieldName();
        $articleId = $this->createArticle();

        $initial = $this->get('structure/articles/' . $articleId . '/metainfo');
        $this->assertSuccess($initial);
        $this->assertArrayHasKey('language_id', $initial['data']);
        $this->assertArrayHasKey('data', $initial['data']);
        $this->assertArrayHasKey($fieldName, $initial['data']['data']);

        $value = 'api test value ' . uniqid();
        $updated = $this->patch('structure/articles/' . $articleId . '/metainfo', [$fieldName => $value]);

        $this->assertSuccess($updated);
        $this->assertSame($value, $updated['data']['data'][$fieldName] ?? null);

        // And it is actually persisted, not just echoed back.
        $reread = $this->get('structure/articles/' . $articleId . '/metainfo');
        $this->assertSame($value, $reread['data']['data'][$fieldName] ?? null);
    }

    public function testUnknownFieldInPatchIsRejected(): void
    {
        $this->resolveArticleFieldName();
        $articleId = $this->createArticle();

        $response = $this->patch('structure/articles/' . $articleId . '/metainfo', ['definitely_not_a_field' => 'x']);

        $this->assertStatus(422, $response);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('allowed', $response['data']);
    }

    public function testStartArticleHasArticleValues(): void
    {
        // in REDAXO 6 article and category meta are separate entities, so a start article has article values too
        $categoryId = $this->createCategory();

        $this->assertSuccess($this->get('structure/articles/' . $categoryId . '/metainfo'));
    }

    public function testCategoryValuesAreReadable(): void
    {
        $categoryId = $this->createCategory();
        $response = $this->get('structure/categories/' . $categoryId . '/metainfo');

        $this->assertSuccess($response);
        $this->assertArrayHasKey('data', $response['data']);
    }

    public function testClangValuesAreReadable(): void
    {
        $clangId = (int) self::$config['test_data']['existing_clang_id'];
        $response = $this->get('system/clangs/' . $clangId . '/metainfo');

        $this->assertSuccess($response);
        $this->assertArrayHasKey('data', $response['data']);
    }

    public function testValuesOfUnknownEntitiesReturnNotFound(): void
    {
        $this->assertStatus(404, $this->get('structure/articles/999999/metainfo'));
        $this->assertStatus(404, $this->get('structure/categories/999999/metainfo'));
        $this->assertStatus(404, $this->get('system/clangs/999999/metainfo'));
        $this->assertStatus(404, $this->get('media/this_file_does_not_exist.png/metainfo'));
    }

    public function testUnknownLanguageInQueryIsRejected(): void
    {
        $articleId = (int) self::$config['test_data']['existing_article_id'];

        $this->assertStatus(404, $this->get('structure/articles/' . $articleId . '/metainfo', ['clang_id' => 9999]));
    }

    /** The article field (key) to exercise the value endpoints with, or a skip when none is configured. */
    private function resolveArticleFieldName(): string
    {
        $configured = (string) self::$config['test_data']['metainfo_article_field'];

        if ('' !== $configured) {
            return $configured;
        }

        self::markTestSkipped('Set API_TEST_METAINFO_ARTICLE_FIELD in tests/.env to test metainfo values.');
    }
}
