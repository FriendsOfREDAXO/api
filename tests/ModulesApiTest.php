<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Modules API.
 */
class ModulesApiTest extends ApiTestCase
{
    // ==================== MODULE LIST TESTS ====================

    public function testGetModuleList(): void
    {
        $response = $this->get('modules');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    // ==================== MODULE CRUD TESTS ====================

    public function testGetModule(): void
    {
        $moduleId = self::$config['test_data']['existing_module_id'];
        $response = $this->get('module/' . $moduleId);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'name');
    }

    public function testGetModuleNotFound(): void
    {
        $response = $this->get('module/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateModule(): void
    {
        $name = $this->generateTestName('module');

        $response = $this->post('modules', [
            'name' => $name,
            'input' => '<p>Test Input</p>',
            'output' => '<p>Test Output</p>',
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('module', $response['data']['id']);
    }

    public function testCreateModuleValidation(): void
    {
        // Ohne Pflichtfeld 'name'
        $response = $this->post('modules', [
            'input' => '<p>Test</p>',
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateModule(): void
    {
        // Erst Modul erstellen
        $name = $this->generateTestName('module');
        $createResponse = $this->post('modules', [
            'name' => $name,
            'input' => '<p>Original Input</p>',
            'output' => '<p>Original Output</p>',
        ]);

        $this->assertStatus(201, $createResponse);
        $moduleId = $createResponse['data']['id'];
        $this->trackResource('module', $moduleId);

        // Dann updaten
        $newName = $this->generateTestName('module_updated');
        $updateResponse = $this->put('module/' . $moduleId, [
            'name' => $newName,
            'input' => '<p>Updated Input</p>',
        ]);

        $this->assertSuccess($updateResponse);
    }

    public function testDeleteModule(): void
    {
        // Erst Modul erstellen
        $name = $this->generateTestName('module_delete');
        $createResponse = $this->post('modules', [
            'name' => $name,
            'input' => '<p>Delete Test</p>',
            'output' => '<p>Delete Test</p>',
        ]);

        $this->assertStatus(201, $createResponse);
        $moduleId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('module/' . $moduleId);

        $this->assertSuccess($deleteResponse);

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('module/' . $moduleId);
        $this->assertStatus(404, $getResponse);
    }
}
