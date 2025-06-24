<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use rex;
use rex_media;
use rex_media_category;
use rex_media_service;
use rex_mediapool;
use rex_pager;
use rex_path;
use rex_sql;
use rex_user;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;

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

        // Media List ❌
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
    }

    /** @api */
    public static function handleCategoryList($Parameter, array $Route): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new Response(json_encode(['error' => 'query field: ' . $e->getMessage() . ' is required']), 400);
        }

        $Authorization = $Route['authorization'] ?? null;
        /** @var rex_user $AuthorizationObject|null */
        $AuthorizationObject = null;
        if (null !== $Authorization->getAuthorizationObject()) {
            $AuthorizationObject = $Authorization->getAuthorizationObject();
        }

        if (null !== $Query['filter']['category_id'] && 0 < $Query['filter']['category_id']) {
            $MediaCategory = null;
            if ($AuthorizationObject) {
                $perm = $AuthorizationObject->getComplexPerm('media');
                if ($perm->hasCategoryPerm($Query['filter']['category_id'])) {
                    $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
                }
            } else {
                $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
            }

            if (null === $MediaCategory) {
                return new Response(json_encode(['error' => 'Category not found or no permission']), 404);
            }
            $CategoriesCollection = $MediaCategory->getChildren();
        } else {
            $CategoriesCollection = [];
            if ($AuthorizationObject) {
                $perm = $AuthorizationObject->getComplexPerm('media');
                if ($perm->hasAll()) {
                    $CategoriesCollection = rex_media_category::getRootCategories();
                }
            } else {
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

        return new Response(json_encode($Categories, JSON_PRETTY_PRINT));
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

    /** @api */
    public static function handleMediaList($Parameter): Response
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

        if (null !== $Query['filter']['category_id'] && 0 < $Query['filter']['category_id']) {
            $MediaCategory = rex_media_category::get($Query['filter']['category_id']);
            if (!$MediaCategory) {
                return new Response(json_encode(['error' => 'Category not found']), 404);
            }
            $SqlQueryWhere[':category_id'] = 'category_id = :category_id';
            $SqlParameters[':category_id'] = $Query['filter']['category_id'];
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

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        // Leider nicht nutzbar. Da Pager über Parameter funktioniert.
        // $pager = new rex_pager(5000);
        // $items = rex_media_service::getList($filter, [], $pager);

        $MediaSQL = rex_sql::factory();
        $Medias = $MediaSQL->getArray('
            select
                ' . implode(',', self::MediaFields) . '
            from
                ' . rex::getTable('media') . '
                ' . (count($SqlQueryWhere) ? 'where ' . implode(' and ', $SqlQueryWhere) : '') . '

            LIMIT :start, :per_page
                ',
            $SqlParameters,
        );

        // var_dump($SqlQueryWhere, $SqlParameters);
        // exit;

        return new Response(json_encode($Medias, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleDeleteMedia($Parameter): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
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
    public static function handleGetMedia($Parameter): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Get specific media - not found']), 404);
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
            'is_in_use' => (false !== rex_mediapool::mediaIsInUse($Parameter['filename']) ? true : false),
            'is_image' => $Media->isImage(),
            'file_exists' => $Media->fileExists(),
        ];

        return new Response(json_encode($Return, JSON_PRETTY_PRINT));
    }

    /** @api */
    public static function handleGetMediaFile($Parameter): Response
    {
        $Media = rex_media::get($Parameter['filename']);

        if (!$Media) {
            return new Response(json_encode(['error' => 'Media not found']), 404);
        }

        if (!$Media->fileExists()) {
            return new Response(json_encode(['error' => 'Media file resource not found']), 404);
        }

        $Response = new Response();
        $Response->headers->set('Content-Type', $Media->getType());
        $Response->headers->set('Content-Disposition', 'inline; filename="' . $Media->getFileName() . '"');
        $Response->setContent(file_get_contents(rex_path::media($Media->getFileName())));

        // var_dump($Media->getType());exit;

        return $Response;
    }
}
