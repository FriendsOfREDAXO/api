<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/**
 * `templates/*` — read-only.
 *
 * Like modules, REDAXO 6 templates are PHP classes (`#[AsTemplate]`) identified by a string key. What they
 * additionally expose are their content sections (REDAXO 5's "ctypes") and the modules allowed in them,
 * which is what a client needs before placing slices.
 */
final class TemplatesApiTest extends ApiTestCase
{
    public function testListReturnsEnvelopeAndKeyBasedFields(): void
    {
        $templates = $this->assertListResponse($this->get('templates'));

        if (0 === count($templates)) {
            self::markTestSkipped('No templates registered in this REDAXO installation.');
        }

        foreach (['key', 'name', 'class', 'is_default'] as $field) {
            $this->assertArrayHasKey($field, $templates[0]);
        }

        $this->assertArrayNotHasKey('id', $templates[0], 'REDAXO 6 templates have no numeric id');
        $this->assertArrayNotHasKey('content', $templates[0], 'The template code is not exposed over the API');
    }

    public function testExactlyOneTemplateIsMarkedAsDefault(): void
    {
        $templates = $this->assertListResponse($this->get('templates'));

        if (0 === count($templates)) {
            self::markTestSkipped('No templates registered in this REDAXO installation.');
        }

        $defaults = array_filter($templates, static fn (array $template): bool => (bool) $template['is_default']);

        $this->assertCount(1, $defaults);
    }

    public function testGetByKeyExposesContentSections(): void
    {
        $key = (string) self::$config['test_data']['existing_template_key'];
        $response = $this->get('templates/' . $key);

        $this->assertSuccess($response);
        $this->assertSame($key, $response['data']['key'] ?? null);
        $this->assertArrayHasKey('content_sections', $response['data']);
        $this->assertNotEmpty($response['data']['content_sections'], 'A template always has at least one content section');

        $section = $response['data']['content_sections'][0];

        foreach (['ctype_id', 'name', 'allowed_modules'] as $field) {
            $this->assertArrayHasKey($field, $section);
        }

        $this->assertGreaterThan(0, (int) $section['ctype_id']);
        $this->assertIsArray($section['allowed_modules']);
    }

    public function testAllowedModulesMatchTheSliceEndpoint(): void
    {
        $key = (string) self::$config['test_data']['existing_template_key'];
        $section = $this->get('templates/' . $key)['data']['content_sections'][0];

        if (0 === count($section['allowed_modules'])) {
            self::markTestSkipped('The template allows no modules in its first content section.');
        }

        // Whatever the template reports as allowed must be placeable through the slice endpoint.
        $articleId = $this->createArticle();

        $created = $this->post('structure/articles/' . $articleId . '/slices', [
            'module' => (string) $section['allowed_modules'][0],
            'ctype_id' => (int) $section['ctype_id'],
            'clang_id' => (int) self::$config['test_data']['existing_clang_id'],
            'value1' => 'allowed module check',
        ]);

        $this->assertStatus(201, $created);
    }

    public function testGetUnknownKeyReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('templates/this-template-does-not-exist'));
    }

    public function testFilterByCategory(): void
    {
        $categoryId = (int) self::$config['test_data']['existing_category_id'];
        $filtered = $this->assertListResponse($this->get('templates', ['filter' => ['category_id' => $categoryId]]));

        $all = $this->assertListResponse($this->get('templates'));

        $this->assertLessThanOrEqual(count($all), count($filtered), 'Filtering by category can only narrow the list');
    }

    public function testFilterByUnknownCategoryReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('templates', ['filter' => ['category_id' => 999999]]));
    }

    public function testWriteEndpointsDoNotExist(): void
    {
        $key = (string) self::$config['test_data']['existing_template_key'];

        $this->assertStatus(405, $this->post('templates', ['key' => 'x', 'name' => 'x']));
        $this->assertStatus(405, $this->patch('templates/' . $key, ['name' => 'x']));
        $this->assertStatus(405, $this->delete('templates/' . $key));
    }
}
