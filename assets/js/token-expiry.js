(function ($) {
    function hasDateValue(expiresGroup) {
        var textInput = expiresGroup.querySelector('input[type="text"]');
        if (textInput) {
            var value = String(textInput.value || '').trim();
            return value !== '' && value !== '0000-00-00 00:00:00';
        }

        var year = expiresGroup.querySelector('select[name*="[year]"]');
        var month = expiresGroup.querySelector('select[name*="[month]"]');
        var day = expiresGroup.querySelector('select[name*="[day]"]');

        if (!year || !month || !day) {
            return false;
        }

        return parseInt(year.value, 10) > 0 && parseInt(month.value, 10) > 0 && parseInt(day.value, 10) > 0;
    }

    function setGroupEnabled(expiresGroup, enabled) {
        var fields = expiresGroup.querySelectorAll('input, select, textarea, button');
        fields.forEach(function (field) {
            field.disabled = !enabled;
        });
        expiresGroup.classList.toggle('text-muted', !enabled);
    }

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

        function applyVisibility(isActive) {
            expiresGroup.style.display = isActive ? '' : 'none';
            setGroupEnabled(expiresGroup, isActive);
        }

        var isFirstInit = checkbox.dataset.apiExpiryInit !== '1';
        checkbox.dataset.apiExpiryInit = '1';

        if (isFirstInit) {
            checkbox.checked = hasDateValue(expiresGroup);
        }

        applyVisibility(checkbox.checked);

        if (checkbox._apiExpiryHandler) {
            checkbox.removeEventListener('change', checkbox._apiExpiryHandler);
            checkbox.removeEventListener('click', checkbox._apiExpiryHandler);
            checkboxGroup.removeEventListener('change', checkbox._apiExpiryHandler);
            checkboxGroup.removeEventListener('click', checkbox._apiExpiryHandler);
        }

        checkbox._apiExpiryHandler = function () {
            applyVisibility(checkbox.checked);
        };

        checkbox.addEventListener('change', checkbox._apiExpiryHandler);
        checkbox.addEventListener('click', checkbox._apiExpiryHandler);
        checkboxGroup.addEventListener('change', checkbox._apiExpiryHandler);
        checkboxGroup.addEventListener('click', checkbox._apiExpiryHandler);
    }

    $(function () {
        init();
    });

    $(document).on('rex:ready', function () {
        init();
    });
})(jQuery);
