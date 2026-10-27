<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\OpenAPIConfig;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Token;
use Override;
use Redaxo\Core\Security\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Self-description of the API: lists exactly the endpoints the caller may use.
 *
 * The route needs no scope of its own — every valid credential gets an answer, otherwise the endpoint would be missing
 * on precisely those tokens where a scope was forgotten.
 */
class Discovery extends RoutePackage
{
    public const array FORMATS = ['compact', 'openapi'];

    #[Override]
    public function loadRoutes(): void
    {
        RouteCollection::registerRoute(
            'me',
            new Route('me', [
                '_controller' => self::class . '::handleMe',
                'query' => [
                    'format' => ['type' => 'string', 'required' => false, 'default' => 'compact', 'description' => '"compact" (default) or "openapi" (OpenAPI specification of the allowed endpoints)'],
                ],
            ], [], [], '', [], ['GET']),
            'List the endpoints and parameters the current credential may use',
            null,
            new BearerAuth(requiresScope: false),
            ['meta'],
        );
    }

    /**
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $route
     * @api
     */
    public static function handleMe(array $parameter, array $route = []): Response
    {
        try {
            $query = RouteCollection::getQuerySet($_REQUEST, $parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $format = strtolower((string) $query['format']);
        if (!in_array($format, self::FORMATS, true)) {
            return new JsonResponse(['error' => 'query field: format must be one of ' . implode(', ', self::FORMATS)], 400);
        }

        $object = ($route['authorization'] ?? null)?->getAuthorizationObject();
        $allowed = [];
        $meta = ['api_base' => '/' . RouteCollection::$preRoute];

        if ($object instanceof Token) {
            $scopes = $object->getScopes();
            foreach (RouteCollection::getRoutes() as $scope => $routeArray) {
                $auth = $routeArray['authorization'];
                if ($auth instanceof BearerAuth && (!$auth->requiresScope || in_array($auth->scope ?? $scope, $scopes, true))) {
                    $allowed[$scope] = $routeArray;
                }
            }

            $meta['auth'] = [
                'type' => 'bearer',
                'token_name' => $object->getName(),
                'expires_at' => $object->getExpiresAt(),
                'scopes' => $scopes,
            ];
        } elseif ($object instanceof User) {
            foreach (RouteCollection::getRoutes() as $scope => $routeArray) {
                if ($routeArray['authorization'] instanceof BackendUser) {
                    $allowed[$scope] = $routeArray;
                }
            }

            $meta['auth'] = ['type' => 'backend_session', 'login' => $object->login, 'admin' => $object->admin];
            $meta['note'] = 'Backend endpoints are not filtered by user permissions: those are checked per request, a listed endpoint may still answer 403.';
        } else {
            return new JsonResponse(['error' => 'Authorization failed'], 401);
        }

        if ('openapi' === $format) {
            return new JsonResponse(json_encode(OpenAPIConfig::getByRoutes($allowed), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [], true);
        }

        $endpoints = [];
        foreach ($allowed as $scope => $routeArray) {
            // the scope a token needs, not the route name: they differ for flows of several routes (chunked upload)
            $auth = $routeArray['authorization'];
            $endpoints[] = self::describeRoute($auth instanceof BearerAuth ? ($auth->scope ?? $scope) : $scope, $routeArray);
        }

        $meta['endpoint_count'] = count($endpoints);
        $meta['openapi_url'] = self::path($route['route'] ?? null) . '?format=openapi';

        return new JsonResponse(json_encode(['meta' => $meta, 'endpoints' => $endpoints], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [], true);
    }

    /**
     * @param array<string, mixed> $routeArray
     * @return array<string, mixed>
     */
    private static function describeRoute(string $scope, array $routeArray): array
    {
        /** @var Route $route */
        $route = $routeArray['route'];
        $methods = $route->getMethods();

        $entry = [
            'scope' => $scope,
            'methods' => [] === $methods ? ['GET'] : $methods,
            'path' => self::path($route),
            'description' => $routeArray['description'],
            'tags' => $routeArray['tags'] ?? ['default'],
        ];

        $pathParameters = [];
        foreach ($route->getRequirements() as $key => $requirement) {
            $pathParameters[$key] = ['required' => true, 'type' => 'string', 'pattern' => $requirement];
        }
        if ($pathParameters) {
            $entry['path_parameters'] = $pathParameters;
        }

        if ($fields = self::describeFields($route->getDefault('query') ?? [])) {
            $entry['query'] = $fields;
        }
        if ($fields = self::describeFields($route->getDefault('Body') ?? [])) {
            $entry['body'] = $fields;
        }

        return $entry;
    }

    /**
     * Mirrors RouteCollection::getQuerySet(): a field without an explicit `required` is required.
     *
     * @param array<array-key, mixed> $definition
     * @return array<string, mixed>
     */
    private static function describeFields(array $definition): array
    {
        $fields = [];
        foreach ($definition as $key => $field) {
            if (!is_array($field)) {
                continue;
            }

            $described = ['type' => $field['type'] ?? 'string', 'required' => $field['required'] ?? true];
            if (array_key_exists('default', $field)) {
                $described['default'] = $field['default'];
            }
            if (isset($field['description']) && '' !== $field['description']) {
                $described['description'] = $field['description'];
            }
            if (isset($field['fields']) && is_array($field['fields'])) {
                $described['fields'] = self::describeFields($field['fields']);
            }

            $fields[(string) $key] = $described;
        }

        return $fields;
    }

    private static function path(?Route $route): string
    {
        return '/' . RouteCollection::$preRoute . (null === $route ? '' : '/' . ltrim($route->getPath(), '/'));
    }
}
