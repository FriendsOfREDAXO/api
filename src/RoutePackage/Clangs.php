<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Core;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\Language\LanguagePermission;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;

use const JSON_PRETTY_PRINT;

class Clangs extends RoutePackage
{
    #[Override]
    public function loadRoutes(): void
    {
        // Clangs List
        RouteCollection::registerRoute(
            'system/clangs/list',
            new Route(
                'system/clangs',
                [
                    '_controller' => self::class . '::handleClangsList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'code' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'status' => [
                                    'type' => 'integer',
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
                        'sort' => [
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
                ['GET']),
            'Access to the list of languages',
            null,
            new BearerAuth(),
        );

        // Clang Get Details
        RouteCollection::registerRoute(
            'system/clangs/get',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => self::class . '::handleGetClang',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get language details',
            null,
            new BearerAuth(),
        );

        // Clang Add
        RouteCollection::registerRoute(
            'system/clangs/add',
            new Route(
                'system/clangs',
                [
                    '_controller' => self::class . '::handleAddClang',
                    'Body' => [
                        'code' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => 'Language ISO code (e.g., "en", "de", "fr")',
                        ],
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => 'Language name',
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                            'description' => 'Priority',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 1,
                            'description' => 'Active status (1 = active, 0 = inactive)',
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Add a language',
            null,
            new BearerAuth(),
        );

        // Clang Update
        RouteCollection::registerRoute(
            'system/clangs/update',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => self::class . '::handleUpdateClang',
                    'Body' => [
                        'code' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                            'description' => 'Language ISO code (e.g., "en", "de", "fr")',
                        ],
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                            'description' => 'Language name',
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                            'description' => 'Sort priority',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                            'description' => 'Active status (1 = active, 0 = inactive)',
                        ],
                    ],
                    'bodyContentType' => 'application/json',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a language',
            null,
            new BearerAuth(),
        );

        // Clang Delete
        RouteCollection::registerRoute(
            'system/clangs/delete',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => self::class . '::handleDeleteClang',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a language',
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

    private static function hasClangPerm(?User $user, int $clangId): bool
    {
        if (null === $user || $user->admin) {
            return true;
        }

        $perm = $user->getComplexPerm('clang');

        return $perm instanceof LanguagePermission && $perm->hasPerm($clangId);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleClangsList(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $user = RouteCollection::getBackendUser($route);
        $filteredClangs = [];

        foreach (Language::getAll() as $clang) {
            // Filter by user clang permission for backend users
            if (!self::hasClangPerm($user, $clang->id)) {
                continue;
            }

            if (null !== ($query['filter']['code'] ?? null) && false === stripos($clang->code, (string) $query['filter']['code'])) {
                continue;
            }

            if (null !== ($query['filter']['name'] ?? null) && false === stripos($clang->name, (string) $query['filter']['name'])) {
                continue;
            }

            if (null !== ($query['filter']['status'] ?? null) && $clang->isOnline() !== (bool) $query['filter']['status']) {
                continue;
            }

            $filteredClangs[] = self::clangToArray($clang);
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'code', 'name', 'priority', 'status'], [['field' => 'priority', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : $query['per_page'];
        $page = 1 > $query['page'] ? 1 : $query['page'];

        $result = ListHelper::paginateArray($filteredClangs, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetClang(array $parameter, array $route = []): Response
    {
        $clang = Language::get((int) $parameter['id']);

        if (null === $clang) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        if (!self::hasClangPerm(RouteCollection::getBackendUser($route), $clang->id)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return new JsonResponse(json_encode(self::clangToArray($clang), JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddClang(array $parameter, array $route = []): Response
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

        $data['status'] = 1 == $data['status'] ? 1 : 0;

        foreach (Language::getAll() as $clang) {
            if ($clang->code === $data['code']) {
                return new JsonResponse(['error' => 'Language code already exists'], 409);
            }
            if ($clang->name === $data['name']) {
                return new JsonResponse(['error' => 'Language name already exists'], 409);
            }
        }

        try {
            // Register to the extension point to get the created ID
            $clangId = null;
            Extension::register('CLANG_ADDED', static function (ExtensionPoint $ep) use (&$clangId): void {
                $clangId = $ep->getParam('id');
            });

            LanguageHandler::addCLang((string) $data['code'], (string) $data['name'], (int) $data['priority'], (bool) $data['status']);

            if (null === $clangId) {
                return new JsonResponse(['error' => 'Failed to create language'], 500);
            }

            Language::reset();

            return new JsonResponse([
                'message' => 'Language created',
                'id' => $clangId,
            ], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateClang(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $id = (int) $parameter['id'];
        $clang = Language::get($id);

        if (null === $clang) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        foreach ($data as $key => $value) {
            if (null === $value) {
                unset($data[$key]);
            }
        }

        if (0 === count($data)) {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        foreach (Language::getAll() as $otherClang) {
            if ($otherClang->id === $id) {
                continue;
            }
            if (isset($data['code']) && $otherClang->code === $data['code']) {
                return new JsonResponse(['error' => 'Language code already exists'], 409);
            }
            if (isset($data['name']) && $otherClang->name === $data['name']) {
                return new JsonResponse(['error' => 'Language name already exists'], 409);
            }
        }

        $code = (string) ($data['code'] ?? $clang->code);
        $name = (string) ($data['name'] ?? $clang->name);
        $priority = (int) ($data['priority'] ?? $clang->priority);
        $status = isset($data['status']) ? 1 == $data['status'] : $clang->isOnline();

        try {
            // Update using the handler which takes care of priorities, cache and the CLANG_UPDATED EP
            if (!LanguageHandler::editCLang($id, $code, $name, $priority, $status)) {
                return new JsonResponse(['error' => 'Failed to update language'], 500);
            }

            Language::reset();

            return new JsonResponse([
                'message' => 'Language updated',
                'id' => $id,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteClang(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $id = (int) $parameter['id'];

        if (null === Language::get($id)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        if (Language::count() <= 1) {
            return new JsonResponse(['error' => 'Cannot delete the last language'], 409);
        }

        try {
            LanguageHandler::deleteCLang($id);
            Language::reset();

            return new JsonResponse([
                'message' => 'Language deleted',
                'id' => $id,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /** @return array<string, int|string> */
    private static function clangToArray(Language $clang): array
    {
        return [
            'id' => $clang->id,
            'code' => $clang->code,
            'name' => $clang->name,
            'priority' => $clang->priority,
            'status' => $clang->isOnline() ? 1 : 0,
        ];
    }
}
