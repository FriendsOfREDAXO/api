<?php

namespace FriendsOfRedaxo\Api;

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
            'tags' => array_values($tags),
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

            $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])] = [
                'summary' => $RouteArray['description'],
                'security' => $security,
                'tags' => $RouteArray['tags'] ?? ['default'],
            ];

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
                if ('file' === ($Parameter['type'] ?? '')) {
                    $hasFileField = true;
                    $RequestBodyProperties[$Key] = [
                        'type' => 'string',
                        'format' => 'binary',
                        'description' => $Parameter['description'] ?? '',
                    ];
                } else {
                    $RequestBodyProperties[$Key] = [
                        'type' => $Parameter['type'],
                        'description' => $Parameter['description'] ?? '',
                        'required' => $Parameter['required'] ?? false,
                    ];
                }
                if ($Parameter['required'] ?? false) {
                    $RequestBodyRequired[] = $Key;
                }
            }

            // in URL
            foreach ($Route->getDefault('query') ?? [] as $Key => $Parameter) {
                if (isset($Parameter['fields']) && is_array($Parameter['fields'])) {
                    $Properties = [];
                    foreach ($Parameter['fields'] as $FieldKey => $Field) {
                        $Properties[$FieldKey] = [
                            'required' => $Field['required'] ?? false,
                            'default' => $Field['default'] ?? null,
                        ];
                    }

                    $Parameters[] = [
                        'name' => $Key,
                        'in' => 'query',
                        'description' => $Field['description'] ?? '',
                        'required' => $Field['required'] ?? false,
                        'schema' => [
                            'type' => 'object',
                            'properties' => $Properties,
                        ],
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
                        'default' => $Parameter['default'] ?? null,
                    ];
                }
            }

            if (0 < count($RequestBodyProperties)) {
                $contentType = $hasFileField ? 'multipart/form-data' : 'application/json';
                $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['requestBody'] = [
                    'required' => true,
                    'content' => [
                        $contentType => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $RequestBodyProperties,
                                'required' => $RequestBodyRequired,
                            ],
                        ],
                    ],
                ];
            }

            $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['parameters'] = $Parameters;
        }

        return $config;
    }
}
