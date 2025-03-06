<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_key_exists;
use function count;
use function is_array;
use function is_string;

use const JSON_PRETTY_PRINT;
use const PASSWORD_DEFAULT;

class Users extends RoutePackage
{
    public const UsersFields = ['id', 'name', 'description', 'login', 'email', 'status', 'admin', 'language', 'startpage', 'login_tries', 'createdate', 'createuser', 'updatedate', 'updateuser', 'password_changed', 'password_change_required', 'lasttrydate', 'lastlogin'];

    public function loadRoutes(): void
    {
        // TODO:
        // - Add user
        // - Update user
        // - AddRoleToUser
        // - DeleteRolefromUser
        // - AddRole
        // - UpdateRole
        // - DeleteRole

        // Users List ✅
        RouteCollection::registerRoute(
            'users/list',
            new Route(
                'users',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleUsersList',
                    'query' => [
                        'filter' => [
                            'fields' => [
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
                ['GET'],
            ),
            'Access to the list of users',
        );

        // User Get Details ✅
        RouteCollection::registerRoute(
            'users/get',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleGetUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get user details',
        );

        // User Delete ✅
        RouteCollection::registerRoute(
            'users/delete',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleDeleteUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a user',
        );

        // User Roles List ✅
        RouteCollection::registerRoute(
            'users/roles/list',
            new Route(
                'users/roles',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleUserRolesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
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
                ['GET'],
            ),
            'Access to the list of user roles',
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

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (null !== $Query['filter']['name']) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        if (null !== $Query['filter']['login']) {
            $SqlQueryWhere[':login'] = 'login LIKE :login';
            $SqlParameters[':login'] = '%' . $Query['filter']['login'] . '%';
        }

        if (null !== $Query['filter']['email'] && is_string($Query['filter']['email'])) {
            $SqlQueryWhere[':email'] = 'email LIKE :email';
            $SqlParameters[':email'] = '%' . $Query['filter']['email'] . '%';
        }

        if (isset($Query['filter']['status']) && null !== $Query['filter']['status']) {
            $SqlQueryWhere[':status'] = 'status = :status';
            $SqlParameters[':status'] = $Query['filter']['status'];
        }

        if (isset($Query['filter']['admin']) && null !== $Query['filter']['admin']) {
            $SqlQueryWhere[':admin'] = 'admin = :admin';
            $SqlParameters[':admin'] = $Query['filter']['admin'];
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
                ' . implode(',', self::UsersFields) . '
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

        $UserSQL = rex_sql::factory();
        $UserData = $UserSQL->getArray(
            'SELECT ' . implode(',', self::UsersFields) . ' FROM ' . rex::getTable('user') . ' WHERE id = :id',
            [':id' => $userId],
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

            if (null !== $Data['role']) {
                $sql->setValue('role', $Data['role']);
            }

            if (null !== $Data['description']) {
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

        $User = rex_user::get($userId);

        if (!$User) {
            return new Response(json_encode(['error' => 'User not found']), 404);
        }

        if (null !== $Data['login']) {
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

            if (null !== $Data['name']) {
                $sql->setValue('name', $Data['name']);
            }

            if (null !== $Data['status']) {
                $sql->setValue('status', $Data['status']);
            }

            if (null !== $Data['language']) {
                $sql->setValue('language', $Data['language']);
            }

            if (null !== $Data['startpage']) {
                $sql->setValue('startpage', $Data['startpage']);
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

        $User = rex_user::get($userId);
        if (!$User) {
            return new Response(json_encode(['error' => 'User not found']), 404);
        }

        if ($User->isAdmin()) {
            $adminSql = rex_sql::factory();
            $adminSql->setQuery('SELECT COUNT(*) as admin_count FROM ' . rex::getTable('user') . ' WHERE admin = 1 and status = 1');
            $adminCount = $adminSql->getValue('admin_count');
            if ($adminCount <= 1) {
                return new Response(json_encode(['error' => 'Cannot delete the last admin user']), 409);
            }
        }

        try {
            $deleteuser = rex_sql::factory();
            $deleteuser->setQuery('DELETE FROM ' . rex::getTablePrefix() . 'user WHERE id = ? LIMIT 1', [$User->getId()]);

            rex_user::clearInstance($User->getId());

            rex_extension::registerPoint(new rex_extension_point('USER_DELETED', '', [
                'id' => $User->getId(),
                'user' => $User,
            ], true));

            return new Response(json_encode(['message' => 'User deleted', 'id' => $User->getId()]), 200);
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

        if (isset($Query['filter']['name']) && null !== $Query['filter']['name']) {
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
            ORDER BY name
            LIMIT :start, :per_page
            ',
            $SqlParameters,
        );

        return new Response(json_encode($Roles, JSON_PRETTY_PRINT));
    }
}
