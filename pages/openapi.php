<?php

use FriendsOfRedaxo\Api\OpenAPIConfig;
use FriendsOfRedaxo\Api\RouteCollection;

if ('1' === rex_request('load_config', 'string')) {
    $config = OpenAPIConfig::getByRoutes(RouteCollection::getRoutes());
    rex_response::cleanOutputBuffers();
    rex_response::sendContent(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json');
    exit;
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

        shortenSummaries();
    };

    // Endpoint-Beschreibungen können sehr lang werden (eigene und die von
    // anderen AddOns). In der Zeile bleibt eine kurze Fassung stehen, der
    // vollständige Text steckt im title-Attribut und erscheint beim Hover.
    // Die Spec selbst bleibt unverändert.
    const SUMMARY_MAX_LENGTH = 50;

    function shortenSummaries() {
        const target = document.getElementById('swagger-ui');
        if (!target) {
            return;
        }

        let running = false;
        const run = () => {
            document.querySelectorAll('#swagger-ui .opblock-summary-description').forEach((element) => {
                // Beim Neu-Rendern durch Swagger UI kann der lange Text zurückkommen,
                // deshalb ist das title-Attribut die Quelle, nicht ein Merker-Flag.
                const full = (element.getAttribute('title') || element.textContent).trim();
                if (full.length <= SUMMARY_MAX_LENGTH) {
                    return;
                }

                const short = full.slice(0, SUMMARY_MAX_LENGTH).trimEnd() + '\u2026';
                if (element.textContent === short) {
                    return;
                }

                element.setAttribute('title', full);
                element.classList.add('rex-api-summary-shortened');
                element.textContent = short;
            });
        };

        // Swagger UI rendert asynchron und baut Zeilen beim Filtern/Aufklappen neu.
        // Direkt statt über requestAnimationFrame, weil das in einem Tab im
        // Hintergrund nicht ausgeführt wird. Die eigenen Änderungen lösen einen
        // weiteren Durchlauf aus, der nichts mehr zu tun findet — keine Schleife.
        new MutationObserver(() => {
            if (running) {
                return;
            }
            running = true;
            try {
                run();
            } finally {
                running = false;
            }
        }).observe(target, {childList: true, subtree: true});

        run();
    }
</script>

<?php

?>
