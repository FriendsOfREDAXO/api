<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use function count;
use const JSON_PRETTY_PRINT;

class Templates extends RoutePackage
{
    public function loadRoutes(): void
    {
        // Templates List
        RouteCollection::registerRoute(
            'templates/list',
            new Route(
                'templates',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Templates::handleTemplateList',
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
                                'active' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'key' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ]
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
                ['GET']
            ),
            'Access to the list of templates',
        );

        // Templates Add
        RouteCollection::registerRoute(
            'templates/add',
            new Route(
                'templates',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Templates::handleAddTemplate',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'content' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'active' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 1,
                        ],
                        'key' => [
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
                ['POST']
            ),
            'Add a templates',
        );

        // Templates Get Details
        RouteCollection::registerRoute(
            'templates/get',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Templates::handleGetTemplate',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']
            ),
            'Get templates details',
        );

        // Templates Update
        RouteCollection::registerRoute(
            'templates/update',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Templates::handleUpdateTemplate',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'content' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'active' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'key' => [
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
                ['PUT', 'PATCH']
            ),
            'Update a templates',
        );

        // Templates Delete
        RouteCollection::registerRoute(
            'templates/delete',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Templates::handleDeleteTemplate',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']
            ),
            'Delete a templates',
        );
    }


    /** @api */
    public static function handleTemplateList($Parameter): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }


        $fields = ['id', '`key`', 'name', 'content', 'active', 'createdate', 'createuser', 'updatedate', 'updateuser', 'attributes', 'revision'];

        $SqlQueryWhere = [];
        $SqlParameters = [];

        // ID-Filter
        if (isset($Query['filter']['id']) && $Query['filter']['id'] !== '' && $Query['filter']['id'] !== null) {
            if (!is_int($Query['filter']['id'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[id]. Expected integer.']), 400);
            }
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        // Name-Filter
        if (isset($Query['filter']['name']) && $Query['filter']['name'] !== '' && $Query['filter']['name'] !== null) {
            if (!is_string($Query['filter']['name'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[name]. Expected string.']), 400);
            }
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        // Active-Filter
        if (isset($Query['filter']['active']) && $Query['filter']['active'] !== '' && $Query['filter']['active'] !== null) {
            if (!is_int($Query['filter']['active']) || !in_array($Query['filter']['active'], [0, 1])) {
                return new Response(json_encode(['error' => 'Invalid value for filter[active].  Must be 0 or 1.']), 400);
            }
            $SqlQueryWhere[':active'] = 'active = :active';
            $SqlParameters[':active'] = $Query['filter']['active'];
        }

        // Key Filter
        if (isset($Query['filter']['key']) && $Query['filter']['key'] !== '' && $Query['filter']['key'] !== null) {
            if (!is_string($Query['filter']['key'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[key]. Expected string.']), 400);
            }
            $SqlQueryWhere[':key'] = '`key` LIKE :key';
            $SqlParameters[':key'] = '%' . $Query['filter']['key'] . '%';
        }

        // Pagination (mit sicheren Default-Werten)
        $per_page = (isset($Query['per_page']) && is_int($Query['per_page']) && $Query['per_page'] > 0) ? $Query['per_page'] : 10;
        $page = (isset($Query['page']) && is_int($Query['page']) && $Query['page'] > 0) ? $Query['page'] : 1;
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $TemplatesSQL = rex_sql::factory();
        // dump($TemplatesSQL->getSql()); exit; // SQL-Abfrage überprüfen
        try {
            $Templates = $TemplatesSQL->getArray(
                '
                SELECT
                    ' . implode(',', $fields) . '
                FROM
                    ' . rex::getTablePrefix() . 'template
                ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
                ORDER BY name ASC
                LIMIT :start, :per_page
                ',
                $SqlParameters,
            );
        } catch (\rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }


        return new Response(json_encode($Templates, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetTemplate($Parameter): Response
    {
        $templateId = $Parameter['id'];

        $TemplateSQL = rex_sql::factory();
        $TemplateData = $TemplateSQL->getArray(
            'SELECT * FROM ' . rex::getTablePrefix() . 'template WHERE id = :id',
            [':id' => $templateId]
        );

        if (empty($TemplateData)) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        return new Response(json_encode($TemplateData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddTemplate($Parameter): Response
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
            $sql->setTable(rex::getTable('template'));
            $sql->setValue('name', $Data['name']);
            $sql->setValue('content', $Data['content']);
            $sql->setValue('active', $Data['active']);

            if (isset($Data['key']) && $Data['key'] !== null) {
                $sql->setValue('key', $Data['key']);
            }

            $sql->setValue('createdate', date('Y-m-d H:i:s'));
            $sql->setValue('createuser', 'API');
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->insert();
            $templateId = $sql->getLastId();

            return new Response(json_encode(['message' => 'Template created', 'id' => $templateId]), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateTemplate($Parameter): Response
    {
        $templateId = $Parameter['id'];
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Check if template exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('template') . ' WHERE id = :id', [':id' => $templateId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('template'));
            $sql->setWhere(['id' => $templateId]);

            // Only update fields that are provided
            if (null !== $Data['name']) {
                $sql->setValue('name', $Data['name']);
            }

            if (null !== $Data['content']) {
                $sql->setValue('content', $Data['content']);
            }

            if (null !== $Data['active']) {
                $sql->setValue('active', $Data['active']);
            }

            if (null !== $Data['key']) {
                $sql->setValue('key', $Data['key']);
            }

            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');

            $sql->update();

            return new Response(json_encode(['message' => 'Template updated', 'id' => $templateId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteTemplate($Parameter): Response
    {
        $templateId = $Parameter['id'];

        // Check if template exists
        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('template') . ' WHERE id = :id', [':id' => $templateId]);

        if ($checkSql->getRows() === 0) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        try {
            // Check if template is in use by articles
            $usageCheckArticles = rex_sql::factory();
            $usageCheckArticles->setQuery(
                'SELECT id FROM ' . rex::getTable('article') . ' WHERE template_id = :id LIMIT 1',
                [':id' => $templateId]
            );

            if ($usageCheckArticles->getRows() > 0) {
                return new Response(json_encode([
                    'error' => 'Cannot delete template. It is in use by one or more articles.'
                ]), 409);
            }

            // Delete template
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('template') . ' WHERE id = :id',
                [':id' => $templateId]
            );

            return new Response(json_encode(['message' => 'Template deleted', 'id' => $templateId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
