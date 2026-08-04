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
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\StructurePermission;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\MediaPool\MediaPoolCache;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use Redaxo\Core\MetaInfo\Field\ChoiceField;
use Redaxo\Core\MetaInfo\Field\MetaField;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaSchema;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;
use function is_scalar;

use const JSON_PRETTY_PRINT;

/**
 * Metainfo field definitions (read-only) and metainfo values (read/write).
 *
 * In REDAXO 6 the field definitions live in code: a project (or addon) declares one {@see MetaSchema} per
 * {@see MetaEntity}, annotated with `#[AsMetaSchema]`, and `console migrate` syncs the backing columns.
 * There is no `rex_metainfo_field` table and no management UI anymore, so the REDAXO 5 addon's
 * `metainfo/fields/add`, `metainfo/fields/update`, `metainfo/fields/delete` and `metainfo/types/list`
 * scopes do not exist here — adding a field means shipping code. The field *type* is the field class
 * itself, which is why the definitions expose a `type` instead of a numeric `type_id`.
 *
 * The value endpoints work exactly like in REDAXO 5.
 */
class Metainfo extends RoutePackage
{
    /** Allowed values of the `prefix` filter — still the public identifier, so REDAXO 5 clients keep working. */
    private const array PREFIXES = ['art_', 'cat_', 'med_', 'clang_'];

    #[Override]
    public function loadRoutes(): void
    {
        // ---------- Field definitions (read-only) ----------

        RouteCollection::registerRoute(
            'metainfo/fields/list',
            new Route(
                'metainfo/fields',
                [
                    '_controller' => self::class . '::handleFieldList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'prefix' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'art_, cat_, med_ or clang_'],
                                'name' => ['type' => 'string', 'required' => false, 'default' => null],
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
            'List metainfo field definitions (from the code-defined meta schemas)',
            null,
            new BearerAuth(),
        );

        RouteCollection::registerRoute(
            'metainfo/fields/get',
            new Route(
                'metainfo/fields/{name}',
                [
                    '_controller' => self::class . '::handleFieldGet',
                ],
                ['name' => '(?:art_|cat_|med_|clang_)[a-zA-Z0-9_]+'],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get a metainfo field definition by its column name (e.g. art_subtitle)',
            null,
            new BearerAuth(),
        );

        // ---------- Values ----------

        RouteCollection::registerRoute(
            'metainfo/articles/values/get',
            new Route(
                'structure/articles/{id}/metainfo',
                [
                    '_controller' => self::class . '::handleArticleValuesGet',
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
                    '_controller' => self::class . '::handleArticleValuesUpdate',
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

        RouteCollection::registerRoute(
            'metainfo/categories/values/get',
            new Route(
                'structure/categories/{id}/metainfo',
                [
                    '_controller' => self::class . '::handleCategoryValuesGet',
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
                    '_controller' => self::class . '::handleCategoryValuesUpdate',
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

        RouteCollection::registerRoute(
            'metainfo/media/values/get',
            new Route(
                'media/{filename}/metainfo',
                [
                    '_controller' => self::class . '::handleMediaValuesGet',
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
                    '_controller' => self::class . '::handleMediaValuesUpdate',
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

        RouteCollection::registerRoute(
            'metainfo/clangs/values/get',
            new Route(
                'system/clangs/{id}/metainfo',
                [
                    '_controller' => self::class . '::handleClangValuesGet',
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
                    '_controller' => self::class . '::handleClangValuesUpdate',
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
    // Field definitions
    // =====================================================================

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleFieldList(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $prefix = $query['filter']['prefix'] ?? null;
        if (null !== $prefix && '' !== $prefix) {
            $entity = self::entityForPrefix((string) $prefix);

            if (null === $entity) {
                return new JsonResponse(['error' => 'Invalid prefix. Allowed: ' . implode(', ', self::PREFIXES)], 400);
            }

            $entities = [$entity];
        } else {
            $entities = MetaEntity::cases();
        }

        $nameFilter = $query['filter']['name'] ?? null;
        $fields = [];

        foreach ($entities as $entity) {
            foreach (MetaSchema::getFields($entity) as $priority => $field) {
                $definition = self::fieldToArray($field, $entity, $priority + 1);

                if (null !== $nameFilter && '' !== $nameFilter && false === stripos($definition['name'], (string) $nameFilter)) {
                    continue;
                }

                $fields[] = $definition;
            }
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['name', 'label', 'priority', 'type', 'prefix'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];

        $result = ListHelper::paginateArray($fields, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleFieldGet(array $parameter, array $route = []): Response
    {
        $permResponse = self::checkAdminPerm(RouteCollection::getBackendUser($route));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $name = (string) $parameter['name'];

        foreach (MetaEntity::cases() as $entity) {
            foreach (MetaSchema::getFields($entity) as $priority => $field) {
                if ($field->columnName($entity) === $name) {
                    return new JsonResponse(json_encode(self::fieldToArray($field, $entity, $priority + 1), JSON_PRETTY_PRINT), 200, [], true);
                }
            }
        }

        return new JsonResponse(['error' => 'Metainfo field not found'], 404);
    }

    // =====================================================================
    // Values
    // =====================================================================

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleArticleValuesGet(array $parameter, array $route = []): Response
    {
        return self::readArticleOrCategoryValues($parameter, true, $route);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleArticleValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::writeArticleOrCategoryValues($parameter, true, $route);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryValuesGet(array $parameter, array $route = []): Response
    {
        return self::readArticleOrCategoryValues($parameter, false, $route);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::writeArticleOrCategoryValues($parameter, false, $route);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMediaValuesGet(array $parameter, array $route = []): Response
    {
        $filename = (string) $parameter['filename'];

        if (null === self::getMediaCategoryId($filename)) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $permResponse = self::checkMediaValuePerm($route, $filename);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $fields = self::valueFields(MetaEntity::Media);
        $values = self::readValues(Core::getTable('media'), 'filename = :filename', [':filename' => $filename], $fields, MetaEntity::Media);

        return new JsonResponse(json_encode(['filename' => $filename, 'data' => $values], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMediaValuesUpdate(array $parameter, array $route = []): Response
    {
        $filename = (string) $parameter['filename'];

        if (null === self::getMediaCategoryId($filename)) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $permResponse = self::checkMediaValuePerm($route, $filename);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $body = json_decode(Core::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $fields = self::valueFields(MetaEntity::Media);
        $error = self::validatePatchKeys($body, $fields, MetaEntity::Media);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(Core::getTable('media'), ['filename' => $filename], $body, $fields, MetaEntity::Media);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        MediaPoolCache::delete($filename);

        $values = self::readValues(Core::getTable('media'), 'filename = :filename', [':filename' => $filename], $fields, MetaEntity::Media);

        return new JsonResponse(json_encode(['message' => 'Metainfo values updated', 'filename' => $filename, 'data' => $values], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleClangValuesGet(array $parameter, array $route = []): Response
    {
        $clangId = (int) $parameter['id'];

        if (null === Language::get($clangId)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        $permResponse = self::checkClangValuePerm($route);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $fields = self::valueFields(MetaEntity::Clang);
        $values = self::readValues(Core::getTable('clang'), 'id = :id', [':id' => $clangId], $fields, MetaEntity::Clang);

        return new JsonResponse(json_encode(['data' => $values], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleClangValuesUpdate(array $parameter, array $route = []): Response
    {
        $clangId = (int) $parameter['id'];

        if (null === Language::get($clangId)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        $permResponse = self::checkClangValuePerm($route);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $body = json_decode(Core::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $fields = self::valueFields(MetaEntity::Clang);
        $error = self::validatePatchKeys($body, $fields, MetaEntity::Clang);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(Core::getTable('clang'), ['id' => $clangId], $body, $fields, MetaEntity::Clang);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        LanguageHandler::generateCache();
        Language::reset();

        $values = self::readValues(Core::getTable('clang'), 'id = :id', [':id' => $clangId], $fields, MetaEntity::Clang);

        return new JsonResponse(json_encode(['message' => 'Metainfo values updated', 'data' => $values], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     */
    private static function readArticleOrCategoryValues(array $parameter, bool $isArticle, array $route): Response
    {
        $id = (int) $parameter['id'];
        $clangId = self::requestedClangId();

        $resolved = self::resolveArticleOrCategory($id, $clangId, $isArticle);
        if (null !== $resolved) {
            return $resolved;
        }

        $permResponse = self::checkStructureValuePerm($route, $id, $clangId, $isArticle);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $entity = $isArticle ? MetaEntity::Article : MetaEntity::Category;
        $fields = self::valueFields($entity);
        $values = self::readValues(Core::getTable('article'), 'id = :id AND clang_id = :clang', [':id' => $id, ':clang' => $clangId], $fields, $entity);

        return new JsonResponse(json_encode([
            'clang_id' => $clangId,
            'data' => $values,
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     */
    private static function writeArticleOrCategoryValues(array $parameter, bool $isArticle, array $route): Response
    {
        $id = (int) $parameter['id'];
        $clangId = self::requestedClangId();

        $resolved = self::resolveArticleOrCategory($id, $clangId, $isArticle);
        if (null !== $resolved) {
            return $resolved;
        }

        $permResponse = self::checkStructureValuePerm($route, $id, $clangId, $isArticle);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $body = json_decode(Core::getRequest()->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        $entity = $isArticle ? MetaEntity::Article : MetaEntity::Category;
        $fields = self::valueFields($entity);
        $error = self::validatePatchKeys($body, $fields, $entity);
        if (null !== $error) {
            return $error;
        }

        try {
            self::applyValuePatch(Core::getTable('article'), ['id' => $id, 'clang_id' => $clangId], $body, $fields, $entity);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        if ($isArticle) {
            ArticleCache::deleteMeta($id, $clangId);
            Extension::dispatch(new ExtensionPoint('ART_META_UPDATED', '', [
                'id' => $id,
                'clang' => $clangId,
            ]));
        } else {
            ArticleCache::generateMeta($id, $clangId);
        }

        $values = self::readValues(Core::getTable('article'), 'id = :id AND clang_id = :clang', [':id' => $id, ':clang' => $clangId], $fields, $entity);

        return new JsonResponse(json_encode([
            'message' => 'Metainfo values updated',
            'clang_id' => $clangId,
            'data' => $values,
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    private static function requestedClangId(): int
    {
        return isset($_REQUEST['clang_id']) && '' !== $_REQUEST['clang_id']
            ? (int) $_REQUEST['clang_id']
            : Language::getStartId();
    }

    private static function resolveArticleOrCategory(int $id, int $clangId, bool $isArticle): ?Response
    {
        if (null === Language::get($clangId)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        if ($isArticle) {
            $article = Article::get($id, $clangId);

            if (null === $article || $article->isStartArticle()) {
                return new JsonResponse(['error' => 'Article not found'], 404);
            }

            return null;
        }

        return null === Category::get($id, $clangId)
            ? new JsonResponse(['error' => 'Category not found'], 404)
            : null;
    }

    // =====================================================================
    // Permissions
    // =====================================================================

    private static function checkAdminPerm(?User $user): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        return new JsonResponse(['error' => 'Permission denied'], 403);
    }

    /**
     * Structure permission check for article/category metainfo. Under bearer auth (no user) the token
     * scope governs access; admins always pass; other backend users need the structure permission for the
     * surrounding category.
     *
     * @param array<string, mixed> $route
     */
    private static function checkStructureValuePerm(array $route, int $id, int $clangId, bool $isArticle): ?Response
    {
        $user = RouteCollection::getBackendUser($route);

        if (null === $user || $user->admin) {
            return null;
        }

        $categoryId = $isArticle ? Article::get($id, $clangId)?->categoryId : $id;
        $perm = $user->getComplexPerm('structure');

        if (!$perm instanceof StructurePermission || !$perm->hasCategoryPerm($categoryId)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }

    /**
     * Admin check for language metainfo. REDAXO's own languages page is admin-only, so backend reads and
     * writes are too. Under bearer auth the token scope governs access.
     *
     * @param array<string, mixed> $route
     */
    private static function checkClangValuePerm(array $route): ?Response
    {
        return self::checkAdminPerm(RouteCollection::getBackendUser($route));
    }

    /**
     * Media pool permission check for media metainfo. Under bearer auth the token scope governs access.
     *
     * @param array<string, mixed> $route
     */
    private static function checkMediaValuePerm(array $route, string $filename): ?Response
    {
        $user = RouteCollection::getBackendUser($route);

        if (null === $user || $user->admin) {
            return null;
        }

        $categoryId = self::getMediaCategoryId($filename);

        if (null === $categoryId) {
            return null; // already handled by the caller
        }

        $perm = $user->getComplexPerm('media');

        if (!$perm instanceof MediaPoolPermission || !$perm->hasCategoryPerm($categoryId)) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        return null;
    }

    private static function getMediaCategoryId(string $filename): ?int
    {
        $rows = Sql::factory()->getArray(
            'SELECT category_id FROM ' . Core::getTable('media') . ' WHERE filename = :filename LIMIT 1',
            [':filename' => $filename],
        );

        return isset($rows[0]['category_id']) ? (int) $rows[0]['category_id'] : null;
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private static function entityForPrefix(string $prefix): ?MetaEntity
    {
        return match ($prefix) {
            'art_' => MetaEntity::Article,
            'cat_' => MetaEntity::Category,
            'med_' => MetaEntity::Media,
            'clang_' => MetaEntity::Clang,
            default => null,
        };
    }

    /**
     * The fields of an entity that actually carry a value, keyed by column name.
     *
     * Structural fields (a `Fieldset` for example) return `null` from `column()` and are skipped — they
     * are the REDAXO 6 equivalent of REDAXO 5's LEGEND type.
     *
     * @return array<string, MetaField>
     */
    private static function valueFields(MetaEntity $entity): array
    {
        $fields = [];

        foreach (MetaSchema::getFields($entity) as $field) {
            if (null === $field->column($entity)) {
                continue;
            }

            $fields[$field->columnName($entity)] = $field;
        }

        return $fields;
    }

    /**
     * @param array<string, MetaField> $fields
     * @param array<string, int|string> $params
     * @return array<string, mixed>
     */
    private static function readValues(string $table, string $where, array $params, array $fields, MetaEntity $entity): array
    {
        if (0 === count($fields)) {
            return [];
        }

        $columns = array_map(static fn (string $name): string => '`' . str_replace('`', '', $name) . '`', array_keys($fields));

        $rows = Sql::factory()->getArray(
            'SELECT ' . implode(', ', $columns) . ' FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1',
            $params,
        );

        if (0 === count($rows)) {
            return [];
        }

        $values = [];
        foreach ($fields as $column => $field) {
            // Each field knows how to turn its stored representation into an application value.
            $values[$column] = $field->format($rows[0][$column] ?? null);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, MetaField> $fields
     */
    private static function validatePatchKeys(array $body, array $fields, MetaEntity $entity): ?Response
    {
        $unknown = array_values(array_diff(array_keys($body), array_keys($fields)));

        if (count($unknown) > 0) {
            return new JsonResponse([
                'error' => 'Unknown metainfo field(s): ' . implode(', ', $unknown),
                'allowed' => array_keys($fields),
            ], 422);
        }

        return null;
    }

    /**
     * @param array<string, int|string> $where Identifier conditions
     * @param array<string, mixed> $body
     * @param array<string, MetaField> $fields
     */
    private static function applyValuePatch(string $table, array $where, array $body, array $fields, MetaEntity $entity): void
    {
        $sql = Sql::factory();
        $sql->setTable($table);
        $sql->setWhere($where);

        $context = new MetaContext($entity);
        $hasAny = false;

        foreach ($body as $column => $value) {
            $field = $fields[$column] ?? null;

            if (null === $field) {
                continue;
            }

            $sql->setValue($column, self::toStorageValue($field, $context, $value));
            $hasAny = true;
        }

        if (!$hasAny) {
            return;
        }

        if (self::tableHasColumn($table, 'updatedate')) {
            $sql->setDateTimeValue('updatedate', time());
        }
        if (self::tableHasColumn($table, 'updateuser')) {
            $sql->setValue('updateuser', Core::getUser()?->login ?? 'API');
        }

        $sql->update();
    }

    /**
     * Converts a JSON value into the field's storage representation.
     *
     * Rather than reimplementing the storage rules of every field type, the value is handed to the field's
     * own `parseRequest()` through `$_POST` — the exact path the backend editor takes. That way custom
     * field classes defined by a project or addon store their values identically, without this addon
     * knowing anything about them.
     */
    private static function toStorageValue(MetaField $field, MetaContext $context, mixed $value): int|string|null
    {
        $column = $field->columnName($context->entity);
        $previous = $_POST[$column] ?? null;

        if (is_array($value)) {
            // A multiple ChoiceField reads an array from the request; every other field reads a string,
            // and the list-capable ones (media/article pickers) expect it comma-separated.
            $_POST[$column] = $field instanceof ChoiceField && $field->multiple
                ? array_values(array_filter(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value), static fn (string $v): bool => '' !== $v))
                : implode(',', array_filter(array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value), static fn (string $v): bool => '' !== $v));
        } elseif (null === $value) {
            $_POST[$column] = '';
        } else {
            $_POST[$column] = is_scalar($value) ? (string) $value : '';
        }

        try {
            return $field->parseRequest($context);
        } finally {
            if (null === $previous) {
                unset($_POST[$column]);
            } else {
                $_POST[$column] = $previous;
            }
        }
    }

    private static function tableHasColumn(string $table, string $column): bool
    {
        return Table::get($table)->hasColumn($column);
    }

    /** The class name without its namespace, e.g. `TextField` — the readable stand-in for a type label. */
    private static function shortClassName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }

    /**
     * @return array{name: string, label: string, prefix: string, entity: string, priority: int, type: string, type_class: string, required: bool, note: string|null, default: string|null, column_type: string|null, meta_table: string, multiple: bool|null}
     */
    private static function fieldToArray(MetaField $field, MetaEntity $entity, int $priority): array
    {
        $column = $field->column($entity);

        return [
            'name' => $field->columnName($entity),
            'label' => $field->label,
            'prefix' => $entity->prefix(),
            'entity' => $entity->name,
            'priority' => $priority,
            // The field class *is* the type in REDAXO 6; the short name is the readable equivalent of
            // REDAXO 5's type label, the FQCN identifies it unambiguously.
            'type' => self::shortClassName($field::class),
            'type_class' => $field::class,
            'required' => $field->required,
            'note' => $field->note,
            'default' => $field->default,
            // `null` for structural fields (e.g. a Fieldset) that store no value at all.
            'column_type' => $column?->getType(),
            'meta_table' => $entity->table(),
            'multiple' => $field instanceof ChoiceField ? $field->multiple : null,
        ];
    }
}
