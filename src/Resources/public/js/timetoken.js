// File: src/Resources/public/js/timetoken.js

/**
 * Time-Token Generator für Formulare
 * Erkennung über FORM_SUBMIT hidden field – template-unabhängig.
 * AJAX-kompatibel via MutationObserver (Issue #2 – mp_forms Kompatibilität)
 */
(function() {
    'use strict';

    function initForm(form) {
        if (!form || form.dataset.c2nInitialized) {
            return;
        }
        form.dataset.c2nInitialized = '1';

        var tokenField = document.createElement('input');
        tokenField.type = 'hidden';
        tokenField.name = 'page_hash';
        tokenField.value = 'js_verified_' + Math.random().toString(36).substr(2, 12);
        form.insertBefore(tokenField, form.firstChild);

        var timestampField = document.createElement('input');
        timestampField.type = 'hidden';
        timestampField.name = 'c2n_client_time';
        timestampField.value = new Date().getTime().toString();
        form.insertBefore(timestampField, form.firstChild);
    }

    function initAllForms() {
        // Sucht nach dem Contao FORM_SUBMIT Hidden-Field – zuverlässiger als Form-ID
        document.querySelectorAll('input[name="FORM_SUBMIT"][value^="auto_form_"]')
            .forEach(function(input) {
                initForm(input.closest('form'));
            });
    }

    function init() {
        initAllForms();

        // MutationObserver für AJAX-injizierte Inhalte (mp_forms etc.)
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType !== 1) {
                            return;
                        }
                        // Neu hinzugefügtes Element direkt prüfen
                        if (node.name === 'FORM_SUBMIT' &&
                            node.value && node.value.indexOf('auto_form_') === 0) {
                            initForm(node.closest('form'));
                        }
                        // Oder enthält es das Hidden-Field?
                        if (node.querySelectorAll) {
                            node.querySelectorAll('input[name="FORM_SUBMIT"][value^="auto_form_"]')
                                .forEach(function(input) {
                                    initForm(input.closest('form'));
                                });
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();