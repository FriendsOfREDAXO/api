<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use InvalidArgumentException;
use Override;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\MediaPool\Media as PoolMedia;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MediaPool\MediaCategoryHandler;
use Redaxo\Core\MediaPool\MediaHandler;
use Redaxo\Core\MediaPool\MediaPool;
use Redaxo\Core\MediaPool\MediaPoolPermission;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Throwable;

use function count;
use function is_array;

use const JSON_PRETTY_PRINT;
use const UPLOAD_ERR_OK;

class Media extends RoutePackage
{
    public const array MEDIA_FIELDS = ['id', 'filename', 'category_id', 'filetype', 'originalname', 'filesize', 'width', 'height', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser'];

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
                                'category_id' => ['type' => 'integer', 'required' => false, 'default' => null],
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
                        'title' => ['type' => 'string', 'required' => false, 'default' => ''],
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
                        'category_id' => ['type' => 'integer', 'required' => false, 'default' => null],
                        'title' => ['type' => 'string', 'required' => false, 'default' => null],
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

    /**
     * @param int|null $categoryId `0`/`null` means the media pool root, which requires the "all
     *     categories" permission.
     */
    private static function checkMediaPerm(?User $user, ?int $categoryId): ?Response
    {
        if (null === $user || $user->admin) {
            return null;
        }

        $perm = $user->getComplexPerm('media');

        if (!$perm instanceof MediaPoolPermission) {
            return new JsonResponse(['error' => 'Permission denied'], 403);
        }

        $allowed = null !== $categoryId && 0 !== $categoryId
            ? $perm->hasCategoryPerm($categoryId)
            : $perm->hasAll();

        return $allowed ? null : new JsonResponse(['error' => 'Permission denied'], 403);
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

        $where = [];
        $params = [];

        if (null !== $query['filter']['category_id']) {
            $categoryId = (int) $query['filter']['category_id'];

            if ($categoryId > 0 && null === MediaCategory::get($categoryId)) {
                return new JsonResponse(['error' => 'Category not found'], 404);
            }

            $where[] = 'category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        if (null !== $query['filter']['title'] && '' !== $query['filter']['title']) {
            $where[] = 'title LIKE :title';
            $params[':title'] = '%' . $query['filter']['title'] . '%';
        }

        foreach (['filename', 'filetype'] as $column) {
            if (null !== $query['filter'][$column] && '' !== $query['filter'][$column]) {
                $where[] = $column . ' = :' . $column;
                $params[':' . $column] = $query['filter'][$column];
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
                $where[] = $column . ' ' . $operator . ' :' . $key;
                $params[':' . $key] = $query['filter'][$key];
            }
        }

        try {
            $sortDefs = ListHelper::parseSort($query['sort'] ?? null, ['id', 'filename', 'category_id', 'filetype', 'filesize', 'title', 'createdate', 'updatedate', 'width', 'height'], [['field' => 'filename', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $countResult = Sql::factory()->getArray(
            'SELECT COUNT(*) as total FROM ' . Core::getTable('media') . ' ' . $whereClause,
            $params,
        );
        $total = (int) $countResult[0]['total'];

        $perPage = 1 > $query['per_page'] ? 10 : (int) $query['per_page'];
        $page = 1 > $query['page'] ? 1 : (int) $query['page'];
        $pagination = ListHelper::paginate($page, $perPage, $total);

        // LIMIT inlined as integers (Sql binds parameters as strings -> MySQL strict mode rejects them).
        $media = Sql::factory()->getArray(
            'SELECT ' . implode(',', self::MEDIA_FIELDS) . '
            FROM ' . Core::getTable('media') . '
            ' . $whereClause . '
            ORDER BY ' . ListHelper::buildSqlOrderBy($sortDefs) . '
            LIMIT ' . (int) $pagination['offset'] . ', ' . (int) $pagination['limit'],
            $params,
        );

        return new JsonResponse(json_encode(ListHelper::wrapResponse($media, $pagination['meta']), JSON_PRETTY_PRINT), 200, [], true);
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleGetMedia(array $parameter, array $route = []): Response
    {
        $media = PoolMedia::get((string) $parameter['filename']);

        if (null === $media) {
            return new JsonResponse(['error' => 'Get specific media - not found'], 404);
        }

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $media->categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $isInUse = false !== MediaPool::mediaIsInUse($media->fileName);
        } catch (Throwable) {
            $isInUse = false;
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
            'createdate' => $media->createDate,
            'createuser' => $media->createUser,
            'updatedate' => $media->updateDate,
            'updateuser' => $media->updateUser,
            'is_in_use' => $isInUse,
            'is_image' => str_starts_with($media->type, 'image/'),
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
        $response->headers->set('Content-Type', $media->type);
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
        if (!isset($_FILES['file']) || UPLOAD_ERR_OK !== $_FILES['file']['error']) {
            return new JsonResponse(['error' => 'No file uploaded or upload error'], 400);
        }

        $request = Core::getRequest();
        $categoryId = (int) ($request->request->get('category_id') ?? $request->query->get('category_id') ?? 0);
        $title = (string) ($request->request->get('title') ?? $request->query->get('title') ?? '');

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (0 !== $categoryId && null === MediaCategory::get($categoryId)) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        try {
            $result = MediaHandler::addMedia([
                'category_id' => $categoryId,
                'title' => $title,
                'file' => [
                    'name' => $_FILES['file']['name'],
                    'tmp_name' => $_FILES['file']['tmp_name'],
                    'error' => $_FILES['file']['error'],
                ],
            ], true);

            if ($result['ok'] ?? false) {
                return new JsonResponse([
                    'message' => 'Media created',
                    'filename' => $result['filename'],
                ], 201);
            }

            return new JsonResponse(['error' => $result['msg'] ?? 'Unknown error'], 400);
        } catch (ApiFunctionException|UserMessageException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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

        $serviceData = [
            'title' => $media->title,
            'category_id' => $media->categoryId,
        ];

        if (str_contains($contentType, 'multipart/form-data')) {
            // Multipart request: file + metadata. PHP only populates $_POST/$_FILES for POST; for PUT and
            // PATCH the body has to be parsed by hand (and on many SAPIs PHP has already consumed it, in
            // which case nothing is found and only the existing values are kept).
            $parsed = 'POST' === Core::getRequest()->getMethod()
                ? ['fields' => $_POST, 'files' => $_FILES]
                : self::parseMultipartInput();

            if (isset($parsed['fields']['category_id'])) {
                $categoryId = (int) $parsed['fields']['category_id'];
                if (0 !== $categoryId && null === MediaCategory::get($categoryId)) {
                    return new JsonResponse(['error' => 'Category not found'], 404);
                }
                $serviceData['category_id'] = $categoryId;
            }

            if (isset($parsed['fields']['title'])) {
                $serviceData['title'] = $parsed['fields']['title'];
            }

            if (isset($parsed['files']['file'])) {
                $serviceData['file'] = $parsed['files']['file'];
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
                $categoryId = (int) $data['category_id'];
                if (0 !== $categoryId && null === MediaCategory::get($categoryId)) {
                    return new JsonResponse(['error' => 'Category not found'], 404);
                }
                $serviceData['category_id'] = $categoryId;
            }

            if (null !== $data['title']) {
                $serviceData['title'] = $data['title'];
            }
        }

        try {
            $result = MediaHandler::updateMedia($filename, $serviceData);

            if ($result['ok'] ?? false) {
                return new JsonResponse([
                    'message' => 'Media updated',
                    'filename' => $filename,
                ], 200);
            }

            return new JsonResponse(['error' => $result['msg'] ?? 'Unknown error'], 400);
        } catch (ApiFunctionException|UserMessageException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        } finally {
            // Clean up the temp file
            if (isset($serviceData['file']['tmp_name']) && file_exists($serviceData['file']['tmp_name'])) {
                @unlink($serviceData['file']['tmp_name']);
            }
        }
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

        if (false !== MediaPool::mediaIsInUse($filename)) {
            return new JsonResponse(['error' => 'Media is in use.', 'filename' => $filename], 409);
        }

        try {
            MediaHandler::deleteMedia($media->fileName);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'filename' => $filename], 500);
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
            $permitted = null === $user
                || $user->admin
                || ($user->getComplexPerm('media') instanceof MediaPoolPermission && $user->getComplexPerm('media')->hasCategoryPerm($filterCategoryId));

            $mediaCategory = $permitted ? MediaCategory::get($filterCategoryId) : null;

            if (null === $mediaCategory) {
                return new JsonResponse(['error' => 'Category not found or no permission'], 404);
            }

            $collection = $mediaCategory->getChildren();
        } else {
            $permitted = null === $user
                || $user->admin
                || ($user->getComplexPerm('media') instanceof MediaPoolPermission && $user->getComplexPerm('media')->hasAll());

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

        $parentId = (int) ($data['parent_id'] ?? 0);

        $permResponse = self::checkMediaPerm(RouteCollection::getBackendUser($route), $parentId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        $parent = null;
        if (0 !== $parentId) {
            $parent = MediaCategory::get($parentId);
            if (null === $parent) {
                return new JsonResponse(['error' => 'Parent category not found'], 404);
            }
        }

        // Mirrors the media pool structure page: MediaCategoryHandler::addCategory() fires
        // MEDIA_CATEGORY_ADDED and takes care of cache invalidation.
        try {
            MediaCategoryHandler::addCategory((string) $data['name'], $parent);
        } catch (ApiFunctionException|UserMessageException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }

        // The handler does not return the new id — fetch the most recent one for this parent/name.
        $rows = Sql::factory()->getArray(
            'SELECT id FROM ' . Core::getTable('media_category') . ' WHERE parent_id = :p AND name = :n ORDER BY id DESC LIMIT 1',
            [':p' => $parentId, ':n' => $data['name']],
        );

        return new JsonResponse([
            'message' => 'Media category created',
            'id' => isset($rows[0]['id']) ? (int) $rows[0]['id'] : null,
        ], 201);
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

        // Mirrors the media pool structure page: MediaCategoryHandler::editCategory() takes only the name
        // and fires MEDIA_CATEGORY_UPDATED. Core does not support parent_id changes there, so neither
        // does the API.
        try {
            MediaCategoryHandler::editCategory($categoryId, ['name' => $data['name']]);
        } catch (ApiFunctionException|UserMessageException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
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

        // Mirrors the media pool structure page: MediaCategoryHandler::deleteCategory() checks for
        // children/media and the MEDIA_CATEGORY_IS_IN_USE extension point itself, then fires
        // MEDIA_CATEGORY_DELETED. A UserMessageException means "still in use" -> 409.
        try {
            MediaCategoryHandler::deleteCategory($categoryId);
        } catch (ApiFunctionException|UserMessageException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
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
