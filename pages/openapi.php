<?php

use FriendsOfREDAXO\API\RouteCollection;
use Redaxo\Addons\Api\OpenAPIConfig;

if ('1' === rex_request('load_config', 'string')) {
    $config = OpenAPIConfig::getByRoutes(RouteCollection::getRoutes());
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
