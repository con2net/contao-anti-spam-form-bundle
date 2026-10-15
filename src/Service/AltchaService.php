<?php
// File: src/Service/AltchaService.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use AltchaOrg\Altcha\Algorithm\Argon2id;
use AltchaOrg\Altcha\Algorithm\DeriveKeyInterface;
use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Algorithm\Scrypt;
use AltchaOrg\Altcha\Algorithm\Sha;
use AltchaOrg\Altcha\Algorithm\ShaAlgorithm;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\ChallengeParameters;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\HmacAlgorithm;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use AltchaOrg\Altcha\VerifySolutionOptions;
use AltchaOrg\Altcha\V1\Altcha as AltchaV1;
use AltchaOrg\Altcha\V1\ChallengeOptions as ChallengeOptionsV1;
use AltchaOrg\Altcha\V1\Hasher\Algorithm as AlgorithmV1;
use Contao\Database;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * ALTCHA Service
 *
 * Validates ALTCHA challenge responses and creates challenges.
 * Uses altcha-org/altcha ^2.0 with support for both legacy and new algorithms.
 *
 * HMAC key priority:
 *   1. ALTCHA_HMAC_KEY in .env.local (power users, recommended for production)
 *   2. Auto-generated key stored in tl_c2n_settings (default, zero-config)
 *
 * Algorithm config mapping:
 *   'pbkdf2'        → PBKDF2/SHA-256 (default for new installations)
 *   'pbkdf2-sha384' → PBKDF2/SHA-384 (stronger, slightly slower)
 *   'pbkdf2-sha512' → PBKDF2/SHA-512 (strongest PBKDF2 variant)
 *   'argon2id'      → Argon2id (requires ext-sodium, memory-bound)
 *   'scrypt'        → Scrypt (requires ext-scrypt)
 *   'SHA-256'       → SHA-256 legacy (V1 format, backwards compatible)
 *   'SHA-384'       → SHA-384 legacy (V1 format, backwards compatible)
 *   'SHA-512'       → SHA-512 legacy (V1 format, backwards compatible)
 *
 * Replay protection: challenges expire after CHALLENGE_LIFETIME seconds, and a solved
 * challenge is accepted only once (its signature is remembered in the cache until it
 * expires).
 *
 * @author con2net webServices
 */
class AltchaService
{
    private const BUNDLE = 'antispam';
    private const KEY_HMAC = 'altcha_hmac_key';
    private const LEGACY_SHA_ALGORITHMS = ['sha-256', 'sha-384', 'sha-512'];
    private const CHALLENGE_LIFETIME = 7200;
    private const USED_CACHE_PREFIX = 'c2n_altcha_used_';

    private ?string $hmacKey;
    private string $algorithmName;
    private ?LoggerInterface $logger;
    private ?LoggingHelper $loggingHelper;
    private ?CacheItemPoolInterface $cache;

    public function __construct(
        ?string $hmacKey = null,
        ?LoggerInterface $logger = null,
        ?LoggingHelper $loggingHelper = null,
        string $algorithmName = 'pbkdf2',
        ?CacheItemPoolInterface $cache = null
    ) {
        $this->hmacKey = $hmacKey;
        $this->logger = $logger;
        $this->loggingHelper = $loggingHelper;
        $this->algorithmName = $algorithmName;
        $this->cache = $cache;
    }

    /**
     * Returns true if the configured algorithm is a legacy SHA value from V1 config.
     */
    private function isLegacyShaConfigure(): bool
    {
        return in_array(strtolower($this->algorithmName), self::LEGACY_SHA_ALGORITHMS, true);
    }

    /**
     * Returns the active HMAC key.
     *
     * Priority: .env.local → tl_c2n_settings → generate new + persist
     */
    private function getHmacKey(): string
    {
        if (!empty($this->hmacKey)) {
            return $this->hmacKey;
        }

        try {
            $db = Database::getInstance();
            $result = $db->prepare(
                "SELECT setting_value FROM tl_c2n_settings WHERE bundle=? AND setting_key=? LIMIT 1"
            )->execute(self::BUNDLE, self::KEY_HMAC);

            if ($result->numRows > 0 && !empty($result->setting_value)) {
                return $result->setting_value;
            }

            $newKey = bin2hex(random_bytes(32));
            $db->prepare(
                "INSERT INTO tl_c2n_settings (bundle, setting_key, setting_value) VALUES (?, ?, ?)"
            )->execute(self::BUNDLE, self::KEY_HMAC, $newKey);

            if ($this->logger) {
                $this->logger->info('ALTCHA: Auto-generated HMAC key saved to tl_c2n_settings.');
            }

            return $newKey;

        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->warning('ALTCHA auto-key failed (run contao:migrate?): ' . $e->getMessage());
            }
            return bin2hex(random_bytes(32));
        }
    }

    /**
     * Creates a V2 DeriveKeyInterface instance based on the configured algorithm.
     *
     * Supported algorithms:
     *   pbkdf2        → PBKDF2/SHA-256 (default)
     *   pbkdf2-sha384 → PBKDF2/SHA-384
     *   pbkdf2-sha512 → PBKDF2/SHA-512
     *   argon2id      → Argon2id (requires ext-sodium)
     *   scrypt        → Scrypt (requires ext-scrypt)
     *
     * @throws \RuntimeException if a required PHP extension is missing
     */
    private function createV2Algorithm(): DeriveKeyInterface
    {
        return match(strtolower($this->algorithmName)) {
            'pbkdf2-sha384' => new Pbkdf2(HmacAlgorithm::SHA384),
            'pbkdf2-sha512' => new Pbkdf2(HmacAlgorithm::SHA512),
            'argon2id'      => $this->createArgon2id(),
            'scrypt'        => $this->createScrypt(),
            default         => new Pbkdf2(), // pbkdf2 / SHA-256 is the default
        };
    }

    /**
     * Creates an Argon2id algorithm instance, checking for ext-sodium availability.
     */
    private function createArgon2id(): Argon2id
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException(
                'ALTCHA: algorithm "argon2id" requires PHP extension ext-sodium. '
                . 'Please install it or switch to algorithm "pbkdf2" in your config.'
            );
        }
        return new Argon2id();
    }

    /**
     * Creates a Scrypt algorithm instance, checking for ext-scrypt availability.
     */
    private function createScrypt(): Scrypt
    {
        if (!extension_loaded('scrypt')) {
            throw new \RuntimeException(
                'ALTCHA: algorithm "scrypt" requires PHP extension ext-scrypt. '
                . 'Please install it or switch to algorithm "pbkdf2" in your config.'
            );
        }
        return new Scrypt();
    }

    /**
     * Resolves the V1 Algorithm enum from a legacy SHA algorithm name.
     */
    private function resolveV1Algorithm(): AlgorithmV1
    {
        return match(strtoupper($this->algorithmName)) {
            'SHA-384' => AlgorithmV1::SHA384,
            'SHA-512' => AlgorithmV1::SHA512,
            default   => AlgorithmV1::SHA256,
        };
    }

    /**
     * Resolves the correct DeriveKeyInterface from a challenge parameters algorithm string.
     * Used during V2 payload verification to match the algorithm the challenge was created with.
     */
    private function resolveAlgorithmFromString(string $algorithmString): DeriveKeyInterface
    {
        $lower = strtolower($algorithmString);

        if (str_starts_with($lower, 'argon2id')) return $this->createArgon2id();
        if (str_starts_with($lower, 'scrypt'))   return $this->createScrypt();

        if (str_starts_with($lower, 'pbkdf2')) {
            if (str_contains($lower, 'sha-384')) return new Pbkdf2(HmacAlgorithm::SHA384);
            if (str_contains($lower, 'sha-512')) return new Pbkdf2(HmacAlgorithm::SHA512);
            return new Pbkdf2();
        }

        if (str_contains($lower, 'sha-384')) return new Sha(ShaAlgorithm::SHA384);
        if (str_contains($lower, 'sha-512')) return new Sha(ShaAlgorithm::SHA512);

        return new Sha();
    }

    /**
     * Deserializes a base64-encoded JSON payload from the JS widget into a Payload object.
     * Handles both V2 format (with 'challenge.parameters') and V1 flat format.
     *
     * @throws \InvalidArgumentException if the payload is malformed
     */
    private function deserializePayload(string $base64Payload): Payload
    {
        $json = base64_decode($base64Payload, strict: true);
        if ($json === false) {
            throw new \InvalidArgumentException('ALTCHA: payload is not valid base64.');
        }

        $data = json_decode($json, associative: true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('ALTCHA: payload is not valid JSON.');
        }

        // V2 format: {challenge: {parameters: {...}, signature: '...'}, solution: {...}}
        if (isset($data['challenge']['parameters'])) {
            if (!isset($data['solution']['counter']) || !isset($data['solution']['derivedKey'])) {
                throw new \InvalidArgumentException('ALTCHA: V2 payload solution is missing counter or derivedKey.');
            }

            $params = ChallengeParameters::fromArray($data['challenge']['parameters']);
            $challenge = new Challenge($params, $data['challenge']['signature'] ?? null);
            $solution = new Solution(
                counter: (int) $data['solution']['counter'],
                derivedKey: (string) $data['solution']['derivedKey'],
            );

            return new Payload($challenge, $solution);
        }

        // V1 flat format: {algorithm, challenge, salt, signature, number}
        if (isset($data['algorithm'], $data['challenge'], $data['salt'], $data['number'])) {
            $params = new ChallengeParameters(
                algorithm: (string) $data['algorithm'],
                nonce: '',
                salt: (string) $data['salt'],
                cost: 1,
                keyPrefix: substr((string) $data['challenge'], 0, 2),
            );
            $challenge = new Challenge($params, $data['signature'] ?? null);
            $solution = new Solution(
                counter: (int) $data['number'],
                derivedKey: (string) $data['challenge'],
            );

            return new Payload($challenge, $solution);
        }

        throw new \InvalidArgumentException('ALTCHA: payload format not recognized (neither V1 nor V2).');
    }

    /**
     * Validates an ALTCHA challenge response.
     * Handles both V1 (legacy SHA) and V2 (PBKDF2/Argon2id) payload formats.
     *
     * @param string $base64Payload Base64-encoded JSON payload from the JS widget
     * @return bool True if valid, false if invalid or on error
     */
    public function validate(string $base64Payload): bool
    {
        try {
            $json = base64_decode($base64Payload, strict: true);
            $data = $json ? json_decode($json, associative: true) : null;

            // V1 flat format → use V1 lib
            if (is_array($data) && isset($data['algorithm'], $data['challenge'], $data['number'])
                && !isset($data['challenge']['parameters'])
            ) {
                $v1 = new AltchaV1($this->getHmacKey());
                $result = $v1->verifySolution($base64Payload);

                if ($result && !$this->consumeChallenge(
                    (string) ($data['signature'] ?? ''),
                    $this->getV1ExpiresAt((string) ($data['salt'] ?? ''))
                )) {
                    return false;
                }

                if ($result) {
                    if ($this->loggingHelper) {
                        $this->loggingHelper->logInfo(
                            'ALTCHA validated successfully (V1/' . strtoupper($data['algorithm']) . ')',
                            __METHOD__
                        );
                    }
                    return true;
                }

                if ($this->loggingHelper) {
                    $this->loggingHelper->logError('ALTCHA FAILED: Invalid V1 solution', __METHOD__);
                }
                return false;
            }

            // V2 format → use V2 lib
            $payload = $this->deserializePayload($base64Payload);
            $algorithm = $this->resolveAlgorithmFromString(
                $payload->challenge->parameters->algorithm
            );

            $altcha = new Altcha(hmacSignatureSecret: $this->getHmacKey());
            $result = $altcha->verifySolution(new VerifySolutionOptions(
                payload: $payload,
                algorithm: $algorithm,
            ));

            if ($result->verified && !$this->consumeChallenge(
                (string) $payload->challenge->signature,
                $payload->challenge->parameters->expiresAt
            )) {
                return false;
            }

            if ($result->verified) {
                if ($this->loggingHelper) {
                    $this->loggingHelper->logInfo(
                        'ALTCHA validated successfully (' . $payload->challenge->parameters->algorithm . ')',
                        __METHOD__
                    );
                }
                return true;
            }

            if ($this->loggingHelper) {
                $this->loggingHelper->logError('ALTCHA FAILED: Invalid solution', __METHOD__);
            }
            return false;

        } catch (\InvalidArgumentException $e) {
            if ($this->loggingHelper) {
                $this->loggingHelper->logError('ALTCHA FAILED: ' . $e->getMessage(), __METHOD__);
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
     * Accepts a verified challenge only once and only if it carries an expiry.
     *
     * The signature is remembered until the challenge expires. Without a cache the
     * check is skipped (behaviour as before), a cache error does not block the user.
     * $expiresAt is int|float since altcha-org/altcha 2.3, 0 means "no expiry" there.
     */
    private function consumeChallenge(string $signature, int|float|null $expiresAt): bool
    {
        if (!$expiresAt || '' === $signature) {
            if ($this->loggingHelper) {
                $this->loggingHelper->logError('ALTCHA FAILED: Challenge without expiry', __METHOD__);
            }
            return false;
        }

        if (null === $this->cache) {
            return true;
        }

        try {
            $item = $this->cache->getItem(self::USED_CACHE_PREFIX . hash('sha256', $signature));

            if ($item->isHit()) {
                if ($this->loggingHelper) {
                    $this->loggingHelper->logError('ALTCHA FAILED: Payload already used (replay)', __METHOD__);
                }
                return false;
            }

            $item->set(true);
            $item->expiresAfter(max(1, (int) ceil($expiresAt - time())));
            $this->cache->save($item);

        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('ALTCHA replay check failed: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }

        return true;
    }

    /**
     * Reads the expiry of a V1 challenge from its salt ("<salt>?expires=<timestamp>").
     */
    private function getV1ExpiresAt(string $salt): ?int
    {
        parse_str(explode('?', $salt, 2)[1] ?? '', $params);

        return isset($params['expires']) && is_numeric($params['expires']) ? (int) $params['expires'] : null;
    }

    /**
     * Creates an ALTCHA challenge.
     *
     * Legacy SHA (SHA-256/384/512): returns V1 flat format for backwards compatibility.
     * PBKDF2/Argon2id/Scrypt: returns V2 format as expected by the V3 widget.
     *
     * @param int $maxNumber Challenge difficulty (maps to PBKDF2 iterations / SHA max number)
     * @param int $saltLength Salt length (only used for legacy SHA algorithms)
     * @param string $algorithmName NOT USED - algorithm is set via constructor/config
     * @param int|null $expires NOT USED - kept for backwards compatibility (see CHALLENGE_LIFETIME)
     * @return array Challenge data as array for JSON output, empty array on error
     */
    public function createChallenge(
        int $maxNumber = 100000,
        int $saltLength = 16,
        string $algorithmName = 'pbkdf2',
        ?int $expires = null
    ): array {
        try {
            // Legacy SHA path: V1 flat format for backwards compatibility
            if ($this->isLegacyShaConfigure()) {
                $v1 = new AltchaV1($this->getHmacKey());
                $v1Options = new ChallengeOptionsV1(
                    algorithm: $this->resolveV1Algorithm(),
                    maxNumber: $maxNumber,
                    saltLength: $saltLength,
                    expires: new \DateTimeImmutable('+' . self::CHALLENGE_LIFETIME . ' seconds'),
                );
                $v1Challenge = $v1->createChallenge($v1Options);

                if ($this->logger) {
                    $this->logger->debug('ALTCHA challenge created (V1/SHA legacy)', [
                        'algorithm' => $this->algorithmName,
                        'maxNumber' => $maxNumber,
                    ]);
                }

                return [
                    'algorithm' => $v1Challenge->algorithm,
                    'challenge' => $v1Challenge->challenge,
                    'salt'      => $v1Challenge->salt,
                    'signature' => $v1Challenge->signature,
                ];
            }

            // V2 path: PBKDF2/Argon2id/Scrypt
            $algorithm = $this->createV2Algorithm();
            $altcha = new Altcha(hmacSignatureSecret: $this->getHmacKey());

            $options = new CreateChallengeOptions(
                algorithm: $algorithm,
                cost: $maxNumber,
                expiresAt: time() + self::CHALLENGE_LIFETIME,
            );

            $challenge = $altcha->createChallenge($options);

            if ($this->logger) {
                $this->logger->debug('ALTCHA challenge created (V2)', [
                    'algorithm' => $algorithm->getAlgorithmName(),
                    'cost'      => $maxNumber,
                ]);
            }

            // V2 format: {parameters: {...}, signature: '...'} + flat fields for widget
            return [
                'parameters' => $challenge->parameters->toArray(),
                'signature'  => $challenge->signature,
                'salt'       => $challenge->parameters->salt,
                'algorithm'  => $challenge->parameters->algorithm,
            ];

        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('ALTCHA challenge creation error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
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