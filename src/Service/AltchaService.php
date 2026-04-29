<?php
// File: src/Service/AltchaService.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

// V1 compatibility namespace: still available in altcha-org/altcha ^2.0.
// Full V2 migration (PBKDF2/Argon2id) will follow in v1.2.0.
use AltchaOrg\Altcha\V1\Altcha;
use AltchaOrg\Altcha\V1\ChallengeOptions;
use AltchaOrg\Altcha\V1\Hasher\Algorithm;
use Contao\Database;
use Psr\Log\LoggerInterface;

/**
 * ALTCHA Service
 *
 * Validates ALTCHA challenge responses and creates challenges.
 *
 * HMAC key priority:
 *   1. ALTCHA_HMAC_KEY in .env.local (power users, recommended for production)
 *   2. Auto-generated key stored in tl_c2n_settings (default, zero-config)
 *
 * @author con2net webServices
 */
class AltchaService
{
    // Bundle identifier used as namespace in tl_c2n_settings
    private const BUNDLE = 'antispam';
    private const KEY_HMAC = 'altcha_hmac_key';

    private string $hmacKey;
    private ?LoggerInterface $logger;
    private ?LoggingHelper $loggingHelper;

    public function __construct(
        ?string $hmacKey = null,
        ?LoggerInterface $logger = null,
        ?LoggingHelper $loggingHelper = null
    ) {
        $this->hmacKey = $hmacKey ?? '';
        $this->logger = $logger;
        $this->loggingHelper = $loggingHelper;
    }

    /**
     * Returns the active HMAC key.
     *
     * Priority: .env.local → tl_c2n_settings → generate new + persist
     */
    private function getHmacKey(): string
    {
        // 1. Manually configured key always takes precedence
        if (!empty($this->hmacKey)) {
            return $this->hmacKey;
        }

        try {
            $db = Database::getInstance();

            // 2. Try to load existing auto-generated key from tl_c2n_settings
            $result = $db->prepare(
                "SELECT setting_value FROM tl_c2n_settings WHERE bundle=? AND setting_key=? LIMIT 1"
            )->execute(self::BUNDLE, self::KEY_HMAC);

            if ($result->numRows > 0 && !empty($result->setting_value)) {
                return $result->setting_value;
            }

            // 3. Generate new key and persist to tl_c2n_settings
            $newKey = bin2hex(random_bytes(32));

            $db->prepare(
                "INSERT INTO tl_c2n_settings (bundle, setting_key, setting_value) VALUES (?, ?, ?)"
            )->execute(self::BUNDLE, self::KEY_HMAC, $newKey);

            if ($this->logger) {
                $this->logger->info('ALTCHA: Auto-generated HMAC key saved to tl_c2n_settings.');
            }

            return $newKey;

        } catch (\Throwable $e) {
            // Fallback: temporary key for this request only.
            // Occurs when DB has not been migrated yet (contao:migrate not run).
            if ($this->logger) {
                $this->logger->warning(
                    'ALTCHA auto-key failed (run contao:migrate?): ' . $e->getMessage()
                );
            }

            return bin2hex(random_bytes(32));
        }
    }

    /**
     * Validates an ALTCHA challenge response.
     *
     * @param string $payload Base64-encoded challenge string from form
     * @return bool True if valid, false if invalid or on error
     */
    public function validate(string $payload): bool
    {
        try {
            $altcha = new Altcha($this->getHmacKey());
            $result = $altcha->verifySolution($payload);

            if ($result) {
                if ($this->logger) {
                    $this->logger->debug('ALTCHA validation successful', [
                        'payload_length' => strlen($payload)
                    ]);
                }

                return true;
            }

            if ($this->logger) {
                $this->logger->debug('ALTCHA validation failed - invalid solution');
            }

            return false;

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
     * Creates an ALTCHA challenge.
     *
     * @param int $maxNumber Maximum number for challenge difficulty
     * @param int $saltLength Salt length in characters (8-32)
     * @param string $algorithmName Hash algorithm ('SHA-256', 'SHA-384', 'SHA-512')
     * @param int|null $expires NOT USED - kept for backwards compatibility
     * @return array Challenge data as array, empty array on error
     */
    public function createChallenge(
        int $maxNumber = 100000,
        int $saltLength = 16,
        string $algorithmName = 'SHA-256',
        ?int $expires = null
    ): array {
        try {
            $altcha = new Altcha($this->getHmacKey());

            $algorithm = match(strtoupper($algorithmName)) {
                'SHA-384' => Algorithm::SHA384,
                'SHA-512' => Algorithm::SHA512,
                default   => Algorithm::SHA256,
            };

            $options = new ChallengeOptions(
                algorithm: $algorithm,
                maxNumber: $maxNumber,
                saltLength: $saltLength
            );

            $challenge = $altcha->createChallenge($options);

            $result = [
                'algorithm' => $challenge->algorithm->value ?? 'SHA-256',
                'challenge' => $challenge->challenge ?? '',
                'salt'      => $challenge->salt ?? '',
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

            return [];
        }
    }
}