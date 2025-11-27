// File: src/Resources/public/js/timetoken.js

/**
 * Time-Token Generator für Formulare
 */
(function() {
    'use strict';

    // Warte bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Alle Formulare finden
        var forms = document.querySelectorAll('form[id^="auto_form_"]');

        forms.forEach(function(form) {
            // Token erstellen
            var tokenField = document.createElement('input');
            tokenField.type = 'hidden';
            tokenField.name = 'page_hash';
            tokenField.value = 'js_verified_' + Math.random().toString(36).substr(2, 12);
            form.insertBefore(tokenField, form.firstChild);

            // Timestamp erstellen
            var timestampField = document.createElement('input');
            timestampField.type = 'hidden';
            timestampField.name = 'c2n_client_time';
            timestampField.value = new Date().getTime().toString();
            form.insertBefore(timestampField, form.firstChild);
        });
    }
})();