<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_extension;
use rex_extension_point;
use rex_module_cache;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_key_exists;
use function count;
use function is_array;

use const JSON_PRETTY_PRINT;

class Modules extends RoutePackage
{
    public const ModuleFields = ['id', 'name', 'key', 'input', 'output', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        // Modules List
        RouteCollection::registerRoute(
            'modules/list',
            new Route(
                'modules',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Modules::handleModulesList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'key' => [  // Added key filter
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
        );

        // Modules Add
        RouteCollection::registerRoute(
            'modules/add',
            new Route(
                'modules',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Modules::handleAddModules',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'key' => [ // Added key field
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'input' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'output' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        // Removed attributes
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Add a module',
        );

        // Modules Get Details
        RouteCollection::registerRoute(
            'modules/get',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Modules::handleGetModules',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get module details',
        );

        // Modules Update
        RouteCollection::registerRoute(
            'modules/update',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Modules::handleUpdateModules',
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
        );

        // Modules Delete
        RouteCollection::registerRoute(
            'modules/delete',
            new Route(
                'modules/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Modules::handleDeleteModules',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a module',
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
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('module'));
            $sql->setValue('name', $Data['name']);
            // Add 'key' to the insert
            if (isset($Data['key'])) { // Check if 'key' is provided, even if it's null
                $sql->setValue('key', $Data['key']);
            }
            $sql->setValue('input', $Data['input']);
            $sql->setValue('output', $Data['output']);
            // Removed attributes

            $sql->setValue('createdate', date('Y-m-d H:i:s'));
            $sql->setValue('createuser', 'API');
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->insert();
            $moduleId = $sql->getLastId();

            return new Response(json_encode(['message' => 'Module created', 'id' => $moduleId]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateModules($Parameter): Response
    {
        $moduleId = $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Check if module exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('module') . ' WHERE id = :id', [':id' => $moduleId]);

        if (0 === $checkSql->getRows()) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('module'));
            $sql->setWhere(['id' => $moduleId]);

            // Only update fields that are provided
            if (null !== $Data['name']) {
                $sql->setValue('name', $Data['name']);
            }

            // Update 'key'
            if (array_key_exists('key', $Data)) { // Use array_key_exists to allow updating to null
                $sql->setValue('key', $Data['key']);
            }

            if (null !== $Data['input']) {
                $sql->setValue('input', $Data['input']);
            }

            if (null !== $Data['output']) {
                $sql->setValue('output', $Data['output']);
            }
            // Removed attributes

            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->update();

            return new Response(json_encode(['message' => 'Module updated', 'id' => $moduleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteModules($Parameter): Response
    {
        $moduleId = $Parameter['id'];

        // Check if module exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('module') . ' WHERE id = :id', [':id' => $moduleId]);

        if (0 === $checkSql->getRows()) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        try {
            // Check if module is in use
            $usageCheck = rex_sql::factory();
            $usageCheck->setQuery(
                'SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE module_id = :id LIMIT 1',
                [':id' => $moduleId],
            );

            if ($usageCheck->getRows() > 0) {
                return new Response(json_encode([
                    'error' => 'Cannot delete module. It is in use by one or more slices.',
                ]), 409);
            }

            // Delete module
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('module') . ' WHERE id = :id',
                [':id' => $moduleId],
            );

            rex_module_cache::delete($moduleId);
            rex_extension::registerPoint(new rex_extension_point('MODULE_DELETED', '', [
                'id' => $moduleId,
            ]));

            return new Response(json_encode(['message' => 'Module deleted', 'id' => $moduleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
