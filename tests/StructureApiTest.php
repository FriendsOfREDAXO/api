<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `structure/*` — articles, categories and slices.
 * @internal
 */
final class StructureApiTest extends ApiTestCase
{
    public function testArticleListReturnsEnvelopeAndFields(): void
    {
        $articles = $this->assertListResponse($this->get('structure/articles'));

        self::assertNotEmpty($articles);

        // `template` holds a template *key* in REDAXO 6; there is no template_id anymore.
        foreach (['id', 'name', 'language_id', 'category_id', 'priority', 'is_category', 'status', 'template'] as $field) {
            self::assertArrayHasKey($field, $articles[0]);
        }

        self::assertArrayNotHasKey('template_id', $articles[0]);
        self::assertArrayNotHasKey('clang_id', $articles[0]);
    }

    public function testArticleListCanBeFilteredToCategoriesOnly(): void
    {
        $categories = $this->assertListResponse($this->get('structure/articles', ['filter' => ['is_category' => 1]]));

        self::assertNotEmpty($categories);
        foreach ($categories as $category) {
            self::assertTrue($category['is_category']);
        }
    }

    public function testArticleListCanBeFilteredToTheRootLevel(): void
    {
        $articles = $this->assertListResponse($this->get('structure/articles', ['filter' => ['category_id' => 0]]));

        foreach ($articles as $article) {
            self::assertNull($article['category_id']);
        }
    }

    public function testArticleListPaginationMetaIsConsistent(): void
    {
        $response = $this->get('structure/articles', ['per_page' => 1, 'page' => 1]);
        $rows = $this->assertListResponse($response);

        self::assertLessThanOrEqual(1, count($rows));
        self::assertSame(1, $response['data']['meta']['per_page']);
        self::assertSame(1, $response['data']['meta']['page']);
    }

    public function testGetArticleReturnsSameShapeAsList(): void
    {
        $articleId = (int) self::$config['test_data']['existing_article_id'];
        $response = $this->get('structure/articles/' . $articleId);

        $this->assertSuccess($response);
        self::assertSame($articleId, $response['data']['id'] ?? null);
        self::assertArrayHasKey('template', $response['data']);
        self::assertArrayHasKey('language_id', $response['data']);
    }

    public function testGetUnknownArticleReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('structure/articles/999999'));
    }

    public function testGetArticleInUnknownLanguageReturnsNotFound(): void
    {
        $articleId = (int) self::$config['test_data']['existing_article_id'];

        $this->assertStatus(404, $this->get('structure/articles/' . $articleId, ['language_id' => 9999]));
    }

    public function testCreateUpdateAndDeleteArticle(): void
    {
        $name = $this->generateTestName('article');
        $articleId = $this->createArticle($name);

        $fetched = $this->get('structure/articles/' . $articleId);
        $this->assertSuccess($fetched);
        self::assertSame($name, $fetched['data']['name'] ?? null);
        self::assertFalse($fetched['data']['is_category']);

        $renamed = $name . '_updated';
        $this->assertSuccess($this->patch('structure/articles/' . $articleId, ['name' => $renamed, 'status' => 1]));

        $updated = $this->get('structure/articles/' . $articleId);
        self::assertSame($renamed, $updated['data']['name'] ?? null);
        self::assertSame(1, (int) $updated['data']['status']);
    }

    public function testUnknownBodyFieldsAreRejected(): void
    {
        $articleId = $this->createArticle();

        // REDAXO 5 field names must not be silently ignored
        $response = $this->patch('structure/articles/' . $articleId, ['name' => 'x', 'clang_id' => 1]);

        $this->assertStatus(400, $response);
        self::assertSame(['clang_id'], $response['data']['fields'] ?? null);
    }

    public function testCreateArticleWithUnknownTemplateIsRejected(): void
    {
        $response = $this->post('structure/articles', [
            'name' => $this->generateTestName('badtemplate'),
            'category_id' => 0,
            'priority' => 1,
            'template' => 'this-template-does-not-exist',
        ]);

        $this->assertStatus(404, $response);
    }

    public function testCreateArticleInUnknownCategoryIsRejected(): void
    {
        $response = $this->post('structure/articles', [
            'name' => $this->generateTestName('badcat'),
            'category_id' => 999999,
            'priority' => 1,
        ]);

        $this->assertStatus(400, $response);
    }

    public function testCreateUpdateAndDeleteCategory(): void
    {
        $name = $this->generateTestName('category');
        $categoryId = $this->createCategory($name);

        $fetched = $this->get('structure/categories/' . $categoryId);
        $this->assertSuccess($fetched);
        self::assertSame($name, $fetched['data']['name'] ?? null);
        self::assertNull($fetched['data']['parent_id']);

        // A category is a start article, so it shows up under structure/articles as well.
        $article = $this->get('structure/articles/' . $categoryId);
        $this->assertSuccess($article);
        self::assertTrue($article['data']['is_category']);

        $renamed = $name . '_updated';
        $this->assertSuccess($this->patch('structure/categories/' . $categoryId, ['name' => $renamed, 'status' => 1]));

        $updated = $this->get('structure/categories/' . $categoryId);
        self::assertSame($renamed, $updated['data']['name'] ?? null);
        self::assertSame(1, (int) $updated['data']['status']);
    }

    public function testCategoryListCanBeFilteredByParent(): void
    {
        $parentId = $this->createCategory();
        $childId = $this->createCategory('', $parentId);

        $children = $this->assertListResponse($this->get('structure/categories', ['filter' => ['parent_id' => $parentId]]));

        self::assertSame([$childId], array_column($children, 'id'));
    }

    public function testCategoryWithContentCannotBeDeleted(): void
    {
        $parentId = $this->createCategory();
        $this->createCategory('', $parentId);

        $this->assertStatus(409, $this->delete('structure/categories/' . $parentId));
    }

    public function testCategoryCannotBeDeletedThroughTheArticleRoute(): void
    {
        $categoryId = $this->createCategory();

        $response = $this->delete('structure/articles/' . $categoryId);

        $this->assertStatus(403, $response);
    }

    public function testStartArticleCannotBeRenamedThroughTheArticleRoute(): void
    {
        $categoryId = $this->createCategory();

        $response = $this->patch('structure/articles/' . $categoryId, ['name' => 'nope']);

        $this->assertStatus(403, $response);
    }

    public function testArticleCanBeCreatedInsideACategory(): void
    {
        $categoryId = $this->createCategory();
        $articleId = $this->createArticle('', $categoryId);

        $fetched = $this->get('structure/articles/' . $articleId);

        $this->assertSuccess($fetched);
        self::assertSame($categoryId, (int) $fetched['data']['category_id']);
    }

    public function testArticleCanBeMovedAndCopied(): void
    {
        $categoryId = $this->createCategory();
        $articleId = $this->createArticle();

        $this->assertSuccess($this->post('structure/articles/' . $articleId . '/move', ['category_id' => $categoryId]));
        self::assertSame($categoryId, $this->get('structure/articles/' . $articleId)['data']['category_id'] ?? null);

        $copy = $this->post('structure/articles/' . $articleId . '/copy', ['category_id' => 0]);
        $this->assertStatus(201, $copy);
        $copyId = (int) $copy['data']['id'];
        $this->trackResource('structure/articles', $copyId);

        self::assertNotSame($articleId, $copyId);
        $copied = $this->get('structure/articles/' . $copyId);
        $this->assertSuccess($copied);
        self::assertArrayHasKey('category_id', $copied['data']);
        self::assertNull($copied['data']['category_id']);
    }

    public function testCategoryCanBeMoved(): void
    {
        $targetId = $this->createCategory();
        $categoryId = $this->createCategory();

        $this->assertSuccess($this->post('structure/categories/' . $categoryId . '/move', ['parent_id' => $targetId]));
        self::assertSame($targetId, $this->get('structure/categories/' . $categoryId)['data']['parent_id'] ?? null);

        // back to the root, so the cleanup can delete the target
        $this->assertSuccess($this->post('structure/categories/' . $categoryId . '/move', ['parent_id' => 0]));
    }

    public function testSliceLifecycle(): void
    {
        $moduleKey = $this->resolveModuleKey();
        $field = $this->resolveTextFieldKey($moduleKey);
        $articleId = $this->createArticle();

        $created = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $moduleKey,
            'content_section_id' => 1,
            'language_id' => (int) self::$config['test_data']['existing_clang_id'],
            'data' => [$field => 'created by the api test suite'],
        ]);

        $this->assertStatus(201, $created);
        self::assertIsArray($created['data']);
        $sliceId = (int) $created['data']['slice_id'];
        self::assertGreaterThan(0, $sliceId);

        // List
        $slices = $this->assertListResponse($this->get('structure/articles/' . $articleId . '/slices'));
        self::assertCount(1, $slices);
        self::assertSame($moduleKey, $slices[0]['module'], 'A slice references its module by key in REDAXO 6');
        self::assertArrayNotHasKey('module_id', $slices[0]);

        // Get
        $slice = $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId);
        $this->assertSuccess($slice);
        self::assertSame('created by the api test suite', $slice['data']['data'][$field] ?? null);

        // Update: only the given fields change
        $this->assertSuccess($this->patch('structure/articles/' . $articleId . '/slices/' . $sliceId, ['data' => [$field => 'updated']]));
        $updated = $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId)['data'];
        self::assertSame('updated', $updated['data'][$field] ?? null);
        self::assertSame(array_keys($slice['data']['data']), array_keys($updated['data']));

        // Status
        $this->assertSuccess($this->patch('structure/articles/' . $articleId . '/slices/' . $sliceId, ['status' => 0]));
        self::assertSame(0, $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId)['data']['status'] ?? null);

        // Update without any content field
        $this->assertStatus(400, $this->patch('structure/articles/' . $articleId . '/slices/' . $sliceId, []));

        // Delete
        $this->assertSuccess($this->delete('structure/articles/' . $articleId . '/slices/' . $sliceId));
        $this->assertStatus(404, $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId));
    }

    public function testSliceFieldsAreValidatedByTheModule(): void
    {
        $moduleKey = $this->resolveModuleKey();
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $moduleKey,
            'data' => ['this_field_does_not_exist' => 'x'],
        ]);

        $this->assertStatus(422, $response);
        self::assertArrayHasKey('this_field_does_not_exist', $response['data']['fields'] ?? []);
    }

    public function testSlicesCanBePlacedAndMoved(): void
    {
        $moduleKey = $this->resolveModuleKey();
        $field = $this->resolveTextFieldKey($moduleKey);
        $articleId = $this->createArticle();
        $add = fn (string $value, ?int $priority = null): int => (int) $this->post('structure/articles/' . $articleId . '/slices', array_filter([
            'module' => $moduleKey,
            'priority' => $priority,
            'data' => [$field => $value],
        ], static fn ($v) => null !== $v))['data']['slice_id'];
        $order = fn (): array => array_map(
            static fn (array $slice): string => (string) $slice['data'][$field],
            $this->assertListResponse($this->get('structure/articles/' . $articleId . '/slices')),
        );

        $a = $add('a');
        $add('b');
        // created within the same second as the others, still placed first
        $add('c', 1);
        self::assertSame(['c', 'a', 'b'], $order());

        $this->assertSuccess($this->post('structure/articles/' . $articleId . '/slices/' . $a . '/move', ['priority' => 3]));
        self::assertSame(['c', 'b', 'a'], $order());

        $this->assertSuccess($this->post('structure/articles/' . $articleId . '/slices/' . $a . '/move', ['direction' => 'up']));
        self::assertSame(['c', 'a', 'b'], $order());

        $this->assertStatus(400, $this->post('structure/articles/' . $articleId . '/slices/' . $a . '/move', []));
    }

    public function testSliceWithUnknownModuleIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => 'this-module-does-not-exist',
            'content_section_id' => 1,
        ]);

        $this->assertStatus(404, $response);
        self::assertSame('Module not found', $response['data']['error'] ?? null);
    }

    public function testSliceWithUnknownContentSectionIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $this->resolveModuleKey(),
            'content_section_id' => 4242,
        ]);

        $this->assertStatus(404, $response);
    }

    public function testSliceWithUnknownLanguageIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $this->resolveModuleKey(),
            'language_id' => 9999,
        ]);

        $this->assertStatus(404, $response);
        self::assertSame('Language not found', $response['data']['error'] ?? null);
    }

    public function testSlicesOfUnknownArticleReturnNotFound(): void
    {
        $this->assertStatus(404, $this->get('structure/articles/999999/slices'));
    }
}
