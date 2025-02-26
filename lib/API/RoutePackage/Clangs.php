<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_clang;
use rex_clang_service;
use rex_extension;
use rex_i18n;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;
use const JSON_PRETTY_PRINT;

class Clangs extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Clangs List
        RouteCollection::registerRoute(
            'system/clangs/list',
            new Route(
                'system/clangs',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Clangs::handleClangsList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
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
                            'type' => 'integer',
                            'required' => false,
                            'default' => 1,
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 100,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET']),
            'Access to the list of languages',
        );

        // Clang Get Details
        RouteCollection::registerRoute(
            'system/clangs/get',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Clangs::handleGetClang',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get language details',
        );

        // Clang Add
        RouteCollection::registerRoute(
            'system/clangs/add',
            new Route(
                'system/clangs',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Clangs::handleAddClang',
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
                            'description' => 'Sort priority',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 1,
                            'description' => 'Active status (1 = active, 0 = inactive)',
                        ],
                    ],
                    'bodyContentType' => 'application/json'
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Add a language',
        );

        // Clang Update
        RouteCollection::registerRoute(
            'system/clangs/update',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Clangs::handleUpdateClang',
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
                    'bodyContentType' => 'application/json'
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a language',
        );

        // Clang Delete
        RouteCollection::registerRoute(
            'system/clangs/delete',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Clangs::handleDeleteClang',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a language',
        );
    }

    /** @api */
    public static function handleClangsList($Parameter): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        // Get all languages from rex_clang
        $allClangs = rex_clang::getAll();
        $filteredClangs = [];
        
        // Apply filters manually
        foreach ($allClangs as $clang) {
            // Filter by ID if specified
            if (isset($Query['filter']['id']) && $Query['filter']['id'] !== null) {
                if ($clang->getId() != $Query['filter']['id']) {
                    continue;
                }
            }
            
            // Filter by code if specified
            if (isset($Query['filter']['code']) && $Query['filter']['code'] !== null) {
                if (stripos($clang->getCode(), $Query['filter']['code']) === false) {
                    continue;
                }
            }
            
            // Filter by name if specified
            if (isset($Query['filter']['name']) && $Query['filter']['name'] !== null) {
                if (stripos($clang->getName(), $Query['filter']['name']) === false) {
                    continue;
                }
            }
            
            // Filter by status if specified
            if (isset($Query['filter']['status']) && $Query['filter']['status'] !== null) {
                $isOnline = (bool)$Query['filter']['status'];
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
                'revision' => $clang->getValue('revision')
            ];
        }
        
        // Sort by priority (already done by rex_clang internally)
        
        // Apply pagination
        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;
        
        $paginatedClangs = array_slice($filteredClangs, $start, $per_page);

        return new Response(json_encode($paginatedClangs, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetClang($Parameter): Response
    {
        $clangId = $Parameter['id'];

        // Check if clang exists
        if (!rex_clang::exists($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        // Get clang object and convert to array
        $clang = rex_clang::get($clangId);
        $clangData = [
            'id' => $clang->getId(),
            'code' => $clang->getCode(),
            'name' => $clang->getName(),
            'priority' => $clang->getPriority(),
            'status' => $clang->isOnline() ? 1 : 0,
            'revision' => $clang->getValue('revision')
        ];

        return new Response(json_encode($clangData, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddClang($Parameter): Response
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

        // Check if code already exists using rex_clang objects
        $allClangs = rex_clang::getAll();
        foreach ($allClangs as $clang) {
            if ($clang->getCode() === $Data['code']) {
                return new Response(json_encode(['error' => 'Language code already exists']), 409);
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
            
            if ($clangId === null) {
                return new Response(json_encode(['error' => 'Failed to create language']), 500);
            }
            
            // Get the created clang
            rex_clang::reset(); // Ensure we get fresh data
            $clang = rex_clang::get($clangId);
            
            // Return the created clang data
            return new Response(json_encode([
                'message' => 'Language created', 
                'id' => $clangId,
                'code' => $clang->getCode(),
                'name' => $clang->getName(),
                'priority' => $clang->getPriority(),
                'status' => $clang->isOnline() ? 1 : 0
            ]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateClang($Parameter): Response
    {
        $clangId = $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Check if clang exists
        if (!rex_clang::exists($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        // Check if code already exists if code is being updated
        if ($Data['code'] !== null) {
            $allClangs = rex_clang::getAll();
            foreach ($allClangs as $clang) {
                if ($clang->getId() != $clangId && $clang->getCode() === $Data['code']) {
                    return new Response(json_encode(['error' => 'Language code already exists']), 409);
                }
            }
        }

        // Get the current values for fields that weren't provided
        $clang = rex_clang::get($clangId);
        $code = $Data['code'] ?? $clang->getCode();
        $name = $Data['name'] ?? $clang->getName();
        $priority = $Data['priority'] ?? $clang->getPriority();
        $status = $Data['status'] ?? ($clang->isOnline() ? 1 : 0);

        try {
            // Update using the service which handles priorities and cache
            // This will trigger the CLANG_UPDATED extension point internally
            $result = rex_clang_service::editCLang($clangId, $code, $name, $priority, $Data['status'] !== null ? $Data['status'] : null);
            
            if ($result === false) {
                return new Response(json_encode(['error' => 'Failed to update language']), 500);
            }

            // Get updated clang
            rex_clang::reset(); // Ensure we get fresh data
            $updatedClang = rex_clang::get($clangId);
            
            return new Response(json_encode([
                'message' => 'Language updated',
                'id' => $clangId,
                'code' => $updatedClang->getCode(),
                'name' => $updatedClang->getName(),
                'priority' => $updatedClang->getPriority(),
                'status' => $updatedClang->isOnline() ? 1 : 0
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteClang($Parameter): Response
    {
        $clangId = $Parameter['id'];

        // Check if clang exists
        if (!rex_clang::exists($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        // Don't allow deletion of the last language
        if (count(rex_clang::getAll()) <= 1) {
            return new Response(json_encode(['error' => 'Cannot delete the last language']), 409);
        }

        // Store language details before deletion for the response
        $clang = rex_clang::get($clangId);
        $clangData = [
            'id' => $clang->getId(),
            'code' => $clang->getCode(),
            'name' => $clang->getName(),
            'priority' => $clang->getPriority(),
            'status' => $clang->isOnline() ? 1 : 0
        ];

        try {
            // Delete the language - this will trigger the CLANG_DELETED extension point internally
            rex_clang_service::deleteCLang($clangId);
            
            return new Response(json_encode([
                'message' => 'Language deleted',
                'id' => $clangData['id'],
                'name' => $clangData['name'],
                'code' => $clangData['code'],
                'priority' => $clangData['priority'],
                'status' => $clangData['status']
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
