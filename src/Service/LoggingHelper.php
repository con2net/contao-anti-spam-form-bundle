<?php
// File: src/Service/LoggingHelper.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

/**
 * Zentraler Logging Helper für Backend System-Log
 *
 * @author con2net / Stefan Meise
 */
class LoggingHelper
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Loggt Fehler/SPAM in ROT
     *
     * Nutzt ERROR Action für rote Darstellung im Backend
     *
     * @param string $message Die Log-Nachricht (OHNE HTML-Tags!)
     * @param string $method Die aufrufende Methode (__METHOD__)
     */
    public function logError(string $message, string $method = __METHOD__): void
    {
        // ContaoContext funktioniert in Contao 4.13 UND 5.3!
        // ERROR Action = ROT im Backend System-Log
        $this->logger->error(
            $message,
            ['contao' => new ContaoContext($method, ContaoContext::ERROR)]
        );
    }

    /**
     * Loggt normale Informationen
     *
     * Keine spezielle Farbgebung (Wie kriege ich GRÜN??)
     *
     * @param string $message Die Log-Nachricht (OHNE HTML-Tags!)
     * @param string $method Die aufrufende Methode (__METHOD__)
     */
    public function logInfo(string $message, string $method = __METHOD__): void
    {
        // ContaoContext funktioniert in Contao 4.13 UND 5.3!
        // FORMS Action = Normal im Backend System-Log
        $this->logger->info(
            $message,
            ['contao' => new ContaoContext($method, ContaoContext::FORMS)]
        );
    }

    /**
     * Loggt Debug-Informationen
     *
     * Geht nur in var/logs, NICHT ins Backend System-Log
     *
     * @param string $message Die Log-Nachricht
     * @param array $context Zusätzliche Context-Daten
     */
    public function logDebug(string $message, array $context = []): void
    {
        // Debug-Logs gehen NUR in die Log-Dateien, NICHT ins Backend System-Log
        $this->logger->debug($message, $context);
    }

    /**
     * Loggt SPAM-Erkennungen
     *
     * Production: Kurze Meldung mit FormName
     * Debug: Detaillierte Meldung mit Grund
     *
     * @param string $formName Name des Formulars
     * @param int $formId ID des Formulars
     * @param bool $debugMode Ob Debug-Modus aktiv ist
     * @param string|null $reason Optional: Detaillierter Grund (nur für Debug)
     */
    public function logSpamDetected(
        string $formName,
        int $formId,
        bool $debugMode = false,
        ?string $reason = null
    ): void
    {
        if ($debugMode && $reason) {
            // Debug-Modus: Detaillierte Meldung mit Grund
            $this->logError(
                sprintf('SPAM DETECTED: %s', $reason),
                __METHOD__
            );
        } elseif (!$debugMode) {
            // Production: Kurze, saubere Meldung
            $this->logError(
                sprintf('Form "%s" (ID: %d) - SPAM DETECTED', $formName, $formId),
                __METHOD__
            );
        }
        // Wenn debugMode=true aber kein reason: Keine Ausgabe
        // (Detail-Logs kommen separat von den einzelnen Checks)
    }
}