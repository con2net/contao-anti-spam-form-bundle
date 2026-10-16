<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/PageListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\FormModel;
use Contao\ModuleModel;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Page Listener
 *
 * Bindet automatisch CSS und JavaScript ein wenn Anti-SPAM aktiviert ist
 * Lädt ALTCHA Assets lokal (DSGVO-konform, kein CDN!)
 */
class PageListener
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
     * Frontend-Modultypen der Mitglieder-Registrierung (Core und NotificationCenter 2.x)
     */
    private const REGISTRATION_MODULE_TYPES = ['registration', 'registrationNotificationCenter'];

    /**
     * Cache-Merker: höchstens ein Logeintrag pro Stunde bei fehlender Migration
     */
    private const MIGRATION_LOG_CACHE_KEY = 'c2n_antispam_registration_migration_logged';
    private const MIGRATION_LOG_INTERVAL = 3600;

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private ContaoFramework $framework;
    private LoggingHelper $loggingHelper;
    private CacheItemPoolInterface $cache;

    /**
     * Ergebnis der Registrierungs-Abfrage pro Request (null = noch nicht abgefragt)
     */
    private ?array $registrationFlags = null;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher,
        ContaoFramework $framework,
        LoggingHelper $loggingHelper,
        CacheItemPoolInterface $cache
    )
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->framework = $framework;
        $this->loggingHelper = $loggingHelper;
        $this->cache = $cache;
    }

    /**
     * Wird bei jedem Request aufgerufen
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $this->registrationFlags = null;

        // Nur im Frontend
        if (!$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        // Prüfen ob irgendein Formular oder Registrierungs-Modul Anti-SPAM aktiviert hat
        $hasAntiSpam = $this->hasAntiSpamForms() || $this->hasAntiSpamRegistrations();

        if ($hasAntiSpam) {
            // CSS einbinden (OHNE |static für bessere Kompatibilität)
            $GLOBALS['TL_CSS'][] = 'bundles/contaoantispamform/css/c2n-styles.css';

            // JavaScript einbinden
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoantispamform/js/timetoken.js';
        }

        // ===== NEU: ALTCHA Assets laden (DSGVO-konform, lokal!) =====
        $hasAltcha = $this->hasAltchaForms() || $this->hasAltchaRegistrations();

        if ($hasAltcha) {
            // ALTCHA Library als ES Module laden (Web Component)
            $GLOBALS['TL_HEAD'][] = '<script type="module" src="bundles/contaoantispamform/js/altcha.min.js' . $this->getAssetVersion('js/altcha.min.js') . '"></script>';

            // Alle vendorten ALTCHA-Sprachpakete laden (offizielle
            // Übersetzungen, registrieren sich selbst im Widget-eigenen
            // i18n-Store - siehe https://altcha.org/docs/widget-customization/).
            // Das Widget wählt daraus zur Laufzeit selbst die zu
            // document.documentElement.lang passende Sprache aus - kein
            // serverseitiges Sprach-Guessing nötig (und an dieser Stelle im
            // Request-Zyklus ist $request->getLocale() ohnehin noch nicht
            // zuverlässig die tatsächliche Seitensprache).
            foreach (self::ALTCHA_I18N_FILES as $file) {
                $GLOBALS['TL_HEAD'][] = '<script type="module" src="bundles/contaoantispamform/js/i18n/' . $file . $this->getAssetVersion('js/i18n/' . $file) . '"></script>';
            }

            // ALTCHA Event-Handler (normales Script)
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoantispamform/js/altcha-handler.js';

            // ALTCHA Styles (OHNE |static)
            $GLOBALS['TL_CSS'][] = 'bundles/contaoantispamform/css/altcha-styles.css';
        }
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

    /**
     * Prüft ob es Formulare mit aktiviertem Anti-SPAM gibt
     */
    private function hasAntiSpamForms(): bool
    {
        // Framework initialisieren BEVOR Models verwendet werden (kritisch für Contao 5.3!)
        if (!$this->framework->isInitialized()) {
            $this->framework->initialize();
        }

        // Fehlende tl_form-Spalten (Installation ohne Migration) dürfen keinen 500er auslösen
        try {
            $forms = FormModel::findBy(['c2n_enable_antispam=?'], [1]);
        } catch (DbalException $e) {
            $this->logMissingMigration($e);

            return false;
        }

        return $forms !== null && $forms->count() > 0;
    }

    /**
     * Prüft ob es Formulare mit aktiviertem ALTCHA gibt
     */
    private function hasAltchaForms(): bool
    {
        // Framework initialisieren (falls noch nicht geschehen)
        if (!$this->framework->isInitialized()) {
            $this->framework->initialize();
        }

        // Formulare mit Anti-SPAM UND ALTCHA aktiviert
        try {
            $forms = FormModel::findBy(
                ['c2n_enable_antispam=?', 'c2n_enable_altcha=?'],
                [1, 1]
            );
        } catch (DbalException $e) {
            $this->logMissingMigration($e);

            return false;
        }

        return $forms !== null && $forms->count() > 0;
    }

    /**
     * Prüft ob es Registrierungs-Module mit aktiviertem Anti-SPAM gibt
     */
    private function hasAntiSpamRegistrations(): bool
    {
        return $this->getRegistrationFlags()['antispam'];
    }

    /**
     * Prüft ob es Registrierungs-Module mit aktiviertem Anti-SPAM UND ALTCHA gibt
     */
    private function hasAltchaRegistrations(): bool
    {
        return $this->getRegistrationFlags()['altcha'];
    }

    /**
     * Fragt die geschützten Registrierungs-Module einmal pro Request ab.
     *
     * Läuft bei jedem Seitenaufruf: Fehlen die tl_module-Spalten (Update ohne
     * Datenbank-Migration), darf das keinen 500er auslösen. Dann wird eine
     * Warnung geloggt (höchstens einmal pro Stunde) und so getan, als gäbe es
     * keine geschützten Module.
     *
     * @return array{antispam: bool, altcha: bool}
     */
    private function getRegistrationFlags(): array
    {
        if (null !== $this->registrationFlags) {
            return $this->registrationFlags;
        }

        $this->registrationFlags = ['antispam' => false, 'altcha' => false];

        if (!$this->framework->isInitialized()) {
            $this->framework->initialize();
        }

        $types = self::REGISTRATION_MODULE_TYPES;

        try {
            $modules = ModuleModel::findBy(
                ['type IN (' . implode(',', array_fill(0, \count($types), '?')) . ')', 'c2n_enable_antispam=?'],
                [...$types, 1]
            );
        } catch (DbalException $e) {
            $this->logMissingMigration($e);

            return $this->registrationFlags;
        }

        if (null === $modules) {
            return $this->registrationFlags;
        }

        $this->registrationFlags['antispam'] = true;

        foreach ($modules as $module) {
            if ($module->c2n_enable_altcha) {
                $this->registrationFlags['altcha'] = true;
                break;
            }
        }

        return $this->registrationFlags;
    }

    /**
     * Loggt die fehlende Migration höchstens einmal pro Stunde, damit tl_log
     * auf Seiten mit Traffic nicht vollläuft.
     */
    private function logMissingMigration(DbalException $e): void
    {
        $item = $this->cache->getItem(self::MIGRATION_LOG_CACHE_KEY);

        if ($item->isHit()) {
            return;
        }

        $item->set(true);
        $item->expiresAfter(self::MIGRATION_LOG_INTERVAL);
        $this->cache->save($item);

        $this->loggingHelper->logError(
            'Anti-SPAM: Settings could not be read (database migration pending?): ' . $e->getMessage(),
            __METHOD__
        );
    }
}