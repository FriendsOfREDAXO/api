<?php

namespace FriendsOfRedaxo\Api\RoutePackage;

use Exception;
use FriendsOfRedaxo\Api\Auth\BackendUser;
use FriendsOfRedaxo\Api\Auth\BearerAuth;
use FriendsOfRedaxo\Api\OpenAPIConfig;
use FriendsOfRedaxo\Api\RouteCollection;
use FriendsOfRedaxo\Api\RoutePackage;
use FriendsOfRedaxo\Api\Token;
use rex_user;
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
 * Self-description of the API: lists exactly those endpoints the caller may use.
 *
 * The route needs no scope of its own — every valid credential gets an answer,
 * otherwise the endpoint would be missing on precisely those tokens where the
 * scope was forgotten.
 */
class Discovery extends RoutePackage
{
    public const FORMATS = ['compact', 'openapi'];

    public function loadRoutes(): void
    {
        // Own capabilities (no scope required) ✅
        RouteCollection::registerRoute(
            'me',
            new Route(
                'me',
                [
                    '_controller' => 'FriendsOfRedaxo\Api\RoutePackage\Discovery::handleMe',
                    'query' => [
                        'format' => [
                            'type' => 'string',
                            'required' => false,
                            'default' => 'compact',
                            'description' => 'Response format: "compact" (default) or "openapi" (OpenAPI 3.0 specification)',
                        ],
                    ],
                ],
                [],
                [],
                '',
                [],
                ['GET']),
            'List the endpoints and parameters the current credential may use',
            null,
            new BearerAuth(false),
            ['meta'],
        );
    }

    /** @api */
    public static function handleMe($Parameter, array $Route = []): Response
    {
        try {
            $Query = RouteCollection::getQuerySet($_REQUEST, $Parameter['query']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'query field: ' . $e->getMessage() . ' is required'], 400);
        }

        $format = mb_strtolower((string) ($Query['format'] ?? 'compact'));
        if (!in_array($format, self::FORMATS, true)) {
            return new JsonResponse(['error' => 'query field: format must be one of ' . implode(', ', self::FORMATS)], 400);
        }

        $Auth = $Route['authorization'] ?? null;
        $AuthObject = (null === $Auth) ? null : $Auth->getAuthorizationObject();

        $Allowed = [];
        $Meta = [
            'api_base' => '/' . RouteCollection::$preRoute,
        ];

        if ($AuthObject instanceof Token) {
            $Scopes = $AuthObject->getScopes();
            foreach (RouteCollection::getRoutes() as $Scope => $RouteArray) {
                $RouteAuth = $RouteArray['authorization'] ?? null;
                if (!$RouteAuth instanceof BearerAuth) {
                    continue;
                }
                if ($RouteAuth->requiresScope() && !in_array($RouteAuth->getScope((string) $Scope), $Scopes, true)) {
                    continue;
                }
                $Allowed[$Scope] = $RouteArray;
            }

            $Meta['auth'] = [
                'type' => 'bearer',
                'token_name' => $AuthObject->getName(),
                'scopes' => $Scopes,
            ];
        } elseif ($AuthObject instanceof rex_user) {
            foreach (RouteCollection::getRoutes() as $Scope => $RouteArray) {
                if (!($RouteArray['authorization'] ?? null) instanceof BackendUser) {
                    continue;
                }
                $Allowed[$Scope] = $RouteArray;
            }

            $Meta['auth'] = [
                'type' => 'backend_session',
                'login' => $AuthObject->getLogin(),
                'admin' => $AuthObject->isAdmin(),
            ];
            $Meta['note'] = 'Backend endpoints are not filtered by user permissions: those are checked per request, a listed endpoint may still answer 403.';
        } else {
            return new JsonResponse(['error' => 'Authorization failed'], 401);
        }

        if ('openapi' === $format) {
            $Config = OpenAPIConfig::getByRoutes($Allowed);
            return new JsonResponse(json_encode($Config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [], true);
        }

        $Endpoints = [];
        foreach ($Allowed as $Scope => $RouteArray) {
            // Ausgegeben wird der geforderte Scope, nicht der Routenname: bei einem
            // Ablauf aus mehreren Routen (Chunked Upload) sind das verschiedene Werte,
            // und ein Client soll den Namen sehen, den er am Token vergeben muss.
            $RouteAuth = $RouteArray['authorization'] ?? null;
            $EffectiveScope = null === $RouteAuth ? (string) $Scope : $RouteAuth->getScope((string) $Scope);
            $Endpoints[] = self::describeRoute($EffectiveScope, $RouteArray);
        }

        $Meta['endpoint_count'] = count($Endpoints);
        $Meta['openapi_url'] = self::path($Route['route'] ?? null) . '?format=openapi';

        $Result = [
            'meta' => $Meta,
            'endpoints' => $Endpoints,
        ];

        return new JsonResponse(json_encode($Result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [], true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function describeRoute(string $Scope, array $RouteArray): array
    {
        /** @var Route $Route */
        $Route = $RouteArray['route'];
        $Methods = $Route->getMethods();

        $Entry = [
            'scope' => $Scope,
            'methods' => (0 === count($Methods)) ? ['GET'] : $Methods,
            'path' => self::path($Route),
            'description' => $RouteArray['description'],
            'tags' => $RouteArray['tags'] ?? ['default'],
        ];

        $PathParameters = [];
        foreach ($Route->getRequirements() as $Key => $Requirement) {
            $Parameter = ['required' => true];
            if (is_array($Requirement)) {
                $Parameter['type'] = $Requirement['type'] ?? 'string';
                if (isset($Requirement['description'])) {
                    $Parameter['description'] = $Requirement['description'];
                }
            } else {
                $Parameter['type'] = 'string';
                $Parameter['pattern'] = $Requirement;
            }
            $PathParameters[$Key] = $Parameter;
        }

        if (0 < count($PathParameters)) {
            $Entry['path_parameters'] = $PathParameters;
        }

        $QueryFields = self::describeFields($Route->getDefault('query') ?? []);
        if (0 < count($QueryFields)) {
            $Entry['query'] = $QueryFields;
        }

        $BodyFields = self::describeFields($Route->getDefault('Body') ?? []);
        if (0 < count($BodyFields)) {
            $Entry['body'] = $BodyFields;
        }

        return $Entry;
    }

    /**
     * Mirrors the semantics of RouteCollection::getQuerySet(): a field without
     * an explicit "required" key is required.
     *
     * @return array<string, mixed>
     */
    private static function describeFields(array $Definition): array
    {
        $Fields = [];
        foreach ($Definition as $Key => $Field) {
            if (!is_array($Field)) {
                continue;
            }

            $Described = [
                'type' => $Field['type'] ?? 'string',
                'required' => $Field['required'] ?? true,
            ];
            if (array_key_exists('default', $Field)) {
                $Described['default'] = $Field['default'];
            }
            if (isset($Field['description']) && '' !== $Field['description']) {
                $Described['description'] = $Field['description'];
            }
            if (isset($Field['fields']) && is_array($Field['fields'])) {
                $Described['fields'] = self::describeFields($Field['fields']);
            }

            $Fields[$Key] = $Described;
        }
        return $Fields;
    }

    private static function path(?Route $Route): string
    {
        if (null === $Route) {
            return '/' . RouteCollection::$preRoute;
        }
        return '/' . RouteCollection::$preRoute . $Route->getPath();
    }
}
