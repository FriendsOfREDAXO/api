<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Structure API (Artikel und Kategorien).
 */
class StructureApiTest extends ApiTestCase
{
    // ==================== ARTICLE TESTS ====================

    public function testGetArticleList(): void
    {
        $response = $this->get('structure/articles');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetArticleListWithFilter(): void
    {
        $response = $this->get('structure/articles', [
            'filter[clang_id]' => self::$config['test_data']['existing_clang_id'],
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetArticleListWithPagination(): void
    {
        $response = $this->get('structure/articles', [
            'page' => 1,
            'per_page' => 5,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
        $this->assertLessThanOrEqual(5, count($response['data']));
    }

    public function testGetArticle(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $response = $this->get('structure/articles/' . $articleId);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'name');
        $this->assertSame($articleId, $response['data']['id']);
    }

    public function testGetArticleNotFound(): void
    {
        $response = $this->get('structure/articles/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateArticle(): void
    {
        $name = $this->generateTestName('article');
        $categoryId = self::$config['test_data']['existing_category_id'];

        $response = $this->post('structure/articles', [
            'name' => $name,
            'category_id' => $categoryId,
            'priority' => 1,
            'status' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'message');

        // Für Aufräumen registrieren
        $this->trackResource('structure/articles', $response['data']['id']);
    }

    public function testCreateArticleValidation(): void
    {
        // Ohne Pflichtfeld 'name'
        $response = $this->post('structure/articles', [
            'category_id' => 0,
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateArticle(): void
    {
        // Erst Artikel erstellen
        $name = $this->generateTestName('article');
        $createResponse = $this->post('structure/articles', [
            'name' => $name,
            'category_id' => 0,
            'priority' => 1,
        ]);

        $this->assertStatus(201, $createResponse);
        $articleId = $createResponse['data']['id'];
        $this->trackResource('structure/articles', $articleId);

        // Dann updaten
        $newName = $this->generateTestName('article_updated');
        $updateResponse = $this->put('structure/articles/' . $articleId, [
            'name' => $newName,
            'priority' => 2,
        ]);

        $this->assertSuccess($updateResponse);
        $this->assertHasField($updateResponse, 'message');
    }

    public function testDeleteArticle(): void
    {
        // Erst Artikel erstellen
        $name = $this->generateTestName('article_delete');
        $createResponse = $this->post('structure/articles', [
            'name' => $name,
            'category_id' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $articleId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('structure/articles/' . $articleId);

        $this->assertSuccess($deleteResponse);
        $this->assertHasField($deleteResponse, 'message');

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('structure/articles/' . $articleId);
        $this->assertStatus(404, $getResponse);
    }

    // ==================== CATEGORY TESTS ====================

    public function testCreateCategory(): void
    {
        $name = $this->generateTestName('category');

        $response = $this->post('structure/categories', [
            'name' => $name,
            'category_id' => 0,
            'priority' => 1,
            'status' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('structure/categories', $response['data']['id']);
    }

    public function testUpdateCategory(): void
    {
        // Erst Kategorie erstellen
        $name = $this->generateTestName('category');
        $createResponse = $this->post('structure/categories', [
            'name' => $name,
            'category_id' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $categoryId = $createResponse['data']['id'];
        $this->trackResource('structure/categories', $categoryId);

        // Dann updaten
        $newName = $this->generateTestName('category_updated');
        $updateResponse = $this->put('structure/categories/' . $categoryId, [
            'name' => $newName,
        ]);

        $this->assertSuccess($updateResponse);
    }

    public function testDeleteCategory(): void
    {
        // Erst Kategorie erstellen
        $name = $this->generateTestName('category_delete');
        $createResponse = $this->post('structure/categories', [
            'name' => $name,
            'category_id' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $categoryId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('structure/categories/' . $categoryId);

        $this->assertSuccess($deleteResponse);
    }

    // ==================== SLICES TESTS ====================

    public function testGetArticleSlices(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $response = $this->get('structure/articles/' . $articleId . '/slices');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetArticleSlicesWithFilter(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $clangId = self::$config['test_data']['existing_clang_id'];

        $response = $this->get('structure/articles/' . $articleId . '/slices', [
            'clang_id' => $clangId,
            'revision' => 0,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testCreateArticleSlice(): void
    {
        $articleId = self::$config['test_data']['existing_article_id'];
        $moduleId = self::$config['test_data']['existing_module_id'];
        $clangId = self::$config['test_data']['existing_clang_id'];

        $response = $this->post('structure/articles/' . $articleId . '/slices', [
            'module_id' => $moduleId,
            'clang_id' => $clangId,
            'ctype_id' => 1,
            'value1' => 'Test Value 1',
        ]);

        // Kann 201 (erstellt) oder 404 (Template/Modul-Zuordnung fehlt) sein
        if (201 === $response['status']) {
            $this->assertHasField($response, 'slice_id');
        } else {
            // Template hat möglicherweise das Modul nicht zugeordnet
            $this->assertContains($response['status'], [400, 404]);
        }
    }
}
