<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Clangs (Sprachen) API.
 */
class ClangsApiTest extends ApiTestCase
{
    // ==================== CLANG LIST TESTS ====================

    public function testGetClangList(): void
    {
        $response = $this->get('system/clangs');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
        $this->assertNotEmpty($response['data'], 'Mindestens eine Sprache sollte existieren.');
    }

    // ==================== CLANG CRUD TESTS ====================

    public function testGetClang(): void
    {
        $clangId = self::$config['test_data']['existing_clang_id'];
        $response = $this->get('system/clangs/' . $clangId);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'code');
        $this->assertHasField($response, 'name');
    }

    public function testGetClangNotFound(): void
    {
        $response = $this->get('system/clangs/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateClang(): void
    {
        $code = 'test_' . uniqid();
        $name = $this->generateTestName('Sprache');

        $response = $this->post('system/clangs', [
            'code' => $code,
            'name' => $name,
            'priority' => 100,
            'status' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('system/clangs', $response['data']['id']);
    }

    public function testCreateClangValidation(): void
    {
        // Ohne Pflichtfelder
        $response = $this->post('system/clangs', [
            'priority' => 1,
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateClang(): void
    {
        // Erst Sprache erstellen
        $code = 'test_' . uniqid();
        $createResponse = $this->post('system/clangs', [
            'code' => $code,
            'name' => 'Original Name',
            'priority' => 100,
            'status' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $clangId = $createResponse['data']['id'];
        $this->trackResource('system/clangs', $clangId);

        // Dann updaten
        $newName = $this->generateTestName('Updated Lang');
        $updateResponse = $this->put('system/clangs/' . $clangId, [
            'name' => $newName,
            'priority' => 50,
        ]);

        $this->assertSuccess($updateResponse);
        $this->assertHasField($updateResponse, 'message');

        // Änderung verifizieren
        $getResponse = $this->get('system/clangs/' . $clangId);
        $this->assertSuccess($getResponse);
        $this->assertSame($newName, $getResponse['data']['name']);
    }

    public function testUpdateClangNotFound(): void
    {
        $response = $this->put('system/clangs/999999', [
            'name' => 'New Name',
        ]);

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testDeleteClang(): void
    {
        // Erst Sprache erstellen
        $code = 'test_del_' . uniqid();
        $createResponse = $this->post('system/clangs', [
            'code' => $code,
            'name' => 'Sprache zum Löschen',
            'priority' => 100,
            'status' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $clangId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('system/clangs/' . $clangId);

        $this->assertSuccess($deleteResponse);
        $this->assertHasField($deleteResponse, 'message');

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('system/clangs/' . $clangId);
        $this->assertStatus(404, $getResponse);
    }

    public function testDeleteClangNotFound(): void
    {
        $response = $this->delete('system/clangs/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testDeleteDefaultClangFails(): void
    {
        // Die Standard-Sprache (ID 1) sollte nicht löschbar sein
        $response = $this->delete('system/clangs/1');

        // Sollte entweder 403 (Forbidden) oder 409 (Conflict) sein
        $this->assertContains($response['status'], [403, 409, 500]);
    }
}
