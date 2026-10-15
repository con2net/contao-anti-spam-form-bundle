<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/RegistrationSpamChecker.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Con2net\ContaoAntiSpamFormBundle\EventListener\RegistrationMarkupListener;
use Contao\ModuleModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Registration Spam Checker
 *
 * Prüft eine abgeschickte Mitglieder-Registrierung. Gleiche Reihenfolge wie der
 * Formular-Pfad (JS-Token -> Timestamp -> Honeypot -> Min-Zeit -> Max-Zeit), danach
 * ALTCHA. Feldwerte kommen direkt aus $_POST: Input::post() korrumpiert den
 * Base64-Payload von ALTCHA.
 *
 * Danach die Content-Analyse (lokal, wie im Formular-Pfad konfigurierbar).
 *
 * StopForumSpam läuft als letzte Prüfung: Ein Bot, der schon lokal auffällt, löst
 * keine externe Anfrage aus. Ist der Dienst nicht erreichbar, wird nicht blockiert.
 */
class RegistrationSpamChecker
{
    private AltchaService $altchaService;
    private IpBlacklistService $ipBlacklistService;
    private ContentAnalysisService $contentAnalysisService;
    private LoggingHelper $loggingHelper;

    public function __construct(
        AltchaService $altchaService,
        IpBlacklistService $ipBlacklistService,
        ContentAnalysisService $contentAnalysisService,
        LoggingHelper $loggingHelper
    )
    {
        $this->altchaService = $altchaService;
        $this->ipBlacklistService = $ipBlacklistService;
        $this->contentAnalysisService = $contentAnalysisService;
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

        if ($module->c2n_enable_content_analysis && null !== ($reason = $this->checkContent($module))) {
            return $reason;
        }

        if ($module->c2n_enable_ip_blacklist) {
            return $this->checkStopForumSpam($clientIp);
        }

        return null;
    }

    /**
     * Content-Analyse über die editierbaren Felder (ohne Passwort), Werte direkt
     * aus $_POST: Input::post() kodiert Sonderzeichen und verfälscht die Tests.
     */
    private function checkContent(ModuleModel $module): ?string
    {
        $data = [];

        foreach (StringUtil::deserialize($module->editable, true) as $field) {
            $value = $_POST[$field] ?? null;

            if ('password' !== $field && \is_string($value)) {
                $data[$field] = $value;
            }
        }

        try {
            $result = $this->contentAnalysisService->analyzeContent($data, $this->buildContentAnalysisConfig($module));
        } catch (\Throwable $e) {
            $this->loggingHelper->logError(
                sprintf('Anti-SPAM: Content analysis failed, registration let through: %s', $e->getMessage()),
                __METHOD__
            );

            return null;
        }

        if ($module->c2n_debug) {
            $this->loggingHelper->logInfo(
                sprintf(
                    'Anti-SPAM: Registration module %d - content analysis score %d / threshold %d%s',
                    $module->id,
                    $result['score'],
                    $result['threshold'],
                    $result['reasons'] ? ': ' . implode('; ', $result['reasons']) : ''
                ),
                __METHOD__
            );
        }

        if (!$result['is_spam']) {
            return null;
        }

        return sprintf(
            'Content analysis score %d >= threshold %d (%s)',
            $result['score'],
            $result['threshold'],
            implode('; ', $result['reasons'])
        );
    }

    /**
     * Gleiche Konfiguration wie AntiSpamFormListener::buildContentAnalysisConfig()
     */
    private function buildContentAnalysisConfig(ModuleModel $module): array
    {
        return [
            'spam_threshold' => (int) ($module->c2n_content_spam_threshold ?: 50),

            'check_urls' => (bool) $module->c2n_content_check_urls,
            'score_urls' => (int) ($module->c2n_content_score_urls ?: 50),
            'fields_urls' => $module->c2n_content_fields_urls,

            'check_special_chars' => (bool) $module->c2n_content_check_special_chars,
            'score_special_chars' => (int) ($module->c2n_content_score_special_chars ?: 40),
            'fields_special_chars' => $module->c2n_content_fields_special_chars,

            'check_tempmail' => (bool) $module->c2n_content_check_tempmail,
            'score_tempmail' => (int) ($module->c2n_content_score_tempmail ?: 30),
            'tempmail_domains' => $module->c2n_content_tempmail_domains ?: '',

            'check_short_message' => (bool) $module->c2n_content_check_short_message,
            'score_short_message' => (int) ($module->c2n_content_score_short_message ?: 25),
            'min_message_length' => (int) ($module->c2n_content_min_message_length ?: 10),
            'fields_short_message' => $module->c2n_content_fields_short_message,

            'check_repetitive' => (bool) $module->c2n_content_check_repetitive,
            'score_repetitive' => (int) ($module->c2n_content_score_repetitive ?: 20),
            'fields_repetitive' => $module->c2n_content_fields_repetitive,

            'check_uppercase' => (bool) $module->c2n_content_check_uppercase,
            'score_uppercase' => (int) ($module->c2n_content_score_uppercase ?: 15),
            'max_uppercase_ratio' => (int) ($module->c2n_content_max_uppercase_ratio ?: 60),
            'fields_uppercase' => $module->c2n_content_fields_uppercase,

            'check_keywords' => (bool) $module->c2n_content_check_keywords,
            'score_keywords' => (int) ($module->c2n_content_score_keywords ?: 10),
            'spam_keywords' => $module->c2n_content_spam_keywords ?: '',
            'fields_keywords' => $module->c2n_content_fields_keywords,
        ];
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
