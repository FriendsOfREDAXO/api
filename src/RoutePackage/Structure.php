<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

use const JSON_PRETTY_PRINT;

class Structure extends RoutePackage
{
    /**
     * Columns exposed by the article list/get endpoints.
     *
     * Note `template` (a template *key*) instead of REDAXO 5's `template_id`: in REDAXO 6 templates are
     * PHP classes identified by a string key, and `rex_article.template` holds that key.
     */
    public const array ARTICLE_FIELDS = ['id', 'pid', 'name', 'catname', 'catpriority', 'clang_id', 'parent_id', 'priority', 'startarticle', 'status', 'template', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    #[Override]
    public function loadRoutes(): void
    {
        // Article List
        RouteCollection::registerRoute(
            'structure/articles/list',
            new Route(
                'structure/articles',
                [
                    '_controller' => self::class . '::handleArticleList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'parent_id' => ['type' => 'int', 'required' => false, 'default' => null],
                                'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                                'is_category' => ['type' => 'int', 'required' => false, 'default' => null],
                                'name' => ['type' => 'string', 'required' => false, 'default' => ''],
                            ],
                            'type' => 'array',
                            'required' => true,
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
            'Access to the list of articles',
            null,
            new BearerAuth(),
        );

        // Article get
        RouteCollection::registerRoute(
            'structure/articles/get',
            new Route(
                'structure/articles/{id}',
                [
                    '_controller' => self::class . '::handleGetArticle',
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get an article',
            null,
            new BearerAuth(),
        );

        // Article add
        RouteCollection::registerRoute(
            'structure/articles/add',
            new Route(
                'structure/articles',
                [
                    '_controller' => self::class . '::handleAddArticle',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                        'priority' => ['type' => 'integer', 'required' => false, 'default' => 0],
                        'category_id' => ['type' => 'integer', 'required' => true, 'default' => 0],
                        'status' => ['type' => 'int', 'required' => false, 'default' => 0],
                        'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key (see /templates)'],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST'],
            ),
            'Add an article',
            null,
            new BearerAuth(),
        );

        // Category add
        RouteCollection::registerRoute(
            'structure/categories/add',
            new Route(
                'structure/categories',
                [
                    '_controller' => self::class . '::handleAddCategory',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                        'priority' => ['type' => 'integer', 'required' => false, 'default' => 0],
                        'category_id' => ['type' => 'integer', 'required' => true, 'default' => 0],
                        'status' => ['type' => 'integer', 'required' => false, 'default' => 0],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST'],
            ),
            'Add a category',
            null,
            new BearerAuth(),
        );

        // Article delete
        RouteCollection::registerRoute(
            'structure/articles/delete',
            new Route(
                'structure/articles/{id}',
                ['_controller' => self::class . '::handleDeleteArticle'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete an article',
            null,
            new BearerAuth(),
        );

        // Category delete
        RouteCollection::registerRoute(
            'structure/categories/delete',
            new Route(
                'structure/categories/{id}',
                ['_controller' => self::class . '::handleDeleteCategory'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a category',
            null,
            new BearerAuth(),
        );

        // Article update
        RouteCollection::registerRoute(
            'structure/articles/update',
            new Route(
                'structure/articles/{id}',
                [
                    '_controller' => self::class . '::handleUpdateArticle',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => false, 'default' => null],
                        'priority' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key (see /templates)'],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update an article',
            null,
            new BearerAuth(),
        );

        // Category update
        RouteCollection::registerRoute(
            'structure/categories/update',
            new Route(
                'structure/categories/{id}',
                [
                    '_controller' => self::class . '::handleUpdateCategory',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => false, 'default' => null],
                        'priority' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key (see /templates)'],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update a category',
            null,
            new BearerAuth(),
        );

        $values = [];
        $medias = [];
        $medialists = [];
        $links = [];
        $linklists = [];

        for ($i = 1; $i <= 19; ++$i) {
            $values['value' . $i] = ['type' => 'string', 'required' => false, 'default' => null];

            if ($i <= 10) {
                $medias['media' . $i] = ['type' => 'string', 'required' => false, 'default' => null];
                $medialists['medialist' . $i] = ['type' => 'string', 'required' => false, 'default' => null];
                $links['link' . $i] = ['type' => 'string', 'required' => false, 'default' => null];
                $linklists['linklist' . $i] = ['type' => 'string', 'required' => false, 'default' => null];
            }
        }

        // Article slices list
        RouteCollection::registerRoute(
            'structure/articles/slices/list',
            new Route(
                'structure/articles/{id}/slices',
                [
                    '_controller' => self::class . '::handleGetArticleSlices',
                    'query' => [
                        'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                        'ctype_id' => ['type' => 'int', 'required' => false, 'default' => null],
                        'revision' => ['type' => 'int', 'required' => false, 'default' => 0],
                        'page' => ['type' => 'int', 'required' => false, 'default' => 1],
                        'per_page' => ['type' => 'int', 'required' => false, 'default' => 100],
                        'sort' => ['type' => 'string', 'required' => false, 'default' => null],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get slices of an article',
            null,
            new BearerAuth(),
        );

        // Article add slice
        RouteCollection::registerRoute(
            'structure/articles/slices/add',
            new Route(
                'structure/articles/{id}/slices',
                [
                    '_controller' => self::class . '::handleAddArticleSlices',
                    'Body' => array_merge(
                        [
                            'module' => ['type' => 'string', 'required' => true, 'default' => null, 'description' => 'Module key (see /modules)'],
                            'ctype_id' => ['type' => 'int', 'required' => false, 'default' => 1, 'description' => 'Content section id of the article template'],
                            'clang_id' => ['type' => 'int', 'required' => true, 'default' => null],
                        ],
                        $values, // value1...19
                        $medias, // media1...10
                        $medialists, // medialist1...10
                        $links, // link1...10
                        $linklists, // linklist1...10
                    ),
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['POST'],
            ),
            'Add a slice to an article',
            null,
            new BearerAuth(),
        );

        // Article slice get
        RouteCollection::registerRoute(
            'structure/articles/slices/get',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => self::class . '::handleGetArticleSlice',
                ],
                ['id' => '\d+', 'slice_id' => '\d+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get a specific slice of an article',
            null,
            new BearerAuth(),
        );

        // Article slice update
        RouteCollection::registerRoute(
            'structure/articles/slices/update',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => self::class . '::handleUpdateArticleSlice',
                    'Body' => array_merge(
                        [
                            'clang_id' => ['type' => 'int', 'required' => false, 'default' => null],
                        ],
                        $values,
                        $medias,
                        $medialists,
                        $links,
                        $linklists,
                    ),
                ],
                ['id' => '\d+', 'slice_id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update a slice of an article',
            null,
            new BearerAuth(),
        );

        // Article slice delete
        RouteCollection::registerRoute(
            'structure/articles/slices/delete',
            new Route(
                'structure/articles/{id}/slices/{slice_id}',
                [
                    '_controller' => self::class . '::handleDeleteArticleSlice',
                ],
                ['id' => '\d+', 'slice_id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a slice of an article',
            null,
            new BearerAuth(),
        );
    }

    /**
     * @param int|null $categoryId The category the element lives in; `null` means root level (which only
     *     users with the "all categories" permission may touch).
     */
    private static function checkStructurePerm(?User $user, ?int $categoryId): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('structure');

        if (!$perm instanceof StructurePermission || !$perm->hasCategoryPerm($categoryId)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }

    private static function checkModulePerm(?User $user, string $moduleKey): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('modules');

        if (!$perm instanceof ModulePermission || !$perm->hasPerm($moduleKey)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleArticleList(array $parameter, array $route = []): Response
    {
        $user = RouteCollection::getBackendUser($route);
        if (null !== $user && !$user->admin) {
            $perm = $user->getComplexPerm('structure');
            if (!$perm instanceof StructurePermission || !$perm->hasStructurePerm()) {
                return new JsonResponse(['error' => 'Permission denied'], 403);
            }
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $where = [];
        $params = [];

        if (null !== $query['filter']['is_category']) {
            $where[] = 'startarticle = :startarticle';
            $params[':startarticle'] = $query['filter']['is_category'] ? 1 : 0;
        }

        if (null !== $query['filter']['clang_id']) {
            $where[] = 'clang_id = :clang';
            $params[':clang'] = $query['filter']['clang_id'];
        }

        if (null !== $query['filter']['parent_id']) {
            $where[] = 'parent_id = :parent_id';
            $params[':parent_id'] = $query['filter']['parent_id'];
        }

        if (null !== $query['filter']['name'] && '' !== $query['filter']['name']) {
            $where[] = 'name LIKE :name';
            $params[':name'] = '%' . $query['filter']['name'] . '%';
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'name', 'catname', 'priority', 'parent_id', 'status', 'template', 'createdate', 'updatedate', 'clang_id', 'catpriority'], [['field' => 'id', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $countResult = Sql::factory()->getArray(
            'SELECT COUNT(*) as total FROM ' . Core::getTable('article') . ' ' . $whereClause,
            $params,
        );
        $total = (int) $countResult[0]['total'];

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];
        $pagination = ListHelper::paginate($page, $perPage, $total);

        // LIMIT inlined as integers (Sql binds parameters as strings -> MySQL strict mode rejects them).
        $articles = Sql::factory()->getArray(
            'SELECT ' . implode(',', self::ARTICLE_FIELDS) . '
            FROM ' . Core::getTable('article') . '
            ' . $whereClause . '
            ORDER BY ' . ListHelper::buildSqlOrderBy($sortDefs) . '
            LIMIT ' . (int) $pagination['offset'] . ', ' . (int) $pagination['limit'],
            $params,
        );

        return new JsonResponse(json_encode(ListHelper::wrapResponse($articles, $pagination['meta']), JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetArticle(array $parameter, array $route = []): Response
    {
        $article = Article::get((int) $parameter['id']);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        // Read the row instead of mapping the Article object: REDAXO 6's Article deliberately drops the
        // category-only columns (pid, catname, catpriority), and this keeps get and list identical.
        $rows = Sql::factory()->getArray(
            'SELECT ' . implode(',', self::ARTICLE_FIELDS) . ' FROM ' . Core::getTable('article') . ' WHERE id = :id AND clang_id = :clang_id',
            [':id' => $article->id, ':clang_id' => $article->clangId],
        );

        if (0 === count($rows)) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        return new JsonResponse(json_encode($rows[0], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddArticle(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), (int) ($data['category_id'] ?? 0));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $categoryId = (int) $data['category_id'];

        if (0 !== $categoryId && null === Category::get($categoryId)) {
            return new JsonResponse(['error' => 'Valid category_id is required'], 400);
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        try {
            $articleId = null;
            Extension::register('ART_ADDED', static function (ExtensionPoint $ep) use (&$articleId): void {
                $articleId ??= $ep->getParam('id');
            });

            ArticleHandler::addArticle([
                'name' => $data['name'],
                'category_id' => $categoryId,
                'priority' => $data['priority'],
                'template' => $data['template'],
                'status' => $data['status'],
            ]);

            if (null === $articleId || null === Article::get($articleId)) {
                return new JsonResponse(['error' => 'Article not created - reason unknown'], 500);
            }

            // ArticleHandler::addArticle() hardcodes status=0 in REDAXO core — apply the requested
            // status explicitly per language.
            if (1 === (int) $data['status']) {
                foreach (Language::getAllIds() as $clangId) {
                    ArticleHandler::articleStatus($articleId, $clangId, 1);
                }
            }

            return new JsonResponse([
                'message' => 'Article created',
                'id' => $articleId,
            ], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddCategory(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), (int) ($data['category_id'] ?? 0));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $categoryId = (int) $data['category_id'];

        if (0 !== $categoryId && null === Category::get($categoryId)) {
            return new JsonResponse(['error' => 'Valid category_id is required'], 400);
        }

        try {
            $newCategoryId = null;
            Extension::register('CAT_ADDED', static function (ExtensionPoint $ep) use (&$newCategoryId): void {
                $newCategoryId ??= $ep->getParam('id');
            });

            CategoryHandler::addCategory($categoryId, [
                'catname' => $data['name'],
                'catpriority' => $data['priority'],
                'status' => $data['status'],
            ]);

            if (null === $newCategoryId || null === Category::get($newCategoryId)) {
                return new JsonResponse(['error' => 'Category not created - reason unknown'], 500);
            }

            return new JsonResponse([
                'message' => 'Category created',
                'id' => $newCategoryId,
            ], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateArticle(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $articleId = (int) $parameter['id'];
        $article = Article::get($articleId);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if ($article->isStartArticle()) {
            return new JsonResponse(['error' => 'Article is a start article. Please use category route to update.'], 403);
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        try {
            // editArticle() requires `name` and resets `template` to the first allowed one when it is
            // absent, so both are always passed — falling back to the article's current values.
            $updateData = [
                'name' => $data['name'] ?? $article->name,
                'template' => $data['template'] ?? $article->getValue('template'),
            ];

            if (null !== $data['priority']) {
                $updateData['priority'] = $data['priority'];
            }

            ArticleHandler::editArticle($articleId, $article->clangId, $updateData);

            if (null !== $data['status']) {
                $currentStatus = $article->isOnline() ? 1 : 0;
                if ($currentStatus !== (int) $data['status']) {
                    ArticleHandler::articleStatus($articleId, $article->clangId, (int) $data['status']);
                }
            }

            return new JsonResponse([
                'message' => 'Article updated',
                'id' => $articleId,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateCategory(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $categoryId = (int) $parameter['id'];
        $category = Category::get($categoryId);

        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $category->id);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        try {
            // editCategory() requires `catname`, so the current one is passed when it is not changing.
            $updateData = ['catname' => $data['name'] ?? $category->getValue('catname')];

            if (null !== $data['priority']) {
                $updateData['catpriority'] = $data['priority'];
            }
            if (null !== $data['template']) {
                $updateData['template'] = $data['template'];
            }

            CategoryHandler::editCategory($categoryId, $category->clangId, $updateData);

            if (null !== $data['status']) {
                $currentStatus = $category->isOnline() ? 1 : 0;
                if ($currentStatus !== (int) $data['status']) {
                    CategoryHandler::categoryStatus($categoryId, $category->clangId, (int) $data['status']);
                }
            }

            return new JsonResponse([
                'message' => 'Category updated',
                'id' => $categoryId,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteArticle(array $parameter, array $route = []): Response
    {
        $article = Article::get((int) $parameter['id']);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if ($article->isStartArticle()) {
            return new JsonResponse(['error' => 'Article is category. Please use category route'], 403);
        }

        try {
            ArticleHandler::deleteArticle($article->id);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'id' => $article->id], 500);
        }

        return new JsonResponse(['message' => 'Article deleted', 'id' => $article->id], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteCategory(array $parameter, array $route = []): Response
    {
        $category = Category::get((int) $parameter['id']);

        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $category->id);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            CategoryHandler::deleteCategory($category->id);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'id' => $category->id], 500);
        }

        return new JsonResponse(['message' => 'Category deleted', 'id' => $category->id], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetArticleSlices(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $articleId = (int) $parameter['id'];
        $article = Article::get($articleId);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $where = ['article_id = :article_id'];
        $params = [':article_id' => $articleId];

        if (null !== $query['clang_id']) {
            $where[] = 'clang_id = :clang_id';
            $params[':clang_id'] = $query['clang_id'];
        }

        if (null !== $query['ctype_id']) {
            $where[] = 'ctype_id = :ctype_id';
            $params[':ctype_id'] = $query['ctype_id'];
        }

        $where[] = 'revision = :revision';
        $params[':revision'] = $query['revision'] ?? 0;

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'ctype_id', 'module', 'priority', 'status', 'createdate', 'updatedate'], [['field' => 'ctype_id', 'direction' => 'asc'], ['field' => 'priority', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $countResult = Sql::factory()->getArray(
            'SELECT COUNT(*) as total FROM ' . Core::getTable('article_slice') . ' ' . $whereClause,
            $params,
        );
        $total = (int) $countResult[0]['total'];

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];
        $pagination = ListHelper::paginate($page, $perPage, $total);

        $slices = Sql::factory()->getArray(
            'SELECT id, article_id, clang_id, ctype_id, module, priority, status, createdate, createuser, updatedate, updateuser, revision
            FROM ' . Core::getTable('article_slice') . '
            ' . $whereClause . '
            ORDER BY ' . ListHelper::buildSqlOrderBy($sortDefs) . '
            LIMIT ' . (int) $pagination['offset'] . ', ' . (int) $pagination['limit'],
            $params,
        );

        return new JsonResponse(json_encode(ListHelper::wrapResponse($slices, $pagination['meta']), JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetArticleSlice(array $parameter, array $route = []): Response
    {
        $articleId = (int) $parameter['id'];
        $article = Article::get($articleId);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $permResponse = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $slice = self::loadSliceForArticle((int) $parameter['slice_id'], $articleId);

        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        return new JsonResponse(json_encode($slice, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddArticleSlices(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $articleId = (int) $parameter['id'];
        $article = Article::get($articleId);

        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found', 'id' => escape((string) $parameter['id'])], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        $permResponse = self::checkStructurePerm($user, $article->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $clangId = (int) $data['clang_id'];
        if (!in_array($clangId, Language::getAllIds(), true)) {
            return new JsonResponse(['error' => 'Clang not found', 'clang_id' => escape((string) $clangId)], 404);
        }

        $moduleKey = (string) $data['module'];
        if (!Module::exists($moduleKey)) {
            return new JsonResponse(['error' => 'Module not found', 'module' => escape($moduleKey)], 404);
        }

        $permResponse = self::checkModulePerm($user, $moduleKey);
        if (null !== $permResponse) {
            return $permResponse;
        }

        // The article's template decides which content sections exist and which modules they allow.
        $templateKey = (string) $article->getValue('template');
        $template = Template::get($templateKey);

        if (null === $template) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape($templateKey)], 404);
        }

        $ctypeId = (int) $data['ctype_id'];
        if ($ctypeId < 1 || !$template->hasContentSection($ctypeId)) {
            return new JsonResponse([
                'error' => 'Template has no such content section',
                'ctype_id' => escape((string) $ctypeId),
                'template' => escape($templateKey),
            ], 404);
        }

        if (!Template::checkModuleAllowed($templateKey, $ctypeId, $moduleKey)) {
            return new JsonResponse([
                'error' => 'Template does not allow this module in that content section',
                'template' => escape($templateKey),
                'ctype_id' => escape((string) $ctypeId),
                'module' => escape($moduleKey),
            ], 404);
        }

        $sliceData = [];
        for ($i = 1; $i <= 19; ++$i) {
            $sliceData['value' . $i] = $data['value' . $i];

            if ($i <= 10) {
                $sliceData['media' . $i] = $data['media' . $i];
                $sliceData['medialist' . $i] = $data['medialist' . $i];
                $sliceData['link' . $i] = $data['link' . $i];
                $sliceData['linklist' . $i] = $data['linklist' . $i];
            }
        }

        try {
            $sliceId = null;
            Extension::register('SLICE_ADDED', static function (ExtensionPoint $ep) use (&$sliceId): void {
                $sliceId ??= $ep->getParam('slice_id');
            });

            // addSlice() fires SLICE_ADDED and ART_CONTENT_UPDATED itself.
            ContentHandler::addSlice($articleId, $clangId, $ctypeId, $moduleKey, $sliceData);

            // The backend content page stamps the article and invalidates its cache after every slice
            // mutation — mirror that here as well.
            self::stampArticleAndInvalidate($articleId, $clangId);

            return new JsonResponse([
                'message' => 'ArticleSlice created',
                'slice_id' => $sliceId,
            ], 201);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateArticleSlice(array $parameter, array $route = []): Response
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $sliceId = (int) $parameter['slice_id'];
        $articleId = (int) $parameter['id'];

        $slice = self::loadSliceForArticle($sliceId, $articleId);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $clangId = (int) $slice['clang_id'];
        $article = Article::get($articleId, $clangId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        $permResponse = self::checkStructurePerm($user, $article->categoryId)
            ?? self::checkModulePerm($user, (string) $slice['module']);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $updateData = [];
        for ($i = 1; $i <= 19; ++$i) {
            if (null !== $data['value' . $i]) {
                $updateData['value' . $i] = $data['value' . $i];
            }

            if ($i <= 10) {
                foreach (['media', 'medialist', 'link', 'linklist'] as $prefix) {
                    if (null !== $data[$prefix . $i]) {
                        $updateData[$prefix . $i] = $data[$prefix . $i];
                    }
                }
            }
        }

        if (0 === count($updateData)) {
            return new JsonResponse(['error' => 'No content fields provided'], 400);
        }

        $ctype = (int) $slice['ctype_id'];
        $moduleKey = (string) $slice['module'];
        $sliceRevision = (int) $slice['revision'];

        try {
            // The PRE EPs (SLICE_UPDATE / SLICE_DELETE) are only fired when a backend user is available:
            // their primary consumer records a history entry keyed by the current user's login, which
            // fails under token auth where no user is set. This is the same convention
            // ContentHandler::addSlice() follows (it fires SLICE_ADDED only, never SLICE_ADD).
            self::fireSlicePreEpIfUserAvailable('SLICE_UPDATE', $sliceId, $articleId, $clangId, $sliceRevision);

            $sql = Sql::factory();
            $sql->setTable(Core::getTable('article_slice'));
            $sql->setWhere(['id' => $sliceId]);
            foreach ($updateData as $key => $value) {
                $sql->setValue($key, $value);
            }
            $sql->addGlobalUpdateFields(self::getApiUser());
            $sql->update();

            ArticleCache::delete($articleId, $clangId);

            $message = Extension::dispatch(new ExtensionPoint('SLICE_UPDATED', '', [
                'article_id' => $articleId,
                'clang' => $clangId,
                'function' => 'edit',
                'slice_id' => $sliceId,
                'page' => '',
                'ctype' => $ctype,
                'category_id' => $article->categoryId,
                'module_key' => $moduleKey,
                'article_revision' => 0,
                'slice_revision' => $sliceRevision,
            ]));

            Extension::dispatch(new ArticleContentUpdated($article, 'slice_updated', $message));

            self::stampArticleAndInvalidate($articleId, $clangId);

            return new JsonResponse([
                'message' => 'Slice updated',
                'slice_id' => $sliceId,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteArticleSlice(array $parameter, array $route = []): Response
    {
        $sliceId = (int) $parameter['slice_id'];
        $articleId = (int) $parameter['id'];

        $slice = self::loadSliceForArticle($sliceId, $articleId);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $clangId = (int) $slice['clang_id'];
        $article = Article::get($articleId, $clangId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        $permResponse = self::checkStructurePerm($user, $article->categoryId)
            ?? self::checkModulePerm($user, (string) $slice['module']);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $ctype = (int) $slice['ctype_id'];
        $moduleKey = (string) $slice['module'];
        $sliceRevision = (int) $slice['revision'];

        try {
            // See the comment in handleUpdateArticleSlice(): the PRE EP needs a user. Because of that
            // ContentHandler::deleteSlice() (which fires it unconditionally) is bypassed and its body
            // replicated here.
            self::fireSlicePreEpIfUserAvailable('SLICE_DELETE', $sliceId, $articleId, $clangId, $sliceRevision);

            Sql::factory()->setQuery('DELETE FROM ' . Core::getTable('article_slice') . ' WHERE id = ?', [$sliceId]);

            Util::organizePriorities(
                Core::getTable('article_slice'),
                'priority',
                'article_id=' . $articleId . ' AND clang_id=' . $clangId . ' AND ctype_id=' . $ctype . ' AND revision=' . $sliceRevision,
                'priority',
            );

            ArticleCache::delete($articleId, $clangId);

            $message = Extension::dispatch(new ExtensionPoint('SLICE_DELETED', '', [
                'article_id' => $articleId,
                'clang' => $clangId,
                'function' => 'delete',
                'slice_id' => $sliceId,
                'page' => '',
                'ctype' => $ctype,
                'category_id' => $article->categoryId,
                'module_key' => $moduleKey,
                'article_revision' => 0,
                'slice_revision' => $sliceRevision,
            ]));

            Extension::dispatch(new ArticleContentUpdated($article, 'slice_deleted', $message));

            self::stampArticleAndInvalidate($articleId, $clangId);

            return new JsonResponse([
                'message' => 'Slice deleted',
                'slice_id' => $sliceId,
            ], 200);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Loads a slice by id and verifies it belongs to the given article. Returns `null` when not found.
     *
     * @return array<string, mixed>|null
     */
    private static function loadSliceForArticle(int $sliceId, int $articleId): ?array
    {
        $rows = Sql::factory()->getArray(
            'SELECT * FROM ' . Core::getTable('article_slice') . ' WHERE id = :slice_id AND article_id = :article_id LIMIT 1',
            [':slice_id' => $sliceId, ':article_id' => $articleId],
        );

        return $rows[0] ?? null;
    }

    /** Fires a slice PRE extension point, but only when a backend user is set — see the call sites for why. */
    private static function fireSlicePreEpIfUserAvailable(string $name, int $sliceId, int $articleId, int $clangId, int $sliceRevision): void
    {
        if (null === Core::getUser()) {
            return;
        }

        Extension::dispatch(new ExtensionPoint($name, '', [
            'slice_id' => $sliceId,
            'article_id' => $articleId,
            'clang_id' => $clangId,
            'slice_revision' => $sliceRevision,
        ]));
    }

    /**
     * Touches `rex_article` (updatedate/updateuser), invalidates the article cache and fires
     * STRUCTURE_CONTENT_ARTICLE_UPDATED — mirrors the backend content page after slice mutations.
     */
    private static function stampArticleAndInvalidate(int $articleId, int $clangId): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTable('article'));
        $sql->setWhere(['id' => $articleId, 'clang_id' => $clangId]);
        $sql->addGlobalUpdateFields(self::getApiUser());
        $sql->update();

        ArticleCache::delete($articleId, $clangId);

        Extension::dispatch(new ExtensionPoint('STRUCTURE_CONTENT_ARTICLE_UPDATED', '', [
            'id' => $articleId,
            'clang' => $clangId,
        ]));
    }

    /** The `updateuser` value written by API-driven changes: the backend user if any, otherwise "API". */
    private static function getApiUser(): string
    {
        return Core::getUser()?->login ?? 'API';
    }
}
