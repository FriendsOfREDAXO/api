<?php

namespace FriendsOfRedaxo\Api\RoutePackage\Backend;

use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage\Users as TokenUsers;

use function strlen;

class Users extends TokenUsers
{
    public function loadRoutes(): void
    {
        $Routes = RouteCollection::getRoutes();

        foreach ($Routes as $Route) {
            if ('users/' === substr($Route['scope'], 0, strlen('users/'))) {
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
