<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Tests;

use function count;

/** `users/*` — backend users and user roles. */
final class UsersApiTest extends ApiTestCase
{
    /** @var list<int> */
    private array $createdUsers = [];

    /** @var list<int> */
    private array $createdRoles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $userId) {
            $this->delete('users/' . $userId);
        }
        $this->createdUsers = [];

        foreach ($this->createdRoles as $roleId) {
            $this->delete('users/roles/' . $roleId);
        }
        $this->createdRoles = [];

        parent::tearDown();
    }

    public function testUserListReturnsEnvelopeAndNeverLeaksPasswords(): void
    {
        $users = $this->assertListResponse($this->get('users'));

        $this->assertNotEmpty($users, 'A REDAXO installation always has at least one user');

        foreach (['id', 'login', 'name', 'email', 'status', 'admin'] as $field) {
            $this->assertArrayHasKey($field, $users[0]);
        }

        foreach (['password', 'previous_passwords', 'session_id'] as $secret) {
            $this->assertArrayNotHasKey($secret, $users[0], 'The user list must not expose ' . $secret);
        }
    }

    public function testGetUnknownUserReturnsNotFound(): void
    {
        $this->assertStatus(404, $this->get('users/999999'));
    }

    public function testUserLifecycle(): void
    {
        $login = strtolower($this->generateTestName('user'));

        $created = $this->post('users', [
            'name' => 'API Test User',
            'login' => $login,
            'password' => 'V3ry-Str0ng-Passw0rd!',
            'email' => 'apitest@example.com',
            'status' => 1,
        ]);

        $this->assertStatus(201, $created);
        $this->assertIsArray($created['data']);
        $userId = (int) $created['data']['id'];
        $this->createdUsers[] = $userId;

        $fetched = $this->get('users/' . $userId);
        $this->assertSuccess($fetched);
        $this->assertSame($login, $fetched['data']['login'] ?? null);
        $this->assertSame(1, (int) $fetched['data']['status']);
        $this->assertArrayNotHasKey('password', $fetched['data']);

        $this->assertSuccess($this->patch('users/' . $userId, ['name' => 'API Test User Renamed', 'status' => 0]));

        $updated = $this->get('users/' . $userId);
        $this->assertSame('API Test User Renamed', $updated['data']['name'] ?? null);
        $this->assertSame(0, (int) $updated['data']['status']);

        $this->assertSuccess($this->delete('users/' . $userId));
        $this->createdUsers = array_values(array_diff($this->createdUsers, [$userId]));

        $this->assertStatus(404, $this->get('users/' . $userId));
    }

    public function testDuplicateLoginIsRejected(): void
    {
        $existing = $this->assertListResponse($this->get('users'))[0];

        $response = $this->post('users', [
            'name' => 'Duplicate',
            'login' => (string) $existing['login'],
            'password' => 'V3ry-Str0ng-Passw0rd!',
        ]);

        $this->assertStatus(409, $response);
    }

    public function testWeakPasswordIsRejected(): void
    {
        $response = $this->post('users', [
            'name' => 'Weak',
            'login' => strtolower($this->generateTestName('weak')),
            'password' => 'a',
        ]);

        $this->assertStatus(400, $response, 'The backend password policy should reject a one-character password');
    }

    public function testMissingRequiredFieldIsRejected(): void
    {
        $this->assertStatus(400, $this->post('users', ['name' => 'No login or password']));
    }

    public function testLastAdminCannotBeDeleted(): void
    {
        $admins = array_values(array_filter(
            $this->assertListResponse($this->get('users')),
            static fn (array $user): bool => 1 === (int) $user['admin'] && 1 === (int) $user['status'],
        ));

        if (1 !== count($admins)) {
            self::markTestSkipped('This installation has more than one active admin.');
        }

        $this->assertStatus(409, $this->delete('users/' . $admins[0]['id']));
    }

    public function testRoleLifecycle(): void
    {
        $name = $this->generateTestName('role');

        $created = $this->post('users/roles', [
            'name' => $name,
            'description' => 'created by the api test suite',
            'perms' => ['general' => ['api[]']],
        ]);

        $this->assertStatus(201, $created);
        $roleId = (int) $created['data']['id'];
        $this->createdRoles[] = $roleId;

        $fetched = $this->get('users/roles/' . $roleId);
        $this->assertSuccess($fetched);
        $this->assertSame($name, $fetched['data']['name'] ?? null);
        $this->assertSame(['general' => ['api[]']], $fetched['data']['perms'] ?? null, 'perms round-trip as a decoded structure');

        $this->assertSuccess($this->patch('users/roles/' . $roleId, ['description' => 'updated']));
        $this->assertSame('updated', $this->get('users/roles/' . $roleId)['data']['description'] ?? null);

        $this->assertSuccess($this->delete('users/roles/' . $roleId));
        $this->createdRoles = array_values(array_diff($this->createdRoles, [$roleId]));

        $this->assertStatus(404, $this->get('users/roles/' . $roleId));
    }

    public function testDuplicateRoleNameIsRejected(): void
    {
        $name = $this->generateTestName('dupe_role');

        $created = $this->post('users/roles', ['name' => $name]);
        $this->assertStatus(201, $created);
        $this->createdRoles[] = (int) $created['data']['id'];

        $this->assertStatus(409, $this->post('users/roles', ['name' => $name]));
    }

    public function testRoleDuplication(): void
    {
        $created = $this->post('users/roles', [
            'name' => $this->generateTestName('source_role'),
            'perms' => ['general' => ['api[]']],
        ]);
        $this->assertStatus(201, $created);
        $sourceId = (int) $created['data']['id'];
        $this->createdRoles[] = $sourceId;

        $duplicate = $this->post('users/roles/' . $sourceId . '/duplicate', []);
        $this->assertStatus(201, $duplicate);
        $duplicateId = (int) $duplicate['data']['id'];
        $this->createdRoles[] = $duplicateId;

        $this->assertNotSame($sourceId, $duplicateId);
        $this->assertSame(['general' => ['api[]']], $this->get('users/roles/' . $duplicateId)['data']['perms'] ?? null);
    }

    public function testRoleAssignment(): void
    {
        $role = $this->post('users/roles', ['name' => $this->generateTestName('assign_role')]);
        $this->assertStatus(201, $role);
        $roleId = (int) $role['data']['id'];
        $this->createdRoles[] = $roleId;

        $user = $this->post('users', [
            'name' => 'API Role Test',
            'login' => strtolower($this->generateTestName('roleuser')),
            'password' => 'V3ry-Str0ng-Passw0rd!',
        ]);
        $this->assertStatus(201, $user);
        $userId = (int) $user['data']['id'];
        $this->createdUsers[] = $userId;

        $this->assertSame([], $this->get('users/' . $userId . '/role')['data']['data'] ?? null);

        $this->assertSuccess($this->post('users/' . $userId . '/role/' . $roleId, []));

        $assigned = $this->get('users/' . $userId . '/role');
        $this->assertSuccess($assigned);
        $this->assertSame([$roleId], array_column($assigned['data']['data'], 'id'));

        // Assigning twice is a conflict
        $this->assertStatus(409, $this->post('users/' . $userId . '/role/' . $roleId, []));

        // A role in use cannot be deleted
        $this->assertStatus(409, $this->delete('users/roles/' . $roleId));

        $this->assertSuccess($this->delete('users/' . $userId . '/role/' . $roleId));
        $this->assertSame([], $this->get('users/' . $userId . '/role')['data']['data'] ?? null);

        // Removing it again reports that it is not assigned
        $this->assertStatus(404, $this->delete('users/' . $userId . '/role/' . $roleId));
    }

    public function testAssigningUnknownRoleReturnsNotFound(): void
    {
        $users = $this->assertListResponse($this->get('users'));

        $this->assertStatus(404, $this->post('users/' . $users[0]['id'] . '/role/999999', []));
    }

    public function testRolesOfUnknownUserReturnNotFound(): void
    {
        $this->assertStatus(404, $this->get('users/999999/role'));
    }
}
