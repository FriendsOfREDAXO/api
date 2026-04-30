<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\ListHelper;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_functional_exception;
use rex_media;
use rex_media_cache;
use rex_media_category;
use rex_media_category_service;
use rex_media_service;
use rex_mediapool;
use rex_path;
use rex_sql;
use rex_user;

use InvalidArgumentException;

use function count;
use function is_array;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use const JSON_PRETTY_PRINT;

class Media extends RoutePackage
{
    public const MediaFields = ['filename', 'category_id', 'filetype', 'originalname', 'filesize', 'width', 'height', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        // Media List ✅
        RouteCollection::registerRoute(
            'media/list',
            new Route(
                'media',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleMediaList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'category_id' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'title' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'filename' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'filetype' => [
                                    'type' => 'string',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'filesize_max' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'filesize_min' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'height_min' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'height_max' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'width_min' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'width_max' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
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
                ['GET']),
            'Access to list of media (of a specific category)',
            null,
            new BearerAuth(),
        );

        // Media delete ✅
        RouteCollection::registerRoute(
            'media/delete',
            new Route(
                'media/{filename}/delete',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleDeleteMedia'],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a media',
            null,
            new BearerAuth(),
        );

        // Media get meta ✅
        RouteCollection::registerRoute(
            'media/get',
            new Route(
                'media/{filename}/info',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleGetMedia',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['GET']),
            'Get a media',
            null,
            new BearerAuth(),
        );

        // Media get file ✅
        RouteCollection::registerRoute(
            'media/get/file',
            new Route(
                'media/{filename}/file',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleGetMediaFile',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.]+'],
                [],
                '',
                [],
                ['GET']),
            'Get a mediafile',
            [
                '200' => [
                    'description' => 'Erfolgreicher Datei-Download',
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

        // Media add ✅
        RouteCollection::registerRoute(
            'media/add',
            new Route(
                'media',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleAddMedia',
                    'Body' => [
                        'file' => [
                            'type' => 'file',
                            'required' => true,
                            'description' => 'Die hochzuladende Datei',
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                        ],
                        'title' => [
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
                ['POST']),
            'Add a media file (multipart/form-data with file field)',
            null,
            new BearerAuth(),
        );

        // Media update ✅
        RouteCollection::registerRoute(
            'media/update',
            new Route(
                'media/{filename}/update',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleUpdateMedia',
                    'Body' => [
                        'file' => [
                            'type' => 'file',
                            'required' => false,
                            'description' => 'Neue Datei (gleiche Dateiendung wie Original)',
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => null,
                        ],
                        'title' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => null,
                        ],
                    ],
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a media',
            null,
            new BearerAuth(),
        );

        // Media Category List ✅
        RouteCollection::registerRoute(
            'media/category/list',
            new Route(
                'media/category',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleCategoryList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'category_id' => [
                                    'type' => 'integer',
                                    'required' => false,
                                    'default' => null,
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
                ['GET']),
            'Access to list of mediacategories',
            null,
            new BearerAuth(),
        );

        // Media Category add ✅
        RouteCollection::registerRoute(
            'media/category/add',
            new Route(
                'media/category',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleAddCategory',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        'parent_id' => [
                            'type' => 'integer',
                            'required' => false,
                            'default' => 0,
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['POST']),
            'Add a media category',
            null,
            new BearerAuth(),
        );

        // Media Category delete ✅
        RouteCollection::registerRoute(
            'media/category/delete',
            new Route(
                'media/category/{id}',
                ['_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleDeleteCategory'],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a media category',
            null,
            new BearerAuth(),
        );

        // Media Category update ✅
        RouteCollection::registerRoute(
            'media/category/update',
            new Route(
                'media/category/{id}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleUpdateCategory',
                    'Body' => [
                        'name' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
                ['id' => '\d+'],
                [],
                '',
                [],
                ['PUT', 'PATCH']),
            'Update a media category (only name — REDAXO core does not allow parent_id changes via the page)',
            null,
            new BearerAuth(),
        );
    }

    /** @api */
    public static function handleCategoryList($Parameter, array $Route): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $user = RouteCollection::getBackendUser($Route);

        if (null !== $Query['filter']['category_id'] && 0 < $Query['filter']['category_id']) {
            $MediaCategory = null;
            if (null !== $user) {
                if ($user->isAdmin()) {
                    $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
                } else {
                    $perm = $user->getComplexPerm('media');
                    if ($perm->hasCategoryPerm($Query['filter']['category_id'])) {
                        $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
                    }
                }
            } else {
                // Token-Auth: Scope berechtigt, kein Rechte-Filter
                $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
            }

            if (null === $MediaCategory) {
                return new Response(json_encode(['error' => 'Category not found or no permission']), 404);
            }
            $CategoriesCollection = $MediaCategory->getChildren();
        } else {
            $CategoriesCollection = [];
            if (null !== $user) {
                if ($user->isAdmin() || $user->getComplexPerm('media')->hasAll()) {
                    $CategoriesCollection = rex_media_category::getRootCategories();
                }
            } else {
                // Token-Auth: Scope berechtigt, kein Rechte-Filter
                $CategoriesCollection = rex_media_category::getRootCategories();
            }
        }

        $Categories = [];
        foreach ($CategoriesCollection as $Category) {
            $Categories[] = [
                'id' => $Category->getId(),
                'name' => $Category->getName(),
                'hasChildren' => $Category->getChildren() ? true : false,
                'parent_id' => $Category->getParentId(),
            ];
        }

        $allowedSortFields = ['id', 'name', 'hasChildren', 'parent_id'];

        try {
            $sortDefs = ListHelper::parseSort($Query['sort'] ?? null, $allowedSortFields, [['field' => 'name', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];

        $result = ListHelper::paginateArray($Categories, $sortDefs, $page, $per_page);

        return new Response(json_encode($result, JSON_PRETTY_PRINT));
    }

    /** @api */
    // public static function handleAddCategory($Parameter)
    // {
    //     $Data = json_decode(rex::getRequest()->getContent(), true);
    //
    //     if (!is_array($Data)) {
    //         return new Response(json_encode(['error' => 'Invalid input']), 400);
    //     }
    //
    //     try {
    //         $Data = RouteCollection::getQuerySet($Data ?? [], $Parameter['Body']);
    //     } catch (Exception $e) {
    //         return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
    //     }
    //
    //     if (0 !== $Data['category_id'] && !rex_category::get($Data['category_id'])) {
    //         return new Response(json_encode(['error' => 'Valid category_id is required']), 400);
    //     }
    //
    //     try {
    //         $CategoryId = null;
    //         rex_extension::register('CAT_ADDED', static function (rex_extension_point $ep) use (&$CategoryId) {
    //             $Params = $ep->getParams();
    //             $CategoryId = $Params['id'];
    //         });
    //
    //         rex_category_service::addCategory($Data['category_id'], [
    //             'catname' => $Data['name'],
    //             'catpriority' => $Data['priority'],
    //             'template_id' => $Data['template_id'],
    //             'status' => $Data['status'],
    //         ]);
    //
    //         $Category = rex_category::get($CategoryId);
    //         if (!$Category) {
    //             return new Response(json_encode(['error' => 'Category not created - reason unknown']), 500);
    //         }
    //
    //         return new Response(json_encode([
    //             'message' => 'Category created',
    //             'id' => $CategoryId,
    //         ],
    //         ), 201);
    //     } catch (Exception $e) {
    //         return new Response(json_encode(['error' => $e->getMessage()]), 500);
    //     }
    // }
    //
    // /** @api */
    // public static function handleDeleteCategory($Parameter): Response
    // {
    //     $Category = rex_category::get($Parameter['id']);
    //     if (!$Category) {
    //         return new Response(json_encode(['error' => 'Category not found']), 404);
    //     }
    //
    //     $CategoryId = $Category->getId();
    //
    //     try {
    //         rex_category_service::deleteCategory($CategoryId);
    //     } catch (Exception $e) {
    //         return new Response(json_encode(['error' => $e->getMessage(), 'id' => $CategoryId]), 500);
    //     }
    //
    //     return new Response(json_encode(['message' => 'Category deleted', 'id' => $CategoryId]), 200);
    // }

    private static function checkMediaPerm(?rex_user $user, ?int $categoryId = null): ?Response
    {
        if (null === $user) {
            return null;
        }
        if ($user->isAdmin()) {
            return null;
        }
        $perm = $user->getComplexPerm('media');
        if (null !== $categoryId && !$perm->hasCategoryPerm($categoryId)) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }
        if (null === $categoryId && !$perm->hasAll()) {
            return new Response(json_encode(['error' => 'Permission denied']), 403);
        }
        return null;
    }

    /** @api */
    public static function handleMediaList($Parameter, array $Route = []): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        // var_dump(rex::getRequest()->getHost()); exit;
        // var_dump($Query);

        $SqlQueryWhere = [];
        $SqlParameters = [];

        if (null !== $Query['filter']['category_id']) {
            $categoryId = (int) $Query['filter']['category_id'];
            if ($categoryId > 0) {
                $MediaCategory = rex_media_category::get($categoryId);
                if (!$MediaCategory) {
                    return new Response(json_encode(['error' => 'Category not found']), 404);
                }
            }
            $SqlQueryWhere[':category_id'] = 'category_id = :category_id';
            $SqlParameters[':category_id'] = $categoryId;
        }

        if (null !== $Query['filter']['title'] && '' != $Query['filter']['title']) {
            $SqlQueryWhere[':title'] = 'title LIKE :title';
            $SqlParameters[':title'] = '%' . $Query['filter']['title'] . '%';
        }

        if (null !== $Query['filter']['filename'] && '' != $Query['filter']['filename']) {
            $SqlQueryWhere[':filename'] = 'filename = :filename';
            $SqlParameters[':filename'] = $Query['filter']['filename'];
        }

        if (null !== $Query['filter']['filetype'] && '' != $Query['filter']['filetype']) {
            $SqlQueryWhere[':filetype'] = 'filetype = :filetype';
            $SqlParameters[':filetype'] = $Query['filter']['filetype'];
        }

        if (null !== $Query['filter']['filesize_max'] && '' != $Query['filter']['filesize_max']) {
            $SqlQueryWhere[':filesize_max'] = 'filesize <= :filesize_max';
            $SqlParameters[':filesize_max'] = $Query['filter']['filesize_max'];
        }
        if (null !== $Query['filter']['filesize_min'] && '' != $Query['filter']['filesize_min']) {
            $SqlQueryWhere[':filesize_min'] = 'filesize >= :filesize_min';
            $SqlParameters[':filesize_min'] = $Query['filter']['filesize_min'];
        }

        if (null !== $Query['filter']['width_max'] && '' != $Query['filter']['width_max']) {
            $SqlQueryWhere[':width_max'] = 'width <= :width_max';
            $SqlParameters[':width_max'] = $Query['filter']['width_max'];
        }

        if (null !== $Query['filter']['width_min'] && '' != $Query['filter']['width_min']) {
            $SqlQueryWhere[':width_min'] = 'width >= :width_min';
            $SqlParameters[':width_min'] = $Query['filter']['width_min'];
        }

        if (null !== $Query['filter']['height_max'] && '' != $Query['filter']['height_max']) {
            $SqlQueryWhere[':height_max'] = 'height <= :height_max';
            $SqlParameters[':height_max'] = $Query['filter']['height_max'];
        }

        if (null !== $Query['filter']['height_min'] && '' != $Query['filter']['height_min']) {
            $SqlQueryWhere[':height_min'] = 'height >= :height_min';
            $SqlParameters[':height_min'] = $Query['filter']['height_min'];
        }

        $allowedSortFields = ['filename', 'category_id', 'filetype', 'filesize', 'title', 'createdate', 'updatedate', 'width', 'height'];

        try {
            $sortDefs = ListHelper::parseSort($Query['sort'] ?? null, $allowedSortFields, [['field' => 'filename', 'direction' => 'asc']]);
        } catch (InvalidArgumentException $e) {
            return ListHelper::sortErrorResponse($e);
        }

        $whereClause = count($SqlQueryWhere) ? 'WHERE ' . implode(' AND ', $SqlQueryWhere) : '';

        // Count total
        $CountSQL = rex_sql::factory();
        $countResult = $CountSQL->getArray(
            'SELECT COUNT(*) as total FROM ' . rex::getTable('media') . ' ' . $whereClause,
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

        $MediaSQL = rex_sql::factory();
        $Medias = $MediaSQL->getArray(
            'SELECT ' . implode(',', self::MediaFields) . '
            FROM ' . rex::getTable('media') . '
            ' . $whereClause . '
            ORDER BY ' . $orderBy . '
            LIMIT ' . $offset . ', ' . $limit,
            $SqlParameters,
        );

        return new Response(json_encode(ListHelper::wrapResponse($Medias, $pagination['meta']), JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleDeleteMedia($Parameter, array $Route = []): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Media->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (false !== rex_mediapool::mediaIsInUse($Parameter['filename'])) {
            return new Response(json_encode(['error' => 'Media is in use.', 'filename' => $Parameter['filename']]), 409);
        }

        try {
            rex_media_service::deleteMedia($Media->getFileName());
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage(), 'filename' => $Parameter['filename']]), 500);
        }

        return new Response(json_encode(['message' => 'Media deleted', 'filename' => $Parameter['filename']]), 200);
    }

    /** @api */
    public static function handleGetMedia($Parameter, array $Route = []): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Get specific media - not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Media->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        try {
            $isInUse = false !== rex_mediapool::mediaIsInUse($Parameter['filename']);
        } catch (\Throwable $e) {
            $isInUse = false;
        }

        $Return = [
            'id' => $Media->getId(),
            'category_id' => $Media->getCategoryId(),
            'filetype' => $Media->getType(),
            'filename' => $Media->getFileName(),
            'originalname' => $Media->getValue('originalname'),
            'filesize' => $Media->getSize(),
            'width' => $Media->getWidth(),
            'height' => $Media->getHeight(),
            'title' => $Media->getTitle(),
            'createdate' => $Media->getCreateDate(),
            'createuser' => $Media->getCreateUser(),
            'updatedate' => $Media->getUpdateDate(),
            'updateuser' => $Media->getUpdateUser(),
            'is_in_use' => $isInUse,
            'is_image' => $Media->isImage(),
            'file_exists' => $Media->fileExists(),
        ];

        return new Response(json_encode($Return, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetMediaFile($Parameter, array $Route = []): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Media->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (!$Media->fileExists()) {
            return new Response(json_encode(['error' => 'Media file resource not found']), 404);
        }

        $Response = new Response();
        $Response->headers->set('Content-Type', $Media->getType());
        $Response->headers->set('Content-Disposition', 'inline; filename="' . addcslashes($Media->getFileName(), '"\\') . '"');
        $Response->setContent(file_get_contents(rex_path::media($Media->getFileName())));

        return $Response;
    }

    /** @api */
    public static function handleAddMedia($Parameter, array $Route = []): Response
    {
        if (!isset($_FILES['file']) || UPLOAD_ERR_OK !== $_FILES['file']['error']) {
            return new Response(json_encode(['error' => 'No file uploaded or upload error']), 400);
        }

        $request = rex::getRequest();
        $categoryId = (int) ($request->request->get('category_id') ?? $request->query->get('category_id') ?? 0);
        $title = (string) ($request->request->get('title') ?? $request->query->get('title') ?? '');

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $categoryId);
        if (null !== $permResponse) {
            return $permResponse;
        }

        if (0 !== $categoryId && !rex_media_category::get($categoryId)) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }

        try {
            $result = rex_media_service::addMedia([
                'category_id' => $categoryId,
                'title' => $title,
                'file' => [
                    'name' => $_FILES['file']['name'],
                    'tmp_name' => $_FILES['file']['tmp_name'],
                    'error' => $_FILES['file']['error'],
                ],
            ], true);

            if ($result['ok']) {
                return new Response(json_encode([
                    'message' => 'Media created',
                    'filename' => $result['filename'],
                ]), 201);
            }

            return new Response(json_encode(['error' => $result['msg'] ?? 'Unknown error']), 400);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }
    }

    /** @api */
    public static function handleUpdateMedia($Parameter, array $Route = []): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Media->getCategoryId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $serviceData = [
            'title' => $Media->getTitle(),
            'category_id' => $Media->getCategoryId(),
        ];

        if (str_contains($contentType, 'multipart/form-data')) {
            // Multipart-Request: Datei + Metadaten (PUT/PATCH füllt $_FILES nicht)
            $parsed = self::parseMultipartInput();

            if (isset($parsed['fields']['category_id'])) {
                $categoryId = (int) $parsed['fields']['category_id'];
                if (0 !== $categoryId && !rex_media_category::get($categoryId)) {
                    return new Response(json_encode(['error' => 'Category not found']), 404);
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
            // JSON-Request: nur Metadaten
            $Data = json_decode(rex::getRequest()->getContent(), true);

            if (!is_array($Data)) {
                $Data = [];
            }

            try {
                $Data = RouteCollection::getQuerySet($Data, $Parameter['Body']);
            } catch (Exception $e) {
                return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
            }

            if (null !== $Data['category_id']) {
                if (0 !== $Data['category_id'] && !rex_media_category::get($Data['category_id'])) {
                    return new Response(json_encode(['error' => 'Category not found']), 404);
                }
                $serviceData['category_id'] = $Data['category_id'];
            }

            if (null !== $Data['title']) {
                $serviceData['title'] = $Data['title'];
            }
        }

        try {
            $result = rex_media_service::updateMedia($Parameter['filename'], $serviceData);

            if ($result['ok']) {
                return new Response(json_encode([
                    'message' => 'Media updated',
                    'filename' => $Parameter['filename'],
                ]), 200);
            }

            return new Response(json_encode(['error' => $result['msg'] ?? 'Unknown error']), 400);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        } finally {
            // Temp-Datei aufräumen
            if (isset($serviceData['file']['tmp_name']) && file_exists($serviceData['file']['tmp_name'])) {
                @unlink($serviceData['file']['tmp_name']);
            }
        }
    }

    /**
     * Parst multipart/form-data aus php://input für PUT/PATCH-Requests.
     * PHP füllt $_FILES nur bei POST automatisch.
     *
     * @return array{fields: array<string, string>, files: array<string, array{name: string, tmp_name: string, type: string, size: int, error: int}>}
     */
    private static function parseMultipartInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (!preg_match('/boundary=(?:"([^"]+)"|(.+))$/i', $contentType, $matches)) {
            return ['fields' => [], 'files' => []];
        }

        $boundary = $matches[1] ?: $matches[2];
        $rawData = file_get_contents('php://input');

        if (false === $rawData || '' === $rawData) {
            return ['fields' => [], 'files' => []];
        }

        $fields = [];
        $files = [];

        $parts = preg_split('/-+' . preg_quote($boundary, '/') . '/', $rawData);

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
            $body = substr($part, $separator + 4);
            $body = rtrim($body, "\r\n");

            if (!preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }
            $fieldName = $nameMatch[1];

            if (preg_match('/filename="([^"]*)"/', $headers, $filenameMatch)) {
                // Datei-Feld
                $tmpFile = tempnam(sys_get_temp_dir(), 'rex_api_upload_');
                file_put_contents($tmpFile, $body);

                preg_match('/Content-Type:\s*(.+)/i', $headers, $typeMatch);
                $mimeType = isset($typeMatch[1]) ? trim($typeMatch[1]) : 'application/octet-stream';

                $files[$fieldName] = [
                    'name' => $filenameMatch[1],
                    'tmp_name' => $tmpFile,
                    'type' => $mimeType,
                    'size' => strlen($body),
                    'error' => UPLOAD_ERR_OK,
                ];
            } else {
                // Normales Feld
                $fields[$fieldName] = $body;
            }
        }

        return ['fields' => $fields, 'files' => $files];
    }

    /** @api */
    public static function handleAddCategory($Parameter, array $Route = []): Response
    {
        $Data = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data, $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, (int) ($Data['parent_id'] ?? 0));
        if (null !== $permResponse) {
            return $permResponse;
        }

        $parentId = (int) ($Data['parent_id'] ?? 0);
        $parent = null;
        if (0 !== $parentId) {
            $parent = rex_media_category::get($parentId);
            if (null === $parent) {
                return new Response(json_encode(['error' => 'Parent category not found']), 404);
            }
        }

        // Mirror mediapool/pages/structure.php (add_file_cat): rex_media_category_service::addCategory()
        // fires MEDIA_CATEGORY_ADDED and handles cache invalidation.
        try {
            rex_media_category_service::addCategory($Data['name'], $parent);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        // Service does not return the new id — fetch the most recent id for this parent_id.
        $row = rex_sql::factory()->getArray(
            'SELECT id FROM ' . rex::getTable('media_category') . ' WHERE parent_id = :p AND name = :n ORDER BY id DESC LIMIT 1',
            [':p' => $parentId, ':n' => $Data['name']],
        );
        $newId = isset($row[0]['id']) ? (int) $row[0]['id'] : null;

        return new Response(json_encode([
            'message' => 'Media category created',
            'id' => $newId,
        ]), 201);
    }

    /** @api */
    public static function handleDeleteCategory($Parameter, array $Route = []): Response
    {
        $Category = rex_media_category::get($Parameter['id']);

        if (!$Category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Category->getId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        // Mirror mediapool/pages/structure.php (delete_file_cat): rex_media_category_service::deleteCategory()
        // checks for children/media and the MEDIA_CATEGORY_IS_IN_USE EP itself, then fires
        // MEDIA_CATEGORY_DELETED. Map rex_functional_exception → 409.
        try {
            rex_media_category_service::deleteCategory((int) $Parameter['id']);
        } catch (rex_functional_exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 409);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        return new Response(json_encode([
            'message' => 'Media category deleted',
            'id' => $Parameter['id'],
        ]), 200);
    }

    /** @api */
    public static function handleUpdateCategory($Parameter, array $Route = []): Response
    {
        $Category = rex_media_category::get($Parameter['id']);

        if (!$Category) {
            return new Response(json_encode(['error' => 'Category not found']), 404);
        }

        $user = RouteCollection::getBackendUser($Route);
        $permResponse = self::checkMediaPerm($user, $Category->getId());
        if (null !== $permResponse) {
            return $permResponse;
        }

        $Data = json_decode(rex::getRequest()->getContent(), true);
        if (!is_array($Data)) {
            return new Response(json_encode(['error' => 'Invalid input']), 400);
        }

        try {
            $Data = RouteCollection::getQuerySet($Data, $Parameter['Body']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'Body field: `' . $e->getMessage() . '` is required']), 400);
        }

        // Mirror mediapool/pages/structure.php (edit_file_cat): rex_media_category_service::editCategory()
        // takes only the name and fires MEDIA_CATEGORY_UPDATED. Core does not support parent_id changes
        // via the page, so the API does not either.
        try {
            rex_media_category_service::editCategory((int) $Parameter['id'], ['name' => $Data['name']]);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => $e->getMessage()]), 500);
        }

        return new Response(json_encode([
            'message' => 'Media category updated',
            'id' => $Parameter['id'],
        ]), 200);
    }
}
