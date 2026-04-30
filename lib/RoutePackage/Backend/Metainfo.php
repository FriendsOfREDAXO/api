<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Metainfo as TokenMetainfo;

use function in_array;

/**
 * Mirror nur die Wert-Endpoints (article/category/media) in den /api/backend/-Pfad.
 * Field-CRUD und Clang-Werte bleiben bewusst Bearer-only — Backend-User sollen
 * Metainfo-Werte an Inhalten pflegen können, aber keine Felddefinitionen anlegen
 * oder Sprach-Metainfo bearbeiten.
 *
 * Permissions werden in den Handlern selbst geprüft (checkStructureValuePerm /
 * checkMediaValuePerm in Metainfo.php).
 */
class Metainfo extends TokenMetainfo
{
    private const MIRRORED_SCOPES = [
        'metainfo/articles/values/get',
        'metainfo/articles/values/update',
        'metainfo/categories/values/get',
        'metainfo/categories/values/update',
        'metainfo/media/values/get',
        'metainfo/media/values/update',
    ];

    public function loadRoutes(): void
    {
        $Routes = RouteCollection::getRoutes();

        foreach ($Routes as $Route) {
            if (!in_array($Route['scope'], self::MIRRORED_SCOPES, true)) {
                continue;
            }

            $scope = 'backend/' . $Route['scope'];
            $route = clone $Route['route'];
            $route->setPath('backend' . $route->getPath());

            RouteCollection::registerRoute(
                $scope,
                $route,
                $Route['description'],
                $Route['responses'],
                new BackendUser(),
                ['backend'],
            );
        }
    }
}
