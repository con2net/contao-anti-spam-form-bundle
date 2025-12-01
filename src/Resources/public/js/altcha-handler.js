// File: src/Resources/public/js/altcha-handler.js

/**
 * ALTCHA Event Handler
 *
 * Verarbeitet Events vom ALTCHA Widget und schreibt Ergebnis in Hidden Input
 *
 * WICHTIG: Dieses Script wird NUR geladen wenn ALTCHA aktiv ist (PageListener prüft das)
 *
 * @author con2net webServices
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
        // Alle ALTCHA Widgets finden
        const widgets = document.querySelectorAll('altcha-widget[data-widget-id]');

        if (widgets.length === 0) {
            // Keine Widgets gefunden - nur im Debug-Modus warnen
            if (isDebugMode()) {
                console.warn('ALTCHA handler loaded but no widgets found');
            }
            return;
        }

          if (isDebugMode()) {
            console.log('ALTCHA handler initialized for ' + widgets.length + ' widget(s)');
        }

        // Event-Handler für jedes Widget einrichten
        widgets.forEach(function(widget) {
            const widgetId = widget.getAttribute('data-widget-id');
            const hiddenInput = document.getElementById('ctrl_' + widgetId);
            const debugMode = widget.hasAttribute('data-debug');

            if (!hiddenInput) {
                // Fehler IMMER loggen (auch ohne Debug)
                console.error('ALTCHA: Hidden input not found for widget ' + widgetId);
                return;
            }

            // State-Change Event (Challenge-Status ändert sich)
            widget.addEventListener('statechange', function(ev) {
                // Nur im Debug-Modus State-Changes loggen
                if (debugMode) {
                    console.log('ALTCHA State [' + widgetId + ']:', ev.detail.state);
                }

                if (ev.detail.state === 'verified') {
                    // Challenge erfolgreich gelöst
                    hiddenInput.value = ev.detail.payload;

                    if (debugMode) {
                        console.log('ALTCHA verified [' + widgetId + ']', {
                            time: ev.detail.time || 'unknown'
                        });
                    }
                } else if (ev.detail.state === 'error') {
                    // Fehler IMMER loggen (auch ohne Debug)
                    console.error('ALTCHA error [' + widgetId + ']:', ev.detail.error);
                    hiddenInput.value = '';
                } else if (ev.detail.state === 'verifying') {
                    if (debugMode) {
                        console.log('ALTCHA verifying [' + widgetId + ']...');
                    }
                } else if (ev.detail.state === 'unverified') {
                    // Challenge zurückgesetzt
                    hiddenInput.value = '';
                }
            });

            // Server-Verified Event (optional, wenn Server-Validierung aktiviert)
            widget.addEventListener('serververified', function(ev) {
                if (debugMode) {
                    console.log('ALTCHA server-verified [' + widgetId + ']');
                }
            });

            // Expired Event (Challenge abgelaufen)
            widget.addEventListener('expired', function(ev) {
                // Expired IMMER warnen (auch ohne Debug)
                console.warn('ALTCHA challenge expired [' + widgetId + ']');
                hiddenInput.value = '';
            });
        });
    }

    /**
     * Prüft ob Debug-Modus aktiv ist
     * @returns {boolean}
     */
    function isDebugMode() {
        // Prüfe ob irgendein Widget das data-debug Attribut hat
        const debugWidget = document.querySelector('altcha-widget[data-debug]');
        return debugWidget !== null;
    }
})();