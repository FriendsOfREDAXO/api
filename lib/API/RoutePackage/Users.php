<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_sql;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use function count;
use const JSON_PRETTY_PRINT;

class Users extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Users List
        RouteCollection::registerRoute(
            'users/list',
            new Route(
                'users',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleUsersList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'login' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'email' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'status' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'admin' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'role' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
                        ],
                        'page' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 1,
                        ],
                        'per_page' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 100,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET']
            ),
            'Access to the list of users',
        );

        // User Get Details
        RouteCollection::registerRoute(
            'users/get',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleGetUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']
            ),
            'Get user details',
        );

        // User Add
        RouteCollection::registerRoute(
            'users/add',
            new Route(
                'users',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleAddUser',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'login' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'password' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'email' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'status' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 1,
                        ],
                        'admin' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
                        ],
                        'language' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => 'de_de',
                        ],
                        'startpage' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => 'structure',
                        ],
                        'role' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']
            ),
            'Add a user',
        );

        // User Update
        RouteCollection::registerRoute(
            'users/update',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleUpdateUser',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'login' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'password' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'email' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'status' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'admin' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'language' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'startpage' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'role' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']
            ),
            'Update a user',
        );

        // User Delete
        RouteCollection::registerRoute(
            'users/delete',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleDeleteUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']
            ),
            'Delete a user',
        );

        // User Roles List
        RouteCollection::registerRoute(
            'users/roles/list',
            new Route(
                'users/roles',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleUserRolesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
                        ],
                        'page' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 1,
                        ],
                        'per_page' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 100,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET']
            ),
            'Access to the list of user roles',
        );

        // User Role Get Details
        RouteCollection::registerRoute(
            'users/roles/get',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleGetUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']
            ),
            'Get user role details',
        );

        // User Role Add
        RouteCollection::registerRoute(
            'users/roles/add',
            new Route(
                'users/roles',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleAddUserRole',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']
            ),
            'Add a user role',
        );

        // User Role Update
        RouteCollection::registerRoute(
            'users/roles/update',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleUpdateUserRole',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']
            ),
            'Update a user role',
        );

        // User Role Delete
        RouteCollection::registerRoute(
            'users/roles/delete',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Users::handleDeleteUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']
            ),
            'Delete a user role',
        );
    }

    /** @api */
    public static function handleUsersList($Parameter): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $excludeFields = ['password', 'previous_passwords', 'session_id'];
        $fields = [];

        $tableFields = rex_sql::factory()->getArray('SHOW COLUMNS FROM ' . rex::getTable('user'));
        foreach ($tableFields as $field) {
            if (!in_array($field['Field'], $excludeFields)) {
                $fields[] = $field['Field'];
            }
        }

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (isset($Query['filter']['id']) && $Query['filter']['id'] !== null) {
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (isset($Query['filter']['name']) && $Query['filter']['name'] !== null) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        if (isset($Query['filter']['login']) && $Query['filter']['login'] !== null) {
            $SqlQueryWhere[':login'] = 'login LIKE :login';
            $SqlParameters[':login'] = '%' . $Query['filter']['login'] . '%';
        }

        if (isset($Query['filter']['email']) && $Query['filter']['email'] !== null) {
            $SqlQueryWhere[':email'] = 'email LIKE :email';
            $SqlParameters[':email'] = '%' . $Query['filter']['email'] . '%';
        }

        if (isset($Query['filter']['status']) && $Query['filter']['status'] !== null) {
            $SqlQueryWhere[':status'] = 'status = :status';
            $SqlParameters[':status'] = $Query['filter']['status'];
        }

        if (isset($Query['filter']['admin']) && $Query['filter']['admin'] !== null) {
            $SqlQueryWhere[':admin'] = 'admin = :admin';
            $SqlParameters[':admin'] = $Query['filter']['admin'];
        }

        if (isset($Query['filter']['role']) && $Query['filter']['role'] !== null) {
            $SqlQueryWhere[':role'] = 'role LIKE :role';
            $SqlParameters[':role'] = '%' . $Query['filter']['role'] . '%';
        }

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $UsersSQL = rex_sql::factory();
        $Users = $UsersSQL->getArray(
            '
            SELECT
                ' . implode(',', $fields) . '
            FROM
                ' . rex::getTable('user') . '
            ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
            ORDER BY name ASC
            LIMIT :start, :per_page
            ',
            $SqlParameters,
        );

        return new Response(json_encode($Users, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetUser($Parameter): Response
    {
        $userId = $Parameter['id'];

        $excludeFields = ['password', 'previous_passwords', 'session_id'];
        $fields = [];

        $tableFields = rex_sql::factory()->getArray('SHOW COLUMNS FROM ' . rex::getTable('user'));
        foreach ($tableFields as $field) {
            if (!in_array($field['Field'], $excludeFields)) {
                $fields[] = $field['Field'];
            }
        }

        $UserSQL = rex_sql::factory();
        $UserData = $UserSQL->getArray(
            'SELECT ' . implode(',', $fields) . ' FROM ' . rex::getTable('user') . ' WHERE id = :id',
            [':id' => $userId]
        );

        if (empty($UserData)) {
            return new Response(json_encode(['error' => 'User not found']), 404);
        }

        return new Response(json_encode($UserData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddUser($Parameter): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE login = :login', [':login' => $Data['login']]);

        if ($checkSql->getRows() > 0) {
            return new Response(json_encode(['error' => 'Login already exists']), 409);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('user'));
            $sql->setValue('name', $Data['name']);
            $sql->setValue('login', $Data['login']);

            if (method_exists('rex_login', 'passwordHash')) {
                $sql->setValue('password', rex_login::passwordHash($Data['password']));
            } else {
                $sql->setValue('password', password_hash($Data['password'], PASSWORD_DEFAULT));
            }

            $sql->setValue('email', $Data['email']);
            $sql->setValue('status', $Data['status']);
            $sql->setValue('admin', $Data['admin']);
            $sql->setValue('language', $Data['language']);
            $sql->setValue('startpage', $Data['startpage']);

            if ($Data['role'] !== null) {
                $sql->setValue('role', $Data['role']);
            }

            if ($Data['description'] !== null) {
                $sql->setValue('description', $Data['description']);
            }

            $sql->setValue('createdate', date('Y-m-d H:i:s'));
            $sql->setValue('createuser', 'API');
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');
            $sql->setValue('password_changed', date('Y-m-d H:i:s'));
            $sql->setValue('login_tries', 0);

            $sql->insert();
            $userId = $sql->getLastId();

            return new Response(json_encode(['message' => 'User created', 'id' => $userId]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateUser($Parameter): Response
    {
        $userId = $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE id = :id', [':id' => $userId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'User not found']), 404);
        }

        if ($Data['login'] !== null) {
            $loginSql = rex_sql::factory();
            $loginSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE login = :login AND id != :id',
                [':login' => $Data['login'], ':id' => $userId]);

            if ($loginSql->getRows() > 0) {
                return new Response(json_encode(['error' => 'Login already exists']), 409);
            }
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('user'));
            $sql->setWhere(['id' => $userId]);

            if ($Data['name'] !== null) {
                $sql->setValue('name', $Data['name']);
            }

            if ($Data['login'] !== null) {
                $sql->setValue('login', $Data['login']);
            }

            if ($Data['password'] !== null) {
                $currentPasswordSql = rex_sql::factory();
                $currentPassword = $currentPasswordSql->getArray(
                    'SELECT password, previous_passwords FROM ' . rex::getTable('user') . ' WHERE id = :id',
                    [':id' => $userId]
                );

                if (method_exists('rex_login', 'passwordHash')) {
                    $hashedPassword = rex_login::passwordHash($Data['password']);
                } else {
                    $hashedPassword = password_hash($Data['password'], PASSWORD_DEFAULT);
                }

                $sql->setValue('password', $hashedPassword);
                $sql->setValue('password_changed', date('Y-m-d H:i:s'));

                if (!empty($currentPassword) && isset($currentPassword[0]['password'])) {
                    $prevPasswords = $currentPassword[0]['previous_passwords']
                        ? json_decode($currentPassword[0]['previous_passwords'], true)
                        : [];

                    array_unshift($prevPasswords, $currentPassword[0]['password']);

                    if (count($prevPasswords) > 5) {
                        $prevPasswords = array_slice($prevPasswords, 0, 5);
                    }

                    $sql->setValue('previous_passwords', json_encode($prevPasswords));
                }
            }

            if ($Data['email'] !== null) {
                $sql->setValue('email', $Data['email']);
            }

            if ($Data['status'] !== null) {
                $sql->setValue('status', $Data['status']);
            }

            if ($Data['admin'] !== null) {
                $sql->setValue('admin', $Data['admin']);
            }

            if ($Data['language'] !== null) {
                $sql->setValue('language', $Data['language']);
            }

            if ($Data['startpage'] !== null) {
                $sql->setValue('startpage', $Data['startpage']);
            }

            if (array_key_exists('role', $Data)) {
                $sql->setValue('role', $Data['role']);
            }

            if (array_key_exists('description', $Data)) {
                $sql->setValue('description', $Data['description']);
            }

            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->update();

            if (method_exists('rex_user', 'clearInstance')) {
                rex_user::clearInstance($userId);
            }

            return new Response(json_encode(['message' => 'User updated', 'id' => $userId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteUser($Parameter): Response
    {
        $userId = $Parameter['id'];

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user') . ' WHERE id = :id', [':id' => $userId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'User not found']), 404);
        }

        $adminSql = rex_sql::factory();
        $adminSql->setQuery('SELECT COUNT(*) as admin_count FROM ' . rex::getTable('user') . ' WHERE admin = 1');
        $adminCount = $adminSql->getValue('admin_count');

        $isAdmin = rex_sql::factory();
        $isAdmin->setQuery('SELECT admin FROM ' . rex::getTable('user') . ' WHERE id = :id', [':id' => $userId]);
        $isAdminUser = $isAdmin->getValue('admin');

        if ($adminCount <= 1 && $isAdminUser == 1) {
            return new Response(json_encode(['error' => 'Cannot delete the only admin user']), 409);
        }

        try {
            if (method_exists('rex_user', 'clearInstance')) {
                rex_user::clearInstance($userId);
            }

            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('user') . ' WHERE id = :id',
                [':id' => $userId]
            );

            return new Response(json_encode(['message' => 'User deleted', 'id' => $userId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUserRolesList($Parameter): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (isset($Query['filter']['id']) && $Query['filter']['id'] !== null) {
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (isset($Query['filter']['name']) && $Query['filter']['name'] !== null) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $RolesSQL = rex_sql::factory();
        $Roles = $RolesSQL->getArray(
            '
            SELECT
                id, name, description
            FROM
                ' . rex::getTable('user_role') . '
            ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
            ORDER BY name ASC
            LIMIT :start, :per_page
            ',
            $SqlParameters,
        );

        return new Response(json_encode($Roles, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetUserRole($Parameter): Response
    {
        $roleId = $Parameter['id'];

        $RoleSQL = rex_sql::factory();
        $RoleData = $RoleSQL->getArray(
            'SELECT id, name, description, createdate, createuser, updatedate, updateuser, revision FROM ' . rex::getTable('user_role') . ' WHERE id = :id',
            [':id' => $roleId]
        );

        if (empty($RoleData)) {
            return new Response(json_encode(['error' => 'User role not found']), 404);
        }

        return new Response(json_encode($RoleData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddUserRole($Parameter): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE name = :name', [':name' => $Data['name']]);

        if ($checkSql->getRows() > 0) {
            return new Response(json_encode(['error' => 'Role name already exists']), 409);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('user_role'));
            $sql->setValue('name', $Data['name']);

            if ($Data['description'] !== null) {
                $sql->setValue('description', $Data['description']);
            }

            $sql->setValue('createdate', date('Y-m-d H:i:s'));
            $sql->setValue('createuser', 'API');
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');
            $sql->setValue('revision', 0);

            $sql->insert();
            $roleId = $sql->getLastId();

            return new Response(json_encode(['message' => 'User role created', 'id' => $roleId]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateUserRole($Parameter): Response
    {
        $roleId = $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE id = :id', [':id' => $roleId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'User role not found']), 404);
        }

        if ($Data['name'] !== null) {
            $nameSql = rex_sql::factory();
            $nameSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE name = :name AND id != :id',
                [':name' => $Data['name'], ':id' => $roleId]);

            if ($nameSql->getRows() > 0) {
                return new Response(json_encode(['error' => 'Role name already exists']), 409);
            }
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('user_role'));
            $sql->setWhere(['id' => $roleId]);

            if ($Data['name'] !== null) {
                $sql->setValue('name', $Data['name']);
            }

            if (array_key_exists('description', $Data)) {
                $sql->setValue('description', $Data['description']);
            }

            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->update();

            return new Response(json_encode(['message' => 'User role updated', 'id' => $roleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteUserRole($Parameter): Response
    {
        $roleId = $Parameter['id'];

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('user_role') . ' WHERE id = :id', [':id' => $roleId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'User role not found']), 404);
        }

        $usageCheck = rex_sql::factory();
        $usageCheck->setQuery(
            'SELECT id FROM ' . rex::getTable('user') . ' WHERE role = :role_id LIMIT 1',
            [':role_id' => $roleId]
        );

        if ($usageCheck->getRows() > 0) {
            return new Response(json_encode([
                'error' => 'Cannot delete role. It is assigned to one or more users.'
            ]), 409);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('user_role') . ' WHERE id = :id',
                [':id' => $roleId]
            );

            return new Response(json_encode(['message' => 'User role deleted', 'id' => $roleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
