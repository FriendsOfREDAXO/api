<?php

namespace FriendsOfRedaxo\Api;

use Exception;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Http\Response as RexResponse;
use Redaxo\Core\Log\Logger;
use Redaxo\Core\Security\User;
use Redaxo\Core\Util\Type;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection as SymfonyRouteCollection;
use Throwable;

use function count;
use function is_array;
use function is_callable;

/**
 * @phpstan-type TRoute array{
 *     scope: string,
 *     route: Route,
 *     description: string,
 *     responses: array<int|string, mixed>|null,
 *     authorization: Auth|null,
 *     tags: list<string>,
 * }
 */
class RouteCollection
{
    /** URL prefix all api routes live under, without leading slash. */
    public static string $preRoute = 'api';

    /** @var list<RoutePackage> */
    private static array $routePackages = [];

    /** @var array<string, TRoute> */
    private static array $routes = [];

    private static bool $packagesLoaded = false;

    /** Guards against re-entering handle() (both entry-point extension points may fire). */
    private static bool $handling = false;

    /**
     * @param array<int|string, mixed>|null $responses
     * @param list<string> $tags
     * @return array<string, TRoute>
     */
    public static function registerRoute(string $scope, Route $route, string $description = '', ?array $responses = null, ?Auth $auth = null, array $tags = []): array
    {
        self::$routes[$scope] = [
            'scope' => $scope,
            'route' => $route,
            'description' => $description,
            'responses' => $responses,
            'authorization' => $auth,
            'tags' => 0 === count($tags) ? ['default'] : $tags,
        ];

        return self::$routes;
    }

    public static function registerRoutePackage(RoutePackage $routePackage): void
    {
        self::$routePackages[] = $routePackage;
    }

    /** @return array<string, TRoute> */
    public static function loadPackageRoutes(): array
    {
        if (self::$packagesLoaded) {
            return self::$routes;
        }

        self::$packagesLoaded = true;

        foreach (self::$routePackages as $routePackage) {
            $routePackage->loadRoutes();
        }

        return self::$routes;
    }

    /** @return array<string, TRoute> */
    public static function getRoutes(): array
    {
        if (!self::$packagesLoaded) {
            self::loadPackageRoutes();
        }

        return self::$routes;
    }

    /**
     * Handles the current request if it addresses the api, and exits.
     *
     * Returns without doing anything for every other request.
     */
    public static function handle(): void
    {
        if (self::$handling) {
            return;
        }

        $request = Core::getRequest();

        // IS REST_API_CALL ?
        if (!str_starts_with($request->getPathInfo(), '/' . self::$preRoute)) {
            return;
        }

        self::$handling = true;

        // Core code paths reached from the handlers (e.g. ContentHandler::addSlice(), which puts it into
        // the SLICE_ADDED params) read the current backend page, and Controller::getCurrentPage() throws
        // when nothing has been set — which is the normal state for a frontend request. Seed a synthetic
        // page so those paths work. Core::isBackend() stays false, so nothing starts behaving as if this
        // were a real backend request.
        if (!Core::isBackend()) {
            Controller::setCurrentPage(self::$preRoute);
        }

        // CORS headers
        $origin = $request->headers->get('Origin');
        if (null !== $origin && '' !== $origin) {
            // Allow CORS only for same-origin requests
            $originParts = parse_url($origin);
            $serverParts = parse_url(Core::getServer());

            if (is_array($originParts) && is_array($serverParts)) {
                $normalizedOrigin = ($originParts['scheme'] ?? '') . '://' . ($originParts['host'] ?? '') . (isset($originParts['port']) ? ':' . $originParts['port'] : '');
                $normalizedServer = ($serverParts['scheme'] ?? '') . '://' . ($serverParts['host'] ?? '') . (isset($serverParts['port']) ? ':' . $serverParts['port'] : '');

                if ($normalizedOrigin === $normalizedServer) {
                    RexResponse::setHeader('Access-Control-Allow-Origin', $origin);
                    RexResponse::setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                    RexResponse::setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept');
                    RexResponse::setHeader('Access-Control-Max-Age', '86400');
                }
            }
        }

        // Handle preflight OPTIONS request
        if ('OPTIONS' === $request->getMethod()) {
            self::send(new Response('', 204));
        }

        try {
            $registeredRoutes = self::getRoutes();

            $routes = new SymfonyRouteCollection();
            foreach ($registeredRoutes as $scope => $registeredRoute) {
                $route = clone $registeredRoute['route'];
                $route->setPath('/' . self::$preRoute . $route->getPath());
                $routes->add($scope, $route);
            }

            $context = new RequestContext();
            $context->fromRequest($request);
            $matcher = new UrlMatcher($routes, $context);

            $parameters = null;
            $response = null;

            try {
                $parameters = $matcher->match($request->getPathInfo());
            } catch (ResourceNotFoundException) {
                $response = new JsonResponse(['error' => 'Route not found'], 404);
            } catch (MethodNotAllowedException $e) {
                $response = new JsonResponse([
                    'error' => 'Method not allowed',
                    'allowed' => $e->getAllowedMethods(),
                ], 405);
            }

            if (null !== $parameters) {
                $controller = $parameters['_controller'] ?? null;

                if (!is_callable($controller)) {
                    $response = new JsonResponse(['error' => 'Controller not found'], 404);
                } else {
                    $routeDefinition = $registeredRoutes[$parameters['_route']];
                    $authObject = $routeDefinition['authorization'];

                    // if no auth object is set, we assume that the route is public
                    if (null !== $authObject && !$authObject->isAuthorized($parameters)) {
                        $response = new JsonResponse(['error' => 'Authorization failed'], 401);
                    } else {
                        try {
                            $response = $controller($parameters, $routeDefinition);
                        } catch (Throwable $e) {
                            Logger::logException($e);
                            $response = new JsonResponse([
                                'error' => 'Internal server error',
                                'message' => $e->getMessage(),
                            ], 500);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Logger::logException($e);
            $response = new JsonResponse(['error' => 'Internal server error'], 500);
        }

        self::send($response ?? new JsonResponse(['error' => 'Internal server error'], 500));
    }

    /**
     * Sends a Symfony response through REDAXO's response layer and terminates the request.
     *
     * Going through {@see RexResponse} instead of `Response::send()` keeps REDAXO's own header handling
     * (nonce, additional headers, buffering) intact.
     */
    private static function send(Response $response): never
    {
        RexResponse::cleanOutputBuffers();
        RexResponse::setStatus(self::formatStatus($response->getStatusCode()));

        foreach ($response->headers->all() as $name => $values) {
            if ('content-type' === $name || 'cache-control' === $name || 'date' === $name) {
                continue;
            }
            foreach ($values as $value) {
                if (null !== $value) {
                    RexResponse::setHeader($name, $value);
                }
            }
        }

        $contentType = $response->headers->get('Content-Type');
        $content = $response->getContent();

        RexResponse::sendContent(false === $content ? '' : $content, $contentType ?? 'application/json');

        exit;
    }

    /** Turns an integer status code into the `"<code> <reason>"` form REDAXO's Response expects. */
    private static function formatStatus(int $code): string
    {
        $text = Response::$statusTexts[$code] ?? '';

        return '' === $text ? (string) $code : $code . ' ' . $text;
    }

    /**
     * The backend user a route was authenticated as, or `null` for bearer-token (and public) routes.
     *
     * Handlers use this to apply the same per-user permission checks the backend pages apply. A `null`
     * user means "no user-bound restrictions", which is how bearer tokens are scoped: their permissions
     * come from the token's scopes, not from a user.
     *
     * @param TRoute $route
     */
    public static function getBackendUser(array $route): ?User
    {
        $auth = $route['authorization'] ?? null;

        if (null === $auth) {
            return null;
        }

        $object = $auth->getAuthorizationObject();

        return $object instanceof User ? $object : null;
    }

    /**
     * Validates and casts a request payload against a field definition.
     *
     * @param array<string, mixed> $request
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     * @throws Exception with the offending field name as message
     */
    public static function getQuerySet(array $request, array $definition): array
    {
        $return = [];
        $key = null;
        $value = null;

        try {
            foreach ($definition as $key => $value) {
                if (isset($value['fields']) && is_array($value['fields'])) {
                    /** @var array<string, mixed> $sub */
                    $sub = is_array($request[$key] ?? null) ? $request[$key] : [];
                    $return[$key] = self::getQuerySet($sub, $value['fields']);
                    continue;
                }

                if ((!isset($definition[$key]['required']) || $definition[$key]['required']) && !isset($request[$key])) {
                    throw new Exception((string) $key);
                }

                if (!isset($request[$key]) || '' === $request[$key]) {
                    $return[$key] = $definition[$key]['default'] ?? null;
                } else {
                    $return[$key] = Type::cast($request[$key], $definition[$key]['type']);
                }
            }
        } catch (Exception $e) {
            if (is_array($value) && isset($value['fields']) && is_array($value['fields'])) {
                throw new Exception($key . '[' . $e->getMessage() . ']');
            }
            throw new Exception((string) $key);
        }

        return $return;
    }
}
