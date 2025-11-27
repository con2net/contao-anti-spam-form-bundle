<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/IpBlacklistService.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Service für IP-Blacklist und E-Mail-Blacklist Prüfung via StopForumSpam.com
 *
 * Features:
 * - IP-Check gegen StopForumSpam
 * - E-Mail-Check gegen StopForumSpam
 * - File-basiertes Caching (24h default)
 * - Whitelist-Support (einzelne IPs + CIDR-Notation)
 * - Error-Handling mit Fallback
 *
 * @author con2net / Stefan Meise
 */
class IpBlacklistService
{
    private const API_URL = 'https://api.stopforumspam.org/api';

    private LoggerInterface $logger;
    private string $cacheDir;
    private int $cacheLifetime;
    private int $apiTimeout;
    private array $whitelist;

    public function __construct(
        LoggerInterface $logger,
        string $projectDir,
        int $cacheLifetime = 86400,
        int $apiTimeout = 3,
        array $whitelist = []
    ) {
        $this->logger = $logger;
        $this->cacheLifetime = $cacheLifetime;
        $this->apiTimeout = $apiTimeout;
        $this->whitelist = $whitelist;

        // Cache-Verzeichnisse erstellen wenn nicht vorhanden
        $this->cacheDir = $projectDir . '/var/cache/antispam/ip_blacklist';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Separater Cache für E-Mails
        $emailCacheDir = $projectDir . '/var/cache/antispam/email_blacklist';
        if (!is_dir($emailCacheDir)) {
            mkdir($emailCacheDir, 0755, true);
        }
    }

    /**
     * Prüft ob eine IP auf der Blacklist steht
     *
     * @param string $ip Die zu prüfende IP-Adresse
     * @return bool true = SPAM, false = OK
     */
    public function isIpBlacklisted(string $ip): bool
    {
        $this->logger->debug('IP Blacklist Check started', ['ip' => $ip]);

        // Whitelist-Check
        if ($this->isWhitelisted($ip)) {
            $this->logger->info('IP is whitelisted, skipping blacklist check', ['ip' => $ip]);
            return false;
        }

        // Cache-Check
        $cachedResult = $this->getCachedResult($ip, 'ip');
        if ($cachedResult !== null) {
            $this->logger->debug('Using cached IP result', [
                'ip' => $ip,
                'blacklisted' => $cachedResult
            ]);
            return $cachedResult;
        }

        // API-Call
        try {
            $isBlacklisted = $this->checkViaApi('ip', $ip);
            $this->cacheResult($ip, $isBlacklisted, 'ip');
            return $isBlacklisted;

        } catch (\Exception $e) {
            $this->logger->warning('IP Blacklist API check failed - letting request through', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ========== NEU: Prüft ob eine E-Mail auf der Blacklist steht ==========
     *
     * @param string $email Die zu prüfende E-Mail-Adresse
     * @return bool true = SPAM, false = OK
     */
    public function isEmailBlacklisted(string $email): bool
    {
        $this->logger->debug('E-Mail Blacklist Check started', ['email' => $email]);

        // E-Mail validieren
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format', ['email' => $email]);
            return false;
        }

        // Cache-Check
        $cachedResult = $this->getCachedResult($email, 'email');
        if ($cachedResult !== null) {
            $this->logger->debug('Using cached E-Mail result', [
                'email' => $email,
                'blacklisted' => $cachedResult
            ]);
            return $cachedResult;
        }

        // API-Call
        try {
            $isBlacklisted = $this->checkViaApi('email', $email);
            $this->cacheResult($email, $isBlacklisted, 'email');
            return $isBlacklisted;

        } catch (\Exception $e) {
            $this->logger->warning('E-Mail Blacklist API check failed - letting request through', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * API-Call zu StopForumSpam.com
     *
     * @param string $type 'ip' oder 'email'
     * @param string $value Die zu prüfende IP oder E-Mail
     */
    private function checkViaApi(string $type, string $value): bool
    {
        $url = self::API_URL . '?' . $type . '=' . urlencode($value) . '&json';

        $this->logger->debug('Calling StopForumSpam API', [
            'url' => $url,
            'type' => $type,
            'timeout' => $this->apiTimeout
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->apiTimeout,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Contao-AntiSpam-Bundle/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception('cURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception('API returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);

        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        if (!isset($data['success']) || $data['success'] !== 1) {
            throw new \Exception('API returned error or invalid response');
        }

        // Blacklist-Status auswerten
        $appears = $data[$type]['appears'] ?? 0;
        $frequency = $data[$type]['frequency'] ?? 0;

        $isBlacklisted = ($appears > 0);

        $this->logger->info('StopForumSpam API result', [
            'type' => $type,
            'value' => $value,
            'blacklisted' => $isBlacklisted,
            'appears' => $appears,
            'frequency' => $frequency,
            'lastseen' => $data[$type]['lastseen'] ?? 'never'
        ]);

        return $isBlacklisted;
    }

    /**
     * Prüft ob IP auf der Whitelist steht
     */
    private function isWhitelisted(string $ip): bool
    {
        foreach ($this->whitelist as $whitelistedIp) {
            if (strpos($whitelistedIp, '/') !== false) {
                if ($this->ipInRange($ip, $whitelistedIp)) {
                    return true;
                }
            } else {
                if ($ip === $whitelistedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Prüft ob IP in einem CIDR-Bereich liegt
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);

        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }

    /**
     * ==========   Holt Ergebnis aus dem Cache ==========
     *
     * @param string $value IP oder E-Mail
     * @param string $type 'ip' oder 'email'
     * @return bool|null null = nicht im Cache oder abgelaufen
     */
    private function getCachedResult(string $value, string $type): ?bool
    {
        $cacheFile = $this->getCacheFilePath($value, $type);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $content = file_get_contents($cacheFile);
        $data = json_decode($content, true);

        if ($data === null) {
            $this->logger->warning('Invalid cache file', ['file' => $cacheFile]);
            unlink($cacheFile);
            return null;
        }

        // Cache abgelaufen?
        $age = time() - $data['cached_at'];
        if ($age > $this->cacheLifetime) {
            $this->logger->debug('Cache expired', [
                'value' => $value,
                'type' => $type,
                'age' => $age,
                'lifetime' => $this->cacheLifetime
            ]);
            unlink($cacheFile);
            return null;
        }

        return $data['blacklisted'];
    }

    /**
     * ========== Speichert Ergebnis im Cache ==========
     *
     * @param string $value IP oder E-Mail
     * @param bool $isBlacklisted Blacklist-Status
     * @param string $type 'ip' oder 'email'
     */
    private function cacheResult(string $value, bool $isBlacklisted, string $type): void
    {
        $cacheFile = $this->getCacheFilePath($value, $type);

        $data = [
            $type => $value,
            'blacklisted' => $isBlacklisted,
            'cached_at' => time()
        ];

        file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT));

        $this->logger->debug('Result cached', [
            'type' => $type,
            'value' => $value,
            'blacklisted' => $isBlacklisted,
            'cache_file' => basename($cacheFile)
        ]);
    }

    /**
     *  Erzeugt Cache-Dateinamen
     *
     * @param string $value IP oder E-Mail
     * @param string $type 'ip' oder 'email'
     */
    private function getCacheFilePath(string $value, string $type): string
    {
        $baseDir = dirname($this->cacheDir);
        $subDir = $type === 'email' ? 'email_blacklist' : 'ip_blacklist';

        return $baseDir . '/' . $subDir . '/' . md5($value) . '.json';
    }

    /**
     * Löscht alle Cache-Dateien die älter als X Sekunden sind
     *
     * @param int $olderThan Sekunden (0 = alles löschen)
     * @param string $type 'ip', 'email' oder 'all'
     * @return int Anzahl gelöschter Dateien
     */
    public function cleanupCache(int $olderThan = 0, string $type = 'all'): int
    {
        $deleted = 0;
        $dirs = [];

        if ($type === 'all' || $type === 'ip') {
            $dirs[] = $this->cacheDir;
        }

        if ($type === 'all' || $type === 'email') {
            $dirs[] = dirname($this->cacheDir) . '/email_blacklist';
        }

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*.json');

            foreach ($files as $file) {
                if ($olderThan === 0) {
                    unlink($file);
                    $deleted++;
                    continue;
                }

                $age = time() - filemtime($file);
                if ($age > $olderThan) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        $this->logger->info('Blacklist cache cleaned', [
            'deleted' => $deleted,
            'type' => $type,
            'older_than' => $olderThan
        ]);

        return $deleted;
    }
}