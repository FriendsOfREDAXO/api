<?php

namespace FriendsOfRedaxo\Api;

use FriendsOfRedaxo\Api\Auth\BearerAuth;
use rex_i18n;
use Symfony\Component\Routing\Route;

use function count;
use function is_array;

class OpenAPIConfig
{
    public static function getByRoutes(array $Routes): array
    {
        $securitySchemes = [];
        $tags = [];
        foreach ($Routes as $Scope => $RouteArray) {
            foreach ($RouteArray['tags'] ?? [] as $Tag) {
                if (!isset($tags[$Tag])) {
                    // tags may come from other addons: without a language key, emit an
                    // empty description instead of a "[translate:…]" placeholder
                    $Key = 'api_openapi_tag_' . $Tag . '_description';
                    $tags[$Tag] = [
                        'name' => $Tag,
                        'description' => rex_i18n::hasMsg($Key) ? rex_i18n::msg($Key) : '',
                    ];
                }
            }

            if (!isset($RouteArray['authorization']) || null === $RouteArray['authorization']) {
                continue;
            }

            $Authorization = $RouteArray['authorization']->getOpenApiConfig();
            if (null === $Authorization) {
                continue;
            }
            if (!isset($securitySchemes[$Authorization['securityScheme']])) {
                $securitySchemes[$Authorization['securityScheme']] = $Authorization;
            }
        }

        $config = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => rex_i18n::msg('api_openapi_title'),
                'description' => rex_i18n::msg('api_openapi_description'),
                'version' => '1.0.0',
            ],
            // array_values: OpenAPI requires "tags" to be an array, a keyed map
            // makes the document invalid for typed parsers
            'tags' => self::sortedTags($tags),
            'servers' => [
                [
                    'url' => '/' . RouteCollection::$preRoute,
                ],
            ],
            'components' => [
                'securitySchemes' => $securitySchemes,
            ],
        ];

        foreach ($Routes as $Scope => $RouteArray) {
            /** @var Route $Route */
            $Route = $RouteArray['route'];
            $security = [];
            if (isset($RouteArray['authorization']) && null !== $RouteArray['authorization']) {
                $Authorization = $RouteArray['authorization']->getOpenApiConfig();
                if (null !== $Authorization) {
                    $security[] = [
                        $Authorization['securityScheme'] => [],
                    ];
                }
            }

            $operation = [
                'summary' => $RouteArray['description'],
                'security' => $security,
                'tags' => $RouteArray['tags'] ?? ['default'],
            ];

            // Den geforderten Scope sichtbar machen. Er darf nicht ins Scope-Array des
            // security-Eintrags: OpenAPI 3.0 erlaubt das nur bei oauth2 und openIdConnect,
            // bei `type: http` muss es leer bleiben. Deshalb als eigene Angabe -- lesbar
            // in der Beschreibung, maschinenlesbar als x-required-scope.
            $requiredScope = self::requiredScope($RouteArray, (string) $Scope);
            if (null !== $requiredScope) {
                $operation['x-required-scope'] = $requiredScope;
                $operation['description'] = '**Scope:** `' . $requiredScope . '`';
            }

            $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])] = $operation;

            $Parameters = [];
            $RequestBodyProperties = [];
            $RequestBodyRequired = [];
            $Responses = [
                '200' => [
                    'description' => 'successful operation',
                ],
                '400' => [
                    'description' => 'Invalid request',
                ],
                '401' => [
                    'description' => 'Not authorized',
                ],
                '404' => [
                    'description' => 'Not found',
                ],
                '409' => [
                    'description' => 'Conflict',
                ],
                '500' => [
                    'description' => 'Internal server error',
                ],
            ];

            // Responses
            if (is_array($RouteArray['responses'])) {
                foreach ($RouteArray['responses'] as $StatusCode => $Response) {
                    $Responses[$StatusCode] = $Response;
                }
            }

            $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['responses'] = $Responses;

            // inPath
            foreach ($Route->getRequirements() ?? [] as $Key => $Parameter) {
                // 'id' => '\d+',
                $Parameters[] = [
                    'name' => $Key,
                    'in' => 'path',
                    'description' => $Parameter['description'] ?? '',
                    'required' => true,
                    'schema' => [
                        'type' => $Parameter['type'] ?? 'string',
                    ],
                ];
            }

            // in Body
            $hasFileField = false;
            foreach ($Route->getDefault('Body') ?? [] as $Key => $Parameter) {
                $Schema = self::getSchema($Parameter, true);

                if ('file' === ($Parameter['type'] ?? '')) {
                    $hasFileField = true;
                    $Schema['format'] = 'binary';
                }

                $RequestBodyProperties[$Key] = $Schema;

                // required gehört als Liste auf Objektebene, nicht in die Property
                if ($Parameter['required'] ?? false) {
                    $RequestBodyRequired[] = $Key;
                }
            }

            // in URL
            foreach ($Route->getDefault('query') ?? [] as $Key => $Parameter) {
                if (isset($Parameter['fields']) && is_array($Parameter['fields'])) {
                    $Properties = [];
                    $RequiredProperties = [];
                    foreach ($Parameter['fields'] as $FieldKey => $Field) {
                        // description im Property: dort gibt es keine Parameter-Ebene
                        $Properties[$FieldKey] = self::getSchema($Field, true);
                        if ($Field['required'] ?? false) {
                            $RequiredProperties[] = $FieldKey;
                        }
                    }

                    $Schema = [
                        'type' => 'object',
                        'properties' => $Properties,
                    ];
                    if (0 < count($RequiredProperties)) {
                        $Schema['required'] = $RequiredProperties;
                    }

                    $Parameters[] = [
                        'name' => $Key,
                        'in' => 'query',
                        'description' => $Parameter['description'] ?? '',
                        'required' => $Parameter['required'] ?? false,
                        'schema' => $Schema,
                        'style' => 'deepObject',
                        'explode' => true,
                    ];
                }

                if (!isset($Parameter['fields'])) {
                    $Parameters[] = [
                        'name' => $Key,
                        'in' => 'query',
                        'description' => $Parameter['description'] ?? '',
                        'required' => $Parameter['required'] ?? false,
                        'schema' => self::getSchema($Parameter),

                    ];
                }
            }

            if (0 < count($RequestBodyProperties)) {
                $contentType = $hasFileField ? 'multipart/form-data' : 'application/json';
                $BodySchema = [
                    'type' => 'object',
                    'properties' => $RequestBodyProperties,
                ];
                // ein leeres required-Array ist kein gültiges JSON Schema
                if (0 < count($RequestBodyRequired)) {
                    $BodySchema['required'] = $RequestBodyRequired;
                }

                $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        $contentType => [
                            'schema' => $BodySchema,
                        ],
                    ],
                ];
            }

            $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['parameters'] = $Parameters;
        }

        return $config;
    }

    /**
     * Builds the OpenAPI schema object for a single parameter definition.
     *
     * OpenAPI requires a parameter to carry its type and default inside "schema";
     * both at parameter level would make the document invalid.
     *
     * @param array<string, mixed> $Definition
     * @param bool $withDescription true for object properties, which have no
     *                              parameter level of their own to carry it
     * @return array<string, mixed>
     */
    private static function getSchema(array $Definition, bool $withDescription = false): array
    {
        $Schema = [
            'type' => self::getSchemaType($Definition['type'] ?? 'string'),
        ];

        // a null default is left out: it would contradict the declared type
        if (isset($Definition['default'])) {
            $Schema['default'] = $Definition['default'];
        }
        if ($withDescription && isset($Definition['description']) && '' !== $Definition['description']) {
            $Schema['description'] = $Definition['description'];
        }

        return $Schema;
    }

    /**
     * Maps the addon's parameter types to the type names OpenAPI knows.
     */
    private static function getSchemaType(string $Type): string
    {
        return match ($Type) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }

    /**
     * Der Scope, der fuer diese Route vergeben sein muss, oder null wenn keiner
     * gebraucht wird (Discovery-Routen) oder die Autorisierung nicht ueber Scopes
     * laeuft (Backend-Session).
     *
     * @param array<string, mixed> $RouteArray
     */
    private static function requiredScope(array $RouteArray, string $routeName): ?string
    {
        $auth = $RouteArray['authorization'] ?? null;
        if (!$auth instanceof BearerAuth) {
            return null;
        }
        if (!$auth->requiresScope()) {
            return null;
        }
        return $auth->getScope($routeName);
    }

    /**
     * Tags alphabetisch, damit die Reihenfolge der Accordions in Swagger UI nicht
     * von der Registrierungsreihenfolge der RoutePackages abhaengt.
     *
     * @param array<string, array<string, string>> $tags
     * @return list<array<string, string>>
     */
    private static function sortedTags(array $tags): array
    {
        ksort($tags);
        return array_values($tags);
    }
}
