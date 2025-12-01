// File: src/Resources/public/js/timetoken.js

/**
 * Time-Token Generator für Formulare
 *
 * Erstellt für ALLE Contao-Formulare:
 * - page_hash: JavaScript-Verifikations-Token (gegen Bots ohne JS)
 * - c2n_client_time: Client-Timestamp für Zeit-Validierung
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
        // ALLE Formulare finden (nicht nur auto_form_*)
        var forms = document.querySelectorAll('form');

        var processedCount = 0;

        forms.forEach(function(form) {
            // Prüfen ob es ein Contao-Formular ist
            // (hat FORM_SUBMIT oder REQUEST_TOKEN Field)
            var hasFormSubmit = form.querySelector('input[name="FORM_SUBMIT"]');
            var hasRequestToken = form.querySelector('input[name="REQUEST_TOKEN"]');

            if (!hasFormSubmit && !hasRequestToken) {
                // Kein Contao-Formular, überspringen
                return;
            }

            // Prüfen ob page_hash bereits existiert (Doppelung vermeiden)
            var existingToken = form.querySelector('input[name="page_hash"]');
            if (existingToken) {
                return;
            }

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

            processedCount++;
        });

        // Debug-Meldung nur im Debug-Modus (wenn gesetzt)
        if (processedCount > 0 && window.console && window.C2N_DEBUG) {
            console.log('✓ Time-Token initialized for ' + processedCount + ' form(s)');
        }
    }
})();