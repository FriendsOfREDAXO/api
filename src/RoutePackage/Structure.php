<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Service\SliceService;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\ArticleSlice;
use Redaxo\Core\Content\ArticleSliceReference;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Content\ContentHandler;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\SliceUpdating;
use Redaxo\Core\Content\Module;
use Redaxo\Core\Content\SliceMoveDirection;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\DatabaseUtil;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Form\FormSchema;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_diff;
use function array_key_exists;
use function array_slice;
use function count;
use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

use const JSON_PRETTY_PRINT;

/**
 * Articles, categories and slices.
 *
 * The contract follows the REDAXO 6 content model: names, status and template are per language
 * (`language_id`, default the start language), priorities and the position in the tree apply to all languages,
 * templates and modules are addressed by key, and the fields of a slice are its `data` object (see
 * {@see SliceService}).
 */
class Structure extends RoutePackage
{
    /** API field => SQL expression of the article list. */
    private const array ARTICLE_COLUMNS = [
        'id' => 'a.id',
        'category_id' => 'a.parent_id',
        'name' => 't.name',
        'priority' => 'a.priority',
        'status' => 't.status',
        'template' => 't.template',
        'language_id' => 't.language_id',
        'is_category' => '(c.id IS NOT NULL)',
        'createdate' => 'a.createdate',
        'createuser' => 'a.createuser',
        'updatedate' => 'a.updatedate',
        'updateuser' => 'a.updateuser',
    ];

    /** API field => SQL expression of the category list. */
    private const array CATEGORY_COLUMNS = [
        'id' => 'c.id',
        'parent_id' => 'a.parent_id',
        'name' => 'ct.name',
        'priority' => 'c.priority',
        'status' => 't.status',
        'template' => 't.template',
        'language_id' => 'ct.language_id',
        'createdate' => 'c.createdate',
        'createuser' => 'c.createuser',
        'updatedate' => 'c.updatedate',
        'updateuser' => 'c.updateuser',
    ];

    #[Override]
    public function loadRoutes(): void
    {
        $language = ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Language id, default: the start language'];
        $list = [
            'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
            'per_page' => ['type' => 'integer', 'required' => false, 'default' => 100],
            'sort' => ['type' => 'string', 'required' => false, 'default' => null],
        ];

        self::route('structure/articles/list', 'structure/articles', 'handleArticleList', ['GET'], 'Access to the list of articles', query: [
            'filter' => [
                'fields' => [
                    'category_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => '0 for the root level'],
                    'language_id' => ['type' => 'integer', 'required' => false, 'default' => null],
                    'is_category' => ['type' => 'integer', 'required' => false, 'default' => null],
                    'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                    'template' => ['type' => 'string', 'required' => false, 'default' => null],
                    'name' => ['type' => 'string', 'required' => false, 'default' => ''],
                ],
                'type' => 'array',
                'required' => false,
                'default' => [],
            ],
        ] + $list);

        self::route('structure/articles/get', 'structure/articles/{id}', 'handleGetArticle', ['GET'], 'Get an article', query: [
            'language_id' => $language,
        ]);

        self::route('structure/articles/add', 'structure/articles', 'handleAddArticle', ['POST'], 'Add an article', body: [
            'name' => ['type' => 'string', 'required' => true],
            'category_id' => ['type' => 'integer', 'required' => false, 'default' => 0, 'description' => '0 for the root level'],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => 0, 'description' => '0 to append'],
            'status' => ['type' => 'integer', 'required' => false, 'default' => 0, 'description' => 'Status in all languages'],
            'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key (see /templates), default: the first one allowed in the category'],
        ]);

        self::route('structure/articles/update', 'structure/articles/{id}', 'handleUpdateArticle', ['PUT', 'PATCH'], 'Update an article', body: [
            'language_id' => $language + ['description' => 'Language of name, status and template, default: the start language'],
            'name' => ['type' => 'string', 'required' => false, 'default' => null],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => null],
            'status' => ['type' => 'integer', 'required' => false, 'default' => null],
            'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key (see /templates)'],
        ]);

        self::route('structure/articles/delete', 'structure/articles/{id}', 'handleDeleteArticle', ['DELETE'], 'Delete an article');

        self::route('structure/articles/move', 'structure/articles/{id}/move', 'handleMoveArticle', ['POST'], 'Move an article into another category', body: [
            'category_id' => ['type' => 'integer', 'required' => true, 'description' => 'Target category, 0 for the root level'],
        ]);

        self::route('structure/articles/copy', 'structure/articles/{id}/copy', 'handleCopyArticle', ['POST'], 'Copy an article with its content into a category', body: [
            'category_id' => ['type' => 'integer', 'required' => true, 'description' => 'Target category, 0 for the root level'],
        ]);

        self::route('structure/categories/list', 'structure/categories', 'handleCategoryList', ['GET'], 'Access to the list of categories', query: [
            'filter' => [
                'fields' => [
                    'parent_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => '0 for the root level'],
                    'language_id' => ['type' => 'integer', 'required' => false, 'default' => null],
                    'status' => ['type' => 'integer', 'required' => false, 'default' => null],
                    'name' => ['type' => 'string', 'required' => false, 'default' => ''],
                ],
                'type' => 'array',
                'required' => false,
                'default' => [],
            ],
        ] + $list);

        self::route('structure/categories/get', 'structure/categories/{id}', 'handleGetCategory', ['GET'], 'Get a category', query: [
            'language_id' => $language,
        ]);

        self::route('structure/categories/add', 'structure/categories', 'handleAddCategory', ['POST'], 'Add a category', body: [
            'name' => ['type' => 'string', 'required' => true],
            'parent_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Parent category, 0 for the root level'],
            'category_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Alias of parent_id (REDAXO 5)'],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => 0, 'description' => '0 to append'],
            'status' => ['type' => 'integer', 'required' => false, 'default' => 0],
        ]);

        self::route('structure/categories/update', 'structure/categories/{id}', 'handleUpdateCategory', ['PUT', 'PATCH'], 'Update a category', body: [
            'language_id' => $language + ['description' => 'Language of name, status and template, default: the start language'],
            'name' => ['type' => 'string', 'required' => false, 'default' => null],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => null],
            'status' => ['type' => 'integer', 'required' => false, 'default' => null],
            'template' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'Template key of the start article (see /templates)'],
        ]);

        self::route('structure/categories/delete', 'structure/categories/{id}', 'handleDeleteCategory', ['DELETE'], 'Delete a category');

        self::route('structure/categories/move', 'structure/categories/{id}/move', 'handleMoveCategory', ['POST'], 'Move a category into another category', body: [
            'parent_id' => ['type' => 'integer', 'required' => true, 'description' => 'Target category, 0 for the root level'],
        ]);

        self::route('structure/articles/slices/list', 'structure/articles/{id}/slices', 'handleGetArticleSlices', ['GET'], 'Get the slices of an article', query: [
            'language_id' => $language,
            'content_section_id' => ['type' => 'integer', 'required' => false, 'default' => null],
            'revision' => ['type' => 'integer', 'required' => false, 'default' => 0],
        ] + $list);

        self::route('structure/articles/slices/get', 'structure/articles/{id}/slices/{slice_id}', 'handleGetArticleSlice', ['GET'], 'Get a slice of an article');

        self::route('structure/articles/slices/add', 'structure/articles/{id}/slices', 'handleAddArticleSlice', ['POST'], 'Add a slice to an article', body: [
            'module' => ['type' => 'string', 'required' => true, 'description' => 'Module key (see /modules)'],
            'language_id' => $language,
            'content_section_id' => ['type' => 'integer', 'required' => false, 'default' => 1, 'description' => 'Content section of the article template (see /templates/{key})'],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Position in the content section, default: append'],
            'revision' => ['type' => 'integer', 'required' => false, 'default' => 0],
            'status' => ['type' => 'integer', 'required' => false, 'default' => 1],
            'data' => ['type' => 'object', 'required' => false, 'default' => null, 'description' => 'Field values keyed by field key (see /modules/{key})'],
        ]);

        self::route('structure/articles/slices/update', 'structure/articles/{id}/slices/{slice_id}', 'handleUpdateArticleSlice', ['PUT', 'PATCH'], 'Update a slice of an article', body: [
            'data' => ['type' => 'object', 'required' => false, 'default' => null, 'description' => 'Field values keyed by field key (see /modules/{key}); missing fields keep their value'],
            'status' => ['type' => 'integer', 'required' => false, 'default' => null],
        ]);

        self::route('structure/articles/slices/move', 'structure/articles/{id}/slices/{slice_id}/move', 'handleMoveArticleSlice', ['POST'], 'Move a slice within its content section', body: [
            'direction' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => '"up" or "down"'],
            'priority' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Target position, instead of a direction'],
        ]);

        self::route('structure/articles/slices/delete', 'structure/articles/{id}/slices/{slice_id}', 'handleDeleteArticleSlice', ['DELETE'], 'Delete a slice of an article');
    }

    /**
     * @param list<string> $methods
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $body
     */
    private static function route(string $scope, string $path, string $handler, array $methods, string $description, ?array $query = null, ?array $body = null): void
    {
        $defaults = ['_controller' => self::class . '::' . $handler];
        if (null !== $query) {
            $defaults['query'] = $query;
        }
        if (null !== $body) {
            $defaults['Body'] = $body;
        }

        $requirements = [];
        if (str_contains($path, '{id}')) {
            $requirements['id'] = '\d+';
        }
        if (str_contains($path, '{slice_id}')) {
            $requirements['slice_id'] = '\d+';
        }

        RouteCollection::registerRoute($scope, new Route($path, $defaults, $requirements, [], '', [], $methods), $description, null, new BearerAuth());
    }

    // ------------------------------------------------------------------ articles

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleArticleList(array $parameter, array $route = []): Response
    {
        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructureAccess($user)) {
            return $response;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $filter = $query['filter'];
        $where = [];
        $params = [];

        if (null !== $filter['is_category']) {
            $where[] = $filter['is_category'] ? 'c.id IS NOT NULL' : 'c.id IS NULL';
        }
        if (null !== $filter['language_id']) {
            $where[] = 't.language_id = :language_id';
            $params['language_id'] = $filter['language_id'];
        }
        if (null !== $filter['category_id']) {
            if (0 === (int) $filter['category_id']) {
                $where[] = 'a.parent_id IS NULL';
            } else {
                $where[] = 'a.parent_id = :parent_id';
                $params['parent_id'] = $filter['category_id'];
            }
        }
        if (null !== $filter['status']) {
            $where[] = 't.status = :status';
            $params['status'] = $filter['status'];
        }
        if (null !== $filter['template'] && '' !== $filter['template']) {
            $where[] = 't.template = :template';
            $params['template'] = $filter['template'];
        }
        if (null !== $filter['name'] && '' !== $filter['name']) {
            $where[] = 't.name LIKE :name';
            $params['name'] = '%' . $filter['name'] . '%';
        }

        $from = 'FROM ' . Core::TABLE_PREFIX . 'article a
            JOIN ' . Core::TABLE_PREFIX . 'article_translation t ON t.article_id = a.id
            LEFT JOIN ' . Core::TABLE_PREFIX . 'category c ON c.id = a.id';

        return self::sqlList($from, $where, $params, $query, self::ARTICLE_COLUMNS, [['field' => 'id', 'direction' => 'asc'], ['field' => 'language_id', 'direction' => 'asc']], static function (array $row) use ($user): ?array {
            if (!self::hasCategoryPerm($user, null === $row['category_id'] ? null : (int) $row['category_id'])) {
                return null;
            }

            return ['is_category' => (bool) $row['is_category']] + self::castRow($row, ['id', 'category_id', 'priority', 'status', 'language_id']);
        });
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetArticle(array $parameter, array $route = []): Response
    {
        $languageId = self::languageId(Core::getRequest()->query->get('language_id'));
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $article = Article::get((int) $parameter['id'], $languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkLanguagePerm($user, $languageId)) {
            return $response;
        }

        return self::json(self::articleToArray($article));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddArticle(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $categoryId = (int) $data['category_id'] ?: null;
        if (null !== $categoryId && null === Category::get($categoryId)) {
            return new JsonResponse(['error' => 'Valid category_id is required'], 400);
        }

        if (null !== $response = self::checkStructurePerm(RouteCollection::getBackendUser($route), $categoryId)) {
            return $response;
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        $values = ['category_id' => $categoryId, 'name' => (string) $data['name']];
        if ((int) $data['priority'] > 0) {
            $values['priority'] = (int) $data['priority'];
        }
        if (null !== $data['template']) {
            $values['template'] = (string) $data['template'];
        }

        try {
            $article = ArticleHandler::add($values);

            // the handler creates the article offline in every language
            if (0 !== (int) $data['status']) {
                foreach (Language::getAllIds() as $languageId) {
                    ArticleHandler::changeStatus($article->id, $languageId, (int) $data['status']);
                }
            }
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Article created', 'id' => $article->id], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateArticle(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $languageId = self::languageId($data['language_id']);
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $article = Article::get((int) $parameter['id'], $languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkLanguagePerm($user, $languageId)) {
            return $response;
        }

        if ($article->isStartArticle() && (null !== $data['name'] || null !== $data['priority'])) {
            return new JsonResponse(['error' => 'Article is a start article, its name and priority belong to the category. Please use the category route.'], 403);
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        $values = array_filter([
            'name' => $data['name'],
            'priority' => null === $data['priority'] ? null : (int) $data['priority'],
            'template' => $data['template'],
        ], static fn ($value) => null !== $value);

        if ([] === $values && null === $data['status']) {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        try {
            if ([] !== $values) {
                ArticleHandler::edit($article->id, $languageId, $values);
            }
            if (null !== $data['status'] && (int) $data['status'] !== $article->status) {
                ArticleHandler::changeStatus($article->id, $languageId, (int) $data['status']);
            }
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Article updated', 'id' => $article->id], 200);
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

        if (null !== $response = self::checkStructurePerm(RouteCollection::getBackendUser($route), $article->categoryId)) {
            return $response;
        }

        if ($article->isStartArticle()) {
            return new JsonResponse(['error' => 'Article is a start article. Please use the category route.'], 403);
        }

        try {
            ArticleHandler::delete($article->id);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage()), 'id' => $article->id], 409);
        }

        return new JsonResponse(['message' => 'Article deleted', 'id' => $article->id], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMoveArticle(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $article = Article::get((int) $parameter['id']);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }
        if ($article->isStartArticle()) {
            return new JsonResponse(['error' => 'Article is a start article. Please move the category.'], 403);
        }

        $targetId = (int) $data['category_id'] ?: null;
        if (null !== $targetId && null === Category::get($targetId)) {
            return new JsonResponse(['error' => 'Target category not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkStructurePerm($user, $targetId)) {
            return $response;
        }
        if (null !== $user && !$user->admin && !$user->hasPerm('moveArticle[]')) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            ArticleHandler::move($article->id, $article->categoryId, $targetId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Article moved', 'id' => $article->id, 'category_id' => $targetId ?? 0], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCopyArticle(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $article = Article::get((int) $parameter['id']);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $targetId = (int) $data['category_id'] ?: null;
        if (null !== $targetId && null === Category::get($targetId)) {
            return new JsonResponse(['error' => 'Target category not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkStructurePerm($user, $targetId)) {
            return $response;
        }
        if (null !== $user && !$user->admin && !$user->hasPerm('copyArticle[]')) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            $copy = ArticleHandler::copy($article->id, $targetId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Article copied', 'id' => $copy->id], 201);
    }

    // ------------------------------------------------------------------ categories

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryList(array $parameter, array $route = []): Response
    {
        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructureAccess($user)) {
            return $response;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $filter = $query['filter'];
        $where = [];
        $params = [];

        if (null !== $filter['language_id']) {
            $where[] = 'ct.language_id = :language_id';
            $params['language_id'] = $filter['language_id'];
        }
        if (null !== $filter['parent_id']) {
            if (0 === (int) $filter['parent_id']) {
                $where[] = 'a.parent_id IS NULL';
            } else {
                $where[] = 'a.parent_id = :parent_id';
                $params['parent_id'] = $filter['parent_id'];
            }
        }
        if (null !== $filter['status']) {
            $where[] = 't.status = :status';
            $params['status'] = $filter['status'];
        }
        if (null !== $filter['name'] && '' !== $filter['name']) {
            $where[] = 'ct.name LIKE :name';
            $params['name'] = '%' . $filter['name'] . '%';
        }

        // a category is the start article of the same id, which holds the parent, the status and the template
        $from = 'FROM ' . Core::TABLE_PREFIX . 'category c
            JOIN ' . Core::TABLE_PREFIX . 'category_translation ct ON ct.category_id = c.id
            JOIN ' . Core::TABLE_PREFIX . 'article a ON a.id = c.id
            JOIN ' . Core::TABLE_PREFIX . 'article_translation t ON t.article_id = c.id AND t.language_id = ct.language_id';

        return self::sqlList($from, $where, $params, $query, self::CATEGORY_COLUMNS, [['field' => 'priority', 'direction' => 'asc'], ['field' => 'id', 'direction' => 'asc']], static function (array $row) use ($user): ?array {
            if (!self::hasCategoryPerm($user, (int) $row['id'])) {
                return null;
            }

            return self::castRow($row, ['id', 'parent_id', 'priority', 'status', 'language_id']);
        });
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetCategory(array $parameter, array $route = []): Response
    {
        $languageId = self::languageId(Core::getRequest()->query->get('language_id'));
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $category = Category::get((int) $parameter['id'], $languageId);
        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $category->id) ?? self::checkLanguagePerm($user, $languageId)) {
            return $response;
        }

        return self::json(self::categoryToArray($category));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddCategory(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $parentId = (int) ($data['parent_id'] ?? $data['category_id']) ?: null;
        if (null !== $parentId && null === Category::get($parentId)) {
            return new JsonResponse(['error' => 'Valid parent_id is required'], 400);
        }

        if (null !== $response = self::checkStructurePerm(RouteCollection::getBackendUser($route), $parentId)) {
            return $response;
        }

        $values = ['parent_id' => $parentId, 'name' => (string) $data['name'], 'status' => (int) $data['status']];
        if ((int) $data['priority'] > 0) {
            $values['priority'] = (int) $data['priority'];
        }

        try {
            $category = CategoryHandler::add($values);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Category created', 'id' => $category->id], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateCategory(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $languageId = self::languageId($data['language_id']);
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $category = Category::get((int) $parameter['id'], $languageId);
        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $category->id) ?? self::checkLanguagePerm($user, $languageId)) {
            return $response;
        }

        if (null !== $data['template'] && !Template::exists((string) $data['template'])) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape((string) $data['template'])], 404);
        }

        $values = array_filter([
            'name' => $data['name'],
            'priority' => null === $data['priority'] ? null : (int) $data['priority'],
        ], static fn ($value) => null !== $value);

        if ([] === $values && null === $data['status'] && null === $data['template']) {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        try {
            if ([] !== $values) {
                CategoryHandler::edit($category->id, $languageId, $values);
            }
            if (null !== $data['template']) {
                ArticleHandler::edit($category->id, $languageId, ['template' => (string) $data['template']]);
            }
            if (null !== $data['status'] && (int) $data['status'] !== $category->status) {
                CategoryHandler::changeStatus($category->id, $languageId, (int) $data['status']);
            }
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Category updated', 'id' => $category->id], 200);
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

        if (null !== $response = self::checkStructurePerm(RouteCollection::getBackendUser($route), $category->id)) {
            return $response;
        }

        try {
            CategoryHandler::delete($category->id);
        } catch (UserMessageException $e) {
            // e.g. the category still contains articles or categories
            return new JsonResponse(['error' => self::plain($e->getMessage()), 'id' => $category->id], 409);
        }

        return new JsonResponse(['message' => 'Category deleted', 'id' => $category->id], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMoveCategory(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $category = Category::get((int) $parameter['id']);
        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $targetId = (int) $data['parent_id'] ?: null;
        if (null !== $targetId && null === Category::get($targetId)) {
            return new JsonResponse(['error' => 'Target category not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $category->id) ?? self::checkStructurePerm($user, $targetId)) {
            return $response;
        }
        if (null !== $user && !$user->admin && !$user->hasPerm('moveCategory[]')) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            CategoryHandler::move($category->id, $targetId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Category moved', 'id' => $category->id, 'parent_id' => $targetId ?? 0], 200);
    }

    // ------------------------------------------------------------------ slices

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

        $languageId = self::languageId($query['language_id']);
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $article = Article::get((int) $parameter['id'], $languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkLanguagePerm($user, $languageId)) {
            return $response;
        }

        $slices = [];
        foreach (ArticleSlice::getSlicesForArticle($article->id, $languageId, (int) $query['revision']) as $slice) {
            if (null !== $query['content_section_id'] && $slice->contentSectionId !== (int) $query['content_section_id']) {
                continue;
            }
            $slices[] = SliceService::toArray($slice);
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'content_section_id', 'module', 'priority', 'status', 'createdate', 'updatedate'], [['field' => 'content_section_id', 'direction' => 'asc'], ['field' => 'priority', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        return self::json(ListHelper::paginateArray($slices, $sortDefs, max(1, (int) $query['page']), (int) $query['per_page'] ?: 100));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetArticleSlice(array $parameter, array $route = []): Response
    {
        $slice = self::loadSlice((int) $parameter['slice_id'], (int) $parameter['id']);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $article = Article::get($slice->articleId, $slice->languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkLanguagePerm($user, $slice->languageId)) {
            return $response;
        }

        return self::json(SliceService::toArray($slice));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddArticleSlice(array $parameter, array $route = []): Response
    {
        $raw = self::rawBody();
        $data = self::body($parameter, $raw);
        if ($data instanceof Response) {
            return $data;
        }

        $languageId = self::languageId($data['language_id']);
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $article = Article::get((int) $parameter['id'], $languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found', 'id' => escape((string) $parameter['id'])], 404);
        }

        $moduleKey = (string) $data['module'];
        $moduleClass = Module::getClass($moduleKey);
        if (null === $moduleClass) {
            return new JsonResponse(['error' => 'Module not found', 'module' => escape($moduleKey)], 404);
        }

        $revision = (int) $data['revision'];
        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkSlicePerms($user, $article, $moduleKey, $revision)) {
            return $response;
        }

        // the template of the article decides which content sections exist and which modules they allow
        $templateKey = (string) $article->templateKey;
        $template = Template::get($templateKey);
        if (null === $template) {
            return new JsonResponse(['error' => 'Template not found', 'template' => escape($templateKey)], 404);
        }

        $contentSectionId = (int) $data['content_section_id'];
        $section = $contentSectionId > 0 ? $template->getContentSection($contentSectionId) : null;
        if (null === $section) {
            return new JsonResponse(['error' => 'Template has no such content section', 'content_section_id' => $contentSectionId, 'template' => escape($templateKey)], 404);
        }

        if (!$template->isModuleAllowed($section, $moduleClass) || !$moduleClass::isAllowedInCategory($article->getCategory())) {
            return new JsonResponse(['error' => 'The module is not allowed in that content section or category', 'module' => escape($moduleKey), 'content_section_id' => $contentSectionId], 422);
        }

        $input = self::dataInput($raw);
        if ($input instanceof Response) {
            return $input;
        }

        $result = SliceService::apply(SliceService::transientSlice($article->id, $languageId, $contentSectionId, $moduleKey, $revision), $input);
        if ($result['errors']) {
            return new JsonResponse(['error' => 'Invalid field values', 'fields' => $result['errors']], 422);
        }

        try {
            $slice = ContentHandler::addSlice($article->id, $languageId, $contentSectionId, $moduleKey, FormSchema::of($moduleClass)->encode($result['module']), revision: $revision);

            // addSlice() orders equal priorities by update date, which is ambiguous within the same second, so the
            // slice is appended first and then moved to the requested position
            if (null !== $data['priority'] && max(1, (int) $data['priority']) < $slice->priority) {
                $slice = self::moveSliceTo($slice, max(1, (int) $data['priority']));
            }

            if (1 !== (int) $data['status']) {
                $slice = ContentHandler::changeSliceStatus($slice->id, (int) $data['status']);
            }

            Module::of($slice)->afterSave();
            self::touchArticle($article->id, $languageId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Slice created', 'slice_id' => $slice->id], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateArticleSlice(array $parameter, array $route = []): Response
    {
        $raw = self::rawBody();
        $data = self::body($parameter, $raw);
        if ($data instanceof Response) {
            return $data;
        }

        $slice = self::loadSlice((int) $parameter['slice_id'], (int) $parameter['id']);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $article = Article::get($slice->articleId, $slice->languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        if (null === Module::getClass($slice->moduleKey)) {
            return new JsonResponse(['error' => 'Module of the slice not found', 'module' => escape($slice->moduleKey)], 409);
        }

        if (null !== $response = self::checkSlicePerms(RouteCollection::getBackendUser($route), $article, $slice->moduleKey, $slice->revision)) {
            return $response;
        }

        $hasData = is_array($raw) && array_key_exists('data', $raw);
        if (!$hasData && null === $data['status']) {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        try {
            if ($hasData) {
                $input = self::dataInput($raw);
                if ($input instanceof Response) {
                    return $input;
                }

                $result = SliceService::apply($slice, $input);
                if ($result['errors']) {
                    return new JsonResponse(['error' => 'Invalid field values', 'fields' => $result['errors']], 422);
                }

                Extension::dispatch(new SliceUpdating($slice));

                $sql = Sql::factory();
                $sql->setTable(Core::TABLE_PREFIX . 'article_slice');
                $sql->setWhere(['id' => $slice->id]);
                $sql->setValue('data', $result['module']->encodeData());
                $sql->addGlobalUpdateFields(self::apiUser());
                $sql->update();

                ArticleCache::deleteContent($article->id, $slice->languageId);

                $slice = ArticleSlice::getArticleSliceById($slice->id, $slice->languageId, $slice->revision) ?? $slice;
                ArticleSliceReference::update($slice);
                Extension::dispatch(new ArticleContentUpdated($article, 'slice_updated', $slice));
                Module::of($slice)->afterSave();
            }

            if (null !== $data['status'] && (int) $data['status'] !== $slice->status) {
                ContentHandler::changeSliceStatus($slice->id, (int) $data['status']);
            }

            self::touchArticle($article->id, $slice->languageId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Slice updated', 'slice_id' => $slice->id], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMoveArticleSlice(array $parameter, array $route = []): Response
    {
        $data = self::body($parameter);
        if ($data instanceof Response) {
            return $data;
        }

        $slice = self::loadSlice((int) $parameter['slice_id'], (int) $parameter['id']);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $article = Article::get($slice->articleId, $slice->languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $response = self::checkSlicePerms($user, $article, $slice->moduleKey, $slice->revision)) {
            return $response;
        }
        if (null !== $user && !$user->admin && !$user->hasPerm('moveSlice[]')) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            if (null !== $data['priority']) {
                $slice = self::moveSliceTo($slice, max(1, (int) $data['priority']));
            } elseif (in_array($data['direction'], ['up', 'down'], true)) {
                $slice = ContentHandler::moveSlice($slice->id, $slice->languageId, 'up' === $data['direction'] ? SliceMoveDirection::Up : SliceMoveDirection::Down);
            } else {
                return new JsonResponse(['error' => 'Either `priority` or `direction` ("up"/"down") is required'], 400);
            }

            self::touchArticle($article->id, $slice->languageId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 409);
        }

        return new JsonResponse(['message' => 'Slice moved', 'slice_id' => $slice->id, 'priority' => $slice->priority], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteArticleSlice(array $parameter, array $route = []): Response
    {
        $slice = self::loadSlice((int) $parameter['slice_id'], (int) $parameter['id']);
        if (null === $slice) {
            return new JsonResponse(['error' => 'Slice not found'], 404);
        }

        $article = Article::get($slice->articleId, $slice->languageId);
        if (null === $article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        if (null !== $response = self::checkSlicePerms(RouteCollection::getBackendUser($route), $article, $slice->moduleKey, $slice->revision)) {
            return $response;
        }

        try {
            ContentHandler::deleteSlice($slice->id);
            self::touchArticle($article->id, $slice->languageId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Slice deleted', 'slice_id' => $slice->id], 200);
    }

    // ------------------------------------------------------------------ helpers

    /** Loads a slice by id, only if it belongs to the given article. */
    private static function loadSlice(int $sliceId, int $articleId): ?ArticleSlice
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT * FROM ' . Core::TABLE_PREFIX . 'article_slice WHERE id = ? AND article_id = ?', [$sliceId, $articleId]);

        return 1 === $sql->getRows() ? ArticleSlice::fromSql($sql) : null;
    }

    /**
     * Moves a slice to the given position within its content section, like the backend does for a new slice at a
     * position: the others are renumbered behind it.
     */
    private static function moveSliceTo(ArticleSlice $slice, int $priority): ArticleSlice
    {
        $where = 'article_id=' . $slice->articleId . ' AND language_id=' . $slice->languageId . ' AND content_section_id=' . $slice->contentSectionId . ' AND revision=' . $slice->revision;

        Sql::factory()
            ->setTable(Core::TABLE_PREFIX . 'article_slice')
            ->setWhere(['id' => $slice->id])
            ->setValue('priority', $priority)
            ->addGlobalUpdateFields(self::apiUser())
            ->update();

        // on equal priorities the moved slice goes first when moving up, last when moving down; decided by id, as the
        // update dates of slices changed within the same second are equal
        DatabaseUtil::organizePriorities(Core::TABLE_PREFIX . 'article_slice', 'priority', $where, 'priority, id ' . ($priority < $slice->priority ? '!= ' : '= ') . $slice->id);

        ArticleCache::deleteContent($slice->articleId, $slice->languageId);

        $moved = ArticleSlice::getArticleSliceById($slice->id, $slice->languageId, $slice->revision) ?? $slice;
        Extension::dispatch(new ArticleContentUpdated(Article::require($slice->articleId, $slice->languageId), 'slice_moved', $moved));

        return $moved;
    }

    /** Stamps the article as updated and drops its cache, like the backend content page after every slice change. */
    private static function touchArticle(int $articleId, int $languageId): void
    {
        Sql::factory()
            ->setTable(Core::TABLE_PREFIX . 'article')
            ->setWhere(['id' => $articleId])
            ->addGlobalUpdateFields(self::apiUser())
            ->update();

        ArticleCache::delete($articleId, $languageId);
    }

    /** The `updateuser` of API changes: the backend user if any, otherwise "API". */
    private static function apiUser(): string
    {
        return Core::getUser()->login ?? 'API';
    }

    /** @return array<array-key, mixed>|null */
    private static function rawBody(): ?array
    {
        $data = json_decode(Core::getRequest()->getContent(), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Reads and validates the JSON body against the `Body` definition of the route.
     *
     * @param array<string, mixed> $parameter
     * @param array<array-key, mixed>|null $raw the decoded body, if already read
     * @return array<string, mixed>|Response
     */
    private static function body(array $parameter, ?array $raw = null): array|Response
    {
        $raw ??= self::rawBody();
        if (null === $raw) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        // unknown fields are rejected instead of ignored, so a client sending the REDAXO 5 names (`clang_id`, `ctype_id`,
        // `value1`) learns about it instead of silently getting defaults
        $unknown = array_diff(array_map('strval', array_keys($raw)), array_keys($parameter['Body']));
        if ([] !== $unknown) {
            return new JsonResponse(['error' => 'Unknown body fields', 'fields' => array_values($unknown), 'allowed' => array_keys($parameter['Body'])], 400);
        }

        // `data` is passed through as is, its fields are validated by the module
        $definition = $parameter['Body'];
        unset($definition['data'], $raw['data']);

        try {
            return RouteCollection::getQuerySet($raw, $definition);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }
    }

    /**
     * @param array<array-key, mixed>|null $raw
     * @return array<array-key, mixed>|Response
     */
    private static function dataInput(?array $raw): array|Response
    {
        $input = $raw['data'] ?? [];

        return is_array($input) ? $input : new JsonResponse(['error' => '`data` must be an object keyed by field key'], 400);
    }

    /** The requested language, default the start language. */
    private static function languageId(mixed $value): int|Response
    {
        if (null === $value || '' === $value) {
            return Language::getStartId();
        }

        $languageId = (int) $value;

        return Language::exists($languageId) ? $languageId : new JsonResponse(['error' => 'Language not found', 'language_id' => $languageId], 404);
    }

    /**
     * Runs a paginated, sorted list query.
     *
     * @param list<string> $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     * @param array<string, string> $columns API field => SQL expression
     * @param array<array{field: string, direction: string}> $defaultSort
     * @param callable(array<string, mixed>): ?array<string, mixed> $map returns `null` to skip the row
     */
    private static function sqlList(string $from, array $where, array $params, array $query, array $columns, array $defaultSort, callable $map): Response
    {
        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, array_keys($columns), $defaultSort);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $select = [];
        foreach ($columns as $field => $expression) {
            $select[] = $expression . ' AS `' . $field . '`';
        }

        // rows without permission are filtered after the query, so the list is built in PHP when a user is restricted
        $rows = Sql::factory()->getArray('SELECT ' . implode(', ', $select) . ' ' . $from . ' ' . $whereClause . ' ORDER BY ' . ListHelper::buildSqlOrderBy($sortDefs), $params);

        $items = [];
        foreach ($rows as $row) {
            if (null !== $item = $map($row)) {
                $items[] = $item;
            }
        }

        $pagination = ListHelper::paginate(max(1, (int) $query['page']), (int) $query['per_page'] ?: 100, count($items));

        return self::json(ListHelper::wrapResponse(array_slice($items, $pagination['offset'], $pagination['limit']), $pagination['meta']));
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $intFields
     * @return array<string, mixed>
     */
    private static function castRow(array $row, array $intFields): array
    {
        foreach ($intFields as $field) {
            if (null !== $row[$field]) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private static function articleToArray(Article $article): array
    {
        return [
            'id' => $article->id,
            'category_id' => $article->startArticle ? $article->getCategory()?->parentId : $article->categoryId,
            'name' => $article->name,
            'priority' => $article->priority,
            'status' => $article->status,
            'template' => $article->templateKey,
            'language_id' => $article->languageId,
            'is_category' => $article->startArticle,
            'path' => $article->path,
            'createdate' => date('Y-m-d H:i:s', $article->createDate),
            'createuser' => $article->createUser,
            'updatedate' => date('Y-m-d H:i:s', $article->updateDate),
            'updateuser' => $article->updateUser,
        ];
    }

    /** @return array<string, mixed> */
    private static function categoryToArray(Category $category): array
    {
        $startArticle = $category->getStartArticle();

        return [
            'id' => $category->id,
            'parent_id' => $category->parentId,
            'name' => $category->name,
            'priority' => $category->priority,
            'status' => $category->status,
            'template' => $startArticle->templateKey,
            'language_id' => $category->languageId,
            'path' => $category->path,
            'createdate' => date('Y-m-d H:i:s', $category->createDate),
            'createuser' => $category->createUser,
            'updatedate' => date('Y-m-d H:i:s', $category->updateDate),
            'updateuser' => $category->updateUser,
        ];
    }

    private static function json(mixed $data, int $status = 200): Response
    {
        return new JsonResponse(json_encode($data, JSON_PRETTY_PRINT), $status, [], true);
    }

    private static function checkStructureAccess(?User $user): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('structure');

        return $perm->hasStructurePerm() ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    private static function hasCategoryPerm(?User $user, ?int $categoryId): bool
    {
        if (null === $user || $user->admin) {
            return true;
        }

        $perm = $user->getComplexPerm('structure');

        return $perm->hasCategoryPerm($categoryId);
    }

    /** @param int|null $categoryId `null` for the root level, which only users with all categories may change */
    private static function checkStructurePerm(?User $user, ?int $categoryId): ?Response
    {
        return self::hasCategoryPerm($user, $categoryId) ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    private static function checkLanguagePerm(?User $user, int $languageId): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('language');

        return $perm->hasPerm($languageId) ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    /** The checks of the backend content page: category, language, module and revision. */
    private static function checkSlicePerms(?User $user, Article $article, string $moduleKey, int $revision): ?Response
    {
        if (null !== $response = self::checkStructurePerm($user, $article->categoryId) ?? self::checkLanguagePerm($user, $article->languageId)) {
            return $response;
        }

        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('modules');
        if (!$perm->hasPerm($moduleKey) || !ContentHandler::hasRevisionPerm($user, $revision)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }
}
