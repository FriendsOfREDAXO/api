<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_clang;
use rex_clang_service;
use rex_extension;
use rex_extension_point;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;

use const JSON_PRETTY_PRINT;

class Clangs extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Clangs List ✅
        RouteCollection::registerRoute(
            'system/clangs/list',
            new Route(
                'system/clangs',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Clangs::handleClangsList',
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

        // Clang Get Details ✅
        RouteCollection::registerRoute(
            'system/clangs/get',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Clangs::handleGetClang',
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

        // Clang Add ✅
        RouteCollection::registerRoute(
            'system/clangs/add',
            new Route(
                'system/clangs',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Clangs::handleAddClang',
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

        // Clang Update ✅
        RouteCollection::registerRoute(
            'system/clangs/update',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Clangs::handleUpdateClang',
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

        // Clang Delete ✅
        RouteCollection::registerRoute(
            'system/clangs/delete',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Clangs::handleDeleteClang',
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
    public static function handleClangsList($Parameter, array $Route = []): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        // Get all languages from rex_clang
        $allClangs = rex_clang::getAll();
        $filteredClangs = [];

        $user = RouteCollection::getBackendUser($Route);

        // Apply filters manually
        foreach ($allClangs as $clang) {
            // Filter by user clang permission for backend users
            if (null !== $user && !$user->isAdmin()) {
                $clangPerm = $user->getComplexPerm('clang');
                if (!$clangPerm->hasPerm($clang->getId())) {
                    continue;
                }
            }

            // Filter by code if specified
            if (isset($Query['filter']['code']) && null !== $Query['filter']['code']) {
                if (false === stripos($clang->getCode(), $Query['filter']['code'])) {
                    continue;
                }
            }

            // Filter by name if specified
            if (isset($Query['filter']['name']) && null !== $Query['filter']['name']) {
                if (false === stripos($clang->getName(), $Query['filter']['name'])) {
                    continue;
                }
            }

            // Filter by status if specified
            if (isset($Query['filter']['status']) && null !== $Query['filter']['status']) {
                $isOnline = (bool) $Query['filter']['status'];
                if ($clang->isOnline() !== $isOnline) {
                    continue;
                }
            }

            // Convert clang object to array with desired fields
            $filteredClangs[] = [
                'id' => $clang->getId(),
                'code' => $clang->getCode(),
                'name' => $clang->getName(),
                'priority' => $clang->getPriority(),
                'status' => $clang->isOnline() ? 1 : 0,
            ];
        }

        // Sort by priority (already done by rex_clang internally)

        return new Response(json_encode($filteredClangs, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetClang($Parameter, array $Route = []): Response
    {
        $clangId = $Parameter['id'];
        $clang = rex_clang::get($clangId);

        if (!$clang) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        if (null !== $user && !$user->isAdmin()) {
            $clangPerm = $user->getComplexPerm('clang');
            if (!$clangPerm->hasPerm($clang->getId())) {
                return new Response(json_encode(['error' => 'Permission denied']), 403);
            }
        }

        $clangData = [
            'id' => $clang->getId(),
            'code' => $clang->getCode(),
            'name' => $clang->getName(),
            'priority' => $clang->getPriority(),
            'status' => $clang->isOnline() ? 1 : 0,
        ];

        return new Response(json_encode($clangData, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddClang($Parameter, array $Route = []): Response
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

        $Data['status'] = (1 == $Data['status']) ? 1 : 0;

        $allClangs = rex_clang::getAll();
        foreach ($allClangs as $clang) {
            if ($clang->getCode() === $Data['code']) {
                return new Response(json_encode(['error' => 'Language code already exists']), 409);
            }
            if ($clang->getName() === $Data['name']) {
                return new Response(json_encode(['error' => 'Language name already exists']), 409);
            }
        }

        try {
            // Register to the extension point to get the created ID
            $clangId = null;
            rex_extension::register('CLANG_ADDED', static function (rex_extension_point $ep) use (&$clangId) {
                $Params = $ep->getParams();
                $clangId = $Params['id'];
            });

            // Add clang - this will trigger the CLANG_ADDED extension point internally
            rex_clang_service::addCLang($Data['code'], $Data['name'], $Data['priority'], $Data['status']);

            if (null === $clangId) {
                return new Response(json_encode(['error' => 'Failed to create language']), 500);
            }

            rex_clang::reset(); // Ensure we get fresh data
            $clang = rex_clang::get($clangId);

            return new Response(json_encode([
                'message' => 'Language created',
                'id' => $clangId,
            ]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateClang($Parameter, array $Route = []): Response
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

        $Clang = rex_clang::get($Parameter['id']);
        if (!$Clang) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        foreach ($Data as $key => $value) {
            if (null === $value) {
                unset($Data[$key]);
            }
        }

        if (0 === count($Data)) {
            return new Response(json_encode(['error' => 'No data provided']), 400);
        }

        if (null !== $Data['code']) {
            $allClangs = rex_clang::getAll();
            foreach ($allClangs as $allClang) {
                if ($allClang->getId() != $Parameter['id']) {
                    if ($Data['code'] && $allClang->getCode() === $Data['code']) {
                        return new Response(json_encode(['error' => 'Language code already exists']), 409);
                    }
                    if ($Data['name'] && $allClang->getName() === $Data['name']) {
                        return new Response(json_encode(['error' => 'Language code already exists']), 409);
                    }
                }
            }
        }

        $code = $Data['code'] ?? $Clang->getCode();
        $name = $Data['name'] ?? $Clang->getName();
        $priority = $Data['priority'] ?? $Clang->getPriority();
        $status = $Data['status'] ?? ($Clang->isOnline() ? 1 : 0);

        try {
            // Update using the service which handles priorities and cache
            // This will trigger the CLANG_UPDATED extension point internally
            $result = rex_clang_service::editCLang($Parameter['id'], $code, $name, $priority, $status);

            if (false === $result) {
                return new Response(json_encode(['error' => 'Failed to update language']), 500);
            }

            return new Response(json_encode([
                'message' => 'Language updated',
                'id' => $Parameter['id'],
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteClang($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $clang = rex_clang::get($Parameter['id']);

        if (!$clang) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        if (count(rex_clang::getAll()) <= 1) {
            return new Response(json_encode(['error' => 'Cannot delete the last language']), 409);
        }

        try {
            rex_clang_service::deleteCLang($Parameter['id']);
            return new Response(json_encode([
                'message' => 'Language deleted',
                'id' => $Parameter['id'],
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
