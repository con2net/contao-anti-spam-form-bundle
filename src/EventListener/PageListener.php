<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/PageListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\FormModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Page Listener
 *
 * Bindet automatisch CSS und JavaScript ein wenn Anti-SPAM aktiviert ist
 * NEU: Lädt ALTCHA Assets lokal (DSGVO-konform, kein CDN!)
 */
class PageListener
{
    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private ContaoFramework $framework;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher,
        ContaoFramework $framework
    )
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->framework = $framework;
    }

    /**
     * Wird bei jedem Request aufgerufen
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Nur im Frontend
        if (!$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        // Prüfen ob irgendein Formular Anti-SPAM aktiviert hat
        $hasAntiSpam = $this->hasAntiSpamForms();

        if ($hasAntiSpam) {
            // CSS einbinden
            $GLOBALS['TL_CSS'][] = 'bundles/contaoantispamform/css/c2n-styles.css|static';

            // JavaScript einbinden
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoantispamform/js/timetoken.js|static';
        }

        // ===== NEU: ALTCHA Assets laden (DSGVO-konform, lokal!) =====
        $hasAltcha = $this->hasAltchaForms();

        if ($hasAltcha) {
            // ALTCHA Library als ES Module laden (Web Component)
            $GLOBALS['TL_HEAD'][] = '<script type="module" src="bundles/contaoantispamform/js/altcha.min.js"></script>';

            // ALTCHA Event-Handler (normales Script)
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaoantispamform/js/altcha-handler.js|static';

            // ALTCHA Styles
            $GLOBALS['TL_CSS'][] = 'bundles/contaoantispamform/css/altcha-styles.css|static';
        }
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

        $forms = FormModel::findBy(['c2n_enable_antispam=?'], [1]);
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
        $forms = FormModel::findBy(
            ['c2n_enable_antispam=?', 'c2n_enable_altcha=?'],
            [1, 1]
        );

        return $forms !== null && $forms->count() > 0;
    }
}