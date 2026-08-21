<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/AntiSpamFormListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\IpBlacklistService;
use Con2net\ContaoAntiSpamFormBundle\Service\ContentAnalysisService;
use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FormFieldModel;
use Contao\FormModel;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Contao\Widget;
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
#[AsHook('validateFormField', method: 'onValidateFormField')]
class AntiSpamFormListener
{
    /**
     * Feldtypen, an die eine SPAM-Blockierungs-Meldung NICHT gehängt werden darf: entweder
     * eigene Spezialfelder (Honeypot ist per CSS versteckt, ALTCHA validiert sich selbst) oder
     * Contao-Kerntypen ohne Fehler-Ausgabeblock im jeweiligen Feld-Template.
     */
    private const SPAM_ERROR_INELIGIBLE_TYPES = [
        'c2n_honeypot', 'c2n_honeypot_textarea', 'c2n_honeypot_checkbox', 'c2n_altcha',
        'hidden', 'submit', 'explanation', 'html', 'fieldsetStart', 'fieldsetStop',
    ];

    private IpBlacklistService $ipBlacklistService;
    private ContentAnalysisService $contentAnalysisService;
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private LoggingHelper $loggingHelper;

    /**
     * Cache des früh (im validateFormField-Hook) ermittelten SPAM-Verdikts pro Formular-ID,
     * damit die Erkennung pro Request nur einmal läuft (der Hook feuert einmal pro Widget).
     */
    private array $earlySpamVerdicts = [];

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

        $config = $this->buildAntiSpamConfig($formModel);
        $debugMode = $config['debugMode'];
        $spamMarker = $config['spamMarker'];
        $minSubmitTime = $config['minSubmitTime'];
        $maxSubmitTime = $config['maxSubmitTime'];
        $blockSpam = $config['blockSpam'];
        $enableIpBlacklist = $config['enableIpBlacklist'];
        $enableContentAnalysis = $config['enableContentAnalysis'];
        $formName = $config['formName'];

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
                $this->blockSpam($form, $formId);
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
                $this->blockSpam($form, $formId);
            }

            if ($blockSpam) {
                // Neuer Startzeitpunkt für einen erneuten Formularversuch
                $session->set($sessionKey, time());
            } else {
                // Bei nur markiertem Spam wird der Vorgang regulär abgeschlossen
                $session->remove($sessionKey);
            }

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
                    $this->loggingHelper->logSpamDetected(
                        $formName,
                        $formId,
                        $debugMode,
                        sprintf('IP %s is on blacklist', $userIp)
                    );

                    $honeypotFields = $this->findAllHoneypotFields($fields);
                    $this->markAsSpam(
                        $submittedData,
                        !empty($honeypotFields) ? $honeypotFields[0] : null,
                        $spamMarker,
                        $formId
                    );

                    if ($blockSpam) {
                        $this->blockSpam($form, $formId);
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
                        $this->loggingHelper->logSpamDetected(
                            $formName,
                            $formId,
                            $debugMode,
                            sprintf('E-Mail %s is on blacklist', $email)
                        );

                        $honeypotFields = $this->findAllHoneypotFields($fields);
                        $this->markAsSpam(
                            $submittedData,
                            !empty($honeypotFields) ? $honeypotFields[0] : null,
                            $spamMarker,
                            $formId
                        );

                        if ($blockSpam) {
                            $this->blockSpam($form, $formId);
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
                $contentConfig = $this->buildContentAnalysisConfig($formModel);

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
                    $this->loggingHelper->logSpamDetected(
                        $formName,
                        $formId,
                        $debugMode,
                        sprintf('Content Analysis Score: %d >= Threshold: %d', $result['score'], $result['threshold'])
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
                        $this->blockSpam($form, $formId);
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
                $this->blockSpam($form, $formId);
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
                    $this->loggingHelper->logSpamDetected(
                        $formName,
                        $formId,
                        $debugMode,
                        sprintf('Honeypot "%s" was filled', $honeypotField)
                    );
                    $this->markAsSpam($submittedData, $honeypotField, $spamMarker, $formId);

                    if ($blockSpam) {
                        $this->blockSpam($form, $formId);
                    } elseif ($debugMode) {
                        $this->loggingHelper->logInfo(
                            'SPAM MARKED: E-Mail will be sent with SPAM marker',
                            __METHOD__
                        );
                    }

                    if ($blockSpam) {
                        // Neuer Startzeitpunkt für einen erneuten Formularversuch
                        $session->set($sessionKey, time());
                    } else {
                        // Bei nur markiertem Spam wird der Vorgang regulär abgeschlossen
                        $session->remove($sessionKey);
                    }

                    return;
                }
            }
        }

        // ===== PRÜFUNG 2: MIN-ZEIT CHECK =====
        if ($timeTaken < $minSubmitTime) {
            $this->loggingHelper->logSpamDetected(
                $formName,
                $formId,
                $debugMode,
                sprintf('Submitted too fast: %d seconds (min: %d)', $timeTaken, $minSubmitTime)
            );

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($form, $formId);
            }

            if ($blockSpam) {
                // Neuer Startzeitpunkt für einen erneuten Formularversuch
                $session->set($sessionKey, time());
            } else {
                // Bei nur markiertem Spam wird der Vorgang regulär abgeschlossen
                $session->remove($sessionKey);
            }

            return;
        }

        // ===== PRÜFUNG 3: MAX-ZEIT CHECK =====
        if ($maxSubmitTime > 0 && $timeTaken > $maxSubmitTime) {
            $this->loggingHelper->logSpamDetected(
                $formName,
                $formId,
                $debugMode,
                sprintf('Submitted too slow: %d seconds (max: %d)', $timeTaken, $maxSubmitTime)
            );

            $this->markAsSpam(
                $submittedData,
                !empty($honeypotFields) ? $honeypotFields[0] : null,
                $spamMarker,
                $formId
            );

            if ($blockSpam) {
                $this->blockSpam($form, $formId);
                // Neuer Startzeitpunkt für einen erneuten Formularversuch
                $session->set($sessionKey, time());
            } else {
                // Bei nur markiertem Spam wird der Vorgang regulär abgeschlossen
                $session->remove($sessionKey);
            }

            return;
        }

        // ===== ALLES OK =====
        $session->remove($sessionKey);

        if (!isset($submittedData['spam_marker'])) {
            $submittedData['spam_marker'] = '';
        }

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

        // Abuse-E-Mail-Umleitung vormerken (nur wenn "SPAM nicht senden" NICHT
        // aktiv ist - der Haken hat weiterhin Vorrang und blockiert komplett).
        // Ausgewertet von den NC v1/v2 Notification-Listenern.
        $formModel = FormModel::findByPk($formId);

        if ($formModel !== null && !$formModel->c2n_block_spam && $formModel->c2n_abuse_email) {
            $GLOBALS['C2N_ABUSE_EMAIL_REDIRECT'] = trim((string) $formModel->c2n_abuse_email);
        }
    }

    /**
     * Blockiert SPAM komplett (keine E-Mail)
     */
    private function blockSpam(Form $form, int $formId): void
    {
        $this->loggingHelper->logError(
            'SPAM BLOCKED: E-Mail will NOT be sent',
            __METHOD__
        );

        $message = $GLOBALS['TL_LANG']['ERR']['c2nSpamBlocked']
            ?? 'Your request could not be processed. Please try again later.';

        $this->resetFormTimer($formId);

        // Form::addError() exists in Contao 5.3+ only (not in 4.13) - shows the
        // error inline on the same page without a redirect.
        if (method_exists($form, 'addError')) {
            $form->addError($message);

            return;
        }

        // On 4.13, blocking normally already happened earlier via the
        // validateFormField hook (onValidateFormField()), which attaches the
        // error to a real widget before Contao commits to the success path -
        // reaching this point on 4.13 means no eligible widget was found (e.g.
        // a form consisting only of a honeypot + submit button). Fall back to
        // the classic flash message + redirect for that rare case.
        Message::addError($message);

        $request = $this->requestStack->getCurrentRequest();
        $uri = $request ? $request->getUri() : '/';
        header('Location: ' . $uri, true, 302);
        exit();
    }

    /**
     * Hook: validateFormField
     *
     * Feuert pro Widget, bevor Contao intern $doNotSubmit final setzt und bevor
     * prepareFormData/processFormData überhaupt laufen. Auf Contao 4.13 (das kein
     * Form::addError() kennt) ist das die einzige Stelle, an der ein SPAM-Block
     * noch OHNE Redirect sichtbar gemacht werden kann: addError() auf einem echten
     * Feld setzt hasErrors() für Contaos eigene Prüfung, die Fehlermeldung wird
     * automatisch im Feld-Template gerendert (derselbe Mechanismus, den
     * AltchaFormField::validate() bereits nutzt).
     *
     * Auf Contao 5.3+ ist das ein reines No-Op, da dort onPrepareFormData()/
     * Form::addError() den Block bereits korrekt und getestet übernimmt.
     */
    public function onValidateFormField(Widget $widget, string $formId, array $formData, Form $form): Widget
    {
        if (method_exists($form, 'addError')) {
            return $widget;
        }

        $numericFormId = (int) $form->id;

        if (array_key_exists($numericFormId, $this->earlySpamVerdicts)) {
            return $widget;
        }

        if (!$this->isEligibleForSpamError($widget)) {
            return $widget;
        }

        $formModel = FormModel::findByPk($numericFormId);

        if (!$formModel || !$formModel->c2n_enable_antispam || !$formModel->c2n_block_spam) {
            $this->earlySpamVerdicts[$numericFormId] = false;

            return $widget;
        }

        $isSpam = $this->isSubmissionSpamEarly($numericFormId, $formModel);
        $this->earlySpamVerdicts[$numericFormId] = $isSpam;

        if (!$isSpam) {
            return $widget;
        }

        $message = $GLOBALS['TL_LANG']['ERR']['c2nSpamBlocked']
            ?? 'Your request could not be processed. Please try again later.';

        $widget->addError($message);
        $this->resetFormTimer($numericFormId);

        $this->loggingHelper->logError(
            sprintf('SPAM BLOCKED (early, via validateFormField on field "%s")', $widget->name),
            __METHOD__
        );

        return $widget;
    }

    /**
     * Prüft, ob ein Widget-Typ eine SPAM-Blockierungs-Meldung sichtbar anzeigen kann
     */
    private function isEligibleForSpamError(Widget $widget): bool
    {
        return !in_array($widget->type, self::SPAM_ERROR_INELIGIBLE_TYPES, true);
    }

    /**
     * Frühe, eigenständige SPAM-Erkennung für den validateFormField-Hook (Contao 4.13).
     *
     * Spiegelt dieselbe Prüfreihenfolge wie __invoke() (PRÜFUNG 0 -> 0a -> 0b -> 0c ->
     * Timestamp -> Honeypot -> Min-Zeit -> Max-Zeit), liest Feldwerte aber direkt aus
     * Input::post()/$_POST/Session statt aus dem zu diesem Zeitpunkt noch nicht
     * vollständig aufgebauten $submittedData-Array. Ein falsch-negatives Ergebnis hier
     * ist unkritisch (die vollständige Prüfung in __invoke() läuft danach ganz normal
     * weiter), ein falsch-positives Ergebnis wäre eine echte Regression - beide Pfade
     * müssen daher synchron gehalten und gemeinsam getestet werden.
     */
    private function isSubmissionSpamEarly(int $formId, FormModel $formModel): bool
    {
        $config = $this->buildAntiSpamConfig($formModel);

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            return true;
        }

        $session = $request->getSession();
        $sessionKey = 'c2n_form_timestamp_' . $formId;

        // PRÜFUNG 0: JavaScript-Token
        $jsToken = $_POST['page_hash'] ?? null;

        if (!$jsToken || !str_starts_with($jsToken, 'js_verified_')) {
            return true;
        }

        $fields = FormFieldModel::findBy('pid', $formId, ['order' => 'sorting']);
        $honeypotFieldNames = [];
        $data = [];

        if ($fields !== null) {
            foreach ($fields as $field) {
                if (in_array($field->type, ['c2n_honeypot', 'c2n_honeypot_textarea', 'c2n_honeypot_checkbox'], true)) {
                    $honeypotFieldNames[] = $field->name;

                    continue;
                }

                if (!in_array($field->type, self::SPAM_ERROR_INELIGIBLE_TYPES, true)) {
                    $data[$field->name] = Input::post($field->name);
                }
            }
        }

        // PRÜFUNG 0a: IP-Blacklist
        if ($config['enableIpBlacklist']) {
            try {
                if ($this->ipBlacklistService->isIpBlacklisted($this->getUserIp())) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->loggingHelper->logError(
                    sprintf('Early IP Blacklist check failed: %s', $e->getMessage()),
                    __METHOD__
                );
            }

            // PRÜFUNG 0b: E-Mail-Blacklist
            $emailCandidates = [];
            foreach (['email', 'e-mail', 'e_mail', 'mail', 'Email', 'E-Mail'] as $field) {
                $emailCandidates[$field] = Input::post($field);
            }

            $email = $this->extractEmail($emailCandidates);

            if ($email) {
                try {
                    if ($this->ipBlacklistService->isEmailBlacklisted($email)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    $this->loggingHelper->logError(
                        sprintf('Early E-Mail Blacklist check failed: %s', $e->getMessage()),
                        __METHOD__
                    );
                }
            }
        }

        // PRÜFUNG 0c: Content-Analyse
        if ($config['enableContentAnalysis']) {
            try {
                $result = $this->contentAnalysisService->analyzeContent(
                    $data,
                    $this->buildContentAnalysisConfig($formModel)
                );

                if ($result['is_spam']) {
                    return true;
                }
            } catch (\Exception $e) {
                $this->loggingHelper->logError(
                    sprintf('Early Content Analysis failed: %s', $e->getMessage()),
                    __METHOD__
                );
            }
        }

        // Timestamp aus Session
        $formLoadTimestamp = $session->get($sessionKey);

        if (!$formLoadTimestamp) {
            return true;
        }

        // PRÜFUNG 1: Honeypot
        foreach ($honeypotFieldNames as $honeypotFieldName) {
            if (trim((string) Input::postUnsafeRaw($honeypotFieldName)) !== '') {
                return true;
            }
        }

        $timeTaken = time() - $formLoadTimestamp;

        // PRÜFUNG 2: Min-Zeit
        if ($timeTaken < $config['minSubmitTime']) {
            return true;
        }

        // PRÜFUNG 3: Max-Zeit
        if ($config['maxSubmitTime'] > 0 && $timeTaken > $config['maxSubmitTime']) {
            return true;
        }

        return false;
    }

    /**
     * Lädt die Anti-SPAM-Konfiguration eines Formulars (wird sowohl von __invoke()
     * als auch von isSubmissionSpamEarly() genutzt, um Drift zwischen beiden zu vermeiden)
     */
    private function buildAntiSpamConfig(FormModel $formModel): array
    {
        return [
            'debugMode' => (bool) $formModel->c2n_debug,
            'spamMarker' => html_entity_decode(
                $formModel->c2n_spam_prefix ?: '*** SPAM *** ',
                ENT_QUOTES,
                'UTF-8'
            ),
            'minSubmitTime' => (int) ($formModel->c2n_min_submit_time ?: 10),
            'maxSubmitTime' => (int) ($formModel->c2n_max_submit_time ?: 0),
            'blockSpam' => (bool) $formModel->c2n_block_spam,
            'enableIpBlacklist' => (bool) ($formModel->c2n_enable_ip_blacklist ?? false),
            'enableContentAnalysis' => (bool) ($formModel->c2n_enable_content_analysis ?? false),
            'formName' => $formModel->title ?: 'Form ' . $formModel->id,
        ];
    }

    /**
     * Baut die Content-Analyse-Konfiguration aus dem FormModel (wird sowohl von
     * __invoke() als auch von isSubmissionSpamEarly() genutzt)
     */
    private function buildContentAnalysisConfig(FormModel $formModel): array
    {
        return [
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
            'fields_uppercase' => $formModel->c2n_content_fields_uppercase,

            // SPAM-Keywords
            'check_keywords' => (bool)$formModel->c2n_content_check_keywords,
            'score_keywords' => (int)($formModel->c2n_content_score_keywords ?: 10),
            'spam_keywords' => $formModel->c2n_content_spam_keywords ?: '',
            'fields_keywords' => $formModel->c2n_content_fields_keywords
        ];
    }

    /**
     * Setzt den Session-Zeitstempel für einen erneuten Formularversuch zurück
     */
    private function resetFormTimer(int $formId): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->hasSession()) {
            $request->getSession()->set('c2n_form_timestamp_' . $formId, time());
        }
    }
}
