<?php
// File: src/Service/AssetLoader.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Asset\Packages;
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

    /**
     * Asset-Paket, das Contao für den public-Ordner des Bundles registriert
     * (AddAssetsPackagesPass, 4.13 bis 5.7)
     */
    private const ASSET_PACKAGE = 'contao_anti_spam_form';

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private Packages $packages;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher, Packages $packages)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->packages = $packages;
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
        $GLOBALS['TL_HEAD']['c2n_altcha'] = '<script type="module" src="' . $this->getModuleScriptUrl('js/altcha.min.js') . '"></script>';

        // Alle vendorten ALTCHA-Sprachpakete laden (offizielle
        // Übersetzungen, registrieren sich selbst im Widget-eigenen
        // i18n-Store - siehe https://altcha.org/docs/widget-customization/).
        // Das Widget wählt daraus zur Laufzeit selbst die zu
        // document.documentElement.lang passende Sprache aus - kein
        // serverseitiges Sprach-Guessing nötig.
        foreach (self::ALTCHA_I18N_FILES as $language => $file) {
            $GLOBALS['TL_HEAD']['c2n_altcha_i18n_' . $language] = '<script type="module" src="' . $this->getModuleScriptUrl('js/i18n/' . $file) . '"></script>';
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
     * URL für Module-Scripts in TL_HEAD. Der Core gibt TL_HEAD unverändert aus, und das
     * Twig-Layout (Contao 5.7) hat kein <base href>: Ein relativer Pfad würde auf /a/b
     * nach /a/bundles/... aufgelöst. Das Asset-Paket des Bundles liefert den Pfad mit
     * Basis-Pfad (Installation im Unterverzeichnis) bzw. Static-Domain, wie beim Core.
     */
    private function getModuleScriptUrl(string $path): string
    {
        $url = $this->packages->getUrl($path, self::ASSET_PACKAGE);
        $version = $this->getAssetVersion($path);

        if ('' !== $version && str_contains($url, '?')) {
            $version = '&' . substr($version, 1);
        }

        return $url . $version;
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
