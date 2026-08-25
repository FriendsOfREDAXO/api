<?php

namespace FriendsOfRedaxo\Api;

use Exception;
use FriendsOfRedaxo\Api\Auth as ApiAuth;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use InvalidArgumentException;
use rex;
use rex_logger;
use rex_response;
use rex_type;
use rex_user;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Throwable;

use function count;
use function is_array;

class RouteCollection
{
    public static $preRoute = 'api';
    private static array $RoutePackages = [];
    private static array $Routes = [];

    /** @var true */
    private static bool $packagesLoaded = false;

    public static function registerRoute(string $scope, Route $Route, string $description = '', ?array $Responses = null, ?ApiAuth $Auth = null, array $tags = []): array
    {
        self::$Routes[$scope] = [
            'scope' => $scope,
            'route' => $Route,
            'description' => $description,
            'responses' => $Responses,
            'authorization' => $Auth,
            'tags' => 0 == count($tags) ? [self::tagFromScope($scope)] : $tags,
        ];
        return self::$Routes;
    }

    /**
     * Gruppe fuer die OpenAPI-Darstellung (Swagger-UI-Accordion) aus dem Scope.
     *
     * Genommen wird das erste Segment: `media/category/add` ergibt `media`. Der Scope
     * selbst waere als Gruppe unbrauchbar -- fast jeder gehoert zu genau einem Endpunkt,
     * das ergaebe ein Accordion pro Endpunkt. Routen, die einen eigenen Tag mitgeben
     * (auch aus anderen AddOns), bleiben unangetastet.
     */
    public static function tagFromScope(string $scope): string
    {
        $segment = explode('/', $scope)[0];
        return '' === $segment ? 'default' : $segment;
    }

    /**
     * Gruppe fuer einen Backend-Spiegel: `backend/media`, `backend/structure`, ...
     *
     * Uebernommen wird die Gruppe der gespiegelten Route, nicht neu aus dem Scope
     * abgeleitet -- sonst weicht der Spiegel ab, wenn die Quellroute einen eigenen
     * Tag gesetzt hat (z.B. `meta` fuer die Selbstauskunft, deren Scope `me` heisst).
     *
     * @param array<string, mixed> $sourceRoute Eintrag aus getRoutes()
     */
    public static function backendTag(array $sourceRoute): string
    {
        $tags = $sourceRoute['tags'] ?? [];
        if (is_array($tags) && isset($tags[0]) && '' !== $tags[0] && 'default' !== $tags[0]) {
            return 'backend/' . (string) $tags[0];
        }
        return 'backend/' . self::tagFromScope((string) ($sourceRoute['scope'] ?? ''));
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

    public static function handle(): void
    {
        // IS REST_API_CALL ?
        if (mb_substr(rex::getRequest()->getPathInfo(), 0, mb_strlen('/' . self::$preRoute)) != '/' . self::$preRoute) {
            return;
        }

        // CORS headers
        $origin = rex::getRequest()->headers->get('Origin');
        if ($origin) {
            // Allow CORS only for same-origin requests
            $serverUrl = (string) rex::getServer();
            $originParts = parse_url($origin);
            $serverParts = parse_url($serverUrl);

            if (is_array($originParts) && is_array($serverParts)) {
                $originScheme = $originParts['scheme'] ?? '';
                $originHost = $originParts['host'] ?? '';
                $originPort = isset($originParts['port']) ? ':' . $originParts['port'] : '';

                $serverScheme = $serverParts['scheme'] ?? '';
                $serverHost = $serverParts['host'] ?? '';
                $serverPort = isset($serverParts['port']) ? ':' . $serverParts['port'] : '';

                $normalizedOrigin = $originScheme . '://' . $originHost . $originPort;
                $normalizedServer = $serverScheme . '://' . $serverHost . $serverPort;

                if ($normalizedOrigin === $normalizedServer) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
                    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
                    header('Access-Control-Max-Age: 86400');
                }
            }
        }

        // Handle preflight OPTIONS request
        if ('OPTIONS' === rex::getRequest()->getMethod()) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(204);
            rex_response::sendContent('');
            exit;
        }

        try {
            self::loadPackageRoutes();
            $routes = new \Symfony\Component\Routing\RouteCollection();
            $RegisterdRoutes = self::getRoutes();

            foreach ($RegisterdRoutes as $AddedRouteScope => $AddedRoute) {
                // clone, so the registered route keeps its unprefixed path:
                // controllers reading getRoutes() (e.g. Discovery) would otherwise see /api twice
                $MatchRoute = clone $AddedRoute['route'];
                $MatchRoute->setPath('/' . self::$preRoute . $MatchRoute->getPath());
                $routes->add($AddedRouteScope, $MatchRoute);
            }

            $context = new RequestContext();
            $context->fromRequest(rex::getRequest());
            $matcher = new UrlMatcher($routes, $context);

            try {
                $parameters = $matcher->match(rex::getRequest()->getPathInfo());
            } catch (ResourceNotFoundException $e) {
                $Response = new JsonResponse(['error' => 'Route not found'], 404);
                $parameters = null;
            } catch (MethodNotAllowedException $e) {
                $Response = new JsonResponse([
                    'error' => 'Method not allowed',
                    'allowed' => $e->getAllowedMethods(),
                ], 405);
                $parameters = null;
            }

            if (null !== $parameters) {
                if (!isset($parameters['_controller'])) {
                    $Response = new JsonResponse(['error' => 'Controller not found'], 404);
                } else {
                    $controller = $parameters['_controller'];
                    $AuthObject = $RegisterdRoutes[$parameters['_route']]['authorization'] ?? null;

                    // if no AuthObject is set, we assume that the route is public
                    if ($AuthObject && !$AuthObject->isAuthorized($parameters)) {
                        $Error = ['error' => 'Authorization failed'];
                        // credential is valid, only the scope is missing: name it, so callers can tell
                        // an invalid token from a missing permission
                        if ($AuthObject instanceof BearerAuth && null !== $AuthObject->getAuthorizationObject()) {
                            $Error['required_scope'] = $AuthObject->getScope($parameters['_route']);
                        }
                        $Response = new JsonResponse($Error, 401);
                    } else {
                        try {
                            $Response = $controller($parameters, $RegisterdRoutes[$parameters['_route']]);
                        } catch (Throwable $e) {
                            rex_logger::logException($e);
                            $Response = new JsonResponse([
                                'error' => 'Internal server error',
                                'message' => $e->getMessage(),
                            ], 500);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            rex_logger::logException($e);
            $Response = new JsonResponse(['error' => 'Internal server error'], 500);
        }

        rex_response::setStatus($Response->getStatusCode());
        rex_response::cleanOutputBuffers();
        rex_response::sendContentType($Response->headers->get('Content-Type'));
        rex_response::sendContent($Response->getContent());
        exit;
    }

    public static function getBackendUser(array $Route): ?rex_user
    {
        $auth = $Route['authorization'] ?? null;
        if (null === $auth) {
            return null;
        }
        $obj = $auth->getAuthorizationObject();
        return $obj instanceof rex_user ? $obj : null;
    }

    /**
     * Validiert und castet Request-Werte gegen eine Route-Definition.
     *
     * @param bool $strict Wirft eine Exception, wenn $request Felder enthält, die die Definition
     *                     nicht kennt. Nur für Bodys gedacht: bei Query-Parametern steckt in
     *                     $_REQUEST regelmäßig Fremdes (Session, Rewrite, Tracking), das kein
     *                     Fehler ist. Ohne die Prüfung werden unbekannte Felder still verworfen —
     *                     ein `revision` im Slice-Body sah damit nach Erfolg aus, obwohl es
     *                     nirgends ankam.
     */
    public static function getQuerySet(array $request, $definition, bool $strict = false)
    {
        if ($strict) {
            $unknown = array_diff(array_keys($request), array_keys((array) $definition));
            if ([] !== $unknown) {
                throw new InvalidArgumentException('Unknown field(s): ' . implode(', ', array_values($unknown)));
            }
        }

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
                        $return[$key] = $definition[$key]['default'] ?? null;
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
