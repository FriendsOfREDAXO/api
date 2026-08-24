(function ($) {
    function init() {
        var checkboxGroup = document.getElementById('yform-api-token-form-expires_active');
        var expiresGroup = document.getElementById('yform-api-token-form-expires_at');
        if (!checkboxGroup || !expiresGroup) {
            return;
        }

        var checkbox = checkboxGroup.querySelector('input[type="checkbox"]');
        if (!checkbox) {
            return;
        }

        // Der Zustand kommt aus dem Formular selbst: pages/token.php setzt den
        // Default der Checkbox (beim Bearbeiten aktiv, wenn ein Ablaufdatum
        // gespeichert ist). Das JS blendet nur ein und aus -- ohne es bleibt die
        // Seite bedienbar und speichert korrekt.
        function applyVisibility() {
            expiresGroup.style.display = checkbox.checked ? '' : 'none';
        }

        applyVisibility();

        if (checkbox._apiExpiryHandler) {
            checkbox.removeEventListener('change', checkbox._apiExpiryHandler);
        }

        checkbox._apiExpiryHandler = applyVisibility;
        checkbox.addEventListener('change', checkbox._apiExpiryHandler);
    }

    $(function () {
        init();
    });

    $(document).on('rex:ready', function () {
        init();
    });
})(jQuery);
