<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Discovery as TokenDiscovery;

class Discovery extends TokenDiscovery
{
    public function loadRoutes(): void
    {
        $Routes = RouteCollection::getRoutes();

        foreach ($Routes as $Route) {
            if ('me' === $Route['scope']) {
                $route = clone $Route['route'];
                $route->setPath('backend' . $route->getPath());

                RouteCollection::registerRoute(
                    'backend/me',
                    $route,
                    $Route['description'],
                    $Route['responses'],
                    new BackendUser(),
                    ['backend'],
                );
            }
        }
    }
}
