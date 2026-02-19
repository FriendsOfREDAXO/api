<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_api_exception;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_user;
use rex_user_role_service;
use rex_user_service;
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

    public function loadRoutes(): void
    {
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
            null,
            new BearerAuth()
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
            null,
            new BearerAuth()
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
            null,
            new BearerAuth()
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
            null,
            new BearerAuth()
        );

        // User Add ✅
        RouteCollection::registerRoute(
            'users/add',
            new Route(
                'users',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleAddUser',
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
                            'required' => false,
                            'default' => '',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 1,
                        ],
                        'admin' => [
                            'type' => 'integer',
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
                ['POST'],
            ),
            'Add a user',
            null,
            new BearerAuth()
        );

        // User Update ✅
        RouteCollection::registerRoute(
            'users/update',
            new Route(
                'users/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleUpdateUser',
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
                        'status' => [
                            'type' => 'integer',
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
                ['PUT', 'PATCH'],
            ),
            'Update a user',
            null,
            new BearerAuth()
        );

        // User Role Get Details ✅
        RouteCollection::registerRoute(
            'users/roles/get',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleGetUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get user role details',
            null,
            new BearerAuth()
        );

        // User Role Add ✅
        RouteCollection::registerRoute(
            'users/roles/add',
            new Route(
                'users/roles',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleAddUserRole',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'description' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                        'perms' => [
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
                ['POST'],
            ),
            'Add a user role',
            null,
            new BearerAuth()
        );

        // User Role Update ✅
        RouteCollection::registerRoute(
            'users/roles/update',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleUpdateUserRole',
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
                        'perms' => [
                            'type' => 'array',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update a user role',
            null,
            new BearerAuth()
        );

        // User Role Delete ✅
        RouteCollection::registerRoute(
            'users/roles/delete',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleDeleteUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a user role',
            null,
            new BearerAuth()
        );

        // User Role Duplicate ✅
        RouteCollection::registerRoute(
            'users/roles/duplicate',
            new Route(
                'users/roles/{id}/duplicate',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Users::handleDuplicateUserRole',
                    'Body' => [
                        'name' => [
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
                ['POST'],
            ),
            'Duplicate a user role',
            null,
            new BearerAuth()
        );
    }

    private static function checkAdminPerm(?rex_user $user): ?Response
    {
        if (null === $user) {
            return null;
        }
        if (!$user->isAdmin()) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }
        return null;
    }

    /** @api */
    public static function handleUsersList($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $filter = [];
        if (null !== $Query['filter']['name']) {
            $filter['name'] = $Query['filter']['name'];
        }
        if (null !== $Query['filter']['login']) {
            $filter['login'] = $Query['filter']['login'];
        }
        if (null !== $Query['filter']['email'] && is_string($Query['filter']['email'])) {
            $filter['email'] = $Query['filter']['email'];
        }
        if (isset($Query['filter']['status']) && null !== $Query['filter']['status']) {
            $filter['status'] = (1 === (int) $Query['filter']['status']) ? 1 : 0;
        }
        if (isset($Query['filter']['admin']) && null !== $Query['filter']['admin']) {
            $filter['admin'] = (1 === (int) $Query['filter']['admin']) ? 1 : 0;
        }

        $users = rex_user_service::getList($filter);

        return new Response(json_encode($users, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetUser($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $Parameter['id'];

        try {
            $user = rex_user_service::getUser($userId);
            return new Response(json_encode($user, JSON_PRETTY_PRINT));
        } catch (rex_api_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 404);
        }
    }

    /** @api */
    public static function handleAddUser($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        try {
            $result = rex_user_service::addUser([
                'login' => $Data['login'],
                'password' => $Data['password'],
                'name' => $Data['name'] ?? '',
                'email' => $Data['email'] ?? '',
                'description' => $Data['description'] ?? '',
                'status' => $Data['status'] ?? 1,
                'admin' => $Data['admin'] ?? 0,
                'language' => $Data['language'] ?? 'de_de',
                'startpage' => $Data['startpage'] ?? 'structure',
                'role' => $Data['role'] ?? '',
            ]);

            return new Response(json_encode($result), 201);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'exists') ? 409 : 400;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateUser($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Build update data array with only set values
        $updateData = [];
        if (null !== $Data['name']) {
            $updateData['name'] = $Data['name'];
        }
        if (null !== $Data['status']) {
            $updateData['status'] = $Data['status'];
        }
        if (null !== $Data['language']) {
            $updateData['language'] = $Data['language'];
        }
        if (null !== $Data['startpage']) {
            $updateData['startpage'] = $Data['startpage'];
        }
        if (array_key_exists('description', $Data) && null !== $Data['description']) {
            $updateData['description'] = $Data['description'];
        }

        try {
            $result = rex_user_service::updateUser($userId, $updateData);
            return new Response(json_encode($result), 200);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteUser($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $Parameter['id'];

        try {
            $result = rex_user_service::deleteUser($userId);
            return new Response(json_encode($result), 200);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 409;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUserRolesList($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $filter = [];
        if (isset($Query['filter']['name']) && null !== $Query['filter']['name']) {
            $filter['name'] = $Query['filter']['name'];
        }

        $roles = rex_user_role_service::getList($filter);

        return new Response(json_encode($roles, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetUserRole($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $roleId = (int) $Parameter['id'];

        try {
            $role = rex_user_role_service::getRole($roleId);
            return new Response(json_encode($role, JSON_PRETTY_PRINT));
        } catch (rex_api_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 404);
        }
    }

    /** @api */
    public static function handleAddUserRole($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        try {
            $result = rex_user_role_service::addRole([
                'name' => $Data['name'],
                'description' => $Data['description'] ?? '',
                'perms' => $Data['perms'] ?? [],
            ]);

            return new Response(json_encode($result), 201);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'exists') ? 409 : 400;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateUserRole($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $roleId = (int) $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Build update data array with only set values
        $updateData = [];
        if (null !== $Data['name']) {
            $updateData['name'] = $Data['name'];
        }
        if (null !== $Data['description']) {
            $updateData['description'] = $Data['description'];
        }
        if (null !== $Data['perms']) {
            $updateData['perms'] = $Data['perms'];
        }

        try {
            $result = rex_user_role_service::updateRole($roleId, $updateData);
            return new Response(json_encode($result), 200);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteUserRole($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $roleId = (int) $Parameter['id'];

        try {
            $result = rex_user_role_service::deleteRole($roleId);
            return new Response(json_encode($result), 200);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 409;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDuplicateUserRole($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $roleId = (int) $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        $newName = null;
        if (is_array($Data) && isset($Parameter['Body'])) {
            try {
                $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
                $newName = $Data['name'] ?? null;
            } catch (Exception $e) {
                // Optional body, ignore errors
            }
        }

        try {
            $result = rex_user_role_service::duplicateRole($roleId, $newName);
            return new Response(json_encode($result), 201);
        } catch (rex_api_exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return new Response(json_encode(['error' => $e->getMessage()]), $code);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
