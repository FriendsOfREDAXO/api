<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use rex;
use rex_addon;
use rex_article;
use rex_article_cache;
use rex_category;
use rex_clang;
use rex_clang_service;
use rex_extension;
use rex_extension_point;
use rex_media_cache;
use rex_metainfo_default_type;
use rex_sql;
use rex_sql_exception;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;

use const JSON_PRETTY_PRINT;

class Metainfo extends RoutePackage
{
    private const PREFIXES = ['art_', 'cat_', 'med_', 'clang_'];

    private const FIELD_COLUMNS = ['id', 'title', 'name', 'priority', 'attributes', 'type_id', '`default`', 'params', 'validate', 'callback', 'restrictions', 'templates', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        $this->loadTypeRoutes();
        $this->loadFieldRoutes();
        $this->loadValueRoutes();
    }

    private function loadTypeRoutes(): void
    {
        // Field types list (read-only)
        RouteCollection::registerRoute(
            'metainfo/types/list',
            new Route(
                'metainfo/types',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleTypeList',
                ],
                [],
                [],
                '',
                [],
                ['GET'],
            ),
            'List metainfo field types (text, textarea, select, date, REX_MEDIA_WIDGET, ...)',
            null,
            new BearerAuth(),
        );
    }

    private function loadFieldRoutes(): void
    {
        // Field list
        RouteCollection::registerRoute(
            'metainfo/fields/list',
            new Route(
                'metainfo/fields',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleFieldList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'prefix' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                    'description' => 'One of art_, cat_, med_, clang_',
                                ],
                                'type_id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                    'description' => 'LIKE filter on the field name',
                                ],
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
            'List metainfo field definitions',
            null,
            new BearerAuth(),
        );

        // Field get
        RouteCollection::registerRoute(
            'metainfo/fields/get',
            new Route(
                'metainfo/fields/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleFieldGet',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get a metainfo field definition',
            null,
            new BearerAuth(),
        );

        // Field add
        RouteCollection::registerRoute(
            'metainfo/fields/add',
            new Route(
                'metainfo/fields',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleFieldAdd',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => 'Field name with prefix (e.g. art_seo_description, med_copyright)',
                        ],
                        'title' => [
                            'type' => 'string',
                            'required' => true,
                            'description' => 'Backend label',
                        ],
                        'type_id' => [
                            'type' => 'int',
                            'required' => true,
                            'description' => 'ID from /api/metainfo/types',
                        ],
                        'priority' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
                        ],
                        'attributes' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                            'description' => 'HTML form attributes (e.g. "class=wide")',
                        ],
                        'default' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                        'params' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                            'description' => 'Field-type-specific parameters (e.g. select options "a|b|c" or SQL query)',
                        ],
                        'validate' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                        'restrictions' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                        'callback' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => '',
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST'],
            ),
            'Create a metainfo field (also adds DB column on the target table)',
            null,
            new BearerAuth(),
        );

        // Field update
        RouteCollection::registerRoute(
            'metainfo/fields/update',
            new Route(
                'metainfo/fields/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleFieldUpdate',
                    'Body' => [
                        'title' => ['type' => 'string', 'required' => false, 'default' => null],
                        'priority' => ['type' => 'int', 'required' => false, 'default' => null],
                        'attributes' => ['type' => 'string', 'required' => false, 'default' => null],
                        'default' => ['type' => 'string', 'required' => false, 'default' => null],
                        'params' => ['type' => 'string', 'required' => false, 'default' => null],
                        'validate' => ['type' => 'string', 'required' => false, 'default' => null],
                        'restrictions' => ['type' => 'string', 'required' => false, 'default' => null],
                        'callback' => ['type' => 'string', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update a metainfo field definition (renaming and type changes are not supported)',
            null,
            new BearerAuth(),
        );

        // Field delete
        RouteCollection::registerRoute(
            'metainfo/fields/delete',
            new Route(
                'metainfo/fields/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleFieldDelete',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a metainfo field definition (also drops the DB column)',
            null,
            new BearerAuth(),
        );
    }

    private function loadValueRoutes(): void
    {
        // Article values
        RouteCollection::registerRoute(
            'metainfo/articles/values/get',
            new Route(
                'structure/articles/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleArticleValuesGet',
                    'query' => [
                        'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get all art_* metainfo values for an article',
            null,
            new BearerAuth(),
        );
        RouteCollection::registerRoute(
            'metainfo/articles/values/update',
            new Route(
                'structure/articles/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleArticleValuesUpdate',
                    'query' => [
                        'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update art_* metainfo values for an article. Body is an arbitrary object keyed by field name.',
            null,
            new BearerAuth(),
        );

        // Category values
        RouteCollection::registerRoute(
            'metainfo/categories/values/get',
            new Route(
                'structure/categories/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleCategoryValuesGet',
                    'query' => [
                        'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get all cat_* metainfo values for a category',
            null,
            new BearerAuth(),
        );
        RouteCollection::registerRoute(
            'metainfo/categories/values/update',
            new Route(
                'structure/categories/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleCategoryValuesUpdate',
                    'query' => [
                        'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update cat_* metainfo values for a category. Body is an arbitrary object keyed by field name.',
            null,
            new BearerAuth(),
        );

        // Media values
        RouteCollection::registerRoute(
            'metainfo/media/values/get',
            new Route(
                'media/{filename}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleMediaValuesGet',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get all med_* metainfo values for a media item',
            null,
            new BearerAuth(),
        );
        RouteCollection::registerRoute(
            'metainfo/media/values/update',
            new Route(
                'media/{filename}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleMediaValuesUpdate',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update med_* metainfo values for a media item. Body is an arbitrary object keyed by field name.',
            null,
            new BearerAuth(),
        );

        // Clang values
        RouteCollection::registerRoute(
            'metainfo/clangs/values/get',
            new Route(
                'system/clangs/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleClangValuesGet',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get all clang_* metainfo values for a language',
            null,
            new BearerAuth(),
        );
        RouteCollection::registerRoute(
            'metainfo/clangs/values/update',
            new Route(
                'system/clangs/{id}/metainfo',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Metainfo::handleClangValuesUpdate',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update clang_* metainfo values for a language. Body is an arbitrary object keyed by field name.',
            null,
            new BearerAuth(),
        );
    }

    // =====================================================================
    // Type handlers
    // =====================================================================

    /** @api */
    public static function handleTypeList($Parameter, array $Route = []): Response
    {
        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT id, label, dbtype, dblength FROM ' . rex::getTable('metainfo_type') . ' ORDER BY id',
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }

        $data = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'dbtype' => (string) $row['dbtype'],
            'dblength' => (int) $row['dblength'],
        ], $rows);

        return new Response(json_encode(['data' => $data], JSON_PRETTY_PRINT));
    }

    // =====================================================================
    // Field-definition handlers (CRUD on rex_metainfo_field)
    // =====================================================================

    /** @api */
    public static function handleFieldList($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $where = [];
        $params = [];

        $prefix = $Query['filter']['prefix'] ?? null;
        if (null !== $prefix && '' !== $prefix) {
            if (!in_array($prefix, self::PREFIXES, true)) {
                return new Response(json_encode(['error' => 'Invalid prefix. Allowed: ' . implode(', ', self::PREFIXES)]), 400);
            }
            $where[] = 'name LIKE :prefix';
            $params[':prefix'] = $prefix . '%';
        }

        $typeId = $Query['filter']['type_id'] ?? null;
        if (null !== $typeId) {
            $where[] = 'type_id = :type_id';
            $params[':type_id'] = (int) $typeId;
        }

        $name = $Query['filter']['name'] ?? null;
        if (null !== $name && '' !== $name) {
            $where[] = 'name LIKE :name';
            $params[':name'] = '%' . $name . '%';
        }

        $allowedSortFields = ['id', 'name', 'priority', 'type_id', 'createdate', 'updatedate'];
        try {
            $sortDefs = ListHelper::parseSort($Query['sort'] ?? null, $allowedSortFields, [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $countResult = rex_sql::factory()->getArray(
                'SELECT COUNT(*) AS total FROM ' . rex::getTable('metainfo_field') . ' ' . $whereClause,
                $params,
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }
        $total = (int) $countResult[0]['total'];

        $perPage = (1 > $Query['per_page']) ? 10 : (int) $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : (int) $Query['page'];
        $pagination = ListHelper::paginate($page, $perPage, $total);

        $orderBy = ListHelper::buildSqlOrderBy($sortDefs);

        // LIMIT inlined as integers — rex_sql binds parameters as strings and MySQL strict mode
        // rejects `LIMIT 'x','y'` (see PR #41 / commit cafc86d for the same fix in Structure).
        $offset = (int) $pagination['offset'];
        $limit = (int) $pagination['limit'];

        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT ' . implode(',', self::FIELD_COLUMNS) . '
                 FROM ' . rex::getTable('metainfo_field') . '
                 ' . $whereClause . '
                 ORDER BY ' . $orderBy . '
                 LIMIT ' . $offset . ', ' . $limit,
                $params,
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }

        $data = array_map(self::shapeFieldRow(...), $rows);

        return new Response(json_encode(ListHelper::wrapResponse($data, $pagination['meta']), JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleFieldGet($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT ' . implode(',', self::FIELD_COLUMNS) . ' FROM ' . rex::getTable('metainfo_field') . ' WHERE id = :id',
                [':id' => (int) $Parameter['id']],
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }

        if (0 === count($rows)) {
            return new Response(json_encode(['error' => 'Metainfo field not found']), 404);
        }

        return new Response(json_encode(self::shapeFieldRow($rows[0]), JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleFieldAdd($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        self::ensureMetainfoFunctions();

        $Data = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data, $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $name = (string) $Data['name'];
        if (1 !== preg_match('/^(?:art_|cat_|med_|clang_)[a-z0-9_]+$/i', $name)) {
            return new Response(json_encode(['error' => 'name must start with art_, cat_, med_ or clang_ and only contain a-z, 0-9, _']), 400);
        }

        $result = rex_metainfo_add_field(
            (string) $Data['title'],
            $name,
            (int) $Data['priority'],
            (string) $Data['attributes'],
            (int) $Data['type_id'],
            (string) $Data['default'],
            (string) $Data['params'],
            (string) $Data['validate'],
            (string) $Data['restrictions'],
            (string) $Data['callback'],
        );

        if (is_string($result)) {
            return new Response(json_encode(['error' => $result]), 409);
        }

        try {
            $row = rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('metainfo_field') . ' WHERE name = :name LIMIT 1',
                [':name' => $name],
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Field created but lookup failed: ' . $e->getMessage()]), 500);
        }

        $newId = isset($row[0]['id']) ? (int) $row[0]['id'] : 0;

        if ($newId > 0) {
            self::stampApiUser((int) $newId, true);
        }

        return new Response(json_encode([
            'message' => 'Metainfo field created',
            'id' => $newId,
            'name' => $name,
        ]), 201);
    }

    /** @api */
    public static function handleFieldUpdate($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $raw = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($raw)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        if (isset($raw['name']) || isset($raw['type_id'])) {
            return new Response(json_encode(['error' => 'Renaming or changing the type of a metainfo field is not supported via the API']), 422);
        }

        try {
            $Data = RouteCollection::getQuerySet($raw, $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        try {
            $existing = rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('metainfo_field') . ' WHERE id = :id LIMIT 1',
                [':id' => (int) $Parameter['id']],
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => 'Database error: ' . $e->getMessage()]), 500);
        }

        if (0 === count($existing)) {
            return new Response(json_encode(['error' => 'Metainfo field not found']), 404);
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('metainfo_field'));
        $sql->setWhere(['id' => (int) $Parameter['id']]);

        $updatable = ['title', 'priority', 'attributes', 'default', 'params', 'validate', 'restrictions', 'callback'];
        $touched = false;
        foreach ($updatable as $field) {
            if (null !== $Data[$field]) {
                $sql->setValue($field, $Data[$field]);
                $touched = true;
            }
        }

        if (!$touched) {
            return new Response(json_encode(['error' => 'No updatable fields provided']), 400);
        }

        $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        $sql->setValue('updateuser', 'API');

        try {
            $sql->update();
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        return new Response(json_encode(['message' => 'Metainfo field updated', 'id' => (int) $Parameter['id']]), 200);
    }

    /** @api */
    public static function handleFieldDelete($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkAdminPerm($user);
        if (null !== $permResponse) {
            return $permResponse;
        }

        self::ensureMetainfoFunctions();

        $result = rex_metainfo_delete_field((int) $Parameter['id']);

        if (is_string($result)) {
            return new Response(json_encode(['error' => $result]), 404);
        }
        if (false === $result) {
            return new Response(json_encode(['error' => 'Failed to delete metainfo field']), 500);
        }

        return new Response(json_encode(['message' => 'Metainfo field deleted', 'id' => (int) $Parameter['id']]), 200);
    }

    // =====================================================================
    // Value handlers
    // =====================================================================

    /** @api */
    public static function handleArticleValuesGet($Parameter, array $Route = []): Response
    {
        return self::readArticleOrCategoryValues($Parameter, true);
    }

    /** @api */
    public static function handleArticleValuesUpdate($Parameter, array $Route = []): Response
    {
        return self::writeArticleOrCategoryValues($Parameter, true);
    }

    /** @api */
    public static function handleCategoryValuesGet($Parameter, array $Route = []): Response
    {
        return self::readArticleOrCategoryValues($Parameter, false);
    }

    /** @api */
    public static function handleCategoryValuesUpdate($Parameter, array $Route = []): Response
    {
        return self::writeArticleOrCategoryValues($Parameter, false);
    }

    /** @api */
    public static function handleMediaValuesGet($Parameter, array $Route = []): Response
    {
        $filename = (string) $Parameter['filename'];
        if (null === self::getMediaIdByFilename($filename)) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        $fields = self::loadFieldsForPrefix('med_');
        $values = self::readValuesFromTable(rex::getTable('media'), 'filename = :filename', [':filename' => $filename], $fields);

        return new Response(json_encode(['filename' => $filename, 'data' => $values], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleMediaValuesUpdate($Parameter, array $Route = []): Response
    {
        $filename = (string) $Parameter['filename'];
        if (null === self::getMediaIdByFilename($filename)) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        $body = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        $fields = self::loadFieldsForPrefix('med_');
        $error = self::validatePatchKeys($body, $fields);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(rex::getTable('media'), ['filename' => $filename], $body, $fields);
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        rex_media_cache::delete($filename);

        $values = self::readValuesFromTable(rex::getTable('media'), 'filename = :filename', [':filename' => $filename], $fields);
        return new Response(json_encode(['message' => 'Metainfo values updated', 'filename' => $filename, 'data' => $values], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleClangValuesGet($Parameter, array $Route = []): Response
    {
        $clangId = (int) $Parameter['id'];
        if (null === rex_clang::get($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        $fields = self::loadFieldsForPrefix('clang_');
        $values = self::readValuesFromTable(rex::getTable('clang'), 'id = :id', [':id' => $clangId], $fields);

        return new Response(json_encode(['data' => $values], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleClangValuesUpdate($Parameter, array $Route = []): Response
    {
        $clangId = (int) $Parameter['id'];
        if (null === rex_clang::get($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        $body = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        $fields = self::loadFieldsForPrefix('clang_');
        $error = self::validatePatchKeys($body, $fields);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(rex::getTable('clang'), ['id' => $clangId], $body, $fields);
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        rex_clang_service::generateCache();

        $values = self::readValuesFromTable(rex::getTable('clang'), 'id = :id', [':id' => $clangId], $fields);
        return new Response(json_encode(['message' => 'Metainfo values updated', 'data' => $values], JSON_PRETTY_PRINT));
    }

    private static function readArticleOrCategoryValues(array $Parameter, bool $isArticle): Response
    {
        $id = (int) $Parameter['id'];
        $clangId = isset($_REQUEST['clang_id']) && '' !== $_REQUEST['clang_id'] ? (int) $_REQUEST['clang_id'] : rex_clang::getStartId();

        $resolved = self::resolveArticleOrCategory($id, $clangId, $isArticle);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        $prefix = $isArticle ? 'art_' : 'cat_';
        $fields = self::loadFieldsForPrefix($prefix);
        $values = self::readValuesFromTable(
            rex::getTable('article'),
            'id = :id AND clang_id = :clang',
            [':id' => $id, ':clang' => $clangId],
            $fields,
        );

        return new Response(json_encode([
            'clang_id' => $clangId,
            'data' => $values,
        ], JSON_PRETTY_PRINT));
    }

    private static function writeArticleOrCategoryValues(array $Parameter, bool $isArticle): Response
    {
        $id = (int) $Parameter['id'];
        $clangId = isset($_REQUEST['clang_id']) && '' !== $_REQUEST['clang_id'] ? (int) $_REQUEST['clang_id'] : rex_clang::getStartId();

        $resolved = self::resolveArticleOrCategory($id, $clangId, $isArticle);
        if ($resolved instanceof Response) {
            return $resolved;
        }

        $body = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        $prefix = $isArticle ? 'art_' : 'cat_';
        $fields = self::loadFieldsForPrefix($prefix);
        $error = self::validatePatchKeys($body, $fields);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(
                rex::getTable('article'),
                ['id' => $id, 'clang_id' => $clangId],
                $body,
                $fields,
            );
        } catch (rex_sql_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        if ($isArticle) {
            rex_article_cache::deleteMeta($id, $clangId);
            rex_extension::registerPoint(new rex_extension_point('ART_META_UPDATED', '', [
                'id' => $id,
                'clang' => $clangId,
            ]));
        } else {
            rex_article_cache::generateMeta($id, $clangId);
        }

        $values = self::readValuesFromTable(
            rex::getTable('article'),
            'id = :id AND clang_id = :clang',
            [':id' => $id, ':clang' => $clangId],
            $fields,
        );

        return new Response(json_encode([
            'message' => 'Metainfo values updated',
            'clang_id' => $clangId,
            'data' => $values,
        ], JSON_PRETTY_PRINT));
    }

    private static function resolveArticleOrCategory(int $id, int $clangId, bool $isArticle): ?Response
    {
        if (null === rex_clang::get($clangId)) {
            return new Response(json_encode(['error' => 'Language not found']), 404);
        }

        if ($isArticle) {
            $article = rex_article::get($id, $clangId);
            if (null === $article || $article->isStartArticle()) {
                return new Response(json_encode(['error' => 'Article not found']), 404);
            }
            return null;
        }

        $category = rex_category::get($id, $clangId);
        if (null === $category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }
        return null;
    }

    private static function getMediaIdByFilename(string $filename): ?int
    {
        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT id FROM ' . rex::getTable('media') . ' WHERE filename = :filename LIMIT 1',
                [':filename' => $filename],
            );
        } catch (rex_sql_exception) {
            return null;
        }
        if (0 === count($rows)) {
            return null;
        }
        return (int) $rows[0]['id'];
    }

    // =====================================================================
    // Helpers
    // =====================================================================

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

    /**
     * The metainfo addon's procedural helpers (rex_metainfo_add_field, ...) are only required
     * in backend context (see metainfo/boot.php). The API runs in frontend context, so we need
     * to load the file ourselves before using those helpers.
     */
    private static function ensureMetainfoFunctions(): void
    {
        if (function_exists('rex_metainfo_add_field')) {
            return;
        }
        $metainfoAddon = rex_addon::get('metainfo');
        if (!$metainfoAddon->isAvailable()) {
            return;
        }
        require_once $metainfoAddon->getPath('functions/function_metainfo.php');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function shapeFieldRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'title' => (string) $row['title'],
            'type_id' => (int) $row['type_id'],
            'priority' => (int) $row['priority'],
            'attributes' => (string) ($row['attributes'] ?? ''),
            'default' => (string) ($row['default'] ?? ''),
            'params' => (string) ($row['params'] ?? ''),
            'validate' => (string) ($row['validate'] ?? ''),
            'restrictions' => (string) ($row['restrictions'] ?? ''),
            'templates' => (string) ($row['templates'] ?? ''),
            'callback' => (string) ($row['callback'] ?? ''),
            'prefix' => self::extractPrefix((string) $row['name']),
            'meta_table' => self::tableForName((string) $row['name']),
            'createdate' => $row['createdate'] ?? null,
            'createuser' => $row['createuser'] ?? null,
            'updatedate' => $row['updatedate'] ?? null,
            'updateuser' => $row['updateuser'] ?? null,
        ];
    }

    private static function extractPrefix(string $name): string
    {
        self::ensureMetainfoFunctions();
        try {
            return rex_metainfo_meta_prefix($name);
        } catch (InvalidArgumentException) {
            return '';
        }
    }

    private static function tableForName(string $name): string
    {
        self::ensureMetainfoFunctions();
        $prefix = self::extractPrefix($name);
        $table = '' === $prefix ? false : rex_metainfo_meta_table($prefix);
        return false === $table ? '' : (string) $table;
    }

    /**
     * @return list<array{id: int, name: string, type_id: int, attributes: string}>
     */
    private static function loadFieldsForPrefix(string $prefix): array
    {
        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT id, name, type_id, attributes FROM ' . rex::getTable('metainfo_field') . ' WHERE name LIKE :prefix ORDER BY priority',
                [':prefix' => $prefix . '%'],
            );
        } catch (rex_sql_exception) {
            return [];
        }

        return array_values(array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'type_id' => (int) $row['type_id'],
            'attributes' => (string) ($row['attributes'] ?? ''),
        ], $rows));
    }

    /**
     * @param list<array{name: string, type_id: int, attributes: string}> $fields
     * @return array<string, mixed>
     */
    private static function readValuesFromTable(string $table, string $where, array $params, array $fields): array
    {
        $values = [];
        if (0 === count($fields)) {
            return $values;
        }

        $columns = array_map(static fn(array $f): string => '`' . str_replace('`', '', $f['name']) . '`', $fields);

        try {
            $rows = rex_sql::factory()->getArray(
                'SELECT ' . implode(', ', $columns) . ' FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1',
                $params,
            );
        } catch (rex_sql_exception) {
            return [];
        }

        if (0 === count($rows)) {
            return [];
        }

        foreach ($fields as $field) {
            $raw = $rows[0][$field['name']] ?? null;
            $values[$field['name']] = self::parseValueFromStorage($raw, $field['type_id'], $field['attributes']);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $body
     * @param list<array{name: string, type_id: int, attributes: string}> $fields
     */
    private static function validatePatchKeys(array $body, array $fields): ?Response
    {
        $known = [];
        foreach ($fields as $f) {
            if (rex_metainfo_default_type::LEGEND === $f['type_id']) {
                continue;
            }
            $known[$f['name']] = true;
        }

        $unknown = [];
        foreach (array_keys($body) as $key) {
            if (!isset($known[$key])) {
                $unknown[] = $key;
            }
        }

        if (count($unknown) > 0) {
            return new Response(json_encode([
                'error' => 'Unknown metainfo field(s): ' . implode(', ', $unknown),
                'allowed' => array_keys($known),
            ]), 422);
        }

        return null;
    }

    /**
     * @param array<string, int|string> $where Identifier conditions
     * @param array<string, mixed> $body
     * @param list<array{name: string, type_id: int, attributes: string}> $fields
     */
    private static function applyValuePatch(string $table, array $where, array $body, array $fields): void
    {
        $byName = [];
        foreach ($fields as $f) {
            $byName[$f['name']] = $f;
        }

        $sql = rex_sql::factory();
        $sql->setTable($table);
        $sql->setWhere($where);

        $hasAny = false;
        foreach ($body as $key => $value) {
            if (!isset($byName[$key])) {
                continue;
            }
            $field = $byName[$key];
            $stored = self::formatValueForStorage($value, $field['type_id'], $field['attributes']);
            $sql->setValue($key, $stored);
            $hasAny = true;
        }

        if (!$hasAny) {
            return;
        }

        if (self::tableHasColumn($table, 'updatedate')) {
            $sql->setValue('updatedate', date('Y-m-d H:i:s'));
        }
        if (self::tableHasColumn($table, 'updateuser')) {
            $sql->setValue('updateuser', 'API');
        }

        $sql->update();
    }

    /**
     * Stamp createuser/updateuser = 'API' on a metainfo_field row that was just created/updated
     * by the metainfo addon's procedural helper (which would otherwise record "frontend").
     */
    private static function stampApiUser(int $fieldId, bool $alsoCreate): void
    {
        try {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('metainfo_field'));
            $sql->setWhere(['id' => $fieldId]);
            if ($alsoCreate) {
                $sql->setValue('createuser', 'API');
            }
            $sql->setValue('updateuser', 'API');
            $sql->update();
        } catch (rex_sql_exception) {
            // best-effort stamping — not worth surfacing to the caller
        }
    }

    private static function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '|' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $rows = rex_sql::factory()->getArray('SHOW COLUMNS FROM ' . $table . ' LIKE :col', [':col' => $column]);
            return $cache[$key] = count($rows) > 0;
        } catch (rex_sql_exception) {
            return $cache[$key] = false;
        }
    }

    private static function formatValueForStorage(mixed $value, int $typeId, string $attributes): mixed
    {
        if (rex_metainfo_default_type::LEGEND === $typeId) {
            return null;
        }

        if (self::isDateType($typeId)) {
            if (null === $value || '' === $value) {
                return 0;
            }
            if (is_int($value)) {
                return $value;
            }
            if (is_string($value)) {
                $ts = strtotime($value);
                return false === $ts ? 0 : $ts;
            }
            return 0;
        }

        if (self::isMultiValueType($typeId, $attributes)) {
            $parts = [];
            if (is_array($value)) {
                foreach ($value as $part) {
                    if (is_scalar($part) && '' !== (string) $part) {
                        $parts[] = (string) $part;
                    }
                }
            } elseif (is_scalar($value) && '' !== (string) $value) {
                $parts[] = (string) $value;
            }
            return 0 === count($parts) ? '' : '|' . implode('|', $parts) . '|';
        }

        if (null === $value) {
            return null;
        }
        return is_scalar($value) ? (string) $value : null;
    }

    private static function parseValueFromStorage(mixed $value, int $typeId, string $attributes): mixed
    {
        if (rex_metainfo_default_type::LEGEND === $typeId) {
            return null;
        }

        if (self::isDateType($typeId)) {
            $ts = (int) $value;
            if (0 === $ts) {
                return null;
            }
            $iso = match ($typeId) {
                rex_metainfo_default_type::TIME => date('H:i:s', $ts),
                rex_metainfo_default_type::DATE => date('Y-m-d', $ts),
                default => date('c', $ts),
            };
            return ['timestamp' => $ts, 'iso' => $iso];
        }

        if (self::isMultiValueType($typeId, $attributes)) {
            if (null === $value || '' === $value) {
                return [];
            }
            $trimmed = trim((string) $value, '|');
            if ('' === $trimmed) {
                return [];
            }
            return explode('|', $trimmed);
        }

        return $value;
    }

    private static function isMultiValueType(int $typeId, string $attributes): bool
    {
        if (in_array($typeId, [
            rex_metainfo_default_type::CHECKBOX,
            rex_metainfo_default_type::REX_MEDIALIST_WIDGET,
            rex_metainfo_default_type::REX_LINKLIST_WIDGET,
        ], true)) {
            return true;
        }
        if (rex_metainfo_default_type::SELECT === $typeId && str_contains($attributes, 'multiple')) {
            return true;
        }
        return false;
    }

    private static function isDateType(int $typeId): bool
    {
        return in_array($typeId, [
            rex_metainfo_default_type::DATE,
            rex_metainfo_default_type::DATETIME,
            rex_metainfo_default_type::TIME,
        ], true);
    }
}
