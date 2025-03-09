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
    public const RolesFields = ['id', 'name', 'description', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    /**
     * Registers API routes for user and role management.
     *
     * Sets up endpoints for listing users with filters (name, login, email, status, admin), fetching user details by ID,
     * deleting a user, and listing user roles with an optional name filter. Future enhancements include routes for creating,
     * updating, and managing user roles.
     */
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
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'admin' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
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

    /**
     * Retrieves a list of users based on provided query filters.
     *
     * This method extracts filter parameters from the request using the routing configuration and builds an SQL
     * query with exact matching conditions for the user's name, login, email, status, and admin fields. It then
     * returns a JSON response containing the list of users. If the mandatory query field is missing or invalid,
     * an error response with a 400 HTTP status code is returned.
     *
     * @api
     * @param array $Parameter Routing parameters that must include a 'query' key for filter configuration.
     * @return Response JSON response containing an array of user records or an error message.
     */
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
            $SqlQueryWhere[':name'] = 'name = :name';
            $SqlParameters[':name'] = $Query['filter']['name'];
        }

        if (null !== $Query['filter']['login']) {
            $SqlQueryWhere[':login'] = 'login = :login';
            $SqlParameters[':login'] = $Query['filter']['login'];
        }

        if (null !== $Query['filter']['email'] && is_string($Query['filter']['email'])) {
            $SqlQueryWhere[':email'] = 'email = :email';
            $SqlParameters[':email'] = $Query['filter']['email'];
        }

        if (isset($Query['filter']['status']) && null !== $Query['filter']['status']) {
            $Query['filter']['status'] = (1 === (int) $Query['filter']['status']) ? 1 : 0;
            $SqlQueryWhere[':status'] = 'status = :status';
            $SqlParameters[':status'] = $Query['filter']['status'];
        }

        if (isset($Query['filter']['admin']) && null !== $Query['filter']['admin']) {
            $Query['filter']['admin'] = (1 === (int) $Query['filter']['admin']) ? 1 : 0;
            $SqlQueryWhere[':admin'] = 'admin = :admin';
            $SqlParameters[':admin'] = $Query['filter']['admin'];
        }

        $UsersSQL = rex_sql::factory();
        $Users = $UsersSQL->getArray(
            '
            SELECT
                ' . implode(',', self::UsersFields) . '
            FROM
                ' . rex::getTable('user') . '
            ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
            ORDER BY name ASC
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

    /**
     * Deletes a user based on the provided ID while ensuring that at least one admin remains.
     *
     * This function retrieves the user using the ID from the $Parameter array. If the user is not found,
     * it returns a 404 error response. If the user is an admin, it verifies that deleting the user will not
     * remove the last active admin, returning a 409 error if it would. Upon successful deletion, the user's
     * cached instance is cleared and a 'USER_DELETED' event is triggered, returning a JSON response confirming
     * the deletion.
     *
     * @api
     * @param array $Parameter An associative array containing the user ID under the key 'id'.
     * @return Response A response object with a JSON message indicating success or the specific error encountered.
     */
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
            $deleteuser->setQuery('DELETE FROM ' . rex::getTable('user') . ' WHERE id = ? LIMIT 1', [$User->getId()]);

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

    /**
     * Handles the API request to list user roles.
     *
     * Extracts query parameters from the incoming request and applies an exact name filter if provided. It fetches roles using
     * the fields defined in the RolesFields constant from the user_role table, orders them by name, and returns the result as a
     * JSON response. If a required query parameter is missing, an error response with a 400 status code is returned.
     *
     * @api
     *
     * @param array $Parameter An associative array containing query parameters under the 'query' key.
     * @return Response JSON response with a list of user roles or an error message.
     */
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
            $SqlQueryWhere[':name'] = 'name = :name';
            $SqlParameters[':name'] = $Query['filter']['name'];
        }

        $RolesSQL = rex_sql::factory();
        $Roles = $RolesSQL->getArray(
            '
            SELECT
                ' . implode(',', self::RolesFields) . '
            FROM
                ' . rex::getTable('user_role') . '
                ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
            ORDER BY name
            ',
            $SqlParameters,
        );

        return new Response(json_encode($Roles, JSON_PRETTY_PRINT));
    }
}
