<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Modules as TokenModules;

use function strlen;

class Modules extends TokenModules
{
    public function loadRoutes(): void
    {
        $Routes = RouteCollection::getRoutes();

        foreach ($Routes as $Route) {
            if ('modules/' === substr($Route['scope'], 0, strlen('modules/'))) {
                $scope = 'backend/' . $Route['scope'];
                $route = clone $Route['route'];
                $route->setPath('backend' . $route->getPath());
                $description = $Route['description'];
                $responses = $Route['responses'];
                $authorization = new BackendUser();

                RouteCollection::registerRoute(
                    $scope,
                    $route,
                    $description,
                    $responses,
                    $authorization,
                    ['backend'],
                );
            }
        }
    }
}
