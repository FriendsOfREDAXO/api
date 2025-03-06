<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;

use const JSON_PRETTY_PRINT;

class Media extends RoutePackage
{
    public const MediaFields = ['filename', 'category_id', 'filetype', 'originalname', 'filesize', 'width', 'height', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser'];

    public function loadRoutes(): void
    {
        // TODO: Mediakategorie list/add/delete/update

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
        );

        // Media get meta ✅
        RouteCollection::registerRoute(
            'media/get',
            new Route(
                'media/{filename}',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Media::handleGetMedia',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.\@]+'],
                [],
                '',
                [],
                ['GET']),
            'Get a media',
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
        );
    }

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
            return new Response(json_encode(['error' => 'Media not found']), 404);
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
