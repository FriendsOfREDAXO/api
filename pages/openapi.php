<?php

use FriendsOfREDAXO\API\RouteCollection;
use Symfony\Component\Routing\Route;

if ('1' === rex_request('load_config', 'string')) {
    rex_response::cleanOutputBuffers();

    $config = [
        'openapi' => '3.0.0',
        'info' => [
            'title' => $this->i18n('api_openapi_title'),
            'description' => $this->i18n('api_openapi_description'),
            'version' => '1.0.0',
        ],
        'servers' => [
            [
                'url' => '/' . RouteCollection::$preRoute,
            ],
        ],
    ];

    $config['components']['securitySchemes']['bearerAuth'] = [
        'type' => 'http',
        'scheme' => 'bearer',
        'bearerFormat' => 'JWT',
    ];

    foreach (RouteCollection::getRoutes() as $Scope => $RouteArray) {
        /** @var Route $Route */
        $Route = $RouteArray['route'];

        $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])] = [
            'summary' => $RouteArray['description'],
            'responses' => [
                '200' => [
                    'description' => 'successful operation',
                ],
                '400' => [
                    'description' => 'Unvalid request',
                ],
                '401' => [
                    'description' => 'Not authorized',
                ],
                '500' => [
                    'description' => 'Internal server error',
                ],
            ],
            'security' => [
                [
                    'bearerAuth' => [],
                ],
            ],
        ];

        $Parameters = [];

        // inPath
        foreach ($Route->getRequirements() ?? [] as $Key => $Parameter) {
            // TODO: Parameter im Pfad
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
        foreach ($Route->getDefault('Body') ?? [] as $Key => $Parameter) {
            $Parameters[] = [
                'name' => $Key,
                'in' => 'body',
                'description' => $Parameter['description'] ?? '',
                'required' => $Parameter['required'] ?? false,
                'schema' => [
                    'type' => $Parameter['type'],
                ],
            ];
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
                    // 'schema' => [
                    //     'type' => $Parameter->getType(),
                    // ],
                ];
            }
        }

        $config['paths'][$Route->getPath()][strtolower($Route->getMethods()[0])]['parameters'] = $Parameters;
    }

    rex_response::sendContent(rex_string::yamlEncode($config), 'text/html'); // , 'application/yaml'
}

?>

<!-- Swagger UI Stylesheet -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.18.3/swagger-ui.css">
<div id="swagger-ui"></div>

<!-- Swagger UI Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/4.18.3/swagger-ui-bundle.js" nonce="' . rex_response::getNonce() . '"></script>
<script nonce="' . rex_response::getNonce() . '">
    // Configuration for Swagger UI
    window.onload = () => {
        SwaggerUIBundle({
            url: "index.php?page=api/openapi&load_config=1", // URL to your OpenAPI specification file
            dom_id: "#swagger-ui",
        });
    };
</script>

<?php

?>
