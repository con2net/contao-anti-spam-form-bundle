<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationMarkupListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\AssetLoader;
use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Con2net\ContaoAntiSpamFormBundle\Widget\AltchaFormField;
use Con2net\ContaoAntiSpamFormBundle\Widget\HoneypotField;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\ModuleModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Registration Markup Listener
 *
 * Fügt Honeypot und (optional) ALTCHA in die Formulare der Mitglieder-Registrierung
 * ein und setzt den Server-Timestamp in der Session. Die Registrierung kennt keine
 * Formular-Generator-Hooks, daher läuft das über parseFrontendTemplate.
 *
 * Gefiltert wird ausschließlich über die formId (tl_registration_<Modul-ID>), nie über
 * den Template-Namen: member_default wird auch von "Persönliche Daten" (tl_member_<ID>)
 * verwendet.
 */
#[AsHook('parseFrontendTemplate')]
class RegistrationMarkupListener
{
    public const FORM_ID_PREFIX = 'tl_registration_';
    public const SESSION_KEY_PREFIX = 'c2n_registration_timestamp_';

    /**
     * Honeypot-Feldname. Kollidiert mit keinem tl_member-Feld (Präfix c2n_).
     */
    public const HONEYPOT_FIELD = 'c2n_homepage';
    public const ALTCHA_FIELD = 'c2n_altcha';

    private const MODULE_TYPES = ['registration', 'registrationNotificationCenter'];

    private RequestStack $requestStack;
    private LoggingHelper $loggingHelper;
    private AssetLoader $assetLoader;

    public function __construct(RequestStack $requestStack, LoggingHelper $loggingHelper, AssetLoader $assetLoader)
    {
        $this->requestStack = $requestStack;
        $this->loggingHelper = $loggingHelper;
        $this->assetLoader = $assetLoader;
    }

    public function __invoke(string $buffer, string $templateName, Template $template): string
    {
        $formId = (string) $template->formId;

        if (!str_starts_with($formId, self::FORM_ID_PREFIX)) {
            return $buffer;
        }

        $moduleId = (int) substr($formId, \strlen(self::FORM_ID_PREFIX));

        // findByPk liest SELECT *, scheitert also nicht an fehlenden Spalten (Migration
        // ausstehend). Neue Spalten sind dann null und gelten als "nicht geschützt".
        $module = ModuleModel::findByPk($moduleId);

        if (null === $module || !\in_array($module->type, self::MODULE_TYPES, true) || !$module->c2n_enable_antispam) {
            return $buffer;
        }

        $this->storeTimestamp($moduleId, (int) $module->c2n_max_submit_time, (bool) $module->c2n_debug);

        // CSS/JS nur auf Seiten, die ein geschütztes Registrierungs-Modul rendern
        // (die ALTCHA Assets lädt das Widget selbst)
        $this->assetLoader->addBaseAssets();

        $markup = (new HoneypotField([
            'id' => 'c2n_hp_' . $moduleId,
            'name' => self::HONEYPOT_FIELD,
        ]))->generate();

        if ($module->c2n_enable_altcha) {
            $markup .= (new AltchaFormField([
                'id' => 'c2n_altcha_' . $moduleId,
                'name' => self::ALTCHA_FIELD,
            ]))->generate();
        }

        return $this->insertMarkup($buffer, $markup, $moduleId);
    }

    /**
     * Setzt den Timestamp nur, wenn keiner existiert oder der vorhandene älter als die
     * Maximalzeit ist. Bei einem Fehler-Re-Render bleibt er erhalten, sonst würde ein
     * Mensch, der schnell korrigiert, an der Mindestzeit scheitern.
     */
    private function storeTimestamp(int $moduleId, int $maxSubmitTime, bool $debug): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            if ($debug) {
                $this->loggingHelper->logError(
                    sprintf('Anti-SPAM: No session available for registration module %d', $moduleId),
                    __METHOD__
                );
            }

            return;
        }

        $session = $request->getSession();
        $sessionKey = self::SESSION_KEY_PREFIX . $moduleId;
        $existing = (int) $session->get($sessionKey, 0);

        if ($existing > 0 && ($maxSubmitTime <= 0 || time() - $existing <= $maxSubmitTime)) {
            if ($debug) {
                $this->loggingHelper->logInfo(
                    sprintf('Anti-SPAM: Using existing SESSION timestamp (%d) for registration module %d', $existing, $moduleId),
                    __METHOD__
                );
            }

            return;
        }

        $timestamp = time();
        $session->set($sessionKey, $timestamp);

        if ($debug) {
            $this->loggingHelper->logInfo(
                sprintf('Anti-SPAM: Timestamp (%d) stored in SESSION for registration module %d', $timestamp, $moduleId),
                __METHOD__
            );
        }
    }

    /**
     * Fügt das Markup vor dem Absende-Widget ein (member_default und member_grouped,
     * mit und ohne div.fields). Fallback für eigene Templates: vor </form>.
     */
    private function insertMarkup(string $buffer, string $markup, int $moduleId): string
    {
        if (preg_match('/<div[^>]*class="[^"]*\bwidget-submit\b/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
            return substr_replace($buffer, $markup, $match[0][1], 0);
        }

        $pos = strripos($buffer, '</form>');

        if (false !== $pos) {
            return substr_replace($buffer, $markup, $pos, 0);
        }

        $this->loggingHelper->logError(
            sprintf('Anti-SPAM: No insertion point found in registration module %d template', $moduleId),
            __METHOD__
        );

        return $buffer;
    }
}
