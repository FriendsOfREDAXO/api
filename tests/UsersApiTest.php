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
}
