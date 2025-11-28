<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/AntiSpamFormListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\IpBlacklistService;
use Con2net\ContaoAntiSpamFormBundle\Service\ContentAnalysisService;
use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FormModel;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Anti-SPAM Listener für Contao Formulare
 *
 * Multi-Layer SPAM-Schutz:
 * 1. JavaScript-Token Check (Bots ohne JS)
 * 2. IP-Blacklist Check (StopForumSpam.com)
 * 3. E-Mail-Blacklist Check (StopForumSpam.com)
 * 4. Content-Analyse (Pattern-basiert, lokal, feldbasiert!)
 * 5. Honeypot-Felder
 * 6. Zeit-basierte Validierung (Min/Max)
 */
#[AsHook('prepareFormData', priority: 100)]
class AntiSpamFormListener
{
    private IpBlacklistService $ipBlacklistService;
    private ContentAnalysisService $contentAnalysisService;
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private LoggingHelper $loggingHelper;

    public function __construct(
        IpBlacklistService $ipBlacklistService,
        ContentAnalysisService $contentAnalysisService,
        LoggerInterface $logger,
        RequestStack $requestStack,
        LoggingHelper $loggingHelper
    ) {
        $this->ipBlacklistService = $ipBlacklistService;
        $this->contentAnalysisService = $contentAnalysisService;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->loggingHelper = $loggingHelper;
    }

    /**
     * Hook: prepareFormData
     * Wird VOR processFormData aufgerufen
     */
    public function __invoke(array &$submittedData, array $labels, array $fields, Form $form): void
    {
        $formId = (int)$form->id;

        // Anti-SPAM Konfiguration aus dem Formular laden
        $formModel = FormModel::findByPk($formId);

        if (!$formModel) {
            return;
        }

        // Prüfen ob Anti-SPAM aktiviert ist
        if (!$formModel->c2n_enable_antispam) {
            return;
        }

        $debugMode = (bool)$formModel->c2n_debug;
        $spamMarker = html_entity_decode(
            $formModel->c2n_spam_prefix ?: '*** SPAM *** ',
            ENT_QUOTES,
            'UTF-8'
        );
        $minSubmitTime = (int)($formModel->c2n_min_submit_time ?: 10);
        $maxSubmitTime = (int)($formModel->c2n_max_submit_time ?: 0);
        $blockSpam = (bool)$formModel->c2n_block_spam;
        $enableIpBlacklist = (bool)($formModel->c2n_enable_ip_blacklist ?? false);
        $enableContentAnalysis = (bool)($formModel->c2n_enable_content_analysis ?? false);

        if ($debugMode) {
            $this->loggingHelper->logInfo(
                sprintf('Anti-SPAM check started for form %d', $formId),
                __METHOD__
            );

            $this->logger->debug('Anti-SPAM check started', [
                'form_id' => $formId,
                'ip_blacklist_enabled' => $enableIpBlacklist,
                'content_analysis_enabled' => $enableContentAnalysis
            ]);
        }

        // Alle Honeypot-Felder suchen (wird für SPAM-Marker benötigt)
        $honeypotFields = $this->findAllHoneypotFields($fields);

        if (empty($honeypotFields)) {
            if ($debugMode) {
                $this->loggingHelper->logInfo('Anti-SPAM enabled but NO Honeypot fields found!', __METHOD__);
            }
            // Weiter prüfen ohne Honeypot (andere Checks funktionieren trotzdem)
        } else {
            if ($debugMode) {
                $this->loggingHelper->logInfo(
                    sprintf('Found %d honeypot field(s): %s', count($honeypotFields), implode(', ', $honeypotFields)),
                    __METHOD__
                );
            }
        }

        // ===== Session über Request holen (Contao 4.13 + 5.3 kompatibel) =====
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            // Kein Request oder keine Session verfügbar
            if ($debugMode) {
                $this->loggingHelper->logError(
                    sprintf('No session available for form %d - blocking submit', $formId),
                    __METHOD__
                );
            }

            // SPAM markieren wenn keine Session (Bot-Verhalten)
            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($formId);
            }

            return;
        }

        $session = $request->getSession();
        $sessionKey = 'c2n_form_timestamp_' . $formId;
        // ====================================================================

        // ========== PRÜFUNG 0: JAVASCRIPT-TOKEN CHECK (ZUERST!) ==========
        $jsToken = $_POST['page_hash'] ?? null;

        if (!$jsToken || !str_starts_with($jsToken, 'js_verified_')) {
            $this->loggingHelper->logError(
                'SPAM DETECTED: No valid JavaScript token! Bot without JS execution.',
                __METHOD__
            );

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($formId);
            }

            $session->remove($sessionKey);
            return;
        }

        if ($debugMode) {
            $this->loggingHelper->logInfo(sprintf('JS-Token validated: %s', $jsToken), __METHOD__);
        }


        // ========== PRÜFUNG 0a: IP-BLACKLIST CHECK ==========
        if ($enableIpBlacklist) {
            $userIp = $this->getUserIp();

            if ($debugMode) {
                $this->loggingHelper->logInfo(sprintf('Checking IP: %s', $userIp), __METHOD__);
            }

            try {
                $isBlacklisted = $this->ipBlacklistService->isIpBlacklisted($userIp);

                if ($isBlacklisted) {
                    $this->loggingHelper->logError(
                        sprintf('SPAM DETECTED: IP %s is on blacklist!', $userIp),
                        __METHOD__
                    );

                    $honeypotFields = $this->findAllHoneypotFields($fields);
                    $this->markAsSpam(
                        $submittedData,
                        !empty($honeypotFields) ? $honeypotFields[0] : null,
                        $spamMarker,
                        $formId
                    );

                    if ($blockSpam) {
                        $this->blockSpam($formId);
                    }

                    return;
                }

                if ($debugMode) {
                    $this->loggingHelper->logInfo(
                        sprintf('IP %s is clean (not on blacklist)', $userIp),
                        __METHOD__
                    );
                }

            } catch (\Exception $e) {
                $this->loggingHelper->logError(
                    sprintf('IP Blacklist check failed: %s', $e->getMessage()),
                    __METHOD__
                );
            }
        }

        // ========== PRÜFUNG 0b: E-MAIL-BLACKLIST CHECK ==========
        if ($enableIpBlacklist) {
            $email = $this->extractEmail($submittedData);

            if ($email) {
                if ($debugMode) {
                    $this->loggingHelper->logInfo(sprintf('Checking E-Mail: %s', $email), __METHOD__);
                }

                try {
                    $isBlacklisted = $this->ipBlacklistService->isEmailBlacklisted($email);

                    if ($isBlacklisted) {
                        $this->loggingHelper->logError(
                            sprintf('SPAM DETECTED: E-Mail %s is on blacklist!', $email),
                            __METHOD__
                        );

                        $honeypotFields = $this->findAllHoneypotFields($fields);
                        $this->markAsSpam(
                            $submittedData,
                            !empty($honeypotFields) ? $honeypotFields[0] : null,
                            $spamMarker,
                            $formId
                        );

                        if ($blockSpam) {
                            $this->blockSpam($formId);
                        }

                        return;
                    }

                    if ($debugMode) {
                        $this->loggingHelper->logInfo(
                            sprintf('E-Mail %s is clean (not on blacklist)', $email),
                            __METHOD__
                        );
                    }

                } catch (\Exception $e) {
                    $this->loggingHelper->logError(
                        sprintf('E-Mail Blacklist check failed: %s', $e->getMessage()),
                        __METHOD__
                    );
                }
            }
        }

        // ========== PRÜFUNG 0c: CONTENT-ANALYSE ==========
        if ($enableContentAnalysis) {
            if ($debugMode) {
                $this->loggingHelper->logInfo('Starting Content Analysis', __METHOD__);
            }

            try {
                // Config aus FormModel laden
                $contentConfig = [
                    'spam_threshold' => (int)($formModel->c2n_content_spam_threshold ?: 50),

                    // URLs im Text
                    'check_urls' => (bool)$formModel->c2n_content_check_urls,
                    'score_urls' => (int)($formModel->c2n_content_score_urls ?: 50),
                    'fields_urls' => $formModel->c2n_content_fields_urls,

                    // Nur Sonderzeichen
                    'check_special_chars' => (bool)$formModel->c2n_content_check_special_chars,
                    'score_special_chars' => (int)($formModel->c2n_content_score_special_chars ?: 40),
                    'fields_special_chars' => $formModel->c2n_content_fields_special_chars,

                    // Tempmail-Adressen
                    'check_tempmail' => (bool)$formModel->c2n_content_check_tempmail,
                    'score_tempmail' => (int)($formModel->c2n_content_score_tempmail ?: 30),
                    'tempmail_domains' => $formModel->c2n_content_tempmail_domains ?: '',

                    // Nachricht zu kurz
                    'check_short_message' => (bool)$formModel->c2n_content_check_short_message,
                    'score_short_message' => (int)($formModel->c2n_content_score_short_message ?: 25),
                    'min_message_length' => (int)($formModel->c2n_content_min_message_length ?: 10),
                    'fields_short_message' => $formModel->c2n_content_fields_short_message,

                    // Repetitive Zeichen
                    'check_repetitive' => (bool)$formModel->c2n_content_check_repetitive,
                    'score_repetitive' => (int)($formModel->c2n_content_score_repetitive ?: 20),
                    'fields_repetitive' => $formModel->c2n_content_fields_repetitive,

                    // Großbuchstaben
                    'check_uppercase' => (bool)$formModel->c2n_content_check_uppercase,
                    'score_uppercase' => (int)($formModel->c2n_content_score_uppercase ?: 15),
                    'max_uppercase_ratio' => (int)($formModel->c2n_content_max_uppercase_ratio ?: 60),
                    'fields_uppercase' => $formModel->c2n_content_fields_uppercase,  // NEU!

                    // SPAM-Keywords
                    'check_keywords' => (bool)$formModel->c2n_content_check_keywords,
                    'score_keywords' => (int)($formModel->c2n_content_score_keywords ?: 10),
                    'spam_keywords' => $formModel->c2n_content_spam_keywords ?: '',
                    'fields_keywords' => $formModel->c2n_content_fields_keywords
                ];

                // Debug: Zeige aktivierte Tests
                if ($debugMode) {
                    $activeTests = [];
                    if ($contentConfig['check_urls']) $activeTests[] = 'URLs';
                    if ($contentConfig['check_special_chars']) $activeTests[] = 'Special Chars';
                    if ($contentConfig['check_tempmail']) $activeTests[] = 'Tempmail';
                    if ($contentConfig['check_short_message']) $activeTests[] = 'Short Message';
                    if ($contentConfig['check_repetitive']) $activeTests[] = 'Repetitive';
                    if ($contentConfig['check_uppercase']) $activeTests[] = 'Uppercase';
                    if ($contentConfig['check_keywords']) $activeTests[] = 'Keywords';

                    $this->loggingHelper->logInfo(
                        sprintf('Active tests: %s', !empty($activeTests) ? implode(', ', $activeTests) : 'NONE'),
                        __METHOD__
                    );
                }

                // Content-Analyse durchführen
                $result = $this->contentAnalysisService->analyzeContent(
                    $submittedData,
                    $contentConfig
                );

                if ($debugMode) {
                    $this->loggingHelper->logInfo(
                        sprintf('Content Analysis Score: %d / %d (Threshold: %d)',
                            $result['score'],
                            $result['threshold'],
                            $result['threshold']
                        ),
                        __METHOD__
                    );

                    if (!empty($result['reasons'])) {
                        foreach ($result['reasons'] as $reason) {
                            if ($result['is_spam']) {
                                $this->loggingHelper->logError('  └─ ' . $reason, __METHOD__);
                            } else {
                                $this->loggingHelper->logInfo('  └─ ' . $reason, __METHOD__);
                            }
                        }
                    } else {
                        $this->loggingHelper->logInfo('  └─ No issues found', __METHOD__);
                    }
                }

                if ($result['is_spam']) {
                    $this->loggingHelper->logError(
                        sprintf('SPAM DETECTED by Content Analysis! Score: %d (Threshold: %d)',
                            $result['score'],
                            $result['threshold']
                        ),
                        __METHOD__
                    );

                    $this->logger->warning('Content Analysis detected SPAM', [
                        'form_id' => $formId,
                        'score' => $result['score'],
                        'threshold' => $result['threshold'],
                        'reasons' => $result['reasons']
                    ]);

                    $honeypotFields = $this->findAllHoneypotFields($fields);
                    $this->markAsSpam(
                        $submittedData,
                        !empty($honeypotFields) ? $honeypotFields[0] : null,
                        $spamMarker,
                        $formId
                    );

                    if ($blockSpam) {
                        $this->blockSpam($formId);
                    }

                    return;
                }

                if ($debugMode) {
                    $this->loggingHelper->logInfo(
                        sprintf('Content Analysis passed (Score: %d < Threshold: %d)',
                            $result['score'],
                            $result['threshold']
                        ),
                        __METHOD__
                    );
                }

            } catch (\Exception $e) {
                $this->loggingHelper->logError(
                    sprintf('Content Analysis failed: %s', $e->getMessage()),
                    __METHOD__
                );

                $this->logger->error('Content Analysis failed', [
                    'error' => $e->getMessage(),
                    'form_id' => $formId
                ]);
            }
        }

        // Timestamp aus SESSION holen
        $formLoadTimestamp = $session->get($sessionKey);

        if (!$formLoadTimestamp) {
            if ($debugMode) {
                $this->loggingHelper->logError(
                    sprintf('No timestamp found in session for form %d', $formId),
                    __METHOD__
                );
            }

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($formId);
            }

            return;
        }

        // Zeit berechnen
        $currentTime = time();
        $timeTaken = $currentTime - $formLoadTimestamp;

        if ($debugMode) {
            $this->loggingHelper->logInfo(
                sprintf('TIME: Form loaded at %d, submitted at %d, took %d seconds (min: %d, max: %d)',
                    $formLoadTimestamp,
                    $currentTime,
                    $timeTaken,
                    $minSubmitTime,
                    $maxSubmitTime
                ),
                __METHOD__
            );
        }

        // ===== PRÜFUNG 1: HONEYPOT-CHECK =====
        foreach ($honeypotFields as $honeypotField) {
            if (isset($submittedData[$honeypotField])) {
                $honeypotValue = trim($submittedData[$honeypotField]);
                $spamMarkerTrimmed = trim($spamMarker);

                if ($honeypotValue === $spamMarkerTrimmed || $honeypotValue === '*** SPAM ***') {
                    $this->loggingHelper->logError(
                        sprintf('SPAM DETECTED: Honeypot "%s" was filled by Ghost!', $honeypotField),
                        __METHOD__
                    );
                    $this->markAsSpam($submittedData, $honeypotField, $spamMarker, $formId);

                    if ($blockSpam) {
                        $this->blockSpam($formId);
                    } else {
                        $this->loggingHelper->logInfo('SPAM MARKED: E-Mail will be sent with SPAM marker', __METHOD__);
                    }

                    $session->remove($sessionKey);
                    return;
                }
            }
        }

        // ===== PRÜFUNG 2: MIN-ZEIT CHECK =====
        if ($timeTaken < $minSubmitTime) {
            $this->loggingHelper->logError(
                sprintf('SPAM DETECTED (too fast): Form submitted in %d seconds (min: %d)', $timeTaken, $minSubmitTime),
                __METHOD__
            );

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($formId);
            }

            $session->remove($sessionKey);
            return;
        }

        // ===== PRÜFUNG 3: MAX-ZEIT CHECK =====
        if ($maxSubmitTime > 0 && $timeTaken > $maxSubmitTime) {
            $this->loggingHelper->logError(
                sprintf('SPAM DETECTED (too slow): Form submitted in %d seconds (max: %d)', $timeTaken, $maxSubmitTime),
                __METHOD__
            );

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($formId);
            }

            $session->remove($sessionKey);
            return;
        }

        // ===== ALLES OK =====
        $session->remove($sessionKey);

        if (!isset($submittedData['spam_marker'])) {
            $submittedData['spam_marker'] = '';
        }

        // TECHNISCHE FELDER AUS RAW_DATA ENTFERNEN
        // Diese Felder sollen NICHT in ##raw_data## erscheinen!
        $this->removeInternalFields($submittedData, $fields);

        if ($debugMode) {
            $this->loggingHelper->logInfo(
                sprintf('Anti-SPAM check passed! Time taken: %d seconds', $timeTaken),
                __METHOD__
            );
        }
    }

    /**
     * Ermittelt die User-IP
     */
    private function getUserIp(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return '127.0.0.1';
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            $value = $request->server->get($header);

            if (!empty($value)) {
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $value);
                    $value = trim($ips[0]);
                }

                if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $value;
                }
            }
        }

        return $request->server->get('REMOTE_ADDR', '127.0.0.1');
    }

    /**
     * Extrahiert E-Mail-Adresse aus Formulardaten
     */
    private function extractEmail(array $data): ?string
    {
        $emailFields = ['email', 'e-mail', 'e_mail', 'mail', 'Email', 'E-Mail'];

        foreach ($emailFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $value = $data[$field];

                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Findet ALLE Honeypot-Felder im Formular
     */
    private function findAllHoneypotFields(array $fields): array
    {
        $honeypotFields = [];

        foreach ($fields as $field) {
            if (in_array($field->type, ['c2n_honeypot', 'c2n_honeypot_textarea', 'c2n_honeypot_checkbox'])) {
                $honeypotFields[] = $field->name;
            }
        }

        return $honeypotFields;
    }

    /**
     * Markiert Formular als SPAM
     */
    private function markAsSpam(array &$submittedData, ?string $honeypotField, string $spamMarker, int $formId): void
    {
        // Honeypot nur befüllen wenn != null
        if ($honeypotField !== null) {
            $submittedData[$honeypotField] = $spamMarker;
        }

        // spam_marker wird IMMER gesetzt! ✅
        $submittedData['spam_marker'] = $spamMarker;
        $GLOBALS['C2N_SPAM_DETECTED'][$formId] = true;
    }

    /**
     * Blockiert SPAM komplett (keine E-Mail)
     */
    private function blockSpam(int $formId): void
    {
        $this->loggingHelper->logError('SPAM BLOCKED: E-Mail will NOT be sent', __METHOD__);

        $GLOBALS['C2N_BLOCK_EMAIL'][$formId] = true;

        $_SESSION['FORM_DATA']['auto_form_' . $formId] = [
            'error' => true,
            'message' => 'Ihre Anfrage konnte nicht verarbeitet werden. Bitte versuchen Sie es später erneut.'
        ];

        $GLOBALS['TL_HOOKS']['prepareFormData'] = [];
        $GLOBALS['TL_HOOKS']['processFormData'] = [];

        $session = System::getContainer()->get('session');
        $session->remove('c2n_form_timestamp_' . $formId);
    }

    /**
     * Entfernt interne/technische Felder aus submittedData
     * Diese Felder sollen NICHT in ##raw_data## erscheinen!
     */
    private function removeInternalFields(array &$submittedData, array $fields): void
    {
        // 1. Alle ALTCHA Felder entfernen
        foreach ($fields as $field) {
            if ($field->type === 'c2n_altcha') {
                unset($submittedData[$field->name]);
            }
        }

        // 2. Alle Honeypot Felder entfernen
        foreach ($fields as $field) {
            if (in_array($field->type, ['c2n_honeypot', 'c2n_honeypot_textarea', 'c2n_honeypot_checkbox'])) {
                unset($submittedData[$field->name]);
            }
        }

        // 3. spam_marker Token entfernen (wird separat als SimpleToken bereitgestellt)
        unset($submittedData['spam_marker']);
    }
}