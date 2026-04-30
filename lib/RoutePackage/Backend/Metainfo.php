<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Metainfo as TokenMetainfo;

use function in_array;

/**
 * Mirror der Wert-Endpoints (article/category/media/clang) in den /api/backend/-Pfad.
 * Field-CRUD bleibt bewusst Bearer-only — Backend-User sollen Werte an Inhalten
 * pflegen können, aber keine Felddefinitionen anlegen.
 *
 * Permissions werden in den Handlern selbst geprüft:
 *   checkStructureValuePerm — Article/Category, structure-Perm gegen Kategorie
 *   checkMediaValuePerm     — Media, media-Perm gegen Media-Kategorie
 *   checkClangValuePerm     — Clang, admin-only (wie core system.clangs.php)
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
        'metainfo/clangs/values/get',
        'metainfo/clangs/values/update',
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
