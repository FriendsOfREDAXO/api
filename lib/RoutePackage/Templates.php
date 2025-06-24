<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_sql_exception;
use rex_template;
use rex_template_cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;

use const JSON_PRETTY_PRINT;

class Templates extends RoutePackage
{
    public const TemplateFields = ['id', '`key`', 'name', 'content', 'active', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        // Templates List ✅
        RouteCollection::registerRoute(
            'templates/list',
            new Route(
                'templates',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Templates::handleTemplateList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'active' => [
                                    'type' => 'integer',
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
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET'],
            ),
            'Access to the list of templates',
            null,
            new BearerAuth()
        );

        // Templates Add ✅
        RouteCollection::registerRoute(
            'templates/add',
            new Route(
                'templates',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Templates::handleAddTemplate',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'content' => [
                            'type' => 'string',
                            'required' => true,
                            'default' => null,
                        ],
                        'active' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
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
                ['POST'],
            ),
            'Add a template',
            null,
            new BearerAuth()
        );

        // Templates Get Details ✅
        RouteCollection::registerRoute(
            'templates/get',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Templates::handleGetTemplate',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get templates details',
            null,
            new BearerAuth()
        );

        // Templates Update ✅
        RouteCollection::registerRoute(
            'templates/update',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Templates::handleUpdateTemplate',
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
                            'type' => 'integer',
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
                ['PUT', 'PATCH'],
            ),
            'Update a template',
            null,
            new BearerAuth()
        );

        // Templates Delete ✅
        RouteCollection::registerRoute(
            'templates/delete',
            new Route(
                'templates/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Templates::handleDeleteTemplate',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a template',
            null,
            new BearerAuth()
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

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (isset($Query['filter']['id']) && '' !== $Query['filter']['id'] && null !== $Query['filter']['id']) {
            if (!is_int($Query['filter']['id'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[id]. Expected integer.']), 400);
            }
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (isset($Query['filter']['name']) && '' !== $Query['filter']['name'] && null !== $Query['filter']['name']) {
            if (!is_string($Query['filter']['name'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[name]. Expected string.']), 400);
            }
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        if (isset($Query['filter']['active']) && '' !== $Query['filter']['active'] && null !== $Query['filter']['active']) {
            if (!is_int($Query['filter']['active']) || !in_array($Query['filter']['active'], [0, 1])) {
                return new Response(json_encode(['error' => 'Invalid value for filter[active].  Must be 0 or 1.']), 400);
            }
            $SqlQueryWhere[':active'] = 'active = :active';
            $SqlParameters[':active'] = $Query['filter']['active'];
        }

        if (isset($Query['filter']['key']) && '' !== $Query['filter']['key'] && null !== $Query['filter']['key']) {
            if (!is_string($Query['filter']['key'])) {
                return new Response(json_encode(['error' => 'Invalid type for filter[key]. Expected string.']), 400);
            }
            $SqlQueryWhere[':key'] = '`key` LIKE :key';
            $SqlParameters[':key'] = $Query['filter']['key'];
        }

        $TemplatesSQL = rex_sql::factory();
        try {
            $Templates = $TemplatesSQL->getArray(
                '
                SELECT
                    ' . implode(',', self::TemplateFields) . '
                FROM
                    ' . rex::getTablePrefix() . 'template
                    ' . (count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '') . '
                ORDER BY
                    name ASC
                ',
                $SqlParameters,
            );
        } catch (rex_sql_exception $e) {
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
            'SELECT ' . implode(',', self::TemplateFields) . ' FROM ' . rex::getTable('template') . 'template WHERE id = :id',
            [':id' => $templateId],
        );

        if (empty($TemplateData)) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        $TemplateData[0]['is_in_use'] = true;
        if (false !== rex_template::templateIsInUse($templateId, 'cant_delete_template_because_its_in_use')) {
            $TemplateData[0]['is_in_use'] = false;
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

        if (null === $Data['name'] || '' === $Data['name']) {
            return new Response(json_encode(['error' => 'name is required']), 400);
        }

        if (null === $Data['content'] || '' === $Data['content']) {
            return new Response(json_encode(['error' => 'content is required']), 400);
        }

        $Data['active'] = (1 === $Data['active']) ? 1 : 0;

        $ctypes = [];

        $categories = [];
        $categories['all'] = 0;

        $modules = [];
        $modules[1]['all'] = 0;

        $TPL = rex_sql::factory();
        $TPL->setTable(rex::getTable('template'));
        $TPL->setValue('key', $Data['key']);
        $TPL->setValue('name', $Data['name']);
        $TPL->setValue('active', $Data['active']);
        $TPL->setValue('content', $Data['content']);
        $TPL->addGlobalCreateFields();
        $TPL->addGlobalUpdateFields();
        $TPL->setArrayValue('attributes', [
            'ctype' => $ctypes,
            'modules' => $modules,
            'categories' => $categories,
        ]);

        try {
            $TPL->insert();
            $templateId = (int) $TPL->getLastId();
            rex_template_cache::delete($templateId);
            rex_extension::registerPoint(new rex_extension_point('TEMPLATE_ADDED', '', [
                'id' => $templateId,
                'key' => $Data['key'],
                'name' => $Data['name'],
                'content' => $Data['content'],
                'active' => $Data['active'],
                'ctype' => $ctypes,
                'modules' => $modules,
                'categories' => $categories,
            ]));
        } catch (rex_sql_exception $e) {
            if (rex_sql::ERROR_VIOLATE_UNIQUE_KEY == $e->getErrorCode()) {
                return new Response(json_encode(['error' => 'key already exists']), 409);
            }
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        return new Response(json_encode(['message' => 'Template created', 'id' => $templateId]), 201);
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

        $templateSql = rex_sql::factory();
        $Template = $templateSql->setQuery('SELECT ' . implode(',', self::TemplateFields) . ',attributes FROM ' . rex::getTable('template') . ' WHERE id = :id', [':id' => $templateId]);

        if (0 === $Template->getRows()) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        if (null === $Data['active']) {
            $Data['active'] = $Template->getValue('active');
        }
        if (0 == $Data['active'] && 1 == $Template->getValue('active')) {
            if (false !== rex_template::templateIsInUse($templateId, 'cant_delete_template_because_its_in_use')) {
                return new Response(json_encode(['error' => 'Template is in use. Active status to 0 is not possible', 'id' => $templateId]), 409);
            }
            if (rex_template::getDefaultId() == $templateId) {
                return new Response(json_encode(['error' => 'Template is default template', 'id' => $templateId]), 409);
            }
        }

        if (null === $Data['name']) {
            $Data['name'] = $Template->getValue('name');
        }

        if (null === $Data['content']) {
            $Data['content'] = $Template->getValue('content');
        }

        if (null === $Data['key']) {
            $Data['key'] = $Template->getValue('key');
        }

        try {
            rex_template_cache::delete($templateId);

            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('template'));
            $sql->setWhere(['id' => $templateId]);
            $sql->setValue('name', $Data['name']);
            $sql->setValue('content', $Data['content']);
            $sql->setValue('active', $Data['active']);
            $sql->setValue('key', $Data['key']);
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
            $sql->setValue('updateuser', 'API');
            $sql->update();

            $attributes = $Template->getArrayValue('attributes');

            rex_extension::registerPoint(new rex_extension_point('TEMPLATE_UPDATED', '', [
                'id' => $templateId,
                'key' => $Data['key'],
                'name' => $Data['name'],
                'content' => $Data['content'],
                'active' => $Data['active'],
                'ctype' => $attributes['ctype'],
                'modules' => $attributes['modules'],
                'categories' => $attributes['categories'],
            ]));

            return new Response(json_encode(['message' => 'Template updated', 'id' => $templateId]), 200);
        } catch (rex_sql_exception $e) {
            if (rex_sql::ERROR_VIOLATE_UNIQUE_KEY == $e->getErrorCode()) {
                return new Response(json_encode(['error' => 'key already exists']), 409);
            }
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteTemplate($Parameter): Response
    {
        $templateId = $Parameter['id'];

        $checkSql = rex_sql::factory();
        $checkSql->setQuery('SELECT id FROM ' . rex::getTable('template') . ' WHERE id = :id', [':id' => $templateId]);

        if (0 === $checkSql->getRows()) {
            return new Response(json_encode(['error' => 'Template not found']), 404);
        }

        if (false !== rex_template::templateIsInUse($templateId, 'cant_delete_template_because_its_in_use')) {
            return new Response(json_encode(['error' => 'Template is in use', 'id' => $templateId]), 409);
        }

        if (rex_template::getDefaultId() == $templateId) {
            return new Response(json_encode(['error' => 'Template is default template', 'id' => $templateId]), 409);
        }

        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('template') . ' WHERE id = :id',
                [':id' => $templateId],
            );
            rex_template_cache::delete($templateId);

            rex_extension::registerPoint(new rex_extension_point('TEMPLATE_DELETED', '', [
                'id' => $templateId,
            ]));

            return new Response(json_encode(['message' => 'Template deleted', 'id' => $templateId]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }
}
