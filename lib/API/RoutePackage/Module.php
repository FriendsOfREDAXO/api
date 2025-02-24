<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_module;
use rex_module_manager;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use function count;
use const JSON_PRETTY_PRINT;

class Module extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Module List
        RouteCollection::registerRoute(
            'module/list',
            new Route(
                'module',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Module::handleModuleList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                            ],
                            'type' => 'array',
                            'required' => true,
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

        // Module Add
        RouteCollection::registerRoute(
            'module/add',
            new Route(
                'module',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Module::handleAddModule',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'input' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'output' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'attributes' => [
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
                ['POST']),
            'Add a module',
        );

        // Module Get Details
        RouteCollection::registerRoute(
            'module/get',
            new Route(
                'module/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Module::handleGetModule',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get module details',
        );

        // Module Update
        RouteCollection::registerRoute(
            'module/update',
            new Route(
                'module/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Module::handleUpdateModule',
                    'Body' => [
                        'name' => [
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
                        'attributes' => [
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
                ['PUT', 'PATCH']),
            'Update a module',
        );

        // Module Delete
        RouteCollection::registerRoute(
            'module/delete',
            new Route(
                'module/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Module::handleDeleteModule',
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
    public static function handleModuleList($Parameter): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $fields = ['id', 'name', 'input', 'output',             'createdate', 'createuser', 'updatedate', 'updateuser', 'attributes', 'revision'];

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (null !== $Query['filter']['id']) {
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (null !== $Query['filter']['name']) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
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
                ' . implode(',', $fields) . '
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
    public static function handleGetModule($Parameter): Response
    {
        $moduleId = $Parameter['id'];
        
        $ModuleSQL = rex_sql::factory();
        $ModuleData = $ModuleSQL->getArray(
            'SELECT * FROM ' . rex::getTablePrefix() . 'module WHERE id = :id',
            [':id' => $moduleId]
        );

        if (empty($ModuleData)) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        return new Response(json_encode($ModuleData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddModule($Parameter): Response
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
            $sql->setValue('input', $Data['input']);
            $sql->setValue('output', $Data['output']);
            
            if (isset($Data['attributes']) && $Data['attributes'] !== null) {
                $sql->setValue('attributes', $Data['attributes']);
            }
            
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
    public static function handleUpdateModule($Parameter): Response
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
        
        if ($checkSql->getRows() === 0) {
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
            
            if (null !== $Data['input']) {
                $sql->setValue('input', $Data['input']);
            }
            
            if (null !== $Data['output']) {
                $sql->setValue('output', $Data['output']);
            }
            
            if (null !== $Data['attributes']) {
                $sql->setValue('attributes', $Data['attributes']);
            }
            
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');
            
            $sql->update();

            return new Response(json_encode(['message' => 'Module updated', 'id' => $moduleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteModule($Parameter): Response
    {
        $moduleId = $Parameter['id'];

        // Check if module exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('module') . ' WHERE id = :id', [':id' => $moduleId]);
        
        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'Module not found']), 404);
        }

        try {
            // Check if module is in use
            $usageCheck = rex_sql::factory();
            $usageCheck->setQuery(
                'SELECT id FROM ' . rex::getTable('article_slice') . ' WHERE module_id = :id LIMIT 1',
                [':id' => $moduleId]
            );
            
            if ($usageCheck->getRows() > 0) {
                return new Response(json_encode([
                    'error' => 'Cannot delete module. It is in use by one or more slices.'
                ]), 409);
            }
            
            // Delete module
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('module') . ' WHERE id = :id',
                [':id' => $moduleId]
            );

            return new Response(json_encode(['message' => 'Module deleted', 'id' => $moduleId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
