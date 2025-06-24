<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_article_cache;
use rex_extension;
use rex_extension_point;
use rex_module_cache;
use rex_sql;
use rex_sql_exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;

use const JSON_PRETTY_PRINT;

class Modules extends RoutePackage
{
    public const ModuleFields = ['id', 'name', 'key', 'input', 'output', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        // Modules List ✅
        RouteCollection::registerRoute(
            'modules/list',
            new Route(
                'modules',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Modules::handleModulesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'key' => [
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
                ['GET']),
            'Access to the list of modules',
            null,
            new BearerAuth()
        );

        // Modules Add ✅
        RouteCollection::registerRoute(
            'modules/add',
            new Route(
                'modules',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Modules::handleAddModules',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'key' => [
                            'type' => 'string',
                            'required' => true,
                            'default' => '',
                        ],
                        'input' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'output' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Add a module',
            null,
            new BearerAuth()
        );

        // Modules Get Details ✅
        RouteCollection::registerRoute(
            'modules/get',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Modules::handleGetModules',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get module details',
            null,
            new BearerAuth()
        );

        // Modules Update ✅
        RouteCollection::registerRoute(
            'modules/update',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Modules::handleUpdateModules',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'key' => [ // Added key field
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'input' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'output' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        // Removed attributes
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a module',
            null,
            new BearerAuth()
        );

        // Modules Delete ✅
        RouteCollection::registerRoute(
            'modules/delete',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Modules::handleDeleteModules',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a module',
            null,
            new BearerAuth()
        );
    }

    /** @api */
    public static function handleModulesList($Parameter): Response
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

        // Add filter for 'key'
        if (null !== $Query['filter']['key']) {
            $SqlQueryWhere[':key'] = '`key` LIKE :key'; // Use backticks for the 'key' column
            $SqlParameters[':key'] = '%' . $Query['filter']['key'] . '%';
        }

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        // TODO
        // - is_in_use

        $ModulesSQL = rex_sql::factory();
        $Modules = $ModulesSQL->getArray(
            '
        SELECT
            `' . implode('`,`', self::ModuleFields) . '`
        FROM
            ' . rex::getTablePrefix() . 'module
            ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
        ORDER BY name ASC
        LIMIT :start, :per_page
        ',
            $SqlParameters,
        );

        return new Response(json_encode($Modules, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetModules($Parameter): Response
    {
        $moduleId = $Parameter['id'];

        $ModulesSQL = rex_sql::factory();
        $ModulesData = $ModulesSQL->getArray(
            'SELECT `' . implode('`,`', self::ModuleFields) . '` FROM ' . rex::getTablePrefix() . 'module WHERE id = :id',
            [':id' => $moduleId],
        );

        if (empty($ModulesData)) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        return new Response(json_encode($ModulesData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddModules($Parameter): Response
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

        try {
            // from structure/content plugin modules.modules.php
            // throws SQL Exception if key exists

            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('module'));
            $sql->setValue('name', $Data['name'] ?? '');
            $sql->setValue('key', $Data['key'] ?? '');
            $sql->setValue('input', $Data['input'] ?? '');
            $sql->setValue('output', $Data['output'] ?? '');
            $sql->setValue('createdate', date('Y-m-d H:i:s'));
            $sql->setValue('createuser', 'API');
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->insert();
            $moduleId = $sql->getLastId();

            rex_extension::registerPoint(new rex_extension_point('MODULE_ADDED', '', [
                'id' => $moduleId,
                'name' => $Data['name'],
                'key' => $Data['key'],
                'input' => $Data['input'],
                'output' => $Data['output'],
            ]));

            return new Response(json_encode(['message' => 'Module created', 'id' => $moduleId]), 201);
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'conflict_key_already_exists']), 409);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateModules($Parameter): Response
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

        $Module = rex_sql::factory();
        $Module->setQuery('SELECT id FROM ' . rex::getTable('module') . ' WHERE id = :id', [':id' => $Parameter['id']]);

        if (0 === $Module->getRows()) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        $Data['name'] ??= $Module->getValue('name');
        $Data['key'] ??= $Module->getValue('key');
        $Data['input'] ??= $Module->getValue('input');
        $Data['output'] ??= $Module->getValue('output');

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('module'));
            $sql->setWhere(['id' => $Parameter['id']]);
            $sql->setValue('name', $Data['name']);
            $sql->setValue('key', $Data['key']); // throws SQL Exception if new key exists
            $sql->setValue('input', $Data['input']);
            $sql->setValue('output', $Data['output']);
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');
            $sql->update();

            rex_module_cache::delete($Parameter['id']);

            rex_extension::registerPoint(new rex_extension_point('MODULE_UPDATED', '', [
                'id' => $Parameter['id'],
                'name' => $Data['name'],
                'key' => $Data['key'],
                'input' => $Data['input'],
                'output' => $Data['output'],
            ]));

            if ($Data['output'] != $Module->getValue('output')) {
                $gc = rex_sql::factory();
                $gc->setQuery(
                    'SELECT DISTINCT(' . rex::getTablePrefix() . 'article.id) FROM ' . rex::getTablePrefix() . 'article
                                LEFT JOIN ' . rex::getTablePrefix() . 'article_slice ON ' . rex::getTablePrefix(
                    ) . 'article.id=' . rex::getTablePrefix() . 'article_slice.article_id
                                WHERE ' . rex::getTablePrefix() . 'article_slice.module_id=?',
                    [$Parameter['id']],
                );
                for ($i = 0; $i < $gc->getRows(); ++$i) {
                    rex_article_cache::delete($gc->getValue(rex::getTablePrefix() . 'article.id'));
                    $gc->next();
                }
            }

            return new Response(json_encode(['message' => 'Module updated', 'id' => $Parameter['id']]), 200);
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'conflict_key_already_exists']), 409);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteModules($Parameter): Response
    {
        $Module = rex_sql::factory();
        $Module->setQuery('SELECT id FROM ' . rex::getTable('module') . ' WHERE id = :id', [':id' => $Parameter['id']]);

        if (0 === $Module->getRows()) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        try {
            $usageCheck = rex_sql::factory();
            $usageCheck->setQuery(
                'SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE module_id = :id LIMIT 1',
                [':id' => $Parameter['id']],
            );

            if ($usageCheck->getRows() > 0) {
                return new Response(json_encode([
                    'error' => 'Cannot delete module. It is in use by one or more slices.',
                ]), 409);
            }

            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('module') . ' WHERE id = :id',
                [':id' => $Parameter['id']],
            );

            $del = rex_sql::factory();
            $del->setQuery(
                'DELETE FROM ' . rex::getTable('module_action') . ' WHERE module_id= :id',
                [':id' => $Parameter['id']]);

            rex_module_cache::delete($Parameter['id']);
            rex_extension::registerPoint(new rex_extension_point('MODULE_DELETED', '', [
                'id' => $Parameter['id'],
            ]));

            return new Response(json_encode(['message' => 'Module deleted', 'id' => $Parameter['id']]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
