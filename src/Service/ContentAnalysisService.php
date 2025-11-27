<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/ContentAnalysisService.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Service für Content-Analyse von Formulardaten
 *
 * Analysiert Formularinhalte auf SPAM-Muster ohne externe APIs.
 * Nutzt Pattern-Matching und heuristische Regeln.
 *
  * Score-basiertes System:
 * - 0-30: Sauber
 * - 31-50: Verdächtig
 * - 51+: SPAM (konfigurierbar)
 *
 * Alle Checks und Scores sind über das FormModel konfigurierbar.
 *
 * @author con2net / Stefan Meise
 */
class ContentAnalysisService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Analysiert Formulardaten und gibt SPAM-Score zurück
     *
     * @param array $formData Formulardaten (Key-Value Array)
     * @param array $config Konfiguration aus dem FormModel
     * @param array $excludeFields Felder die ignoriert werden sollen
     * @return array ['is_spam' => bool, 'score' => int, 'reasons' => array]
     */
    public function analyzeContent(array $formData, array $config, array $excludeFields = []): array
    {
        $score = 0;
        $reasons = [];
        $threshold = (int)($config['spam_threshold'] ?? 50);

        $this->logger->debug('Content Analysis started', [
            'field_count' => count($formData),
            'threshold' => $threshold
        ]);

        // Technische Felder immer ausschließen
        $excludeFields = array_merge($excludeFields, [
            'FORM_SUBMIT', 'REQUEST_TOKEN', 'submit', 'page_hash',
            'c2n_client_time', 'spam_marker', 'captcha'
        ]);

        // ===== PRÜFUNG 1: URLs im Text =====
        if (!empty($config['check_urls'])) {
            $urlScore = (int)($config['score_urls'] ?? 50);
            $allowedFields = $this->deserializeFields($config['fields_urls'] ?? null);

            if (!empty($allowedFields)) {
                foreach ($formData as $fieldName => $value) {
                    // Technische Felder überspringen
                    if (in_array($fieldName, $excludeFields) || empty($value) || !is_string($value)) {
                        continue;
                    }

                    // Nur wenn Feld in allowed list
                    if (!in_array($fieldName, $allowedFields)) {
                        continue;
                    }

                    // URLs nicht im E-Mail-Feld prüfen (Sicherheitscheck)
                    if ($fieldName === 'email') {
                        continue;
                    }

                    if ($this->containsUrl($value)) {
                        $score += $urlScore;
                        $reasons[] = sprintf('URLs found in field "%s" (+%d)', $fieldName, $urlScore);

                        $this->logger->info('SPAM indicator: URLs in text', [
                            'field' => $fieldName,
                            'score_added' => $urlScore
                        ]);

                        break; // Ein Fund reicht
                    }
                }
            } else {
                $this->logger->debug('URL check skipped: No fields selected');
            }
        }

        // ===== PRÜFUNG 2: Nur Sonderzeichen =====
        if (!empty($config['check_special_chars'])) {
            $specialCharScore = (int)($config['score_special_chars'] ?? 40);
            $allowedFields = $this->deserializeFields($config['fields_special_chars'] ?? null);

            if (!empty($allowedFields)) {
                foreach ($formData as $fieldName => $value) {
                    if (in_array($fieldName, $excludeFields) || empty($value) || !is_string($value)) {
                        continue;
                    }

                    if (!in_array($fieldName, $allowedFields)) {
                        continue;
                    }

                    if ($this->isOnlySpecialChars($value)) {
                        $score += $specialCharScore;
                        $reasons[] = sprintf('Only special characters in field "%s" (+%d)', $fieldName, $specialCharScore);

                        $this->logger->info('SPAM indicator: Only special chars', [
                            'field' => $fieldName,
                            'score_added' => $specialCharScore
                        ]);

                        break;
                    }
                }
            } else {
                $this->logger->debug('Special chars check skipped: No fields selected');
            }
        }

        // ===== PRÜFUNG 3: Tempmail-Adresse =====
        if (!empty($config['check_tempmail'])) {
            $tempmailScore = (int)($config['score_tempmail'] ?? 30);
            $tempmailDomains = $this->parseTempmailDomains($config['tempmail_domains'] ?? '');

            $email = $formData['email'] ?? $formData['e-mail'] ?? $formData['mail'] ?? null;
            if ($email && $this->isTempMail($email, $tempmailDomains)) {
                $score += $tempmailScore;
                $reasons[] = sprintf('Temporary/disposable email address detected (+%d)', $tempmailScore);

                $this->logger->info('SPAM indicator: Tempmail address', [
                    'email' => $email,
                    'score_added' => $tempmailScore
                ]);
            }
        }

        // ===== PRÜFUNG 4: Nachricht zu kurz =====
        if (!empty($config['check_short_message'])) {
            $shortMessageScore = (int)($config['score_short_message'] ?? 25);
            $minLength = (int)($config['min_message_length'] ?? 10);
            $allowedFields = $this->deserializeFields($config['fields_short_message'] ?? null);

            if (!empty($allowedFields)) {
                foreach ($allowedFields as $fieldName) {
                    if (isset($formData[$fieldName]) && is_string($formData[$fieldName])) {
                        $messageLength = strlen(trim($formData[$fieldName]));

                        if ($messageLength > 0 && $messageLength < $minLength) {
                            $score += $shortMessageScore;
                            $reasons[] = sprintf('Message too short in field "%s": %d characters (min: %d) (+%d)',
                                $fieldName, $messageLength, $minLength, $shortMessageScore);

                            $this->logger->info('SPAM indicator: Message too short', [
                                'field' => $fieldName,
                                'length' => $messageLength,
                                'min_length' => $minLength,
                                'score_added' => $shortMessageScore
                            ]);

                            break;
                        }
                    }
                }
            } else {
                $this->logger->debug('Short message check skipped: No fields selected');
            }
        }

        // ===== PRÜFUNG 5: Repetitive Zeichen =====
        if (!empty($config['check_repetitive'])) {
            $repetitiveScore = (int)($config['score_repetitive'] ?? 20);
            $allowedFields = $this->deserializeFields($config['fields_repetitive'] ?? null);

            if (!empty($allowedFields)) {
                foreach ($formData as $fieldName => $value) {
                    if (in_array($fieldName, $excludeFields) || empty($value) || !is_string($value)) {
                        continue;
                    }

                    if (!in_array($fieldName, $allowedFields)) {
                        continue;
                    }

                    if ($this->hasRepetitivePatterns($value)) {
                        $score += $repetitiveScore;
                        $reasons[] = sprintf('Repetitive patterns in field "%s" (+%d)', $fieldName, $repetitiveScore);

                        $this->logger->info('SPAM indicator: Repetitive patterns', [
                            'field' => $fieldName,
                            'score_added' => $repetitiveScore
                        ]);

                        break;
                    }
                }
            } else {
                $this->logger->debug('Repetitive check skipped: No fields selected');
            }
        }

        // ===== PRÜFUNG 6: Viele Großbuchstaben =====
        if (!empty($config['check_uppercase'])) {
            $uppercaseScore = (int)($config['score_uppercase'] ?? 15);
            $maxRatio = (int)($config['max_uppercase_ratio'] ?? 60) / 100;
            $allowedFields = $this->deserializeFields($config['fields_uppercase'] ?? null);

            if (!empty($allowedFields)) {
                foreach ($formData as $fieldName => $value) {
                    if (in_array($fieldName, $excludeFields) || empty($value) || !is_string($value)) {
                        continue;
                    }

                    if (!in_array($fieldName, $allowedFields)) {
                        continue;
                    }

                    $uppercaseRatio = $this->getUppercaseRatio($value);
                    if ($uppercaseRatio > $maxRatio) {
                        $score += $uppercaseScore;
                        $reasons[] = sprintf('Too many uppercase letters in field "%s": %.0f%% (+%d)',
                            $fieldName, $uppercaseRatio * 100, $uppercaseScore);

                        $this->logger->info('SPAM indicator: Too many uppercase', [
                            'field' => $fieldName,
                            'ratio' => $uppercaseRatio,
                            'max_ratio' => $maxRatio,
                            'score_added' => $uppercaseScore
                        ]);

                        break;
                    }
                }
            } else {
                $this->logger->debug('Uppercase check skipped: No fields selected');
            }
        }

        // ===== PRÜFUNG 7: SPAM-Keywords =====
        if (!empty($config['check_keywords'])) {
            $keywordScore = (int)($config['score_keywords'] ?? 10);
            $keywords = $this->parseKeywords($config['spam_keywords'] ?? '');
            $allowedFields = $this->deserializeFields($config['fields_keywords'] ?? null);

            if (!empty($keywords) && !empty($allowedFields)) {
                $totalKeywordCount = 0;

                foreach ($formData as $fieldName => $value) {
                    if (in_array($fieldName, $excludeFields) || empty($value) || !is_string($value)) {
                        continue;
                    }

                    if (!in_array($fieldName, $allowedFields)) {
                        continue;
                    }

                    $keywordCount = $this->countSpamKeywords($value, $keywords);
                    if ($keywordCount > 0) {
                        $totalKeywordCount += $keywordCount;
                    }
                }

                if ($totalKeywordCount > 0) {
                    $addedScore = min($totalKeywordCount * $keywordScore, 30); // Max. 30 Punkte
                    $score += $addedScore;
                    $reasons[] = sprintf('%d SPAM keyword(s) found (+%d)', $totalKeywordCount, $addedScore);

                    $this->logger->info('SPAM indicator: Keywords found', [
                        'keyword_count' => $totalKeywordCount,
                        'score_added' => $addedScore
                    ]);
                }
            } else {
                $this->logger->debug('Keywords check skipped: No fields selected or no keywords defined');
            }
        }

        $isSpam = ($score >= $threshold);

        $this->logger->info('Content Analysis completed', [
            'total_score' => $score,
            'threshold' => $threshold,
            'is_spam' => $isSpam,
            'reasons_count' => count($reasons)
        ]);

        return [
            'is_spam' => $isSpam,
            'score' => $score,
            'threshold' => $threshold,
            'reasons' => $reasons
        ];
    }

    /**
     * Deserialisiert Feldauswahl aus der Datenbank
     *
     * @param mixed $serialized Serialized array oder null
     * @return array Feldnamen-Array
     */
    private function deserializeFields($serialized): array
    {
        if (empty($serialized)) {
            return [];
        }

        // Wenn schon Array, direkt zurückgeben
        if (is_array($serialized)) {
            return $serialized;
        }

        // Deserialisieren
        $fields = @unserialize($serialized);

        if (!is_array($fields)) {
            return [];
        }

        return $fields;
    }

    /**
     * Prüft ob Text URLs enthält
     */
    private function containsUrl(string $text): bool
    {
        $patterns = [
            '/https?:\/\//i',
            '/www\./i',
            '/\b\w+\.(com|net|org|de|co\.uk|info|biz|io|app)\b/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft ob Text nur aus Sonderzeichen besteht
     */
    private function isOnlySpecialChars(string $text): bool
    {
        if (strlen($text) < 3) {
            return false;
        }

        return preg_match('/^[^\w\s]+$/u', $text) === 1;
    }

    /**
     * Prüft auf repetitive Zeichen (6+ gleiche Zeichen hintereinander)
     */
    private function hasRepetitivePatterns(string $text): bool
    {
        return preg_match('/(.)\1{5,}/', $text) === 1;
    }

    /**
     * Berechnet Anteil der Großbuchstaben
     */
    private function getUppercaseRatio(string $text): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);

        if (strlen($letters) === 0) {
            return 0.0;
        }

        $uppercase = preg_replace('/[^A-Z]/', '', $letters);

        return strlen($uppercase) / strlen($letters);
    }

    /**
     * Zählt SPAM-Keywords im Text
     */
    private function countSpamKeywords(string $text, array $keywords): int
    {
        $text = strtolower($text);
        $count = 0;

        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Prüft ob E-Mail eine Tempmail/Wegwerf-Adresse ist
     */
    private function isTempMail(string $email, array $tempmailDomains): bool
    {
        if (empty($tempmailDomains)) {
            return false;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        return in_array($domain, $tempmailDomains);
    }

    /**
     * Parst Tempmail-Domains aus Textarea (eine pro Zeile)
     */
    private function parseTempmailDomains(string $input): array
    {
        if (empty($input)) {
            return [];
        }

        $lines = explode("\n", $input);
        $domains = [];

        foreach ($lines as $line) {
            $domain = trim($line);
            if (!empty($domain)) {
                $domains[] = strtolower($domain);
            }
        }

        return $domains;
    }

    /**
     * Parst Keywords aus Textarea/String (komma-getrennt)
     */
    private function parseKeywords(string $input): array
    {
        if (empty($input)) {
            return [];
        }

        $keywords = explode(',', $input);
        $parsed = [];

        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword)) {
                $parsed[] = strtolower($keyword);
            }
        }

        return $parsed;
    }
}