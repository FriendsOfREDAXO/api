<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

/**
 * Tests für die Users API.
 */
class UsersApiTest extends ApiTestCase
{
    // ==================== USER LIST TESTS ====================

    public function testGetUserList(): void
    {
        $response = $this->get('users');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetUserListWithFilter(): void
    {
        $response = $this->get('users', [
            'filter[status]' => 1,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);

        // Alle Ergebnisse sollten status = 1 haben
        foreach ($response['data'] as $user) {
            $this->assertEquals(1, $user['status']);
        }
    }

    public function testGetUserListFilterByAdmin(): void
    {
        $response = $this->get('users', [
            'filter[admin]' => 1,
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);

        // Alle Ergebnisse sollten Admins sein
        foreach ($response['data'] as $user) {
            $this->assertEquals(1, $user['admin']);
        }
    }

    // ==================== USER CRUD TESTS ====================

    public function testGetUser(): void
    {
        // Erst Liste abrufen
        $listResponse = $this->get('users');

        if (empty($listResponse['data'])) {
            $this->markTestSkipped('Keine User in der Datenbank vorhanden.');
        }

        $userId = $listResponse['data'][0]['id'];
        $response = $this->get('users/' . $userId);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'login');
        $this->assertHasField($response, 'name');
    }

    public function testGetUserNotFound(): void
    {
        $response = $this->get('users/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateUser(): void
    {
        $login = strtolower($this->generateTestName('user'));
        $name = $this->generateTestName('User Name');

        $response = $this->post('users', [
            'login' => $login,
            'name' => $name,
            'password' => 'TestPassword123!',
            'email' => $login . '@example.com',
            'status' => 1,
            'admin' => 0,
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');

        $this->trackResource('users', $response['data']['id']);
    }

    public function testCreateUserDuplicateLogin(): void
    {
        $login = strtolower($this->generateTestName('user'));

        // Ersten User erstellen
        $response1 = $this->post('users', [
            'login' => $login,
            'name' => 'Test User 1',
            'password' => 'TestPassword123!',
        ]);

        $this->assertStatus(201, $response1);
        $this->trackResource('users', $response1['data']['id']);

        // Zweiten User mit gleichem Login erstellen (sollte fehlschlagen)
        $response2 = $this->post('users', [
            'login' => $login,
            'name' => 'Test User 2',
            'password' => 'TestPassword123!',
        ]);

        $this->assertStatus(409, $response2);
        $this->assertError($response2);
    }

    public function testCreateUserValidation(): void
    {
        // Ohne Pflichtfelder
        $response = $this->post('users', [
            'name' => 'Test User',
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateUser(): void
    {
        // Erst User erstellen
        $login = strtolower($this->generateTestName('user'));
        $createResponse = $this->post('users', [
            'login' => $login,
            'name' => 'Original Name',
            'password' => 'TestPassword123!',
        ]);

        $this->assertStatus(201, $createResponse);
        $userId = $createResponse['data']['id'];
        $this->trackResource('users', $userId);

        // Dann updaten
        $newName = $this->generateTestName('Updated Name');
        $updateResponse = $this->put('users/' . $userId, [
            'name' => $newName,
            'status' => 0,
        ]);

        $this->assertSuccess($updateResponse);
        $this->assertHasField($updateResponse, 'message');
    }

    public function testDeleteUser(): void
    {
        // Erst User erstellen
        $login = strtolower($this->generateTestName('user_delete'));
        $createResponse = $this->post('users', [
            'login' => $login,
            'name' => 'User To Delete',
            'password' => 'TestPassword123!',
            'admin' => 0,
        ]);

        $this->assertStatus(201, $createResponse);
        $userId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('users/' . $userId);

        $this->assertSuccess($deleteResponse);
        $this->assertHasField($deleteResponse, 'message');

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('users/' . $userId);
        $this->assertStatus(404, $getResponse);
    }

    public function testDeleteUserNotFound(): void
    {
        $response = $this->delete('users/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    // ==================== USER ROLES TESTS ====================

    public function testGetRolesList(): void
    {
        $response = $this->get('users/roles');

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetRolesListWithFilter(): void
    {
        $response = $this->get('users/roles', [
            'filter[name]' => 'Admin',
        ]);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
    }

    public function testGetRole(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Test Role Description',
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];
        $this->trackResource('users/roles', $roleId);

        // Dann abrufen
        $response = $this->get('users/roles/' . $roleId);

        $this->assertSuccess($response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'name');
        $this->assertHasField($response, 'description');
        $this->assertEquals($name, $response['data']['name']);
    }

    public function testGetRoleNotFound(): void
    {
        $response = $this->get('users/roles/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testCreateRole(): void
    {
        $name = $this->generateTestName('role');

        $response = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Test Role Description',
            'perms' => [
                'general' => '|structure|mediapool|',
            ],
        ]);

        $this->assertStatus(201, $response);
        $this->assertHasField($response, 'id');
        $this->assertHasField($response, 'message');

        $this->trackResource('users/roles', $response['data']['id']);
    }

    public function testCreateRoleDuplicateName(): void
    {
        $name = $this->generateTestName('role');

        // Erste Rolle erstellen
        $response1 = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Test Role 1',
        ]);

        $this->assertStatus(201, $response1);
        $this->trackResource('users/roles', $response1['data']['id']);

        // Zweite Rolle mit gleichem Namen erstellen (sollte fehlschlagen)
        $response2 = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Test Role 2',
        ]);

        $this->assertStatus(409, $response2);
        $this->assertError($response2);
    }

    public function testCreateRoleValidation(): void
    {
        // Ohne Pflichtfeld 'name'
        $response = $this->post('users/roles', [
            'description' => 'Test Role without name',
        ]);

        $this->assertStatus(400, $response);
        $this->assertError($response);
    }

    public function testUpdateRole(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Original Description',
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];
        $this->trackResource('users/roles', $roleId);

        // Dann updaten
        $newName = $this->generateTestName('role_updated');
        $updateResponse = $this->put('users/roles/' . $roleId, [
            'name' => $newName,
            'description' => 'Updated Description',
        ]);

        $this->assertSuccess($updateResponse);
        $this->assertHasField($updateResponse, 'message');

        // Prüfen ob Update erfolgreich war
        $getResponse = $this->get('users/roles/' . $roleId);
        $this->assertSuccess($getResponse);
        $this->assertEquals($newName, $getResponse['data']['name']);
        $this->assertEquals('Updated Description', $getResponse['data']['description']);
    }

    public function testUpdateRoleNotFound(): void
    {
        $response = $this->put('users/roles/999999', [
            'name' => 'Updated Name',
        ]);

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testUpdateRoleWithPerms(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Role with Perms',
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];
        $this->trackResource('users/roles', $roleId);

        // Mit Berechtigungen updaten
        $updateResponse = $this->put('users/roles/' . $roleId, [
            'perms' => [
                'general' => '|structure|mediapool|',
                'options' => '|advancedMode[]|',
            ],
        ]);

        $this->assertSuccess($updateResponse);

        // Prüfen ob Berechtigungen gesetzt wurden
        $getResponse = $this->get('users/roles/' . $roleId);
        $this->assertSuccess($getResponse);
        $this->assertArrayHasKey('perms', $getResponse['data']);
        $this->assertIsArray($getResponse['data']['perms']);
    }

    public function testDeleteRole(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role_delete');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Role To Delete',
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];

        // Dann löschen
        $deleteResponse = $this->delete('users/roles/' . $roleId);

        $this->assertSuccess($deleteResponse);
        $this->assertHasField($deleteResponse, 'message');

        // Prüfen ob wirklich gelöscht
        $getResponse = $this->get('users/roles/' . $roleId);
        $this->assertStatus(404, $getResponse);
    }

    public function testDeleteRoleNotFound(): void
    {
        $response = $this->delete('users/roles/999999');

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }

    public function testDuplicateRole(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Original Role',
            'perms' => [
                'general' => '|structure|',
            ],
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];
        $this->trackResource('users/roles', $roleId);

        // Dann duplizieren
        $duplicateResponse = $this->post('users/roles/' . $roleId . '/duplicate', [
            'name' => $name . '_copy',
        ]);

        $this->assertStatus(201, $duplicateResponse);
        $this->assertHasField($duplicateResponse, 'id');
        $this->trackResource('users/roles', $duplicateResponse['data']['id']);

        // Prüfen ob Duplikat existiert
        $getResponse = $this->get('users/roles/' . $duplicateResponse['data']['id']);
        $this->assertSuccess($getResponse);
        $this->assertEquals($name . '_copy', $getResponse['data']['name']);
    }

    public function testDuplicateRoleWithoutName(): void
    {
        // Erst Rolle erstellen
        $name = $this->generateTestName('role');
        $createResponse = $this->post('users/roles', [
            'name' => $name,
            'description' => 'Original Role',
        ]);

        $this->assertStatus(201, $createResponse);
        $roleId = $createResponse['data']['id'];
        $this->trackResource('users/roles', $roleId);

        // Duplizieren ohne Namen (automatischer Name)
        $duplicateResponse = $this->post('users/roles/' . $roleId . '/duplicate', []);

        $this->assertStatus(201, $duplicateResponse);
        $this->assertHasField($duplicateResponse, 'id');
        $this->trackResource('users/roles', $duplicateResponse['data']['id']);
    }

    public function testDuplicateRoleNotFound(): void
    {
        $response = $this->post('users/roles/999999/duplicate', []);

        $this->assertStatus(404, $response);
        $this->assertError($response);
    }
}
