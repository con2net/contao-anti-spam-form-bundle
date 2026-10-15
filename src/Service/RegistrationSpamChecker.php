<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/RegistrationSpamChecker.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Con2net\ContaoAntiSpamFormBundle\EventListener\RegistrationMarkupListener;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Registration Spam Checker
 *
 * Prüft eine abgeschickte Mitglieder-Registrierung. Gleiche Reihenfolge wie der
 * Formular-Pfad (JS-Token -> Timestamp -> Honeypot -> Min-Zeit -> Max-Zeit), danach
 * ALTCHA. Feldwerte kommen direkt aus $_POST: Input::post() korrumpiert den
 * Base64-Payload von ALTCHA.
 */
class RegistrationSpamChecker
{
    private AltchaService $altchaService;

    public function __construct(AltchaService $altchaService)
    {
        $this->altchaService = $altchaService;
    }

    /**
     * @return string|null Grund für den Spam-Verdacht, null wenn alles in Ordnung ist
     */
    public function check(ModuleModel $module, ?SessionInterface $session): ?string
    {
        if (null === $session) {
            return 'No session available';
        }

        $jsToken = $_POST['page_hash'] ?? null;

        if (!\is_string($jsToken) || !str_starts_with($jsToken, 'js_verified_')) {
            return 'JavaScript token missing or invalid';
        }

        $timestamp = (int) $session->get(RegistrationMarkupListener::SESSION_KEY_PREFIX . $module->id, 0);

        if ($timestamp <= 0) {
            return 'Session timestamp missing';
        }

        $honeypot = $_POST[RegistrationMarkupListener::HONEYPOT_FIELD] ?? '';

        if (!\is_string($honeypot) || '' !== trim($honeypot)) {
            return 'Honeypot filled';
        }

        $timeTaken = time() - $timestamp;
        $minSubmitTime = (int) $module->c2n_min_submit_time;
        $maxSubmitTime = (int) $module->c2n_max_submit_time;

        if ($timeTaken < $minSubmitTime) {
            return sprintf('Submitted too fast (%ds < %ds)', $timeTaken, $minSubmitTime);
        }

        if ($maxSubmitTime > 0 && $timeTaken > $maxSubmitTime) {
            return sprintf('Submitted too slow (%ds > %ds)', $timeTaken, $maxSubmitTime);
        }

        if ($module->c2n_enable_altcha) {
            $payload = $_POST[RegistrationMarkupListener::ALTCHA_FIELD] ?? '';

            if (!\is_string($payload) || '' === $payload) {
                return 'ALTCHA payload missing';
            }

            if (!$this->altchaService->validate($payload)) {
                return 'ALTCHA solution invalid';
            }
        }

        return null;
    }
}
