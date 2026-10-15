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
 *
 * StopForumSpam läuft als letzte Prüfung: Ein Bot, der schon lokal auffällt, löst
 * keine externe Anfrage aus. Ist der Dienst nicht erreichbar, wird nicht blockiert.
 */
class RegistrationSpamChecker
{
    private AltchaService $altchaService;
    private IpBlacklistService $ipBlacklistService;
    private LoggingHelper $loggingHelper;

    public function __construct(
        AltchaService $altchaService,
        IpBlacklistService $ipBlacklistService,
        LoggingHelper $loggingHelper
    )
    {
        $this->altchaService = $altchaService;
        $this->ipBlacklistService = $ipBlacklistService;
        $this->loggingHelper = $loggingHelper;
    }

    /**
     * @param string|null $clientIp IP des Besuchers (vom Main-Request, nicht vom Fragment)
     *
     * @return string|null Grund für den Spam-Verdacht, null wenn alles in Ordnung ist
     */
    public function check(ModuleModel $module, ?SessionInterface $session, ?string $clientIp): ?string
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

        if ($module->c2n_enable_ip_blacklist) {
            return $this->checkStopForumSpam($clientIp);
        }

        return null;
    }

    /**
     * IP und E-Mail gegen StopForumSpam.com. Fail-open: Fehler blockieren nie.
     */
    private function checkStopForumSpam(?string $clientIp): ?string
    {
        try {
            if (null !== $clientIp && '' !== $clientIp && $this->ipBlacklistService->isIpBlacklisted($clientIp)) {
                return sprintf('IP %s is on StopForumSpam blacklist', $clientIp);
            }

            $email = $_POST['email'] ?? '';

            if (\is_string($email) && '' !== ($email = trim($email)) && $this->ipBlacklistService->isEmailBlacklisted($email)) {
                return sprintf('E-Mail %s is on StopForumSpam blacklist', $email);
            }
        } catch (\Throwable $e) {
            $this->loggingHelper->logError(
                sprintf('Anti-SPAM: StopForumSpam check failed, registration let through: %s', $e->getMessage()),
                __METHOD__
            );
        }

        return null;
    }
}
