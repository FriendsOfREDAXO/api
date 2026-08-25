(function ($) {
    // Nur bei dieser Auswahl wird das Datumsfeld gebraucht -- der Wert kommt aus
    // Token::ExpiryPresetCustom.
    var CustomPreset = 'custom';

    function init() {
        var presetGroup = document.getElementById('yform-api-token-form-expires_preset');
        var expiresGroup = document.getElementById('yform-api-token-form-expires_at');
        if (!presetGroup || !expiresGroup) {
            return;
        }

        var select = presetGroup.querySelector('select');
        if (!select) {
            return;
        }

        // Der Zustand kommt aus dem Formular selbst: pages/token.php setzt die
        // Vorauswahl (beim Bearbeiten „benutzerdefiniert", wenn ein Ablaufdatum
        // gespeichert ist). Das JS blendet nur ein und aus -- ohne es bleibt die
        // Seite bedienbar und speichert korrekt.
        function applyVisibility() {
            expiresGroup.style.display = CustomPreset === select.value ? '' : 'none';
        }

        applyVisibility();

        if (select._apiExpiryHandler) {
            select.removeEventListener('change', select._apiExpiryHandler);
        }

        select._apiExpiryHandler = applyVisibility;
        select.addEventListener('change', select._apiExpiryHandler);
    }

    $(function () {
        init();
    });

    $(document).on('rex:ready', function () {
        init();
    });
})(jQuery);
