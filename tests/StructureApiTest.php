<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/** `structure/*` — articles, categories and slices. */
final class StructureApiTest extends ApiTestCase
{
    public function testArticleListReturnsEnvelopeAndFields(): void
    {
        $articles = $this->assertListResponse($this->get('structure/articles'));

        $this->assertNotEmpty($articles);

        // `template` holds a template *key* in REDAXO 6; there is no template_id anymore.
        foreach (['id', 'name', 'clang_id', 'parent_id', 'priority', 'startarticle', 'status', 'template'] as $field) {
            $this->assertArrayHasKey($field, $articles[0]);
        }

        $this->assertArrayNotHasKey('template_id', $articles[0]);
    }

    public function testArticleListCanBeFilteredToCategoriesOnly(): void
    {
        $categories = $this->assertListResponse($this->get('structure/articles', ['filter' => ['is_category' => 1]]));

        foreach ($categories as $category) {
            $this->assertSame(1, (int) $category['startarticle']);
        }
    }

    public function testArticleListPaginationMetaIsConsistent(): void
    {
        $response = $this->get('structure/articles', ['per_page' => 1, 'page' => 1]);
        $rows = $this->assertListResponse($response);

        $this->assertLessThanOrEqual(1, count($rows));
        $this->assertSame(1, $response['data']['meta']['per_page']);
        $this->assertSame(1, $response['data']['meta']['page']);
    }

    public function testGetArticleReturnsSameShapeAsList(): void
    {
        $articleId = (int) self::$config['test_data']['existing_article_id'];
        $response = $this->get('structure/articles/' . $articleId);

        $this->assertSuccess($response);
        $this->assertSame($articleId, $response['data']['id'] ?? null);
        $this->assertArrayHasKey('template', $response['data']);
    }

    public function testGetUnknownArticleReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('structure/articles/999999'));
    }

    public function testCreateUpdateAndDeleteArticle(): void
    {
        $name = $this->generateTestName('article');
        $articleId = $this->createArticle($name);

        $fetched = $this->get('structure/articles/' . $articleId);
        $this->assertSuccess($fetched);
        $this->assertSame($name, $fetched['data']['name'] ?? null);
        $this->assertSame(0, (int) $fetched['data']['startarticle']);

        $renamed = $name . '_updated';
        $this->assertSuccess($this->patch('structure/articles/' . $articleId, ['name' => $renamed, 'status' => 1]));

        $updated = $this->get('structure/articles/' . $articleId);
        $this->assertSame($renamed, $updated['data']['name'] ?? null);
        $this->assertSame(1, (int) $updated['data']['status']);
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

        // A category is a start article, so it shows up under structure/articles as well.
        $fetched = $this->get('structure/articles/' . $categoryId);
        $this->assertSuccess($fetched);
        $this->assertSame(1, (int) $fetched['data']['startarticle']);
        $this->assertSame($name, $fetched['data']['catname'] ?? null);

        $renamed = $name . '_updated';
        $this->assertSuccess($this->patch('structure/categories/' . $categoryId, ['name' => $renamed]));
        $this->assertSame($renamed, $this->get('structure/articles/' . $categoryId)['data']['catname'] ?? null);
    }

    public function testCategoryCannotBeDeletedThroughTheArticleRoute(): void
    {
        $categoryId = $this->createCategory();

        $response = $this->delete('structure/articles/' . $categoryId);

        $this->assertStatus(403, $response);
    }

    public function testStartArticleCannotBeUpdatedThroughTheArticleRoute(): void
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
        $this->assertSame($categoryId, (int) $fetched['data']['parent_id']);
    }

    public function testSliceLifecycle(): void
    {
        $moduleKey = $this->resolveModuleKey();
        $articleId = $this->createArticle();
        $clangId = (int) self::$config['test_data']['existing_clang_id'];

        $created = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $moduleKey,
            'ctype_id' => 1,
            'clang_id' => $clangId,
            'value1' => 'created by the api test suite',
        ]);

        $this->assertStatus(201, $created);
        $this->assertIsArray($created['data']);
        $sliceId = (int) $created['data']['slice_id'];
        $this->assertGreaterThan(0, $sliceId);

        // List
        $slices = $this->assertListResponse($this->get('structure/articles/' . $articleId . '/slices'));
        $this->assertCount(1, $slices);
        $this->assertSame($moduleKey, $slices[0]['module'], 'A slice references its module by key in REDAXO 6');
        $this->assertArrayNotHasKey('module_id', $slices[0]);

        // Get
        $slice = $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId);
        $this->assertSuccess($slice);
        $this->assertSame('created by the api test suite', $slice['data']['value1'] ?? null);

        // Update
        $this->assertSuccess($this->patch('structure/articles/' . $articleId . '/slices/' . $sliceId, ['value1' => 'updated']));
        $this->assertSame('updated', $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId)['data']['value1'] ?? null);

        // Update without any content field
        $this->assertStatus(400, $this->patch('structure/articles/' . $articleId . '/slices/' . $sliceId, []));

        // Delete
        $this->assertSuccess($this->delete('structure/articles/' . $articleId . '/slices/' . $sliceId));
        $this->assertStatus(404, $this->get('structure/articles/' . $articleId . '/slices/' . $sliceId));
    }

    public function testSliceWithUnknownModuleIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => 'this-module-does-not-exist',
            'ctype_id' => 1,
            'clang_id' => (int) self::$config['test_data']['existing_clang_id'],
        ]);

        $this->assertStatus(404, $response);
        $this->assertSame('Module not found', $response['data']['error'] ?? null);
    }

    public function testSliceWithUnknownContentSectionIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $this->resolveModuleKey(),
            'ctype_id' => 4242,
            'clang_id' => (int) self::$config['test_data']['existing_clang_id'],
        ]);

        $this->assertStatus(404, $response);
    }

    public function testSliceWithUnknownLanguageIsRejected(): void
    {
        $articleId = $this->createArticle();

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => $this->resolveModuleKey(),
            'ctype_id' => 1,
            'clang_id' => 9999,
        ]);

        $this->assertStatus(404, $response);
        $this->assertSame('Clang not found', $response['data']['error'] ?? null);
    }

    public function testSlicesOfUnknownArticleReturnNotFound(): void
    {
        $this->assertStatus(404, $this->get('structure/articles/999999/slices'));
    }
}
