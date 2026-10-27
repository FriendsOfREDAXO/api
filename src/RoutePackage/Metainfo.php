<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Service\FormValues;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Content\CategoryHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaField;
use Redaxo\Core\MetaInfo\MetaSchema;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_key_exists;
use function count;
use function is_array;
use function strlen;

use const JSON_PRETTY_PRINT;

/**
 * Metainfo field definitions (read-only) and metainfo values (read/write).
 *
 * In REDAXO 6 the field definitions live in code: a project (or addon) declares {@see MetaSchema} classes per
 * {@see MetaEntity}, annotated with `#[AsMetaSchema]`, and `console migrate` syncs the backing columns (`meta_<key>`).
 * There is no field table and no management UI anymore, so the REDAXO 5 scopes for adding, updating and deleting
 * fields do not exist here — adding a field means shipping code.
 *
 * Values are exchanged keyed by the field key (the column without the `meta_` prefix) in their stored representation,
 * like the `data` of slices. Writes go through the core handlers, so the extension points fire like in the backend,
 * and are validated by the field types; fields a schema does not allow for the item (`isFieldAllowed()`) are neither
 * shown nor writable.
 */
class Metainfo extends RoutePackage
{
    /** Entity names of the API, plus the REDAXO 5 prefixes for compatibility. */
    private const array ENTITIES = [
        'article' => MetaEntity::Article,
        'category' => MetaEntity::Category,
        'media' => MetaEntity::Media,
        'language' => MetaEntity::Language,
        'art_' => MetaEntity::Article,
        'cat_' => MetaEntity::Category,
        'med_' => MetaEntity::Media,
        'clang_' => MetaEntity::Language,
    ];

    #[Override]
    public function loadRoutes(): void
    {
        $language = ['language_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Language of the translated fields, default: the start language']];

        self::route('metainfo/fields/list', 'metainfo/fields', 'handleFieldList', ['GET'], 'List metainfo field definitions (from the code-defined meta schemas)', [], [
            'filter' => [
                'fields' => [
                    'entity' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'article, category, media or language'],
                    'prefix' => ['type' => 'string', 'required' => false, 'default' => null, 'description' => 'REDAXO 5 alias of entity: art_, cat_, med_ or clang_'],
                    'name' => ['type' => 'string', 'required' => false, 'default' => null],
                ],
                'type' => 'array',
                'required' => false,
                'default' => [],
            ],
            'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
            'per_page' => ['type' => 'integer', 'required' => false, 'default' => 100],
            'sort' => ['type' => 'string', 'required' => false, 'default' => null],
        ]);
        self::route('metainfo/fields/get', 'metainfo/fields/{name}', 'handleFieldGet', ['GET'], 'Get a metainfo field definition by its column name (e.g. meta_subtitle)', ['name' => 'meta_[a-zA-Z0-9_]+']);

        $id = ['id' => '\d+'];
        $filename = ['filename' => '[a-zA-Z0-9\-\_\.\@]+'];

        self::route('metainfo/articles/values/get', 'structure/articles/{id}/metainfo', 'handleArticleValuesGet', ['GET'], 'Get the metainfo values of an article', $id, $language);
        self::route('metainfo/articles/values/update', 'structure/articles/{id}/metainfo', 'handleArticleValuesUpdate', ['PUT', 'PATCH'], 'Update metainfo values of an article. Body: object keyed by field key.', $id, $language);
        self::route('metainfo/categories/values/get', 'structure/categories/{id}/metainfo', 'handleCategoryValuesGet', ['GET'], 'Get the metainfo values of a category', $id, $language);
        self::route('metainfo/categories/values/update', 'structure/categories/{id}/metainfo', 'handleCategoryValuesUpdate', ['PUT', 'PATCH'], 'Update metainfo values of a category. Body: object keyed by field key.', $id, $language);
        self::route('metainfo/media/values/get', 'media/{filename}/metainfo', 'handleMediaValuesGet', ['GET'], 'Get the metainfo values of a media item', $filename, $language);
        self::route('metainfo/media/values/update', 'media/{filename}/metainfo', 'handleMediaValuesUpdate', ['PUT', 'PATCH'], 'Update metainfo values of a media item. Body: object keyed by field key.', $filename, $language);
        self::route('metainfo/clangs/values/get', 'system/clangs/{id}/metainfo', 'handleClangValuesGet', ['GET'], 'Get the metainfo values of a language', $id);
        self::route('metainfo/clangs/values/update', 'system/clangs/{id}/metainfo', 'handleClangValuesUpdate', ['PUT', 'PATCH'], 'Update metainfo values of a language. Body: object keyed by field key.', $id);
    }

    /**
     * @param list<string> $methods
     * @param array<string, string> $requirements
     * @param array<string, mixed>|null $query
     */
    private static function route(string $scope, string $path, string $handler, array $methods, string $description, array $requirements = [], ?array $query = null): void
    {
        $defaults = ['_controller' => self::class . '::' . $handler];
        if (null !== $query) {
            $defaults['query'] = $query;
        }

        RouteCollection::registerRoute($scope, new Route($path, $defaults, $requirements, [], '', [], $methods), $description, null, new BearerAuth());
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
        if (null !== $response = self::checkAdminPerm(RouteCollection::getBackendUser($route))) {
            return $response;
        }

        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $entities = MetaEntity::cases();
        $entityFilter = $query['filter']['entity'] ?? $query['filter']['prefix'];
        if (null !== $entityFilter && '' !== $entityFilter) {
            $entity = self::ENTITIES[(string) $entityFilter] ?? null;
            if (null === $entity) {
                return new JsonResponse(['error' => 'Invalid entity, expected article, category, media or language'], 400);
            }
            $entities = [$entity];
        }

        $fields = [];
        foreach ($entities as $entity) {
            foreach (MetaSchema::getFields($entity) as $priority => $field) {
                if (null !== $query['filter']['name'] && '' !== $query['filter']['name'] && false === stripos($field->column, (string) $query['filter']['name'])) {
                    continue;
                }
                $fields[] = self::fieldToArray($field, $priority + 1);
            }
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['name', 'key', 'entity', 'priority', 'type'], [['field' => 'entity', 'direction' => 'asc'], ['field' => 'priority', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        return self::json(ListHelper::paginateArray($fields, $sortDefs, max(1, (int) $query['page']), (int) $query['per_page'] ?: 100));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleFieldGet(array $parameter, array $route = []): Response
    {
        if (null !== $response = self::checkAdminPerm(RouteCollection::getBackendUser($route))) {
            return $response;
        }

        // the same column may exist for several entities, each is its own field
        $fields = [];
        foreach (MetaEntity::cases() as $entity) {
            foreach (MetaSchema::getFields($entity) as $priority => $field) {
                if ($field->column === $parameter['name']) {
                    $fields[] = self::fieldToArray($field, $priority + 1);
                }
            }
        }

        if (0 === count($fields)) {
            return new JsonResponse(['error' => 'Metainfo field not found'], 404);
        }

        return self::json(1 === count($fields) ? $fields[0] : $fields);
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
        return self::articleValues($parameter, $route, null);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleArticleValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::withBody(static fn (array $body) => self::articleValues($parameter, $route, $body));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryValuesGet(array $parameter, array $route = []): Response
    {
        return self::categoryValues($parameter, $route, null);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::withBody(static fn (array $body) => self::categoryValues($parameter, $route, $body));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMediaValuesGet(array $parameter, array $route = []): Response
    {
        return self::mediaValues($parameter, $route, null);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMediaValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::withBody(static fn (array $body) => self::mediaValues($parameter, $route, $body));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleClangValuesGet(array $parameter, array $route = []): Response
    {
        return self::languageValues($parameter, $route, null);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleClangValuesUpdate(array $parameter, array $route = []): Response
    {
        return self::withBody(static fn (array $body) => self::languageValues($parameter, $route, $body));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @param array<array-key, mixed>|null $body `null` to read
     */
    private static function articleValues(array $parameter, array $route, ?array $body): Response
    {
        $languageId = self::languageId();
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

        $context = new MetaContext(MetaEntity::Article, $article, $article->getCategory());

        return self::values($context, $article, $languageId, $body, static function (array $meta) use ($article, $languageId): void {
            ArticleHandler::edit($article->id, $languageId, [], $meta);
        }, static fn () => Article::get($article->id, $languageId));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @param array<array-key, mixed>|null $body
     */
    private static function categoryValues(array $parameter, array $route, ?array $body): Response
    {
        $languageId = self::languageId();
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

        $context = new MetaContext(MetaEntity::Category, $category, $category->getParent());

        return self::values($context, $category, $languageId, $body, static function (array $meta) use ($category, $languageId): void {
            CategoryHandler::edit($category->id, $languageId, [], $meta);
        }, static fn () => Category::get($category->id, $languageId));
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @param array<array-key, mixed>|null $body
     */
    private static function mediaValues(array $parameter, array $route, ?array $body): Response
    {
        $languageId = self::languageId();
        if ($languageId instanceof Response) {
            return $languageId;
        }

        $media = Media::get((string) $parameter['filename'], $languageId);
        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $user = RouteCollection::getBackendUser($route);
        if (null !== $user && !$user->admin) {
            $perm = $user->getComplexPerm('media');
            if (!$perm->hasCategoryPerm($media->categoryId)) {
                return new JsonResponse(['error' => 'Permission denied'], 403);
            }
        }

        $context = new MetaContext(MetaEntity::Media, $media, mediaCategory: $media->getCategory());

        return self::values($context, $media, $languageId, $body, static function (array $meta) use ($media, $languageId): void {
            MediaHandler::edit($media->fileName, $languageId, [], $meta);
        }, static function () use ($media, $languageId): ?Media {
            Media::clearInstances($media->fileName);

            return Media::get($media->fileName, $languageId);
        });
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @param array<array-key, mixed>|null $body
     */
    private static function languageValues(array $parameter, array $route, ?array $body): Response
    {
        // the languages page of the backend is admin only
        if (null !== $response = self::checkAdminPerm(RouteCollection::getBackendUser($route))) {
            return $response;
        }

        $language = Language::get((int) $parameter['id']);
        if (null === $language) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        $context = new MetaContext(MetaEntity::Language, $language);

        return self::values($context, $language, null, $body, static function (array $meta) use ($language): void {
            LanguageHandler::edit($language->id, [], $meta);
        }, static fn () => Language::get($language->id));
    }

    /**
     * Reads the values of the item, or validates and saves the given ones first.
     *
     * @param array<array-key, mixed>|null $body values keyed by field key (a `meta_` prefix is accepted), `null` to read
     * @param callable(array<string, mixed>): void $save saves the meta values (keyed by field key, typed) via the handler
     * @param callable(): ?object $reload the item after saving
     */
    private static function values(MetaContext $context, object $subject, ?int $languageId, ?array $body, callable $save, callable $reload): Response
    {
        $allowed = self::allowedFields($context);

        if (null !== $body) {
            $input = [];
            foreach ($body as $key => $value) {
                $key = (string) $key;
                $input[str_starts_with($key, MetaSchema::COLUMN_PREFIX) ? substr($key, strlen(MetaSchema::COLUMN_PREFIX)) : $key] = $value;
            }

            $errors = [];
            foreach ($input as $key => $_) {
                if (!isset($allowed[$key])) {
                    $errors[$key] = 'Unknown field';
                }
            }
            if ($errors) {
                return new JsonResponse(['error' => 'Invalid field values', 'fields' => $errors, 'allowed' => array_keys($allowed)], 422);
            }

            $meta = [];
            foreach (self::schemaFields($allowed) as $class => $fields) {
                $properties = array_values(array_map(static fn (MetaField $field): string => $field->property, $fields));
                $schemaInput = array_intersect_key($input, $fields);
                if ([] === $schemaInput) {
                    continue;
                }

                $schema = $class::load($subject);
                $errors += FormValues::apply($schema, $schemaInput, static fn () => new Form($schema, name: 'meta', csrf: false, only: $properties), $properties);
                if ($errors) {
                    continue;
                }

                $error = $schema->validate();
                if (null !== $error) {
                    $errors[''] = FormValues::text($error);
                    continue;
                }

                foreach ($fields as $key => $field) {
                    if (array_key_exists($key, $schemaInput)) {
                        $meta[$key] = $field->definition->getValue($schema);
                    }
                }
            }

            if ($errors) {
                return new JsonResponse(['error' => 'Invalid field values', 'fields' => $errors], 422);
            }

            try {
                if ([] !== $meta) {
                    $save($meta);
                }
            } catch (UserMessageException $e) {
                return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
            }

            $subject = $reload() ?? $subject;
        }

        $data = [];
        foreach (self::schemaFields($allowed) as $class => $fields) {
            $properties = array_map(static fn (MetaField $field): string => $field->property, $fields);
            $data += FormValues::read($class::load($subject), array_values($properties));
        }

        $result = ['data' => (object) $data];
        if (null !== $languageId) {
            $result = ['language_id' => $languageId] + $result;
        }
        if (null !== $body) {
            $result = ['message' => 'Metainfo values updated'] + $result;
        }

        return self::json($result);
    }

    /**
     * The fields of the entity the given item may show, keyed by field key.
     *
     * @return array<string, MetaField>
     */
    private static function allowedFields(MetaContext $context): array
    {
        $fields = [];
        foreach (MetaSchema::getFields($context->entity) as $field) {
            if ($field->schema::isFieldAllowed($field->property, $context)) {
                $fields[$field->definition->key] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, MetaField> $fields
     * @return array<class-string<MetaSchema>, array<string, MetaField>>
     */
    private static function schemaFields(array $fields): array
    {
        $bySchema = [];
        foreach ($fields as $key => $field) {
            $bySchema[$field->schema][$key] = $field;
        }

        return $bySchema;
    }

    /** @return array<string, mixed> */
    private static function fieldToArray(MetaField $field, int $priority): array
    {
        return [
            'name' => $field->column,
            'entity' => strtolower($field->entity->name),
            'priority' => $priority,
            'schema' => $field->schema,
            'property' => $field->property,
            'translated' => $field->translated,
            'table' => $field->table(),
        ] + FormValues::describe($field->definition);
    }

    /** @param callable(array<array-key, mixed>): Response $handler */
    private static function withBody(callable $handler): Response
    {
        $body = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($body)) {
            return new JsonResponse(['error' => 'Invalid input, expected an object keyed by field key'], 400);
        }

        return $handler($body);
    }

    /** The requested language (`language_id`, or `clang_id` of the REDAXO 5 addon), default the start language. */
    private static function languageId(): int|Response
    {
        $query = Core::getRequest()->query;
        $value = $query->get('language_id') ?? $query->get('clang_id');

        if (null === $value || '' === $value) {
            return Language::getStartId();
        }

        return Language::exists((int) $value) ? (int) $value : new JsonResponse(['error' => 'Language not found'], 404);
    }

    private static function json(mixed $data): Response
    {
        return new JsonResponse(json_encode($data, JSON_PRETTY_PRINT), 200, [], true);
    }

    // =====================================================================
    // Permissions
    // =====================================================================

    private static function checkAdminPerm(?User $user): ?Response
    {
        return null === $user || $user->admin ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    private static function checkStructurePerm(?User $user, ?int $categoryId): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('structure');

        return $perm->hasCategoryPerm($categoryId) ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    private static function checkLanguagePerm(?User $user, int $languageId): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('language');

        return $perm->hasPerm($languageId) ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }
}
