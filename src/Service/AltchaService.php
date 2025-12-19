<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/AltchaService.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\ChallengeOptions;
use AltchaOrg\Altcha\Hasher\Algorithm;
use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Psr\Log\LoggerInterface;

/**
 * ALTCHA Service
 *
 * Validates ALTCHA Challenge-Responses and creates Challenges
 *
 * Das expires-Property wird NICHT mehr verwendet, da es in neueren
 * ALTCHA Library Versionen nicht mehr existiert.
 *
 * @author con2net webServices
 */
class AltchaService
{
    private string $hmacKey;
    private ?LoggerInterface $logger;
    private ?LoggingHelper $loggingHelper;

    public function __construct(
        ?string $hmacKey = null,
        ?LoggerInterface $logger = null,
        ?LoggingHelper $loggingHelper = null
    ) {
        $this->hmacKey = $hmacKey ?: '';
        $this->logger = $logger;
        $this->loggingHelper = $loggingHelper;
    }

    /**
     * Validates an ALTCHA Challenge-Response
     *
     * @param string $payload Base64-encoded Challenge-String from form
     * @return bool True if valid, false if invalid or error
     */
    public function validate(string $payload): bool
    {
        try {
            $altcha = new Altcha($this->hmacKey);
            $result = $altcha->verifySolution($payload);

            if ($result) {
                // Success: Widget loggt das im Debug-Modus ins Backend System-Log
                // Hier nur technisches Debug-Log für var/logs
                if ($this->logger) {
                    $this->logger->debug('ALTCHA validation successful', [
                        'payload_length' => strlen($payload)
                    ]);
                }

                return true;
            } else {
                if ($this->logger) {
                    $this->logger->debug('ALTCHA validation failed - invalid solution');
                }

                // Widget loggt den User-sichtbaren Fehler, hier nur Debug-Info
                return false;
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('ALTCHA validation error: ' . $e->getMessage(), [
                    'exception' => get_class($e)
                ]);
            }

            if ($this->loggingHelper) {
                $this->loggingHelper->logError(
                    'ALTCHA validation error: ' . $e->getMessage(),
                    __METHOD__
                );
            }

            return false;
        }
    }

    /**
     * Creates an ALTCHA Challenge
     *
     * @param int $maxNumber Maximum number for challenge (difficulty)
     * @param int $saltLength Length of salt (8-32 characters)
     * @param string $algorithmName Hash algorithm ('SHA-256', 'SHA-384', 'SHA-512')
     * @param int|null $expires Challenge expiration in seconds (NOT USED - kept for compatibility)
     * @return array Challenge data as array
     */
    public function createChallenge(
        int $maxNumber = 100000,
        int $saltLength = 16,
        string $algorithmName = 'SHA-256',
        ?int $expires = null
    ): array
    {
        try {
            $altcha = new Altcha($this->hmacKey);

            // Convert string to Algorithm enum
            $algorithm = match($algorithmName) {
                'SHA-384' => Algorithm::SHA384,
                'SHA-512' => Algorithm::SHA512,
                default => Algorithm::SHA256
            };

            // Create ChallengeOptions
            $options = new ChallengeOptions(
                algorithm: $algorithm,
                maxNumber: $maxNumber,
                saltLength: $saltLength
            );

            // Create challenge
            $challenge = $altcha->createChallenge($options);

            // Return as array for JSON serialization
            $result = [
                'algorithm' => $challenge->algorithm->value ?? 'SHA-256',
                'challenge' => $challenge->challenge ?? '',
                'salt' => $challenge->salt ?? '',
                'signature' => $challenge->signature ?? '',
            ];

            if ($this->logger) {
                $this->logger->debug('ALTCHA challenge created', [
                    'algorithm' => $algorithmName,
                    'maxNumber' => $maxNumber,
                    'saltLength' => $saltLength
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('ALTCHA challenge creation error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'maxNumber' => $maxNumber,
                    'saltLength' => $saltLength
                ]);
            }

            if ($this->loggingHelper) {
                $this->loggingHelper->logError(
                    'ALTCHA challenge creation error: ' . $e->getMessage(),
                    __METHOD__
                );
            }

            // Return empty array on error
            return [];
        }
    }
}