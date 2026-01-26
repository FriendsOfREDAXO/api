<?php

declare(strict_types=1);

/**
 * Service class for user role operations.
 *
 * @package redaxo\api
 */
final class rex_user_role_service
{
    /**
     * Creates a new user role.
     *
     * @param array{name: string, description?: string, perms?: array<string, mixed>} $data
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function addRole(array $data): array
    {
        if (empty($data['name'])) {
            throw new rex_api_exception(rex_i18n::msg('user_role_name_required'));
        }

        // Check if role name already exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE name = ?', [$data['name']]);

        if ($checkSql->getRows() > 0) {
            throw new rex_api_exception(rex_i18n::msg('user_role_name_exists'));
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('user_role'));
        $sql->setValue('name', $data['name']);
        $sql->setValue('description', $data['description'] ?? '');

        // Handle permissions
        if (isset($data['perms']) && is_array($data['perms'])) {
            $sql->setValue('perms', json_encode($data['perms']));
        } else {
            $sql->setValue('perms', json_encode([]));
        }

        $sql->addGlobalCreateFields(self::getUser());
        $sql->addGlobalUpdateFields(self::getUser());

        $sql->insert();
        $roleId = (int) $sql->getLastId();

        rex_extension::registerPoint(new rex_extension_point('USER_ROLE_ADDED', '', [
            'id' => $roleId,
            'name' => $data['name'],
            'data' => $data,
        ]));

        return [
            'id' => $roleId,
            'message' => rex_i18n::msg('user_role_added'),
        ];
    }

    /**
     * Updates an existing user role.
     *
     * @param int $roleId
     * @param array{name?: string, description?: string, perms?: array<string, mixed>} $data
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function updateRole(int $roleId, array $data): array
    {
        // Check if role exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE id = ?', [$roleId]);

        if (0 === $checkSql->getRows()) {
            throw new rex_api_exception(rex_i18n::msg('user_role_not_found'));
        }

        // Check if new name already exists for another role
        if (!empty($data['name'])) {
            $nameSql = rex_sql::factory();
            $nameSql->setQuery(
                'SELECT id FROM ' . rex::getTable('user_role') . ' WHERE name = ? AND id != ?',
                [$data['name'], $roleId]
            );

            if ($nameSql->getRows() > 0) {
                throw new rex_api_exception(rex_i18n::msg('user_role_name_exists'));
            }
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('user_role'));
        $sql->setWhere(['id' => $roleId]);

        if (isset($data['name'])) {
            $sql->setValue('name', $data['name']);
        }

        if (isset($data['description'])) {
            $sql->setValue('description', $data['description']);
        }

        if (isset($data['perms']) && is_array($data['perms'])) {
            $sql->setValue('perms', json_encode($data['perms']));
        }

        $sql->addGlobalUpdateFields(self::getUser());
        $sql->update();

        rex_extension::registerPoint(new rex_extension_point('USER_ROLE_UPDATED', '', [
            'id' => $roleId,
            'data' => $data,
        ]));

        return [
            'id' => $roleId,
            'message' => rex_i18n::msg('user_role_updated'),
        ];
    }

    /**
     * Deletes a user role.
     *
     * @param int $roleId
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function deleteRole(int $roleId): array
    {
        // Check if role exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id, name FROM ' . rex::getTable('user_role') . ' WHERE id = ?', [$roleId]);

        if (0 === $checkSql->getRows()) {
            throw new rex_api_exception(rex_i18n::msg('user_role_not_found'));
        }

        $roleName = $checkSql->getValue('name');

        // Check if role is in use
        $usageSql = rex_sql::factory();
        $usageSql->setQuery(
            'SELECT COUNT(*) as count FROM ' . rex::getTable('user') . ' WHERE FIND_IN_SET(?, role)',
            [$roleId]
        );

        if ((int) $usageSql->getValue('count') > 0) {
            throw new rex_api_exception(rex_i18n::msg('user_role_in_use'));
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('user_role') . ' WHERE id = ? LIMIT 1', [$roleId]);

        rex_extension::registerPoint(new rex_extension_point('USER_ROLE_DELETED', '', [
            'id' => $roleId,
            'name' => $roleName,
        ]));

        return [
            'id' => $roleId,
            'message' => rex_i18n::msg('user_role_deleted'),
        ];
    }

    /**
     * Gets a list of user roles.
     *
     * @param array{name?: string} $filter
     * @param string $orderBy
     * @param string $orderDirection
     * @return list<array<string, mixed>>
     */
    public static function getList(array $filter = [], string $orderBy = 'name', string $orderDirection = 'ASC'): array
    {
        $allowedFields = ['id', 'name', 'description', 'perms', 'createdate', 'createuser', 'updatedate', 'updateuser'];

        $sqlWhere = [];
        $sqlParams = [];

        if (!empty($filter['name'])) {
            $sqlWhere[] = 'name LIKE :name';
            $sqlParams[':name'] = '%' . $filter['name'] . '%';
        }

        $orderBy = in_array($orderBy, $allowedFields, true) ? $orderBy : 'name';
        $orderDirection = 'DESC' === strtoupper($orderDirection) ? 'DESC' : 'ASC';

        $sql = rex_sql::factory();
        $roles = $sql->getArray(
            'SELECT ' . implode(', ', $allowedFields) . ' FROM ' . rex::getTable('user_role') .
            (count($sqlWhere) > 0 ? ' WHERE ' . implode(' AND ', $sqlWhere) : '') .
            ' ORDER BY ' . $orderBy . ' ' . $orderDirection,
            $sqlParams
        );

        // Decode perms JSON for each role
        foreach ($roles as &$role) {
            if (!empty($role['perms'])) {
                $role['perms'] = json_decode($role['perms'], true);
            }
        }

        return $roles;
    }

    /**
     * Gets a single user role by ID.
     *
     * @param int $roleId
     * @throws rex_api_exception
     * @return array<string, mixed>
     */
    public static function getRole(int $roleId): array
    {
        $allowedFields = ['id', 'name', 'description', 'perms', 'createdate', 'createuser', 'updatedate', 'updateuser'];

        $sql = rex_sql::factory();
        $roles = $sql->getArray(
            'SELECT ' . implode(', ', $allowedFields) . ' FROM ' . rex::getTable('user_role') . ' WHERE id = ?',
            [$roleId]
        );

        if (empty($roles)) {
            throw new rex_api_exception(rex_i18n::msg('user_role_not_found'));
        }

        $role = $roles[0];

        // Decode perms JSON
        if (!empty($role['perms'])) {
            $role['perms'] = json_decode($role['perms'], true);
        }

        return $role;
    }

    /**
     * Duplicates a user role.
     *
     * @param int $roleId
     * @param string|null $newName Optional new name for the duplicated role
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function duplicateRole(int $roleId, ?string $newName = null): array
    {
        $role = self::getRole($roleId);

        if (null === $newName) {
            $newName = $role['name'] . ' (Kopie)';
        }

        return self::addRole([
            'name' => $newName,
            'description' => $role['description'] ?? '',
            'perms' => $role['perms'] ?? [],
        ]);
    }

    /**
     * @return string
     */
    private static function getUser(): string
    {
        return rex::getUser()?->getLogin() ?? rex::getEnvironment();
    }
}
