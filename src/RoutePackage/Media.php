<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\Media as PoolMedia;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryHandler;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPool;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Throwable;

use function array_key_exists;
use function array_slice;
use function count;
use function is_array;
use function strlen;

use const JSON_PRETTY_PRINT;
use const UPLOAD_ERR_OK;

class Media extends RoutePackage
{
    /** API field => SQL expression of the media list. */
    private const array MEDIA_COLUMNS = [
        'id' => 'm.id',
        'filename' => 'm.filename',
        'category_id' => 'm.category_id',
        'filetype' => 'm.filetype',
        'originalname' => 'm.originalname',
        'filesize' => 'm.filesize',
        'width' => 'm.width',
        'height' => 'm.height',
        'title' => 't.title',
        'language_id' => 't.language_id',
        'createdate' => 'm.createdate',
        'createuser' => 'm.createuser',
        'updatedate' => 'm.updatedate',
        'updateuser' => 'm.updateuser',
    ];

    /** Filenames may contain letters, digits and `-_.@` — same character class as the REDAXO 5 addon. */
    private const string FILENAME_PATTERN = '[a-zA-Z0-9\-\_\.\@]+';

    #[Override]
    public function loadRoutes(): void
    {
        // Media List
        RouteCollection::registerRoute(
            'media/list',
            new Route(
                'media',
                [
                    '_controller' => self::class . '::handleMediaList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'category_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => '0 for the root level'],
                                'language_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Language of the title, default: the start language'],
                                'title' => ['type' => 'string', 'required' => false, 'default' => null],
                                'filename' => ['type' => 'string', 'required' => false, 'default' => null],
                                'filetype' => ['type' => 'string', 'required' => false, 'default' => null],
                                'filesize_max' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'filesize_min' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'height_min' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'height_max' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'width_min' => ['type' => 'integer', 'required' => false, 'default' => null],
                                'width_max' => ['type' => 'integer', 'required' => false, 'default' => null],
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
            'Access to list of media (of a specific category)',
            null,
            new BearerAuth(),
        );

        // Media delete
        RouteCollection::registerRoute(
            'media/delete',
            new Route(
                'media/{filename}/delete',
                ['_controller' => self::class . '::handleDeleteMedia'],
                ['filename' => self::FILENAME_PATTERN],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a media',
            null,
            new BearerAuth(),
        );

        // Media get meta
        RouteCollection::registerRoute(
            'media/get',
            new Route(
                'media/{filename}/info',
                [
                    '_controller' => self::class . '::handleGetMedia',
                ],
                ['filename' => self::FILENAME_PATTERN],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get a media',
            null,
            new BearerAuth(),
        );

        // Media get file
        RouteCollection::registerRoute(
            'media/get/file',
            new Route(
                'media/{filename}/file',
                [
                    '_controller' => self::class . '::handleGetMediaFile',
                ],
                ['filename' => self::FILENAME_PATTERN],
                [],
                '',
                [],
                ['GET'],
            ),
            'Get a mediafile',
            [
                '200' => [
                    'description' => 'Successful file download',
                    'content' => [
                        '*/*' => [
                            'schema' => [
                                'type' => 'string',
                                'format' => 'binary',
                            ],
                        ],
                    ],
                ],
            ],
            new BearerAuth(),
        );

        // Media add
        RouteCollection::registerRoute(
            'media/add',
            new Route(
                'media',
                [
                    '_controller' => self::class . '::handleAddMedia',
                    'Body' => [
                        'file' => ['type' => 'file', 'required' => true, 'description' => 'The file to upload'],
                        'category_id' => ['type' => 'integer', 'required' => false, 'default' => 0],
                        'title' => ['type' => 'string', 'required' => false, 'default' => '', 'description' => 'Title in all languages'],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST'],
            ),
            'Add a media file (multipart/form-data with file field)',
            null,
            new BearerAuth(),
        );

        // Media update
        RouteCollection::registerRoute(
            'media/update',
            new Route(
                'media/{filename}/update',
                [
                    '_controller' => self::class . '::handleUpdateMedia',
                    'Body' => [
                        'file' => ['type' => 'file', 'required' => false, 'description' => 'New file (same extension as the original)'],
                        'category_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => '0 for the root level'],
                        'title' => ['type' => 'string', 'required' => false, 'default' => null],
                        'language_id' => ['type' => 'integer', 'required' => false, 'default' => null, 'description' => 'Language of the title, default: the start language'],
                    ],
                ],
                ['filename' => self::FILENAME_PATTERN],
                [],
                '',
                [],
                // POST is allowed on purpose: PHP consumes a multipart/form-data body without populating
                // $_FILES for PUT/PATCH (and on most SAPIs php://input is empty afterwards), so replacing
                // the file itself only works reliably over POST. PUT/PATCH stay available for JSON
                // metadata updates and for multipart on setups that do keep the body readable.
                ['PUT', 'PATCH', 'POST'],
            ),
            'Update a media (JSON metadata via PUT/PATCH, file replacement via POST multipart/form-data)',
            null,
            new BearerAuth(),
        );

        // Media Category List
        RouteCollection::registerRoute(
            'media/category/list',
            new Route(
                'media/category',
                [
                    '_controller' => self::class . '::handleCategoryList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'category_id' => ['type' => 'integer', 'required' => false, 'default' => null],
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
            'Access to list of mediacategories',
            null,
            new BearerAuth(),
        );

        // Media Category add
        RouteCollection::registerRoute(
            'media/category/add',
            new Route(
                'media/category',
                [
                    '_controller' => self::class . '::handleAddCategory',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                        'parent_id' => ['type' => 'integer', 'required' => false, 'default' => 0],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST'],
            ),
            'Add a media category',
            null,
            new BearerAuth(),
        );

        // Media Category delete
        RouteCollection::registerRoute(
            'media/category/delete',
            new Route(
                'media/category/{id}',
                ['_controller' => self::class . '::handleDeleteCategory'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE'],
            ),
            'Delete a media category',
            null,
            new BearerAuth(),
        );

        // Media Category update
        RouteCollection::registerRoute(
            'media/category/update',
            new Route(
                'media/category/{id}',
                [
                    '_controller' => self::class . '::handleUpdateCategory',
                    'Body' => [
                        'name' => ['type' => 'string', 'required' => true],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH'],
            ),
            'Update a media category (only name — REDAXO core does not allow parent_id changes via the page)',
            null,
            new BearerAuth(),
        );
    }

    /** @param int|null $categoryId `null` (or 0) for the media pool root */
    private static function hasMediaPerm(?User $user, ?int $categoryId): bool
    {
        if (null === $user || $user->admin) {
            return true;
        }

        $perm = $user->getComplexPerm('media');

        return $perm->hasCategoryPerm($categoryId ?: null);
    }

    private static function checkMediaPerm(?User $user, ?int $categoryId): ?Response
    {
        return self::hasMediaPerm($user, $categoryId) ? null : new JsonResponse(['error' => 'Permission denied'], 403);
    }

    private static function isInUse(PoolMedia $media): bool
    {
        try {
            return !MediaPool::findUsages($media)->isEmpty();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMediaList(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $user = RouteCollection::getBackendUser($route);
        $languageId = (int) ($query['filter']['language_id'] ?? 0) ?: Language::getStartId();
        if (!Language::exists($languageId)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        $where = ['t.language_id = :language_id'];
        $params = ['language_id' => $languageId];

        if (null !== $query['filter']['category_id']) {
            $categoryId = (int) $query['filter']['category_id'];

            if ($categoryId > 0 && null === MediaCategory::get($categoryId)) {
                return new JsonResponse(['error' => 'Category not found'], 404);
            }

            if (0 === $categoryId) {
                $where[] = 'm.category_id IS NULL';
            } else {
                $where[] = 'm.category_id = :category_id';
                $params['category_id'] = $categoryId;
            }
        }

        if (null !== $query['filter']['title'] && '' !== $query['filter']['title']) {
            $where[] = 't.title LIKE :title';
            $params['title'] = '%' . $query['filter']['title'] . '%';
        }

        foreach (['filename', 'filetype'] as $column) {
            if (null !== $query['filter'][$column] && '' !== $query['filter'][$column]) {
                $where[] = 'm.' . $column . ' = :' . $column;
                $params[$column] = $query['filter'][$column];
            }
        }

        $ranges = [
            'filesize_max' => ['filesize', '<='],
            'filesize_min' => ['filesize', '>='],
            'width_max' => ['width', '<='],
            'width_min' => ['width', '>='],
            'height_max' => ['height', '<='],
            'height_min' => ['height', '>='],
        ];

        foreach ($ranges as $key => [$column, $operator]) {
            if (null !== $query['filter'][$key] && '' !== $query['filter'][$key]) {
                $where[] = 'm.' . $column . ' ' . $operator . ' :' . $key;
                $params[$key] = $query['filter'][$key];
            }
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, array_keys(self::MEDIA_COLUMNS), [['field' => 'filename', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $select = [];
        foreach (self::MEDIA_COLUMNS as $field => $expression) {
            $select[] = $expression . ' AS `' . $field . '`';
        }

        $rows = Sql::factory()->getArray(
            'SELECT ' . implode(', ', $select) . '
            FROM ' . Core::TABLE_PREFIX . 'media m
            JOIN ' . Core::TABLE_PREFIX . 'media_translation t ON t.media_id = m.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . ListHelper::buildSqlOrderBy($sortDefs),
            $params,
        );

        // rows of categories the backend user may not see are dropped after the query
        $media = [];
        foreach ($rows as $row) {
            if (!self::hasMediaPerm($user, null === $row['category_id'] ? null : (int) $row['category_id'])) {
                continue;
            }
            foreach (['id', 'category_id', 'filesize', 'width', 'height', 'language_id'] as $field) {
                $row[$field] = null === $row[$field] ? null : (int) $row[$field];
            }
            $media[] = $row;
        }

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];
        $pagination = ListHelper::paginate($page, $perPage, count($media));

        return new JsonResponse(json_encode(ListHelper::wrapResponse(array_slice($media, $pagination['offset'], $pagination['limit']), $pagination['meta']), JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetMedia(array $parameter, array $route = []): Response
    {
        $languageId = (int) Core::getRequest()->query->get('language_id', '0') ?: Language::getStartId();
        $media = Language::exists($languageId) ? PoolMedia::get((string) $parameter['filename'], $languageId) : null;

        if (null === $media) {
            return new JsonResponse(['error' => 'Get specific media - not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $media->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        return new JsonResponse(json_encode([
            'id' => $media->id,
            'category_id' => $media->categoryId,
            'filetype' => $media->type,
            'filename' => $media->fileName,
            'originalname' => $media->originalFileName,
            'filesize' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'title' => $media->title,
            'language_id' => $media->languageId,
            'url' => Url::absoluteBase('media/' . $media->fileName),
            'createdate' => date('Y-m-d H:i:s', $media->createDate),
            'createuser' => $media->createUser,
            'updatedate' => date('Y-m-d H:i:s', $media->updateDate),
            'updateuser' => $media->updateUser,
            'is_in_use' => self::isInUse($media),
            'is_image' => str_starts_with((string) $media->type, 'image/'),
            'file_exists' => $media->fileExists(),
        ], JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetMediaFile(array $parameter, array $route = []): Response
    {
        $media = PoolMedia::get((string) $parameter['filename']);

        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $media->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (!$media->fileExists()) {
            return new JsonResponse(['error' => 'Media file resource not found'], 404);
        }

        $response = new Response((string) file_get_contents(Path::media($media->fileName)));
        $response->headers->set('Content-Type', $media->type ?? 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'inline; filename="' . addcslashes($media->fileName, '"\\') . '"');

        return $response;
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleAddMedia(array $parameter, array $route = []): Response
    {
        $request = Core::getRequest();
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded or upload error'], 400);
        }

        $categoryId = (int) ($request->request->get('category_id') ?? $request->query->get('category_id') ?? 0) ?: null;
        $title = (string) ($request->request->get('title') ?? $request->query->get('title') ?? '');

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (null !== $categoryId && null === MediaCategory::get($categoryId)) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        try {
            $media = MediaHandler::add(['file' => $file, 'category_id' => $categoryId, 'title' => $title]);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Media created', 'filename' => $media->fileName], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateMedia(array $parameter, array $route = []): Response
    {
        $filename = (string) $parameter['filename'];
        $media = PoolMedia::get($filename);

        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $media->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $contentType = (string) (Core::getRequest()->headers->get('Content-Type') ?? '');

        $serviceData = [];
        $languageId = Language::getStartId();
        $tmpFile = null;

        if (str_contains($contentType, 'multipart/form-data')) {
            // Multipart request: file + metadata. PHP only populates $_POST/$_FILES for POST; for PUT and
            // PATCH the body has to be parsed by hand (and on many SAPIs PHP has already consumed it, in
            // which case nothing is found and only the existing values are kept).
            $parsed = 'POST' === Core::getRequest()->getMethod()
                ? ['fields' => $_POST, 'files' => $_FILES]
                : self::parseMultipartInput();

            if (isset($parsed['fields']['category_id'])) {
                $categoryId = (int) $parsed['fields']['category_id'] ?: null;
                if (null !== $categoryId && null === MediaCategory::get($categoryId)) {
                    return new JsonResponse(['error' => 'Category not found'], 404);
                }
                $serviceData['category_id'] = $categoryId;
            }

            if (isset($parsed['fields']['title'])) {
                $serviceData['title'] = (string) $parsed['fields']['title'];
            }

            if (isset($parsed['fields']['language_id'])) {
                $languageId = (int) $parsed['fields']['language_id'];
            }

            $upload = 'POST' === Core::getRequest()->getMethod() ? Core::getRequest()->files->get('file') : null;
            if ($upload instanceof UploadedFile) {
                $serviceData['file'] = $upload;
            } elseif (isset($parsed['files']['file']) && 'POST' !== Core::getRequest()->getMethod()) {
                $tmpFile = $parsed['files']['file']['tmp_name'];
                $serviceData['file'] = new SymfonyFile($tmpFile);
            }
        } else {
            // JSON request: metadata only
            $data = json_decode(Core::getRequest()->getContent(), true);

            if (!is_array($data)) {
                $data = [];
            }

            try {
                $data = RouteCollection::getQuerySet($data, $parameter['Body']);
            } catch (Exception $e) {
                return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
            }

            if (null !== $data['category_id']) {
                $categoryId = (int) $data['category_id'] ?: null;
                if (null !== $categoryId && null === MediaCategory::get($categoryId)) {
                    return new JsonResponse(['error' => 'Category not found'], 404);
                }
                $serviceData['category_id'] = $categoryId;
            }

            if (null !== $data['title']) {
                $serviceData['title'] = (string) $data['title'];
            }

            if (null !== $data['language_id']) {
                $languageId = (int) $data['language_id'];
            }
        }

        if (!Language::exists($languageId)) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        if (array_key_exists('category_id', $serviceData) && null !== $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $serviceData['category_id'])) {
            return $permResponse;
        }

        try {
            MediaHandler::edit($filename, $languageId, $serviceData);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        } finally {
            if (null !== $tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        return new JsonResponse(['message' => 'Media updated', 'filename' => $filename], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteMedia(array $parameter, array $route = []): Response
    {
        $filename = (string) $parameter['filename'];
        $media = PoolMedia::get($filename);

        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $media->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (self::isInUse($media)) {
            return new JsonResponse(['error' => 'Media is in use.', 'filename' => $filename], 409);
        }

        try {
            MediaHandler::delete($media->fileName);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage()), 'filename' => $filename], 409);
        }

        return new JsonResponse(['message' => 'Media deleted', 'filename' => $filename], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleCategoryList(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $user = RouteCollection::getBackendUser($route);
        $filterCategoryId = null !== $query['filter']['category_id'] ? (int) $query['filter']['category_id'] : 0;

        if ($filterCategoryId > 0) {
            // A bearer token is already scoped by the route, so only backend users get filtered further.
            $permitted = self::hasMediaPerm($user, $filterCategoryId);

            $mediaCategory = $permitted ? MediaCategory::get($filterCategoryId) : null;

            if (null === $mediaCategory) {
                return new JsonResponse(['error' => 'Category not found or no permission'], 404);
            }

            $collection = $mediaCategory->getChildren();
        } else {
            $permitted = null === $user
                || $user->admin
                || $user->getComplexPerm('media')->hasAll();

            $collection = $permitted ? MediaCategory::getRootCategories() : [];
        }

        $categories = [];
        foreach ($collection as $category) {
            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'hasChildren' => count($category->getChildren()) > 0,
                'parent_id' => $category->parentId ?? 0,
            ];
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'name', 'hasChildren', 'parent_id'], [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];

        $result = ListHelper::paginateArray($categories, $sortDefs, $page, $perPage);

        return new JsonResponse(json_encode($result, JSON_PRETTY_PRINT), 200, [], true);
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

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        $parentId = (int) ($data['parent_id'] ?? 0) ?: null;

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $parentId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (null !== $parentId && null === MediaCategory::get($parentId)) {
            return new JsonResponse(['error' => 'Parent category not found'], 404);
        }

        try {
            $category = MediaCategoryHandler::add(['parent_id' => $parentId, 'name' => (string) $data['name']]);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse(['message' => 'Media category created', 'id' => $category->id], 201);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleUpdateCategory(array $parameter, array $route = []): Response
    {
        $categoryId = (int) $parameter['id'];
        $category = MediaCategory::get($categoryId);

        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $category->id);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $data = json_decode(Core::getRequest()->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        try {
            $data = RouteCollection::getQuerySet($data, $parameter['Body']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Body field: `' . $e->getMessage() . '` is required'], 400);
        }

        // like the media pool structure page only the name can be changed, core has no move for media categories
        try {
            MediaCategoryHandler::edit($categoryId, ['name' => (string) $data['name']]);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 400);
        }

        return new JsonResponse([
            'message' => 'Media category updated',
            'id' => $categoryId,
        ], 200);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleDeleteCategory(array $parameter, array $route = []): Response
    {
        $categoryId = (int) $parameter['id'];
        $category = MediaCategory::get($categoryId);

        if (null === $category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $category->id);
        if (null !== $permResponse) {
            return $permResponse;
        }

        // the handler refuses a category with children or media, or one that is in use -> 409
        try {
            MediaCategoryHandler::delete($categoryId);
        } catch (UserMessageException $e) {
            return new JsonResponse(['error' => self::plain($e->getMessage())], 409);
        }

        return new JsonResponse([
            'message' => 'Media category deleted',
            'id' => $categoryId,
        ], 200);
    }

    /**
     * Parses `multipart/form-data` from `php://input` for PUT/PATCH requests — PHP only populates
     * `$_FILES` automatically for POST.
     *
     * @return array{fields: array<string, string>, files: array<string, array{name: string, tmp_name: string, type: string, size: int, error: int}>}
     */
    private static function parseMultipartInput(): array
    {
        $contentType = (string) (Core::getRequest()->headers->get('Content-Type') ?? '');

        if (!preg_match('/boundary=(?:"([^"]+)"|(.+))$/i', $contentType, $matches)) {
            return ['fields' => [], 'files' => []];
        }

        $boundary = '' !== $matches[1] ? $matches[1] : $matches[2];
        $rawData = file_get_contents('php://input');

        if (false === $rawData || '' === $rawData) {
            return ['fields' => [], 'files' => []];
        }

        $fields = [];
        $files = [];

        $parts = preg_split('/-+' . preg_quote($boundary, '/') . '/', $rawData) ?: [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");

            if ('' === $part || '--' === trim($part)) {
                continue;
            }

            $separator = strpos($part, "\r\n\r\n");
            if (false === $separator) {
                continue;
            }

            $headers = substr($part, 0, $separator);
            $body = rtrim(substr($part, $separator + 4), "\r\n");

            if (!preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }
            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/', $headers, $filenameMatch)) {
                $tmpFile = (string) tempnam(sys_get_temp_dir(), 'rex_api_upload_');
                file_put_contents($tmpFile, $body);

                preg_match('/Content-Type:\s*(.+)/i', $headers, $typeMatch);

                $files[$fieldName] = [
                    'name' => $filenameMatch[1],
                    'tmp_name' => $tmpFile,
                    'type' => isset($typeMatch[1]) ? trim($typeMatch[1]) : 'application/octet-stream',
                    'size' => strlen($body),
                    'error' => UPLOAD_ERR_OK,
                ];
            } else {
                $fields[$fieldName] = $body;
            }
        }

        return ['fields' => $fields, 'files' => $files];
    }
}
