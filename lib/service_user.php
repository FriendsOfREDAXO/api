<?php

declare(strict_types=1);

/**
 * Service class for user operations.
 *
 * @package redaxo\api
 */
final class rex_user_service
{
    /**
     * Creates a new user.
     *
     * @param array{login: string, password: string, name?: string, email?: string, description?: string, admin?: int, status?: int, role?: string, language?: string, startpage?: string, password_change_required?: bool} $data
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function addUser(array $data): array
    {
        if (empty($data['login'])) {
            throw new rex_api_exception(rex_i18n::msg('user_missing_login'));
        }

        if (empty($data['password'])) {
            throw new rex_api_exception(rex_i18n::msg('user_missing_password'));
        }

        // Check if login already exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE login = ?', [$data['login']]);

        if ($checkSql->getRows() > 0) {
            throw new rex_api_exception(rex_i18n::msg('user_login_exists'));
        }

        // Validate email if provided
        if (!empty($data['email']) && !rex_validator::factory()->email($data['email'])) {
            throw new rex_api_exception(rex_i18n::msg('invalid_email'));
        }

        // Validate password policy
        $passwordPolicy = rex_backend_password_policy::factory();
        if (true !== $msg = $passwordPolicy->check($data['password'], null)) {
            throw new rex_api_exception($msg);
        }

        $passwordHash = rex_login::passwordHash($data['password']);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('user'));
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
        $sql->addGlobalCreateFields(self::getUser());
        $sql->addGlobalUpdateFields(self::getUser());

        $sql->insert();
        $userId = (int) $sql->getLastId();

        $user = rex_user::require($userId);

        rex_extension::registerPoint(new rex_extension_point('USER_ADDED', '', [
            'id' => $userId,
            'user' => $user,
            'password' => $data['password'],
        ], true));

        return [
            'id' => $userId,
            'message' => rex_i18n::msg('user_added'),
        ];
    }

    /**
     * Updates an existing user.
     *
     * @param int $userId
     * @param array{name?: string, email?: string, description?: string, admin?: int, status?: int, role?: string, language?: string, startpage?: string, password?: string, password_change_required?: bool, login_tries_reset?: bool} $data
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function updateUser(int $userId, array $data): array
    {
        $user = rex_user::get($userId);
        if (!$user) {
            throw new rex_api_exception('User not found');
        }

        // Validate email if provided
        if (!empty($data['email']) && !rex_validator::factory()->email($data['email'])) {
            throw new rex_api_exception(rex_i18n::msg('invalid_email'));
        }

        // Validate password if provided
        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordPolicy = rex_backend_password_policy::factory();
            if (true !== $msg = $passwordPolicy->check($data['password'], $userId)) {
                throw new rex_api_exception($msg);
            }
            $passwordHash = rex_login::passwordHash($data['password']);
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('user'));
        $sql->setWhere(['id' => $userId]);

        if (isset($data['name'])) {
            $sql->setValue('name', $data['name']);
        }

        if (isset($data['email'])) {
            $sql->setValue('email', $data['email']);
        }

        if (isset($data['description'])) {
            $sql->setValue('description', $data['description']);
        }

        if (isset($data['admin'])) {
            $sql->setValue('admin', 1 === (int) $data['admin'] ? 1 : 0);
        }

        if (isset($data['status'])) {
            $sql->setValue('status', 1 === (int) $data['status'] ? 1 : 0);
        }

        if (isset($data['role'])) {
            $sql->setValue('role', $data['role']);
        }

        if (isset($data['language'])) {
            $sql->setValue('language', $data['language']);
        }

        if (isset($data['startpage'])) {
            $sql->setValue('startpage', $data['startpage']);
        }

        if (null !== $passwordHash) {
            $passwordPolicy = rex_backend_password_policy::factory();
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

        $sql->addGlobalUpdateFields(self::getUser());
        $sql->update();

        rex_user::clearInstance($userId);
        $user = rex_user::require($userId);

        rex_extension::registerPoint(new rex_extension_point('USER_UPDATED', '', [
            'id' => $userId,
            'user' => $user,
            'password' => $data['password'] ?? null,
        ], true));

        // Remove sessions if password was changed
        if (null !== $passwordHash) {
            rex_user_session::getInstance()->removeSessionsExceptCurrent($userId);
        }

        return [
            'id' => $userId,
            'message' => rex_i18n::msg('user_data_updated'),
        ];
    }

    /**
     * Deletes a user.
     *
     * @param int $userId
     * @param int|null $currentUserId The ID of the current user (to prevent self-deletion)
     * @throws rex_api_exception
     * @return array{id: int, message: string}
     */
    public static function deleteUser(int $userId, ?int $currentUserId = null): array
    {
        $user = rex_user::get($userId);
        if (!$user) {
            throw new rex_api_exception(rex_i18n::msg('user_not_found'));
        }

        // Prevent self-deletion
        if (null !== $currentUserId && $userId === $currentUserId) {
            throw new rex_api_exception(rex_i18n::msg('user_notdeleteself'));
        }

        // Check if this is the last admin
        if ($user->isAdmin()) {
            $adminSql = rex_sql::factory();
            $adminSql->setQuery('SELECT COUNT(*) as admin_count FROM ' . rex::getTable('user') . ' WHERE admin = 1 AND status = 1');
            $adminCount = (int) $adminSql->getValue('admin_count');
            if ($adminCount <= 1) {
                throw new rex_api_exception(rex_i18n::msg('user_admin_delete_notallowed'));
            }
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('user') . ' WHERE id = ? LIMIT 1', [$userId]);

        rex_user::clearInstance($userId);

        rex_extension::registerPoint(new rex_extension_point('USER_DELETED', '', [
            'id' => $userId,
            'user' => $user,
        ], true));

        return [
            'id' => $userId,
            'message' => rex_i18n::msg('user_deleted'),
        ];
    }

    /**
     * Gets a list of users.
     *
     * @param array{name?: string, login?: string, email?: string, status?: int, admin?: int} $filter
     * @param string $orderBy
     * @param string $orderDirection
     * @return list<array<string, mixed>>
     */
    public static function getList(array $filter = [], string $orderBy = 'name', string $orderDirection = 'ASC'): array
    {
        $allowedFields = ['id', 'name', 'description', 'login', 'email', 'status', 'admin', 'language', 'startpage', 'login_tries', 'createdate', 'createuser', 'updatedate', 'updateuser', 'password_changed', 'password_change_required', 'lasttrydate', 'lastlogin', 'role'];

        $sqlWhere = [];
        $sqlParams = [];

        if (!empty($filter['name'])) {
            $sqlWhere[] = 'name LIKE :name';
            $sqlParams[':name'] = '%' . $filter['name'] . '%';
        }

        if (!empty($filter['login'])) {
            $sqlWhere[] = 'login LIKE :login';
            $sqlParams[':login'] = '%' . $filter['login'] . '%';
        }

        if (!empty($filter['email'])) {
            $sqlWhere[] = 'email LIKE :email';
            $sqlParams[':email'] = '%' . $filter['email'] . '%';
        }

        if (isset($filter['status'])) {
            $sqlWhere[] = 'status = :status';
            $sqlParams[':status'] = (int) $filter['status'];
        }

        if (isset($filter['admin'])) {
            $sqlWhere[] = 'admin = :admin';
            $sqlParams[':admin'] = (int) $filter['admin'];
        }

        $orderBy = in_array($orderBy, $allowedFields, true) ? $orderBy : 'name';
        $orderDirection = 'DESC' === strtoupper($orderDirection) ? 'DESC' : 'ASC';

        $sql = rex_sql::factory();
        return $sql->getArray(
            'SELECT ' . implode(', ', $allowedFields) . ' FROM ' . rex::getTable('user') .
            (count($sqlWhere) > 0 ? ' WHERE ' . implode(' AND ', $sqlWhere) : '') .
            ' ORDER BY ' . $orderBy . ' ' . $orderDirection,
            $sqlParams
        );
    }

    /**
     * Gets a single user by ID.
     *
     * @param int $userId
     * @throws rex_api_exception
     * @return array<string, mixed>
     */
    public static function getUser(int $userId): array
    {
        $allowedFields = ['id', 'name', 'description', 'login', 'email', 'status', 'admin', 'language', 'startpage', 'login_tries', 'createdate', 'createuser', 'updatedate', 'updateuser', 'password_changed', 'password_change_required', 'lasttrydate', 'lastlogin', 'role'];

        $sql = rex_sql::factory();
        $users = $sql->getArray(
            'SELECT ' . implode(', ', $allowedFields) . ' FROM ' . rex::getTable('user') . ' WHERE id = ?',
            [$userId]
        );

        if (empty($users)) {
            throw new rex_api_exception(rex_i18n::msg('user_not_found'));
        }

        return $users[0];
    }

    /**
     * @return string
     */
    private static function getUser(): string
    {
        return rex::getUser()?->getLogin() ?? rex::getEnvironment();
    }
}
