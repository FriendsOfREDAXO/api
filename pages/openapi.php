<?php

use FriendsOfRedaxo\Api\OpenAPIConfig;
use FriendsOfRedaxo\Api\RouteCollection;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;

if ('1' === Request::request('load_config', 'string')) {
    $config = OpenAPIConfig::getByRoutes(RouteCollection::getRoutes());
    Response::cleanOutputBuffers();
    Response::sendContent((string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json');
    exit;
}

?>

<!-- Swagger UI Stylesheet -->
<div id="swagger-ui"></div>

<!-- Swagger UI Script -->
<script nonce="<?= Response::getNonce() ?>">
    // Configuration for Swagger UI
    window.onload = () => {
        SwaggerUIBundle({
            url: "index.php?page=api/openapi&load_config=1", // URL to the OpenAPI specification
            dom_id: "#swagger-ui",
        });
    };
</script>
