<?php

namespace FriendsOfREDAXO\API;

use Exception;
use rex;
use rex_response;
use rex_type;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

use function in_array;
use function is_array;

class RouteCollection
{
    public static $preRoute = 'api';
    private static array $RoutePackages = [];
    private static array $Routes = [];

    /** @var true */
    private static bool $packagesLoaded = false;

    public static function registerRoute(string $scope, Route $Route, string $description = ''): array
    {
        self::$Routes[$scope] = [
            'scope' => $scope,
            'route' => $Route,
            'description' => $description,
        ];
        return self::$Routes;
    }

    public static function registerRoutePackage($RoutePackage): void
    {
        self::$RoutePackages[] = $RoutePackage;
    }

    public static function loadPackageRoutes()
    {
        self::$packagesLoaded = true;
        foreach (self::$RoutePackages as $RoutePackage) {
            $RoutePackage->loadRoutes();
        }
        return self::$Routes;
    }

    public static function getRoutes(): array
    {
        if (!self::$packagesLoaded) {
            self::loadPackageRoutes();
        }
        return self::$Routes;
    }

    public static function getRoutesByToken(Token $Token)
    {
        $Scopes = array_intersect($Token->getScopes(), self::getScopes());

        $Routes = [];
        foreach (self::getRoutes() as $RouteScope => $Route) {
            if (in_array($RouteScope, $Scopes)) {
                $Routes[$RouteScope] = $Route;
            }
        }

        return $Routes;
    }

    public static function getScopes(): array
    {
        if (!self::$packagesLoaded) {
            self::loadPackageRoutes();
        }

        $Scopes = [];

        /** @var Route $Route */
        foreach (self::$Routes as $RouteScope => $Route) {
            $Scopes[] = $Route['scope'];
        }

        return $Scopes;
    }

    public static function handle(): void
    {
        // IS REST_API_CALL ?
        if (mb_substr(rex::getRequest()->getPathInfo(), 0, mb_strlen('/' . self::$preRoute)) != '/' . self::$preRoute) {
            return;
        }

        try {
            self::loadPackageRoutes();

            $Token = Token::getFromBearerToken();
            if (null === $Token) {
                $Response = new Response(json_encode(['error' => 'Token not found or token is not active']), 401);
            } else {
                $routes = new \Symfony\Component\Routing\RouteCollection();

                $Routes = self::getRoutesByToken($Token);

                foreach ($Routes as $AddedRouteScope => $AddedRoute) {
                    $AddedRoute['route']->setPath('/' . self::$preRoute . $AddedRoute['route']->getPath());
                    $routes->add($AddedRouteScope, $AddedRoute['route']);
                }

                $context = new RequestContext();
                $context->fromRequest(rex::getRequest());
                $matcher = new UrlMatcher($routes, $context);

                $parameters = $matcher->match(rex::getRequest()->getPathInfo());

                if (isset($parameters['_controller'])) {
                    $controller = $parameters['_controller'];
                    $Response = $controller($parameters);
                } else {
                    $Response = new Response(json_encode(['error' => 'Controller Not found']), 404);
                }
            }
        } catch (Exception $e) {
            $Response = new Response(json_encode(['error' => 'Route with method not found or no token has no access']), 401);
        }

        rex_response::setStatus($Response->getStatusCode());
        rex_response::cleanOutputBuffers();
        rex_response::sendContentType('application/json');
        rex_response::sendContent($Response->getContent());
    }

    public static function getQuerySet(array $request, $definition)
    {
        try {
            $return = [];
            foreach ($definition as $key => $value) {
                if (isset($value['fields']) && is_array($value['fields'])) {
                    $return[$key] = self::getQuerySet($request[$key] ?? [], $value['fields']);
                } else {
                    if ((!isset($definition[$key]['required']) || $definition[$key]['required']) && !isset($request[$key])) {
                        throw new Exception($key);
                    }
                    if (!isset($request[$key]) || '' === $request[$key]) {
                        $return[$key] = $definition[$key]['default'];
                    } else {
                        $return[$key] = rex_type::cast($request[$key], $definition[$key]['type']);
                    }
                }
            }
        } catch (Exception $e) {
            if (isset($value['fields']) && is_array($value['fields'])) {
                throw new Exception($key . '[' . $e->getMessage() . ']');
            }
            throw new Exception($key);
        }
        return $return;
    }
}
