<?php
// File: src/Service/AssetLoader.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Bindet CSS und JavaScript ein, sobald ein geschütztes Formular oder Registrierungs-
 * Modul bzw. ein ALTCHA-Widget gerendert wird (nicht mehr global pro Request).
 * Lädt ALTCHA Assets lokal (DSGVO-konform, kein CDN!)
 *
 * Feste Schlüssel: Mehrere Formulare auf einer Seite rufen das mehrfach auf, und das
 * Twig-Layout (Contao 5.7) gibt TL_HEAD ohne array_unique() aus. Was beim Rendern eines
 * Fragments hinzukommt, landet trotzdem im <head> (fe_page ersetzt die Platzhalter erst
 * nach dem Rendern der Inhalte, das Twig-Layout liest end_of_head in einem defer-Block).
 */
class AssetLoader
{
    /**
     * Vendorte ALTCHA-Sprachpakete (offizielle Übersetzungen aus dem
     * altcha-Paket, dist/i18n/*.js). Der Schlüssel ist der Contao-Sprachcode,
     * der Wert der Dateiname im Bundle.
     */
    private const ALTCHA_I18N_FILES = [
        'de' => 'de.js',
        'en' => 'en.js',
        'es' => 'es-es.js',
        'fr' => 'fr-fr.js',
        'it' => 'it.js',
        'nl' => 'nl.js',
        'pl' => 'pl.js',
    ];

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * Assets für JS-Token, Honeypot und Zeitprüfung
     */
    public function addBaseAssets(): void
    {
        if (!$this->isFrontendRequest()) {
            return;
        }

        // CSS einbinden (OHNE |static für bessere Kompatibilität)
        $GLOBALS['TL_CSS']['c2n_styles'] = 'bundles/contaoantispamform/css/c2n-styles.css';

        // JavaScript einbinden
        $GLOBALS['TL_JAVASCRIPT']['c2n_timetoken'] = 'bundles/contaoantispamform/js/timetoken.js';
    }

    /**
     * Assets für das ALTCHA-Widget
     */
    public function addAltchaAssets(): void
    {
        if (!$this->isFrontendRequest()) {
            return;
        }

        // ALTCHA Library als ES Module laden (Web Component)
        $GLOBALS['TL_HEAD']['c2n_altcha'] = '<script type="module" src="bundles/contaoantispamform/js/altcha.min.js' . $this->getAssetVersion('js/altcha.min.js') . '"></script>';

        // Alle vendorten ALTCHA-Sprachpakete laden (offizielle
        // Übersetzungen, registrieren sich selbst im Widget-eigenen
        // i18n-Store - siehe https://altcha.org/docs/widget-customization/).
        // Das Widget wählt daraus zur Laufzeit selbst die zu
        // document.documentElement.lang passende Sprache aus - kein
        // serverseitiges Sprach-Guessing nötig.
        foreach (self::ALTCHA_I18N_FILES as $language => $file) {
            $GLOBALS['TL_HEAD']['c2n_altcha_i18n_' . $language] = '<script type="module" src="bundles/contaoantispamform/js/i18n/' . $file . $this->getAssetVersion('js/i18n/' . $file) . '"></script>';
        }

        // ALTCHA Event-Handler (normales Script)
        $GLOBALS['TL_JAVASCRIPT']['c2n_altcha_handler'] = 'bundles/contaoantispamform/js/altcha-handler.js';

        // ALTCHA Styles (OHNE |static)
        $GLOBALS['TL_CSS']['c2n_altcha_styles'] = 'bundles/contaoantispamform/css/altcha-styles.css';
    }

    /**
     * Nur im Frontend: Die Formularfeld-Vorschau im Backend ruft parse() ebenfalls auf
     */
    private function isFrontendRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request && $this->scopeMatcher->isFrontendRequest($request);
    }

    /**
     * Versionsparameter für Module-Scripts in TL_HEAD, gleiches Schema wie
     * Template::generateScriptTag() bei TL_JAVASCRIPT (dort automatisch).
     */
    private function getAssetVersion(string $path): string
    {
        $mtime = @filemtime(__DIR__ . '/../Resources/public/' . $path);

        return false === $mtime ? '' : '?v=' . substr(md5((string) $mtime), 0, 8);
    }
}
