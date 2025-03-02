<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_article;
use rex_article_service;
use rex_category;
use rex_category_service;
use rex_clang;
use rex_content_service;
use rex_extension;
use rex_extension_point;
use rex_sql;
use rex_template;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

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
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleArticleList',
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
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET']),
            'Access to the list of articles',
        );

        // Article anzeigen ❌
        // /api/structure/articles/{id}

        // Article add ✅
        RouteCollection::registerRoute(
            'structure/articles/add',
            new Route(
                'structure/articles/',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleAddArticle',
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
        );

        // Category add ✅
        RouteCollection::registerRoute(
            'structure/categories/add',
            new Route(
                'structure/categories/',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleAddCategory',
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
        );

        // Article delete ✅
        RouteCollection::registerRoute(
            'structure/articles/delete',
            new Route(
                'structure/articles/{id}',
                ['_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleDeleteArticle'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete an article',
        );

        // Category delete ✅
        RouteCollection::registerRoute(
            'structure/categories/delete',
            new Route(
                'structure/categories/{id}',
                ['_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleDeleteCategory'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a category',
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

        // Article Slices anzeigen ❌
        // /api/structure/articles/{id}/slices

        // Article Add Slice ✅
        RouteCollection::registerRoute(
            'structure/articles/slices/add',
            new Route(
                'structure/articles/{id}/slices',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Structure::handleAddArticleSlices',
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
        );

        // Article Slice anzeigen ❌
        // /api/structure/articles/{id}/slices/{slice_id}

        // Slice eines Artikel ändern ❌
        // /api/structure/articles/{id}/slices/{slice_id}
    }

    /** @api */
    public static function handleArticleList($Parameter): Response
    {
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

        if (null !== $Query['filter']['name']) {
            $SqlQueryWhere[':name'] = 'id LIKE :name';
            $SqlParameters[':name'] = '%' . $Query['filter']['id'] . '%';
        }

        $ArticeFields = ['id', 'pid', 'name', 'catname', 'catpriority', 'clang_id', 'parent_id', 'priority', 'startarticle', 'status', 'template_id', 'createdate', 'createuser', 'updatedate', 'updateuser', 'revision'];

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $ArticlesSQL = rex_sql::factory();
        $Articles = $ArticlesSQL->getArray(
            '
            select
                ' . implode(',', $ArticeFields) . '
            from
                ' . rex::getTablePrefix() . 'article
            ' . (count($SqlQueryWhere) ? 'where ' . implode(' and ', $SqlQueryWhere) : '') . '

            LIMIT :start, :per_page
                ',
            $SqlParameters,
        );

        return new Response(json_encode($Articles, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleAddArticle($Parameter): Response
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
    public static function handleAddCategory($Parameter)
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
    public static function handleDeleteArticle($Parameter): Response
    {
        $Article = rex_article::get($Parameter['id']);
        if (!$Article) {
            return new Response(json_encode(['error' => 'Article not found']), 404);
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
    public static function handleDeleteCategory($Parameter): Response
    {
        $Category = rex_category::get($Parameter['id']);
        if (!$Category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
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
    public static function handleAddArticleSlices($Parameter): Response
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
}
