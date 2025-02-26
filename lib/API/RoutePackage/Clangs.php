<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_clang;
use rex_clang_service;
use rex_i18n;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;
use const JSON_PRETTY_PRINT;

class ClangsAPI extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Clangs List
        RouteCollection::registerRoute(
            'system/clangs/list',
            new Route(
                'system/clangs',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\ClangsAPI::handleClangsList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'int',
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
                ['GET']),
            'Access to the list of languages',
        );

        // Clang Get Details
        RouteCollection::registerRoute(
            'system/clangs/get',
            new Route(
                'system/clangs/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\ClangsAPI::handleGetClang',
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
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\ClangsAPI::handleAddClang',
                    'Body' => [
                        'code' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'priority' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
                        ],
                        'status' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 1,
                        ],
                    ],
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
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\ClangsAPI::handleUpdateClang',
                    'Body' => [
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
                        'priority' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'status' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
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
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\ClangsAPI::handleDeleteClang',
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

        $fields = ['id', 'code', 'name', 'priority', 'status', 'revision'];

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (isset($Query['filter']['id']) && $Query['filter']['id'] !== null) {
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (isset($Query['filter']['code']) && $Query['filter']['code'] !== null) {
            $SqlQueryWhere[':code'] = 'code LIKE :code';
            $SqlParameters[':code'] = '%' . $Query['filter']['code'] . '%';
        }

        if (isset($Query['filter']['name']) && $Query['filter']['name'] !== null) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        if (isset($Query['filter']['status']) && $Query['filter']['status'] !== null) {
            $SqlQueryWhere[':status'] = 'status = :status';
            $SqlParameters[':status'] = $Query['filter']['status'];
        }

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $ClangsSQL = rex_sql::factory();
        $Clangs = $ClangsSQL->getArray(
            '
            SELECT
                ' . implode(',', $fields) . '
            FROM
                ' . rex::getTablePrefix() . 'clang
            ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
            ORDER BY priority ASC
            LIMIT :start, :per_page
            ',
            $SqlParameters,
        );

        return new Response(json_encode($Clangs, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetClang($Parameter): Response
    {
        $clangId = $Parameter['id'];

        // Check if clang exists
        if (!rex_clang::exists($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        $ClangSQL = rex_sql::factory();
        $ClangData = $ClangSQL->getArray(
            'SELECT id, code, name, priority, status, revision FROM ' . rex::getTablePrefix() . 'clang WHERE id = :id',
            [':id' => $clangId]
        );

        if (empty($ClangData)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        return new Response(json_encode($ClangData[0], JSON_PRETTY_PRINT));
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

        // Check if code already exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTablePrefix() . 'clang WHERE code = :code', [':code' => $Data['code']]);

        if ($checkSql->getRows() > 0) {
            return new Response(json_encode(['error' => 'Language code already exists']), 409);
        }

        try {
            // Register to the extension point to get the created ID
            $clangId = null;
            rex_extension::register('CLANG_ADDED', static function (rex_extension_point $ep) use (&$clangId) {
                $Params = $ep->getParams();
                $clangId = $Params['id'];
            });

            // Add clang and trigger CLANG_ADDED extension point
            rex_clang_service::addCLang($Data['code'], $Data['name'], $Data['priority'], $Data['status']);
            
            if ($clangId === null) {
                return new Response(json_encode(['error' => 'Failed to create language']), 500);
            }

            return new Response(json_encode(['message' => 'Language created', 'id' => $clangId]), 201);
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
            $checkSql = rex_sql::factory();
            $checkSql->setQuery('SELECT id FROM ' . rex::getTablePrefix() . 'clang WHERE code = :code AND id != :id', 
                [':code' => $Data['code'], ':id' => $clangId]);

            if ($checkSql->getRows() > 0) {
                return new Response(json_encode(['error' => 'Language code already exists']), 409);
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
            $result = rex_clang_service::editCLang($clangId, $code, $name, $priority, $Data['status'] !== null ? $Data['status'] : null);
            
            if ($result === false) {
                return new Response(json_encode(['error' => 'Failed to update language']), 500);
            }

            return new Response(json_encode(['message' => 'Language updated', 'id' => $clangId]), 200);
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
        if (rex_clang::count() <= 1) {
            return new Response(json_encode(['error' => 'Cannot delete the last language']), 409);
        }

        // Store language details before deletion for the response
        $clang = rex_clang::get($clangId);

        try {
            // The service method returns void but throws exceptions on error
            rex_clang_service::deleteCLang($clangId);
            
            return new Response(json_encode([
                'message' => 'Language deleted', 
                'id' => $clangId,
                'name' => $clang->getName(),
                'code' => $clang->getCode()
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
