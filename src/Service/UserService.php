<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Service;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\User;
use Redaxo\Core\Security\UserRole;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\Validator;

use function count;
use function in_array;
use function is_array;

/**
 * Service class for user operations.
 *
 * Mirrors what REDAXO's own users page (`pages/user/users.php`) does, including the extension points it
 * fires and the session invalidation after a password change.
 */
final class UserService
{
    /** Columns exposed by the list/get endpoints. */
    public const array FIELDS = ['id', 'name', 'description', 'login', 'email', 'status', 'admin', 'language', 'startpage', 'theme', 'login_tries', 'createdate', 'createuser', 'updatedate', 'updateuser', 'password_changed', 'password_change_required', 'lasttrydate', 'lastlogin'];

    private function __construct() {}

    /**
     * @param array{login: string, password: string, name?: string, email?: string, description?: string, admin?: int, status?: int, role?: string, language?: string, startpage?: string, password_change_required?: bool} $data
     * @throws ApiFunctionException
     * @return array{id: int, message: string}
     */
    public static function addUser(array $data): array
    {
        if (empty($data['login'])) {
            throw new ApiFunctionException(I18n::msg('user_missing_login'));
        }

        if (empty($data['password'])) {
            throw new ApiFunctionException(I18n::msg('user_missing_password'));
        }

        $checkSql = Sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . Core::TABLE_PREFIX . 'user WHERE login = ?', [$data['login']]);

        if ($checkSql->getRows() > 0) {
            throw new ApiFunctionException(I18n::msg('user_login_exists'));
        }

        if (!empty($data['email']) && !Validator::factory()->email($data['email'])) {
            throw new ApiFunctionException(I18n::msg('invalid_email'));
        }

        $passwordPolicy = BackendLogin::getPasswordPolicy();
        if (true !== $msg = $passwordPolicy->check($data['password'], null)) {
            throw new ApiFunctionException($msg);
        }

        $passwordHash = BackendLogin::passwordHash($data['password']);

        $sql = Sql::factory();
        $sql->setTable(Core::TABLE_PREFIX . 'user');
        $sql->setValue('login', $data['login']);
        $sql->setValue('password', $passwordHash);
        $sql->setValue('name', $data['name'] ?? '');
        $sql->setValue('email', $data['email'] ?? '');
        $sql->setValue('description', $data['description'] ?? '');
        $sql->setValue('admin', isset($data['admin']) && 1 === (int) $data['admin'] ? 1 : 0);
        $sql->setValue('status', isset($data['status']) && 1 === (int) $data['status'] ? 1 : 0);
        $sql->setValue('language', $data['language'] ?? '');
        $sql->setValue('startpage', $data['startpage'] ?? '');
        $sql->setValue('password_change_required', isset($data['password_change_required']) && $data['password_change_required'] ? 1 : 0);
        $sql->setDateTimeValue('password_changed', time());
        $sql->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords(null, $passwordHash));
        $sql->setValue('login_tries', 0);
        $sql->addGlobalCreateFields(self::getCurrentUserLogin());
        $sql->addGlobalUpdateFields(self::getCurrentUserLogin());

        $sql->insert();
        $userId = $sql->getLastId();

        UserRole::setForUser($userId, self::parseRoleIds($data['role'] ?? ''));
        User::clearInstance($userId);

        Extension::dispatch(new ExtensionPoint('USER_ADDED', '', [
            'id' => $userId,
            'user' => User::require($userId),
            'password' => $data['password'],
        ], readonly: true));

        return [
            'id' => $userId,
            'message' => I18n::msg('user_added'),
        ];
    }

    /**
     * @param array{name?: string, email?: string, description?: string, admin?: int, status?: int, role?: string, language?: string, startpage?: string, password?: string, password_change_required?: bool, login_tries_reset?: bool} $data
     * @throws ApiFunctionException
     * @return array{id: int, message: string}
     */
    public static function updateUser(int $userId, array $data): array
    {
        $user = User::get($userId);
        if (null === $user) {
            throw new ApiFunctionException(I18n::msg('api_user_not_found'));
        }

        if (!empty($data['email']) && !Validator::factory()->email($data['email'])) {
            throw new ApiFunctionException(I18n::msg('invalid_email'));
        }

        $passwordHash = null;
        $passwordPolicy = BackendLogin::getPasswordPolicy();
        if (!empty($data['password'])) {
            if (true !== $msg = $passwordPolicy->check($data['password'], $userId)) {
                throw new ApiFunctionException($msg);
            }
            $passwordHash = BackendLogin::passwordHash($data['password']);
        }

        $sql = Sql::factory();
        $sql->setTable(Core::TABLE_PREFIX . 'user');
        $sql->setWhere(['id' => $userId]);

        foreach (['name', 'email', 'description', 'language', 'startpage'] as $column) {
            if (isset($data[$column])) {
                $sql->setValue($column, $data[$column]);
            }
        }

        foreach (['admin', 'status'] as $column) {
            if (isset($data[$column])) {
                $sql->setValue($column, 1 === (int) $data[$column] ? 1 : 0);
            }
        }

        if (null !== $passwordHash) {
            $sql->setValue('password', $passwordHash);
            $sql->setDateTimeValue('password_changed', time());
            $sql->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords($user, $passwordHash));
        }

        if (isset($data['password_change_required'])) {
            $sql->setValue('password_change_required', $data['password_change_required'] ? 1 : 0);
        }

        if (!empty($data['login_tries_reset'])) {
            $sql->setValue('login_tries', 0);
        }

        $sql->addGlobalUpdateFields(self::getCurrentUserLogin());
        $sql->update();

        if (isset($data['role'])) {
            UserRole::setForUser($userId, self::parseRoleIds($data['role']));
        }

        User::clearInstance($userId);

        Extension::dispatch(new ExtensionPoint('USER_UPDATED', '', [
            'id' => $userId,
            'user' => User::require($userId),
            'password' => $data['password'] ?? null,
        ], readonly: true));

        // Remove sessions if password was changed
        if (null !== $passwordHash) {
            UserSession::getInstance()->removeSessionsExceptCurrent($userId);
        }

        return [
            'id' => $userId,
            'message' => I18n::msg('user_data_updated'),
        ];
    }

    /**
     * @param int|null $currentUserId The ID of the current user (to prevent self-deletion)
     * @throws ApiFunctionException
     * @return array{id: int, message: string}
     */
    public static function deleteUser(int $userId, ?int $currentUserId = null): array
    {
        $user = User::get($userId);
        if (null === $user) {
            throw new ApiFunctionException(I18n::msg('api_user_not_found'));
        }

        if (null !== $currentUserId && $userId === $currentUserId) {
            throw new ApiFunctionException(I18n::msg('user_notdeleteself'));
        }

        // Check if this is the last admin
        if ($user->admin) {
            $adminSql = Sql::factory();
            $adminSql->setQuery('SELECT COUNT(*) as admin_count FROM ' . Core::TABLE_PREFIX . 'user WHERE admin = 1 AND status = 1');
            if ((int) $adminSql->getValue('admin_count') <= 1) {
                throw new ApiFunctionException(I18n::msg('api_user_admin_delete_notallowed'));
            }
        }

        Sql::factory()->setQuery('DELETE FROM ' . Core::TABLE_PREFIX . 'user WHERE id = ? LIMIT 1', [$userId]);

        User::clearInstance($userId);

        Extension::dispatch(new ExtensionPoint('USER_DELETED', '', [
            'id' => $userId,
            'user' => $user,
        ], readonly: true));

        return [
            'id' => $userId,
            'message' => I18n::msg('user_deleted'),
        ];
    }

    /**
     * @param array{name?: string, login?: string, email?: string, status?: int, admin?: int} $filter
     * @return list<array<string, mixed>>
     */
    public static function getList(array $filter = [], string $orderBy = 'name', string $orderDirection = 'ASC'): array
    {
        $sqlWhere = [];
        $sqlParams = [];

        foreach (['name', 'login', 'email'] as $column) {
            if (!empty($filter[$column])) {
                $sqlWhere[] = $column . ' LIKE :' . $column;
                $sqlParams[':' . $column] = '%' . $filter[$column] . '%';
            }
        }

        foreach (['status', 'admin'] as $column) {
            if (isset($filter[$column])) {
                $sqlWhere[] = $column . ' = :' . $column;
                $sqlParams[':' . $column] = (int) $filter[$column];
            }
        }

        $orderBy = in_array($orderBy, self::FIELDS, true) ? $orderBy : 'name';
        $orderDirection = 'DESC' === strtoupper($orderDirection) ? 'DESC' : 'ASC';

        $users = Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::TABLE_PREFIX . 'user'
            . (count($sqlWhere) > 0 ? ' WHERE ' . implode(' AND ', $sqlWhere) : '')
            . ' ORDER BY ' . $orderBy . ' ' . $orderDirection,
            $sqlParams,
        );

        return array_map(self::withRoles(...), $users);
    }

    /**
     * @throws ApiFunctionException
     * @return array<string, mixed>
     */
    public static function getUser(int $userId): array
    {
        $users = Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::TABLE_PREFIX . 'user WHERE id = ?',
            [$userId],
        );

        if (0 === count($users)) {
            throw new ApiFunctionException(I18n::msg('api_user_not_found'));
        }

        return self::withRoles($users[0]);
    }

    /**
     * The roles are assigned in `rex_user_role_assignment`.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private static function withRoles(array $user): array
    {
        $user['roles'] = UserRole::getIdsForUser((int) $user['id']);

        return $user;
    }

    /**
     * Role ids as list or comma separated string (the REDAXO 5 format of `rex_user.role`).
     *
     * @param string|list<int|string> $roles
     * @return list<int>
     */
    public static function parseRoleIds(string|array $roles): array
    {
        $ids = is_array($roles) ? $roles : explode(',', $roles);

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    public static function getCurrentUserLogin(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
