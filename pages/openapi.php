<?php

use FriendsOfRedaxo\Api\OpenAPIConfig;
use FriendsOfRedaxo\Api\RouteCollection;

if ('1' === rex_request('load_config', 'string')) {
    $config = OpenAPIConfig::getByRoutes(RouteCollection::getRoutes());
    rex_response::sendContent(rex_string::yamlEncode($config), 'text/html'); // , 'application/yaml'
}

?>

<!-- Swagger UI Stylesheet -->
<div id="swagger-ui"></div>

<!-- Swagger UI Script -->
<script nonce="<?php echo rex_response::getNonce(); ?>">
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
