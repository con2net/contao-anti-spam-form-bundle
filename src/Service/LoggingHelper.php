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
}