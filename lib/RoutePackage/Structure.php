<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_article;
use rex_article_cache;
use rex_article_service;
use rex_category;
use rex_category_service;
use rex_clang;
use rex_content_service;
use rex_extension;
use rex_extension_point;
use rex_extension_point_art_content_updated;
use rex_sql;
use rex_sql_util;
use rex_template;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use InvalidArgumentException;

use function count;
use function in_array;
use function is_array;

use const JSON_PRETTY_PRINT;

class Structure extends RoutePackage
{
    public function loadRoutes(): void
    {
        // TODO?
        // Über den Header z.B. X-REDAXO-API-COUNT: 100
        // Über den Header z.B. X-REDAXO-API-PAGE: 1
        // Über den Header z.B. X-REDAXO-API-PER-PAGE: 10
        // 'structure/articles/{id}/{clang}'
        // 'structure/categories/{id}/{clang}'

        // Article List ✅
        RouteCollection::registerRoute(
            'structure/articles/list',
            new Route(
                'structure/articles',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleArticleList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'parent_id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'clang_id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'revision' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => 0,
                                ],
                                'is_category' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => '',
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
                ['GET'],
            ),
            'Access to the list of articles',
            null,
            new BearerAuth()
        );

        // Article anzeigen ✅
        RouteCollection::registerRoute(
            'structure/articles/get',
            new Route(
                'structure/articles/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleGetArticle',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get an article',
            null,
            new BearerAuth()
        );

        // Article add ✅
        RouteCollection::registerRoute(
            'structure/articles/add',
            new Route(
                'structure/articles',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleAddArticle',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'required' => true,
                            'default' => 0,
                        ],
                        'status' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
                        ],
                        'template_id' => [
                            'type' => 'integer',
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
            'Add an article',
            null,
            new BearerAuth()
        );

        // Category add ✅
        RouteCollection::registerRoute(
            'structure/categories/add',
            new Route(
                'structure/categories',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleAddCategory',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'required' => true,
                            'default' => 0,
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                        ],
                        'template_id' => [
                            'type' => 'integer',
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
            'Add a category',
            null,
            new BearerAuth()
        );

        // Article delete ✅
        RouteCollection::registerRoute(
            'structure/articles/delete',
            new Route(
                'structure/articles/{id}',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleDeleteArticle'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete an article',
            null,
            new BearerAuth()
        );

        // Category delete ✅
        RouteCollection::registerRoute(
            'structure/categories/delete',
            new Route(
                'structure/categories/{id}',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleDeleteCategory'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a category',
            null,
            new BearerAuth()
        );

        // Article update ✅
        RouteCollection::registerRoute(
            'structure/articles/update',
            new Route(
                'structure/articles/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleUpdateArticle',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                        ],
                        'template_id' => [
                            'type' => 'integer',
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
            'Update an article',
            null,
            new BearerAuth()
        );

        // Category update ✅
        RouteCollection::registerRoute(
            'structure/categories/update',
            new Route(
                'structure/categories/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleUpdateCategory',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                        'priority' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                        ],
                        'status' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                        ],
                        'template_id' => [
                            'type' => 'integer',
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
            'Update a category',
            null,
            new BearerAuth()
        );

        for ($i = 1; $i <= 19; ++$i) {
            $Values['value' . $i] = [
                'type' => 'string',
                'required' => false,
                'default' => null,
            ];
            if ($i <= 10) {
                $Medias['media' . $i] = [
                    'type' => 'string',
                    'required' => false,
                    'default' => null,
                ];
                $Medialists['medialist' . $i] = [
                    'type' => 'string',
                    'required' => false,
                    'default' => null,
                ];
                $Links['link' . $i] = [
                    'type' => 'string',
                    'required' => false,
                    'default' => null,
                ];
                $Linklists['linklist' . $i] = [
                    'type' => 'string',
                    'required' => false,
                    'default' => null,
                ];
            }
        }

        // Article Slices anzeigen ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/list',
            new Route(
                'structure/articles/{id}/slices',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleGetArticleSlices',
                    'query' => [
                        'clang_id' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'ctype_id' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => null,
                        ],
                        'revision' => [
                            'type' => 'int',
                            'required' => false,
                            'default' => 0,
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
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET']),
            'Get slices of an article',
            null,
            new BearerAuth()
        );

        // Article Add Slice ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/add',
            new Route(
                'structure/articles/{id}/slices',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleAddArticleSlices',
                    'Body' => array_merge(
                        [
                            'module_id' => [
                                'type' => 'int',
                                'required' => true,
                                'default' => null,
                            ],
                            'ctype_id' => [
                                'type' => 'int',
                                'required' => false,
                                'default' => 1,
                            ],
                            'clang_id' => [
                                'type' => 'int',
                                'required' => true,
                                'default' => null,
                            ],
                        ],
                        $Values, // value1...19
                        $Medias, // media1...10
                        $Medialists, // medialist1...10
                        $Links, // link1...10
                        $Linklists, // linklist1...10
                    ),
                ],
                [
                    'id' => '\d+',
                ],
                [],
                '',
                [],
                ['POST']),
            'Add a slice to an article',
            null,
            new BearerAuth()
        );

        // Article Slice anzeigen ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/get',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleGetArticleSlice',
                ],
                [
                    'id' => '\d+',
                    'slice_id' => '\d+',
                ],
                [],
                '',
                [],
                ['GET']),
            'Get a specific slice of an article',
            null,
            new BearerAuth()
        );

        // Slice eines Artikel ändern ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/update',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleUpdateArticleSlice',
                    'Body' => array_merge(
                        [
                            'clang_id' => [
                                'type' => 'int',
                                'required' => false,
                                'default' => null,
                            ],
                        ],
                        $Values,
                        $Medias,
                        $Medialists,
                        $Links,
                        $Linklists,
                    ),
                ],
                [
                    'id' => '\d+',
                    'slice_id' => '\d+',
                ],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a slice of an article',
            null,
            new BearerAuth()
        );

        // Slice eines Artikel löschen ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/delete',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Structure::handleDeleteArticleSlice',
                ],
                [
                    'id' => '\d+',
                    'slice_id' => '\d+',
                ],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a slice of an article',
            null,
            new BearerAuth()
        );
    }

    private static function checkStructurePerm(?rex_user $user, ?int $categoryId = null): ?Response
    {
        if (null === $user) {
            return null;
        }
        if ($user->isAdmin()) {
            return null;
        }
        $perm = $user->getComplexPerm('structure');
        if (null !== $categoryId && !$perm->hasCategoryPerm($categoryId)) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }
        return null;
    }

    /** @api */
    public static function handleArticleList($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        if (null !== $user && !$user->isAdmin()) {
            $perm = $user->getComplexPerm('structure');
            if (!$perm->hasStructurePerm()) {
                return new Response(json_encode(['error' => 'Permission denied']), 403);
            }
        }

        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (null !== $Query['filter']['is_category']) {
            $SqlQueryWhere[':startarticle'] = 'startarticle = :startarticle';
            $SqlParameters[':startarticle'] = ($Query['filter']['is_category']) ? 1 : 0;
        }

        $SqlQueryWhere[':revision'] = 'revision = :revision';
        $SqlParameters[':revision'] = (!$Query['filter']['revision'] || 0 > $Query['filter']['revision']) ? 0 : $Query['filter']['revision'];

        if (null !== $Query['filter']['clang_id']) {
            $SqlQueryWhere[':clang'] = 'clang_id = :clang';
            $SqlParameters[':clang'] = $Query['filter']['clang_id'];
        }

        if (null !== $Query['filter']['parent_id']) {
            $SqlQueryWhere[':parent_id'] = 'parent_id = :parent_id';
            $SqlParameters[':parent_id'] = $Query['filter']['parent_id'];
        }

        if (null !== $Query['filter']['name'] && '' !== $Query['filter']['name']) {
            $SqlQueryWhere[':name'] = 'name LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['name'] . '%';
        }

        $ArticleFields = ['id', 'pid', 'name', 'catname', 'catpriority', 'clang_id', 'parent_id', 'priority', 'startarticle', 'status', 'template_id', 'createdate', 'createuser', 'updatedate', 'updateuser', 'revision'];
        $allowedSortFields = ['id', 'name', 'catname', 'priority', 'parent_id', 'status', 'template_id', 'createdate', 'updatedate', 'clang_id', 'catpriority'];

        try {
            $sortDefs = ListHelper::parseSort($Query['sort'] ?? null, $allowedSortFields, [['field' => 'id', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '';

        // Count total
        $CountSQL = rex_sql::factory();
        $countResult = $CountSQL->getArray(
            'SELECT COUNT(*) as total FROM ' . rex::getTablePrefix() . 'article ' . $whereClause,
            $SqlParameters,
        );
        $total = (int) $countResult[0]['total'];

        $per_page = (1 > $Query['per_page']) ? 10 : (int) $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : (int) $Query['page'];
        $pagination = ListHelper::paginate($page, $per_page, $total);

        $orderBy = ListHelper::buildSqlOrderBy($sortDefs);

        // LIMIT inlined as integers (rex_sql binds as string -> MySQL strict mode rejects).
        $offset = (int) $pagination['offset'];
        $limit = (int) $pagination['limit'];

        $ArticlesSQL = rex_sql::factory();
        $Articles = $ArticlesSQL->getArray(
            'SELECT ' . implode(',', $ArticleFields) . '
            FROM ' . rex::getTablePrefix() . 'article
            ' . $whereClause . '
            ORDER BY ' . $orderBy . '
            LIMIT ' . $offset . ', ' . $limit,
            $SqlParameters,
        );

        return new Response(json_encode(ListHelper::wrapResponse($Articles, $pagination['meta']), JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddArticle($Parameter, array $Route = []): Response
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, (int) (json_decode(rex::getRequest()->getContent(), true)['category_id'] ?? 0));
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

        if (0 !== $Data['category_id'] && !rex_category::get($Data['category_id'])) {
            return new Response(json_encode(['error' => 'Valid category_id is required']), 400);
        }

        try {
            $ArticleId = null;
            rex_extension::register('ART_ADDED', static function (rex_extension_point $ep) use (&$ArticleId) {
                $Params = $ep->getParams();
                $ArticleId = $Params['id'];
            });

            rex_article_service::addArticle([
                'name' => $Data['name'],
                'category_id' => $Data['category_id'],
                'priority' => $Data['priority'],
                'template_id' => $Data['template_id'],
                'status' => $Data['status'],
            ]);

            $Article = rex_article::get($ArticleId);
            if (!$Article) {
                return new Response(json_encode(['error' => 'Article not created - reason unknown']), 500);
            }

            return new Response(json_encode([
                'message' => 'Article created',
                'id' => $ArticleId,
            ],
            ), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleAddCategory($Parameter, array $Route = [])
    {
        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, (int) (json_decode(rex::getRequest()->getContent(), true)['category_id'] ?? 0));
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

        if (0 !== $Data['category_id'] && !rex_category::get($Data['category_id'])) {
            return new Response(json_encode(['error' => 'Valid category_id is required']), 400);
        }

        try {
            $CategoryId = null;
            rex_extension::register('CAT_ADDED', static function (rex_extension_point $ep) use (&$CategoryId) {
                $Params = $ep->getParams();
                $CategoryId = $Params['id'];
            });

            rex_category_service::addCategory($Data['category_id'], [
                'catname' => $Data['name'],
                'catpriority' => $Data['priority'],
                'template_id' => $Data['template_id'],
                'status' => $Data['status'],
            ]);

            $Category = rex_category::get($CategoryId);
            if (!$Category) {
                return new Response(json_encode(['error' => 'Category not created - reason unknown']), 500);
            }

            return new Response(json_encode([
                'message' => 'Category created',
                'id' => $CategoryId,
            ],
            ), 201);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteArticle($Parameter, array $Route = []): Response
    {
        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        if ($Article->isStartArticle()) {
            return new Response(json_encode(['error' => 'Article is category. Please use category route']), 403);
        }

        $ArticleId = $Article->getId();

        try {
            rex_article_service::deleteArticle($ArticleId);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage(), 'id' => $ArticleId]), 500);
        }

        return new Response(json_encode(['message' => 'Article deleted', 'id' => $ArticleId]), 200);
    }

    /** @api */
    public static function handleDeleteCategory($Parameter, array $Route = []): Response
    {
        $Category = rex_category::get($Parameter['id']);
        if (!$Category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Category->getId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $CategoryId = $Category->getId();

        try {
            rex_category_service::deleteCategory($CategoryId);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage(), 'id' => $CategoryId]), 500);
        }

        return new Response(json_encode(['message' => 'Category deleted', 'id' => $CategoryId]), 200);
    }

    /** @api */
    public static function handleAddArticleSlices($Parameter, array $Route = []): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);

        if (!is_array($Data)) {
            return new Response(json_encode([
                'error' => 'Invalid input',
            ]), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data, $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode([
                'error' => 'Body field: `' . $e->getMessage() . '` is required',
            ]), 400);
        }

        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode([
                'error' => 'Article not found',
                'id' => rex_escape($Parameter['id']),
            ]), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $clangs = rex_clang::getAllIds();
        if (!in_array($Data['clang_id'], $clangs)) {
            return new Response(json_encode([
                'error' => 'Clang not found',
                'clang_id' => rex_escape($Data['clang_id']),
            ]), 404);
        }

        // Get Template of Article
        $TemplateId = $Article->getValue('template_id');
        $Template = new rex_template($TemplateId);

        if (!$Template) {
            return new Response(json_encode([
                'error' => 'Template not found',
                'id' => rex_escape($TemplateId)],
            ), 404);
        }

        $CTypeId = null;
        $Ctypes = $Template->getCtypes();
        if (0 == count($Ctypes) && 1 != $Data['ctype_id']) {
            return new Response(json_encode([
                'error' => 'Template has not such ctype',
                'ctype' => rex_escape($Data['ctype_id']),
                'template_id' => rex_escape($TemplateId),
            ]), 404);
        }
        if (0 == count($Ctypes)) {
            $CTypeId = 1;
        } else {
            if (0 < count($Ctypes)) {
                foreach ($Ctypes as $Ctype) {
                    if ($Data['ctype_id'] == $Ctype->getId()) {
                        $CTypeId = $Ctype->getId();
                        break;
                    }
                }
            }
        }

        if (null === $CTypeId) {
            return new Response(json_encode([
                'error' => 'Template has not such ctype',
                'ctype' => rex_escape($Data['ctype_id']),
                'template_id' => rex_escape($TemplateId),
            ]), 404);
        }

        $ModuleQuery = rex_sql::factory()->setQuery('select * from rex_module where id = :id', ['id' => $Data['module_id']]);
        if (0 === $ModuleQuery->getRows()) {
            return new Response(json_encode([
                'error' => 'Module not found',
                'module_id' => rex_escape($Data['module_id']),
            ]), 404);
        }

        $TemplateQuery = rex_sql::factory()->setQuery('select * from rex_template where id = :id', ['id' => $Template->getId()]);
        $TemplateHasModule = rex_template::hasModule($TemplateQuery->getArrayValue('attributes'), $CTypeId, $Data['module_id']);
        if (!$TemplateHasModule) {
            return new Response(json_encode([
                'error' => 'Template has no module in such ctype',
                'template_id' => rex_escape($Template->getId()),
                'ctype_id' => rex_escape($Data['ctype_id']),
                'module_id' => rex_escape($Data['module_id']),
            ]), 404);
        }

        // value1...19
        // media1...19
        // medialist1...10
        // link1...10
        // linklist1...10

        $SliceData = [];
        for ($i = 1; $i <= 19; ++$i) {
            $SliceData['value' . $i] = $Data['value' . $i];
            if ($i <= 10) {
                $SliceData['media' . $i] = $Data['media' . $i];
                $SliceData['medialist' . $i] = $Data['medialist' . $i];
                $SliceData['link' . $i] = $Data['link' . $i];
                $SliceData['linklist' . $i] = $Data['linklist' . $i];
            }
        }

        try {
            $SliceId = null;
            rex_extension::register('SLICE_ADDED', static function (rex_extension_point $ep) use (&$SliceId) {
                $Params = $ep->getParams();
                $SliceId = $Params['slice_id'];
            });

            rex_content_service::addSlice(
                $Parameter['id'],
                $Data['clang_id'],
                $Data['ctype_id'],
                $Data['module_id'],
                $SliceData,
            );

            return new Response(json_encode([
                'message' => 'ArticleSlice created',
                'slice_id' => $SliceId,
            ]), 201);
        } catch (Exception $e) {
            return new Response(json_encode([
                'error' => $e->getMessage(),
            ]), 500);
        }
    }

    /** @api */
    public static function handleGetArticle($Parameter, array $Route = []): Response
    {
        $Article = rex_article::get($Parameter['id']);

        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $Return = [
            'id' => $Article->getId(),
            'pid' => $Article->getValue('pid'),
            'name' => $Article->getName(),
            'catname' => $Article->getValue('catname'),
            'catpriority' => $Article->getValue('catpriority'),
            'clang_id' => $Article->getClangId(),
            'parent_id' => $Article->getParentId(),
            'priority' => $Article->getPriority(),
            'startarticle' => $Article->isStartArticle() ? 1 : 0,
            'status' => $Article->isOnline() ? 1 : 0,
            'template_id' => $Article->getTemplateId(),
            'createdate' => $Article->getCreateDate(),
            'createuser' => $Article->getCreateUser(),
            'updatedate' => $Article->getUpdateDate(),
            'updateuser' => $Article->getUpdateUser(),
            'revision' => $Article->getValue('revision'),
        ];

        return new Response(json_encode($Return, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleUpdateArticle($Parameter, array $Route = []): Response
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

        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        if ($Article->isStartArticle()) {
            return new Response(json_encode(['error' => 'Article is a start article. Please use category route to update.']), 403);
        }

        try {
            $updateData = [];

            if (null !== $Data['name']) {
                $updateData['name'] = $Data['name'];
            }
            if (null !== $Data['priority']) {
                $updateData['priority'] = $Data['priority'];
            }
            if (null !== $Data['template_id']) {
                $updateData['template_id'] = $Data['template_id'];
            }

            if (!empty($updateData)) {
                rex_article_service::editArticle($Parameter['id'], $Article->getClangId(), $updateData);
            }

            if (null !== $Data['status']) {
                $currentStatus = $Article->isOnline() ? 1 : 0;
                if ($currentStatus !== $Data['status']) {
                    rex_article_service::changeStatus($Parameter['id'], $Article->getClangId(), $Data['status']);
                }
            }

            return new Response(json_encode([
                'message' => 'Article updated',
                'id' => $Parameter['id'],
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateCategory($Parameter, array $Route = []): Response
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

        $Category = rex_category::get($Parameter['id']);
        if (!$Category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Category->getId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $updateData = [];

            if (null !== $Data['name']) {
                $updateData['catname'] = $Data['name'];
            }
            if (null !== $Data['priority']) {
                $updateData['catpriority'] = $Data['priority'];
            }
            if (null !== $Data['template_id']) {
                $updateData['template_id'] = $Data['template_id'];
            }

            if (!empty($updateData)) {
                rex_category_service::editCategory($Parameter['id'], $Category->getClangId(), $updateData);
            }

            if (null !== $Data['status']) {
                $currentStatus = $Category->isOnline() ? 1 : 0;
                if ($currentStatus !== $Data['status']) {
                    rex_category_service::changeStatus($Parameter['id'], $Category->getClangId(), $Data['status']);
                }
            }

            return new Response(json_encode([
                'message' => 'Category updated',
                'id' => $Parameter['id'],
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleGetArticleSlices($Parameter, array $Route = []): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $SqlQueryWhere = ['article_id = :article_id'];
        $SqlParameters = [':article_id' => $Parameter['id']];

        if (null !== $Query['clang_id']) {
            $SqlQueryWhere[] = 'clang_id = :clang_id';
            $SqlParameters[':clang_id'] = $Query['clang_id'];
        }

        if (null !== $Query['ctype_id']) {
            $SqlQueryWhere[] = 'ctype_id = :ctype_id';
            $SqlParameters[':ctype_id'] = $Query['ctype_id'];
        }

        $SqlQueryWhere[] = 'revision = :revision';
        $SqlParameters[':revision'] = $Query['revision'] ?? 0;

        $allowedSortFields = ['id', 'ctype_id', 'module_id', 'priority', 'status', 'createdate', 'updatedate'];

        try {
            $sortDefs = ListHelper::parseSort($Query['sort'] ?? null, $allowedSortFields, [['field' => 'ctype_id', 'direction' => 'asc'], ['field' => 'priority', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $SqlQueryWhere);

        // Count total
        $CountSQL = rex_sql::factory();
        $countResult = $CountSQL->getArray(
            'SELECT COUNT(*) as total FROM ' . rex::getTable('article_slice') . ' ' . $whereClause,
            $SqlParameters,
        );
        $total = (int) $countResult[0]['total'];

        $per_page = (1 > $Query['per_page']) ? 10 : (int) $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : (int) $Query['page'];
        $pagination = ListHelper::paginate($page, $per_page, $total);

        $orderBy = ListHelper::buildSqlOrderBy($sortDefs);

        $offset = (int) $pagination['offset'];
        $limit = (int) $pagination['limit'];

        $SlicesSQL = rex_sql::factory();
        $Slices = $SlicesSQL->getArray(
            'SELECT id, article_id, clang_id, ctype_id, module_id, priority, status, createdate, createuser, updatedate, updateuser, revision
            FROM ' . rex::getTable('article_slice') . '
            ' . $whereClause . '
            ORDER BY ' . $orderBy . '
            LIMIT ' . $offset . ', ' . $limit,
            $SqlParameters,
        );

        return new Response(json_encode(ListHelper::wrapResponse($Slices, $pagination['meta']), JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetArticleSlice($Parameter, array $Route = []): Response
    {
        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $SliceSQL = rex_sql::factory();
        $SliceData = $SliceSQL->getArray(
            'SELECT * FROM ' . rex::getTable('article_slice') . ' WHERE id = :slice_id AND article_id = :article_id',
            [':slice_id' => $Parameter['slice_id'], ':article_id' => $Parameter['id']],
        );

        if (empty($SliceData)) {
            return new Response(json_encode(['error' => 'Slice not found']), 404);
        }

        return new Response(json_encode($SliceData[0], JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleUpdateArticleSlice($Parameter, array $Route = []): Response
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

        $sliceId = (int) $Parameter['slice_id'];
        $articleId = (int) $Parameter['id'];

        $Slice = self::loadSliceForArticle($sliceId, $articleId);
        if (null === $Slice) {
            return new Response(json_encode(['error' => 'Slice not found']), 404);
        }

        $clangId = (int) $Slice['clang_id'];
        $Article = rex_article::get($articleId, $clangId);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }
        if (null !== $user && !$user->getComplexPerm('modules')->hasPerm((int) $Slice['module_id'])) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }

        $UpdateData = [];
        for ($i = 1; $i <= 19; ++$i) {
            if (null !== $Data['value' . $i]) {
                $UpdateData['value' . $i] = $Data['value' . $i];
            }
            if ($i <= 10) {
                if (null !== $Data['media' . $i]) {
                    $UpdateData['media' . $i] = $Data['media' . $i];
                }
                if (null !== $Data['medialist' . $i]) {
                    $UpdateData['medialist' . $i] = $Data['medialist' . $i];
                }
                if (null !== $Data['link' . $i]) {
                    $UpdateData['link' . $i] = $Data['link' . $i];
                }
                if (null !== $Data['linklist' . $i]) {
                    $UpdateData['linklist' . $i] = $Data['linklist' . $i];
                }
            }
        }

        if (0 === count($UpdateData)) {
            return new Response(json_encode(['error' => 'No content fields provided']), 400);
        }

        $ctype = (int) $Slice['ctype_id'];
        $moduleId = (int) $Slice['module_id'];
        $sliceRevision = (int) $Slice['revision'];
        $articleRevision = 0;
        $categoryId = $Article->getCategoryId();

        try {
            // The PRE EPs (SLICE_UPDATE / SLICE_DELETE) are intentionally not fired here.
            // Their primary consumer is the structure/history plugin which calls
            // rex::requireUser()->getValue('login') to record history_user — that fails in
            // token-auth API context where no rex_user is set. Same convention as the existing
            // addSlice handler, which uses rex_content_service::addSlice() and fires SLICE_ADDED
            // (POST) only, never SLICE_ADD (PRE). All POST EPs below are fired exactly like the
            // backend content.php page does.
            self::fireSliceUpdatePreEpIfUserAvailable($sliceId, $articleId, $clangId, $sliceRevision);

            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('article_slice'));
            $sql->setWhere(['id' => $sliceId]);
            foreach ($UpdateData as $key => $value) {
                $sql->setValue($key, $value);
            }
            $sql->addGlobalUpdateFields('API');
            $sql->update();

            $info = '';
            $epParams = [
                'article_id' => $articleId,
                'clang' => $clangId,
                'function' => 'edit',
                'slice_id' => $sliceId,
                'page' => '',
                'ctype' => $ctype,
                'category_id' => $categoryId,
                'module_id' => $moduleId,
                'article_revision' => &$articleRevision,
                'slice_revision' => &$sliceRevision,
            ];

            // ----- POST EPs (mirror content.php:226-228)
            $info = rex_extension::registerPoint(new rex_extension_point('SLICE_UPDATED', $info, $epParams));
            /* deprecated */ $info = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SLICE_UPDATED', $info, $epParams));
            $info = rex_extension::registerPoint(new rex_extension_point_art_content_updated($Article, 'slice_updated', $info));

            // Article timestamp + cache (content.php:298-308)
            self::stampArticleAndInvalidate($articleId, $clangId);

            return new Response(json_encode([
                'message' => 'Slice updated',
                'slice_id' => $sliceId,
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleDeleteArticleSlice($Parameter, array $Route = []): Response
    {
        $sliceId = (int) $Parameter['slice_id'];
        $articleId = (int) $Parameter['id'];

        $Slice = self::loadSliceForArticle($sliceId, $articleId);
        if (null === $Slice) {
            return new Response(json_encode(['error' => 'Slice not found']), 404);
        }

        $clangId = (int) $Slice['clang_id'];
        $Article = rex_article::get($articleId, $clangId);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkStructurePerm($user, $Article->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }
        if (null !== $user && !$user->getComplexPerm('modules')->hasPerm((int) $Slice['module_id'])) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }

        $ctype = (int) $Slice['ctype_id'];
        $moduleId = (int) $Slice['module_id'];
        $sliceRevision = (int) $Slice['revision'];
        $articleRevision = 0;
        $categoryId = $Article->getCategoryId();

        try {
            // SLICE_DELETE PRE-EP is fired only when a backend user is available — see comment
            // on the update handler. We bypass rex_content_service::deleteSlice() (which would
            // unconditionally fire SLICE_DELETE) and replicate its body inline.
            self::fireSliceDeletePreEpIfUserAvailable($sliceId, $articleId, $clangId, $sliceRevision);

            $del = rex_sql::factory();
            $del->setQuery('DELETE FROM ' . rex::getTable('article_slice') . ' WHERE id = ?', [$sliceId]);

            rex_sql_util::organizePriorities(
                rex::getTable('article_slice'),
                'priority',
                'article_id=' . $articleId . ' AND clang_id=' . $clangId . ' AND ctype_id=' . $ctype . ' AND revision=' . $sliceRevision,
                'priority',
            );

            $info = '';
            $epParams = [
                'article_id' => $articleId,
                'clang' => $clangId,
                'function' => 'delete',
                'slice_id' => $sliceId,
                'page' => '',
                'ctype' => $ctype,
                'category_id' => $categoryId,
                'module_id' => $moduleId,
                'article_revision' => &$articleRevision,
                'slice_revision' => &$sliceRevision,
            ];

            // ----- POST EPs (mirror content.php:288-290)
            $info = rex_extension::registerPoint(new rex_extension_point('SLICE_DELETED', $info, $epParams));
            /* deprecated */ $info = rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_SLICE_DELETED', $info, $epParams));
            $info = rex_extension::registerPoint(new rex_extension_point_art_content_updated($Article, 'slice_deleted', $info));

            // Article timestamp + cache (content.php:298-308)
            self::stampArticleAndInvalidate($articleId, $clangId);

            return new Response(json_encode([
                'message' => 'Slice deleted',
                'slice_id' => $sliceId,
            ]), 200);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /**
     * Load a slice by id and verify it belongs to the given article. Returns null when not found.
     *
     * @return array<string, mixed>|null
     */
    private static function loadSliceForArticle(int $sliceId, int $articleId): ?array
    {
        $rows = rex_sql::factory()->getArray(
            'SELECT * FROM ' . rex::getTable('article_slice') . ' WHERE id = :slice_id AND article_id = :article_id LIMIT 1',
            [':slice_id' => $sliceId, ':article_id' => $articleId],
        );
        return $rows[0] ?? null;
    }

    /**
     * Fires SLICE_UPDATE (PRE) only when a backend user is set. The structure/history plugin
     * listens to this EP and unconditionally calls rex::requireUser()->getValue('login') for
     * its history_user column — so firing it without a user throws "User object does not exist".
     * Skipping is the same convention rex_content_service::addSlice() uses (it also avoids the
     * SLICE_ADD PRE EP and only fires SLICE_ADDED).
     */
    private static function fireSliceUpdatePreEpIfUserAvailable(int $sliceId, int $articleId, int $clangId, int $sliceRevision): void
    {
        if (null === rex::getUser()) {
            return;
        }
        rex_extension::registerPoint(new rex_extension_point('SLICE_UPDATE', '', [
            'slice_id' => $sliceId,
            'article_id' => $articleId,
            'clang_id' => $clangId,
            'slice_revision' => $sliceRevision,
        ]));
    }

    /**
     * Fires SLICE_DELETE (PRE) only when a backend user is set — same reasoning as
     * fireSliceUpdatePreEpIfUserAvailable().
     */
    private static function fireSliceDeletePreEpIfUserAvailable(int $sliceId, int $articleId, int $clangId, int $sliceRevision): void
    {
        if (null === rex::getUser()) {
            return;
        }
        rex_extension::registerPoint(new rex_extension_point('SLICE_DELETE', '', [
            'slice_id' => $sliceId,
            'article_id' => $articleId,
            'clang_id' => $clangId,
            'slice_revision' => $sliceRevision,
        ]));
    }

    /**
     * Touches rex_article (updatedate/updateuser), invalidates the article cache, and fires
     * STRUCTURE_CONTENT_ARTICLE_UPDATED — mirrors backend content.php:298-308 after slice mutations.
     */
    private static function stampArticleAndInvalidate(int $articleId, int $clangId): void
    {
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('article'));
        $sql->setWhere(['id' => $articleId, 'clang_id' => $clangId]);
        $sql->addGlobalUpdateFields('API');
        $sql->update();

        rex_article_cache::delete($articleId, $clangId);

        rex_extension::registerPoint(new rex_extension_point('STRUCTURE_CONTENT_ARTICLE_UPDATED', '', [
            'id' => $articleId,
            'clang' => $clangId,
        ]));
    }
}
