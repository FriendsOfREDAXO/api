<?php

namespace FriendsOfREDAXO\API\RoutePackage;

use Exception;
use FriendsOfREDAXO\API\RouteCollection;
use FriendsOfREDAXO\API\RoutePackage;
use rex;
use rex_media;
use rex_media_category;
use rex_media_service;
use rex_mediapool;
use rex_path;
use rex_sql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function count;

use const JSON_PRETTY_PRINT;

class Media extends RoutePackage
{
    public function loadRoutes(): void
    {
        // TODO: Mediakategorie list/add/delete/update

        // Media List
        RouteCollection::registerRoute(
            'media/list',
            new Route(
                'media',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Media::handleMediaList',
                    'query' => [
                        'filter' => [
                            'fields' => [
                                'id' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'category_id' => [
                                    'type' => 'int',
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
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => 10000000,
                                ],
                                'filesize_min' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'height_min' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'height_max' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => 10000000,
                                ],
                                'width_min' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => null,
                                ],
                                'width_max' => [
                                    'type' => 'int',
                                    'required' => false,
                                    'default' => 10000000,
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

        // Media delete
        RouteCollection::registerRoute(
            'media/delete',
            new Route(
                'media/{filename}/delete',
                ['_controller' => 'FriendsOfREDAXO\API\RoutePackage\Media::handleDeleteMedia'],
                ['filename' => '[a-zA-Z0-9\-\_\.]+'],
                [],
                '',
                [],
                ['DELETE']),
            'Delete a media',
        );

        // Media get
        RouteCollection::registerRoute(
            'media/get',
            new Route(
                'media/{filename}',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Media::handleGetMedia',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.]+'],
                [],
                '',
                [],
                ['GET']),
            'Get a media'
        );

        // responses:
        // "200":
        //   description: Erfolgreicher Datei-Download
        //   content:
        //     application/octet-stream:
        //       schema:
        //         type: string
        //         format: binary

        // Media get
        RouteCollection::registerRoute(
            'media/get/file',
            new Route(
                'media/{filename}/file',
                [
                    '_controller' => 'FriendsOfREDAXO\API\RoutePackage\Media::handleGetMediaFile',
                ],
                ['filename' => '[a-zA-Z0-9\-\_\.]+'],
                [],
                '',
                [],
                ['GET']),
            'Get a mediafile',
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

        $fields = ['id', 'category_id', 'filetype', 'filename', 'originalname', 'filesize', 'width', 'height', 'title', 'createdate', 'createuser', 'updatedate', 'updateuser'];

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

        if (null !== $Query['filter']['id'] && 0 < $Query['filter']['id']) {
            $SqlQueryWhere[':id'] = 'id = :id';
            $SqlParameters[':id'] = $Query['filter']['id'];
        }

        if (null !== $Query['filter']['title'] && '' != $Query['filter']['title']) {
            $SqlQueryWhere[':title'] = 'title LIKE :title';
            $SqlParameters[':title'] = $Query['filter']['title'];
        }

        if (null !== $Query['filter']['filename'] && '' != $Query['filter']['filename']) {
            $SqlQueryWhere[':filename'] = 'filename LIKE :filename';
            $SqlParameters[':filename'] = $Query['filter']['filename'];
        }

        if (null !== $Query['filter']['filetype'] && '' != $Query['filter']['filetype']) {
            $SqlQueryWhere[':filetype'] = 'filename LIKE :filetype';
            $SqlParameters[':filetype'] = $Query['filter']['filetype'];
        }

        $SqlQueryWhere[':filesize_max'] = 'filesize <= :filesize_max';
        $SqlParameters[':filesize_max'] = $Query['filter']['filesize_max'];

        $SqlQueryWhere[':filesize_min'] = 'filesize >= :filesize_min';
        $SqlParameters[':filesize_min'] = $Query['filter']['filesize_min'];

        $SqlQueryWhere[':width_max'] = 'width <= :width_max';
        $SqlParameters[':width_max'] = $Query['filter']['width_max'];

        $SqlQueryWhere[':width_min'] = 'width >= :width_min';
        $SqlParameters[':width_min'] = $Query['filter']['width_min'];

        $per_page = (1 > $Query['per_page']) ? 10 : $Query['per_page'];
        $page = (1 > $Query['page']) ? 1 : $Query['page'];
        $start = ($page - 1) * $per_page;

        $SqlParameters[':per_page'] = $per_page;
        $SqlParameters[':start'] = $start;

        $ArticlesSQL = rex_sql::factory();
        $Articles = $ArticlesSQL->getArray(
            '
            select
                ' . implode(',', $fields) . '
            from
                ' . rex::getTablePrefix() . 'media
            ' . (count($SqlQueryWhere) ? 'where ' . implode(' and ', $SqlQueryWhere) : '') . '

            LIMIT :start, :per_page
                ',
            $SqlParameters,
        );

        return new Response(json_encode($Articles, JSON_PRETTY_PRINT));
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

        return $Response;
    }
}
