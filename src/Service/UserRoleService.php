<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Service;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Translation\I18n;

use function count;
use function in_array;
use function is_array;

/**
 * Service class for user role operations.
 *
 * REDAXO's own roles page (`pages/user/roles.php`) drives the CRUD through the core form and fires no
 * role-specific extension point; this mirrors that exactly and stays silent as well.
 */
final class UserRoleService
{
    public const array FIELDS = ['id', 'name', 'description', 'perms', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    private function __construct() {}

    /**
     * @param array{name: string, description?: string, perms?: array<string, mixed>} $data
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
     */
    public static function addRole(array $data): array
    {
        if (empty($data['name'])) {
            throw new ApiFunctionException(I18n::msg('api_user_role_name_required'));
        }

        $checkSql = Sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . Core::getTable('user_role') . ' WHERE name = ?', [$data['name']]);

        if ($checkSql->getRows() > 0) {
            throw new ApiFunctionException(I18n::msg('api_user_role_name_exists'));
        }

        $sql = Sql::factory();
        $sql->setTable(Core::getTable('user_role'));
        $sql->setValue('name', $data['name']);
        $sql->setValue('description', $data['description'] ?? '');
        $sql->setValue('perms', json_encode(isset($data['perms']) && is_array($data['perms']) ? $data['perms'] : []));
        $sql->addGlobalCreateFields(UserService::getCurrentUserLogin());
        $sql->addGlobalUpdateFields(UserService::getCurrentUserLogin());

        $sql->insert();

        return [
            'id' => $sql->getLastId(),
            'message' => I18n::msg('user_role_added'),
        ];
    }

    /**
     * @param array{name?: string, description?: string, perms?: array<string, mixed>} $data
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
     */
    public static function updateRole(int $roleId, array $data): array
    {
        $checkSql = Sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . Core::getTable('user_role') . ' WHERE id = ?', [$roleId]);

        if (0 === $checkSql->getRows()) {
            throw new ApiFunctionException(I18n::msg('api_user_role_not_found'));
        }

        if (!empty($data['name'])) {
            $nameSql = Sql::factory();
            $nameSql->setQuery(
                'SELECT id FROM ' . Core::getTable('user_role') . ' WHERE name = ? AND id != ?',
                [$data['name'], $roleId],
            );

            if ($nameSql->getRows() > 0) {
                throw new ApiFunctionException(I18n::msg('api_user_role_name_exists'));
            }
        }

        $sql = Sql::factory();
        $sql->setTable(Core::getTable('user_role'));
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

        $sql->addGlobalUpdateFields(UserService::getCurrentUserLogin());
        $sql->update();

        return [
            'id' => $roleId,
            'message' => I18n::msg('api_user_role_updated'),
        ];
    }

    /**
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
     */
    public static function deleteRole(int $roleId): array
    {
        $checkSql = Sql::factory();
        $checkSql->setQuery('SELECT id, name FROM ' . Core::getTable('user_role') . ' WHERE id = ?', [$roleId]);

        if (0 === $checkSql->getRows()) {
            throw new ApiFunctionException(I18n::msg('api_user_role_not_found'));
        }

        $usageSql = Sql::factory();
        $usageSql->setQuery(
            'SELECT COUNT(*) as count FROM ' . Core::getTable('user') . ' WHERE FIND_IN_SET(?, role)',
            [$roleId],
        );

        if ((int) $usageSql->getValue('count') > 0) {
            throw new ApiFunctionException(I18n::msg('api_user_role_in_use'));
        }

        Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('user_role') . ' WHERE id = ? LIMIT 1', [$roleId]);

        return [
            'id' => $roleId,
            'message' => I18n::msg('user_role_deleted'),
        ];
    }

    /**
     * @param array{name?: string} $filter
     * @return list<array<string, mixed>>
     */
    public static function getList(array $filter = [], string $orderBy = 'name', string $orderDirection = 'ASC'): array
    {
        $sqlWhere = [];
        $sqlParams = [];

        if (!empty($filter['name'])) {
            $sqlWhere[] = 'name LIKE :name';
            $sqlParams[':name'] = '%' . $filter['name'] . '%';
        }

        $orderBy = in_array($orderBy, self::FIELDS, true) ? $orderBy : 'name';
        $orderDirection = 'DESC' === strtoupper($orderDirection) ? 'DESC' : 'ASC';

        $roles = Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::getTable('user_role')
            . (count($sqlWhere) > 0 ? ' WHERE ' . implode(' AND ', $sqlWhere) : '')
            . ' ORDER BY ' . $orderBy . ' ' . $orderDirection,
            $sqlParams,
        );

        foreach ($roles as &$role) {
            if (!empty($role['perms'])) {
                $role['perms'] = json_decode((string) $role['perms'], true);
            }
        }

        return $roles;
    }

    /**
     * @return array<string, mixed>
     * @throws ApiFunctionException
     */
    public static function getRole(int $roleId): array
    {
        $roles = Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::getTable('user_role') . ' WHERE id = ?',
            [$roleId],
        );

        if (0 === count($roles)) {
            throw new ApiFunctionException(I18n::msg('api_user_role_not_found'));
        }

        $role = $roles[0];

        if (!empty($role['perms'])) {
            $role['perms'] = json_decode((string) $role['perms'], true);
        }

        return $role;
    }

    /**
     * @param string|null $newName Optional new name for the duplicated role
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
     */
    public static function duplicateRole(int $roleId, ?string $newName = null): array
    {
        $role = self::getRole($roleId);

        return self::addRole([
            'name' => $newName ?? $role['name'] . ' (' . I18n::msg('api_copy') . ')',
            'description' => $role['description'] ?? '',
            'perms' => is_array($role['perms'] ?? null) ? $role['perms'] : [],
        ]);
    }
}
