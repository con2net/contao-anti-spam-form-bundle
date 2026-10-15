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
        // Delegiert in der Capture-Phase am document: erfasst auch Widgets, die erst
        // später ins DOM kommen (z.B. Contao-AJAX-Formular ersetzt das <form> nach
        // einem Validierungsfehler). Capture, weil die Widget-Events nicht bubbeln müssen.
        document.addEventListener('statechange', onStateChange, true);
        document.addEventListener('serververified', onServerVerified, true);
        document.addEventListener('expired', onExpired, true);

        if (isDebugMode()) {
            console.log('ALTCHA handler initialized for ' + document.querySelectorAll('altcha-widget[data-widget-id]').length + ' widget(s)');
        }
    }

    /**
     * Liefert Widget, ID und Hidden Input zum Event, null wenn es kein Widget von uns ist
     */
    function resolve(ev) {
        const widget = ev.target instanceof Element ? ev.target.closest('altcha-widget[data-widget-id]') : null;

        if (!widget) {
            return null;
        }

        const widgetId = widget.getAttribute('data-widget-id');
        const hiddenInput = document.getElementById('ctrl_' + widgetId);

        if (!hiddenInput) {
            // Fehler IMMER loggen (auch ohne Debug)
            console.error('ALTCHA: Hidden input not found for widget ' + widgetId);
            return null;
        }

        return { widgetId: widgetId, hiddenInput: hiddenInput, debugMode: widget.hasAttribute('data-debug') };
    }

    // State-Change Event (Challenge-Status ändert sich)
    function onStateChange(ev) {
        const ctx = resolve(ev);

        if (!ctx) {
            return;
        }

        // Nur im Debug-Modus State-Changes loggen
        if (ctx.debugMode) {
            console.log('ALTCHA State [' + ctx.widgetId + ']:', ev.detail.state);
        }

        if (ev.detail.state === 'verified') {
            // Challenge erfolgreich gelöst
            ctx.hiddenInput.value = ev.detail.payload;

            if (ctx.debugMode) {
                console.log('ALTCHA verified [' + ctx.widgetId + ']', {
                    time: ev.detail.time || 'unknown'
                });
            }
        } else if (ev.detail.state === 'error') {
            // Fehler IMMER loggen (auch ohne Debug)
            console.error('ALTCHA error [' + ctx.widgetId + ']:', ev.detail.error);
            ctx.hiddenInput.value = '';
        } else if (ev.detail.state === 'verifying') {
            if (ctx.debugMode) {
                console.log('ALTCHA verifying [' + ctx.widgetId + ']...');
            }
        } else if (ev.detail.state === 'unverified') {
            // Challenge zurückgesetzt
            ctx.hiddenInput.value = '';
        }
    }

    // Server-Verified Event (optional, wenn Server-Validierung aktiviert)
    function onServerVerified(ev) {
        const ctx = resolve(ev);

        if (ctx && ctx.debugMode) {
            console.log('ALTCHA server-verified [' + ctx.widgetId + ']');
        }
    }

    // Expired Event (Challenge abgelaufen)
    function onExpired(ev) {
        const ctx = resolve(ev);

        if (!ctx) {
            return;
        }

        // Expired IMMER warnen (auch ohne Debug)
        console.warn('ALTCHA challenge expired [' + ctx.widgetId + ']');
        ctx.hiddenInput.value = '';
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