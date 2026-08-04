<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\Api\Service;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\BackendPasswordPolicy;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Security\User;
use Redaxo\Core\Security\UserSession;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Validator\Validator;

use function count;
use function in_array;

/**
 * Service class for user operations.
 *
 * Mirrors what REDAXO's own users page (`pages/user/users.php`) does, including the extension points it
 * fires and the session invalidation after a password change.
 */
final class UserService
{
    /** Columns exposed by the list/get endpoints. */
    public const array FIELDS = ['id', 'name', 'description', 'login', 'email', 'status', 'admin', 'language', 'startpage', 'theme', 'login_tries', 'createdate', 'createuser', 'updatedate', 'updateuser', 'password_changed', 'password_change_required', 'lasttrydate', 'lastlogin', 'role'];

    private function __construct() {}

    /**
     * @param array{login: string, password: string, name?: string, email?: string, description?: string, admin?: int, status?: int, role?: string, language?: string, startpage?: string, password_change_required?: bool} $data
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
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
        $checkSql->setQuery('SELECT id FROM ' . Core::getTable('user') . ' WHERE login = ?', [$data['login']]);

        if ($checkSql->getRows() > 0) {
            throw new ApiFunctionException(I18n::msg('user_login_exists'));
        }

        if (!empty($data['email']) && !Validator::factory()->email($data['email'])) {
            throw new ApiFunctionException(I18n::msg('invalid_email'));
        }

        $passwordPolicy = BackendPasswordPolicy::factory();
        if (true !== $msg = $passwordPolicy->check($data['password'], null)) {
            throw new ApiFunctionException($msg);
        }

        $passwordHash = Login::passwordHash($data['password']);

        $sql = Sql::factory();
        $sql->setTable(Core::getTable('user'));
        $sql->setValue('login', $data['login']);
        $sql->setValue('password', $passwordHash);
        $sql->setValue('name', $data['name'] ?? '');
        $sql->setValue('email', $data['email'] ?? '');
        $sql->setValue('description', $data['description'] ?? '');
        $sql->setValue('admin', isset($data['admin']) && 1 === (int) $data['admin'] ? 1 : 0);
        $sql->setValue('status', isset($data['status']) && 1 === (int) $data['status'] ? 1 : 0);
        $sql->setValue('role', $data['role'] ?? '');
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
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
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
        $passwordPolicy = BackendPasswordPolicy::factory();
        if (!empty($data['password'])) {
            if (true !== $msg = $passwordPolicy->check($data['password'], $userId)) {
                throw new ApiFunctionException($msg);
            }
            $passwordHash = Login::passwordHash($data['password']);
        }

        $sql = Sql::factory();
        $sql->setTable(Core::getTable('user'));
        $sql->setWhere(['id' => $userId]);

        foreach (['name', 'email', 'description', 'role', 'language', 'startpage'] as $column) {
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
     * @return array{id: int, message: string}
     * @throws ApiFunctionException
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
            $adminSql->setQuery('SELECT COUNT(*) as admin_count FROM ' . Core::getTable('user') . ' WHERE admin = 1 AND status = 1');
            if ((int) $adminSql->getValue('admin_count') <= 1) {
                throw new ApiFunctionException(I18n::msg('api_user_admin_delete_notallowed'));
            }
        }

        Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('user') . ' WHERE id = ? LIMIT 1', [$userId]);

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

        return Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::getTable('user')
            . (count($sqlWhere) > 0 ? ' WHERE ' . implode(' AND ', $sqlWhere) : '')
            . ' ORDER BY ' . $orderBy . ' ' . $orderDirection,
            $sqlParams,
        );
    }

    /**
     * @return array<string, mixed>
     * @throws ApiFunctionException
     */
    public static function getUser(int $userId): array
    {
        $users = Sql::factory()->getArray(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM ' . Core::getTable('user') . ' WHERE id = ?',
            [$userId],
        );

        if (0 === count($users)) {
            throw new ApiFunctionException(I18n::msg('api_user_not_found'));
        }

        return $users[0];
    }

    public static function getCurrentUserLogin(): string
    {
        return Core::getUser()?->login ?? Core::getEnvironment()->value;
    }
}
