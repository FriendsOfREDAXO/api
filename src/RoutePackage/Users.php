<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Service\UserRoleService;
use FriendsOfRedaxo\Api\Service\UserService;
use InvalidArgumentException;
use Override;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;

use const JSON_PRETTY_PRINT;

class Users extends RoutePackage
{
    #[Override]
    public function loadRoutes(): void
    {
        // Users List
        RouteCollection::registerRoute(
            'users/list',
            new Route(
                'users',
                [
                    '_controller' => self::class . '::handleUsersList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => ['type' => 'string', 'required' => false, 'default' => null],
                                'login' => ['type' => 'string', 'required' => false, 'default' => null],
                                'email' => ['type' => 'string', 'required' => false, 'default' => null],
                                'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'admin' => ['type' => 'integer', 'required' => false, 'default' => null],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
                        ],
                        'page' => ['type' => 'int', 'required' => false, 'default' => 1],
                        'per_page' => ['type' => 'int', 'required' => false, 'default' => 100],
                        'sort' => ['type' => 'string', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );

        // User Get Details
        RouteCollection::registerRoute(
            'users/get',
            new Route(
                'users/{id}',
                [
                    '_controller' => self::class . '::handleGetUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get user details',
            null,
            new BearerAuth(),
        );

        // User Delete
        RouteCollection::registerRoute(
            'users/delete',
            new Route(
                'users/{id}',
                [
                    '_controller' => self::class . '::handleDeleteUser',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a user',
            null,
            new BearerAuth(),
        );

        // List roles assigned to a user
        RouteCollection::registerRoute(
            'users/role/list',
            new Route(
                'users/{id}/role',
                [
                    '_controller' => self::class . '::handleListUserRoles',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'List roles assigned to a user',
            null,
            new BearerAuth(),
        );

        // Assign a role to a user
        RouteCollection::registerRoute(
            'users/role/assign',
            new Route(
                'users/{id}/role/{role_id}',
                [
                    '_controller' => self::class . '::handleAssignUserRole',
                ],
                ['id' => '\d+', 'role_id' => '\d+'],
                [],
                '',
                [],
                ['POST'],
            ),
            'Assign a role to a user',
            null,
            new BearerAuth(),
        );

        // Remove a role from a user
        RouteCollection::registerRoute(
            'users/role/remove',
            new Route(
                'users/{id}/role/{role_id}',
                [
                    '_controller' => self::class . '::handleRemoveUserRole',
                ],
                ['id' => '\d+', 'role_id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Remove a role from a user',
            null,
            new BearerAuth(),
        );

        // User Roles List
        RouteCollection::registerRoute(
            'users/roles/list',
            new Route(
                'users/roles',
                [
                    '_controller' => self::class . '::handleUserRolesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => ['type' => 'string', 'required' => false, 'default' => null],
                            ],
                            'type' => 'array',
                            'required' => false,
                            'default' => [],
                        ],
                        'page' => ['type' => 'int', 'required' => false, 'default' => 1],
                        'per_page' => ['type' => 'int', 'required' => false, 'default' => 100],
                        'sort' => ['type' => 'string', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );

        // User Add
        RouteCollection::registerRoute(
            'users/add',
            new Route(
                'users',
                [
                    '_controller' => self::class . '::handleAddUser',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                        'login' => ['type' => 'string', 'required' => true],
                        'password' => ['type' => 'string', 'required' => true],
                        'email' => ['type' => 'string', 'required' => false, 'default' => ''],
                        'status' => ['type' => 'integer', 'required' => false, 'default' => 1],
                        'admin' => ['type' => 'integer', 'required' => false, 'default' => 0],
                        'language' => ['type' => 'string', 'required' => false, 'default' => 'de_de'],
                        'startpage' => ['type' => 'string', 'required' => false, 'default' => 'structure'],
                        'role' => ['type' => 'string', 'required' => false, 'default' => null],
                        'description' => ['type' => 'string', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );

        // User Update
        RouteCollection::registerRoute(
            'users/update',
            new Route(
                'users/{id}',
                [
                    '_controller' => self::class . '::handleUpdateUser',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => false, 'default' => null],
                        'login' => ['type' => 'string', 'required' => false, 'default' => null],
                        'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'language' => ['type' => 'string', 'required' => false, 'default' => null],
                        'startpage' => ['type' => 'string', 'required' => false, 'default' => null],
                        'description' => ['type' => 'string', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );

        // User Role Get Details
        RouteCollection::registerRoute(
            'users/roles/get',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => self::class . '::handleGetUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get user role details',
            null,
            new BearerAuth(),
        );

        // User Role Add
        RouteCollection::registerRoute(
            'users/roles/add',
            new Route(
                'users/roles',
                [
                    '_controller' => self::class . '::handleAddUserRole',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                        'description' => ['type' => 'string', 'required' => false, 'default' => ''],
                        'perms' => ['type' => 'array', 'required' => false, 'default' => []],
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
            new BearerAuth(),
        );

        // User Role Update
        RouteCollection::registerRoute(
            'users/roles/update',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => self::class . '::handleUpdateUserRole',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => false, 'default' => null],
                        'description' => ['type' => 'string', 'required' => false, 'default' => null],
                        'perms' => ['type' => 'array', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );

        // User Role Delete
        RouteCollection::registerRoute(
            'users/roles/delete',
            new Route(
                'users/roles/{id}',
                [
                    '_controller' => self::class . '::handleDeleteUserRole',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a user role',
            null,
            new BearerAuth(),
        );

        // User Role Duplicate
        RouteCollection::registerRoute(
            'users/roles/duplicate',
            new Route(
                'users/roles/{id}/duplicate',
                [
                    '_controller' => self::class . '::handleDuplicateUserRole',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => false, 'default' => null],
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
            new BearerAuth(),
        );
    }

    private static function checkAdminPerm(?User $user): ?Response
    {
        if (null === $user) {
            return null;
        }

        if (!$user->admin) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }

    /**
     * Maps a service-layer exception to an HTTP status. Service messages come from `I18n::msg()` and may
     * be German or English depending on the configured backend language — match both so the status is
     * stable across locales.
     */
    private static function statusFromApiException(ApiFunctionException $e, int $defaultCode): int
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'not found') || str_contains($msg, 'nicht gefunden')) {
            return 404;
        }

        if (str_contains($msg, 'exists') || str_contains($msg, 'existiert')) {
            return 409;
        }

        return $defaultCode;
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUsersList(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $filter = [];
        foreach (['name', 'login'] as $column) {
            if (null !== ($query['filter'][$column] ?? null)) {
                $filter[$column] = $query['filter'][$column];
            }
        }
        if (is_string($query['filter']['email'] ?? null)) {
            $filter['email'] = $query['filter']['email'];
        }
        foreach (['status', 'admin'] as $column) {
            if (null !== ($query['filter'][$column] ?? null)) {
                $filter[$column] = 1 === (int) $query['filter'][$column] ? 1 : 0;
            }
        }

        $users = UserService::getList($filter);

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'name', 'login', 'email', 'status', 'admin', 'createdate', 'updatedate', 'lastlogin'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : $query['per_page'];
        $page = 1 > $query['page'] ? 1 : $query['page'];

        $result = ListHelper::paginateArray($users, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetUser(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            return new JsonResponse(json_encode(UserService::getUser((int) $parameter['id']), JSON_PRETTY_PRINT), 200, [], true);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddUser(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        try {
            $result = UserService::addUser([
                'login' => (string) $data['login'],
                'password' => (string) $data['password'],
                'name' => (string) ($data['name'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'status' => (int) ($data['status'] ?? 1),
                'admin' => (int) ($data['admin'] ?? 0),
                'language' => (string) ($data['language'] ?? 'de_de'),
                'startpage' => (string) ($data['startpage'] ?? 'structure'),
                'role' => (string) ($data['role'] ?? ''),
            ]);

            return new JsonResponse($result, 201);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 400));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateUser(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        // Build update data array with only set values
        $updateData = [];
        foreach (['name', 'status', 'language', 'startpage'] as $column) {
            if (null !== $data[$column]) {
                $updateData[$column] = $data[$column];
            }
        }
        if (array_key_exists('description', $data) && null !== $data['description']) {
            $updateData['description'] = $data['description'];
        }

        try {
            return new JsonResponse(UserService::updateUser((int) $parameter['id'], $updateData), 200);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 400));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteUser(array $parameter, array $route = []): Response
    {
        $currentUser = RouteCollection::getBackendUser($route);
        $permResponse = self::checkAdminPerm($currentUser);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            return new JsonResponse(UserService::deleteUser((int) $parameter['id'], $currentUser?->id), 200);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 409));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleListUserRoles(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $parameter['id'];
        $roleIds = self::loadUserRoleIds($userId);

        if (null === $roleIds) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $roles = [];
        if (count($roleIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $rows = Sql::factory()->getArray(
                'SELECT id, name, description FROM ' . Core::getTable('user_role') . ' WHERE id IN (' . $placeholders . ') ORDER BY id',
                $roleIds,
            );
            $roles = array_map(static fn (array $r): array => [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'description' => (string) $r['description'],
            ], $rows);
        }

        return new JsonResponse(json_encode([
            'user_id' => $userId,
            'data' => $roles,
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAssignUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $parameter['id'];
        $roleId = (int) $parameter['role_id'];

        $current = self::loadUserRoleIds($userId);
        if (null === $current) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $roleRows = Sql::factory()->getArray('SELECT id FROM ' . Core::getTable('user_role') . ' WHERE id = :id', [':id' => $roleId]);
        if (0 === count($roleRows)) {
            return new JsonResponse(['error' => 'Role not found'], 404);
        }

        if (in_array($roleId, $current, true)) {
            return new JsonResponse(['error' => 'Role already assigned'], 409);
        }

        $current[] = $roleId;
        sort($current);
        self::storeUserRoleIds($userId, $current);

        return new JsonResponse([
            'message' => 'Role assigned',
            'user_id' => $userId,
            'role_id' => $roleId,
            'roles' => $current,
        ], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleRemoveUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $userId = (int) $parameter['id'];
        $roleId = (int) $parameter['role_id'];

        $current = self::loadUserRoleIds($userId);
        if (null === $current) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        if (!in_array($roleId, $current, true)) {
            return new JsonResponse(['error' => 'Role not assigned to user'], 404);
        }

        $remaining = array_values(array_filter($current, static fn (int $r): bool => $r !== $roleId));
        self::storeUserRoleIds($userId, $remaining);

        return new JsonResponse([
            'message' => 'Role removed',
            'user_id' => $userId,
            'role_id' => $roleId,
            'roles' => $remaining,
        ], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUserRolesList(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $filter = [];
        if (null !== ($query['filter']['name'] ?? null)) {
            $filter['name'] = $query['filter']['name'];
        }

        $roles = UserRoleService::getList($filter);

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'name', 'description', 'createdate', 'updatedate'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : $query['per_page'];
        $page = 1 > $query['page'] ? 1 : $query['page'];

        $result = ListHelper::paginateArray($roles, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            return new JsonResponse(json_encode(UserRoleService::getRole((int) $parameter['id']), JSON_PRETTY_PRINT), 200, [], true);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        try {
            $result = UserRoleService::addRole([
                'name' => (string) $data['name'],
                'description' => (string) ($data['description'] ?? ''),
                'perms' => is_array($data['perms'] ?? null) ? $data['perms'] : [],
            ]);

            return new JsonResponse($result, 201);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 400));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        // Build update data array with only set values
        $updateData = [];
        foreach (['name', 'description', 'perms'] as $column) {
            if (null !== $data[$column]) {
                $updateData[$column] = $data[$column];
            }
        }

        try {
            return new JsonResponse(UserRoleService::updateRole((int) $parameter['id'], $updateData), 200);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 400));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            return new JsonResponse(UserRoleService::deleteRole((int) $parameter['id']), 200);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 409));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDuplicateUserRole(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        $newName = null;
        if (is_array($data) && isset($parameter['Body'])) {
            try {
                $data = RouteCollection::getQuerySet($data, $parameter['Body']);
                $newName = null !== $data['name'] ? (string) $data['name'] : null;
            } catch (Exception) {
                // Optional body, ignore errors
            }
        }

        try {
            return new JsonResponse(UserRoleService::duplicateRole((int) $parameter['id'], $newName), 201);
        } catch (ApiFunctionException $e) {
            return new JsonResponse(['error' => $e->getMessage()], self::statusFromApiException($e, 400));
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Returns sorted, unique role IDs from a comma-separated `user.role` string.
     * Empty strings and bogus tokens (non-digits) are dropped.
     *
     * @return list<int>
     */
    private static function parseRoleIds(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ('' !== $part && ctype_digit($part)) {
                $ids[(int) $part] = (int) $part;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    /**
     * Returns sorted role IDs of a user, or `null` if the user does not exist.
     *
     * @return list<int>|null
     */
    private static function loadUserRoleIds(int $userId): ?array
    {
        $rows = Sql::factory()->getArray('SELECT role FROM ' . Core::getTable('user') . ' WHERE id = :id', [':id' => $userId]);

        if (0 === count($rows)) {
            return null;
        }

        return self::parseRoleIds((string) ($rows[0]['role'] ?? ''));
    }

    /**
     * Persists role IDs to `rex_user.role` and mirrors the side effects the backend users page performs
     * after any user update: clear the user instance cache and fire USER_UPDATED.
     *
     * @param list<int> $roleIds
     */
    private static function storeUserRoleIds(int $userId, array $roleIds): void
    {
        $update = Sql::factory();
        $update->setTable(Core::getTable('user'));
        $update->setWhere(['id' => $userId]);
        $update->setValue('role', implode(',', $roleIds));
        $update->addGlobalUpdateFields(UserService::getCurrentUserLogin());
        $update->update();

        User::clearInstance($userId);

        Extension::dispatch(new ExtensionPoint('USER_UPDATED', '', [
            'id' => $userId,
            'user' => User::require($userId),
            'password' => null,
        ], readonly: true));
    }
}
