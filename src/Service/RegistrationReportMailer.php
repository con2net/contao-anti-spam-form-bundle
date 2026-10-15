<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Service/RegistrationReportMailer.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Service;

use Contao\Config;
use Contao\Controller;
use Contao\Email;
use Contao\ModuleModel;
use Contao\StringUtil;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Registration Report Mailer
 *
 * Schickt bei der Spam-Reaktion "report" einen Bericht als Nur-Text-Mail an die
 * Adresse(n) des Moduls. Die Inhalte stammen vom Bot: Steuerzeichen werden entfernt,
 * Werte gekürzt, Passwörter und Uploads ausgelassen. Betreff und Header enthalten
 * keine Eingaben des Besuchers.
 *
 * Das Template wird direkt eingebunden statt über FrontendTemplate::parse(): Contao
 * würde sonst Insert-Tags im Bot-Inhalt ersetzen.
 *
 * Drossel: höchstens 10 Berichte pro Stunde und Modul. Darüber hinaus wird nur
 * mitgezählt (ohne Inhalte), der nächste Bericht nennt die Anzahl.
 */
class RegistrationReportMailer
{
    public const TEMPLATE = 'c2n_registration_abuse';

    private const CACHE_KEY_PREFIX = 'c2n_registration_report_';
    private const MAX_REPORTS = 10;
    private const WINDOW = 3600;
    // Hält den Zähler der unterdrückten Berichte auch über ruhige Stunden hinweg
    private const STATE_LIFETIME = 604800;
    private const MAX_VALUE_LENGTH = 500;
    private const SKIPPED_INPUT_TYPES = ['password', 'upload', 'fileTree'];

    private CacheItemPoolInterface $cache;
    private LoggingHelper $loggingHelper;

    public function __construct(CacheItemPoolInterface $cache, LoggingHelper $loggingHelper)
    {
        $this->cache = $cache;
        $this->loggingHelper = $loggingHelper;
    }

    public function send(ModuleModel $module, string $reason, Request $request): void
    {
        $moduleId = (int) $module->id;
        $recipients = trim((string) $module->c2n_report_email);

        if ('' === $recipients) {
            $this->loggingHelper->logError(
                sprintf('Anti-SPAM: Registration module %d - no report address configured, report not sent', $moduleId),
                __METHOD__
            );

            return;
        }

        $sender = $GLOBALS['TL_ADMIN_EMAIL'] ?? Config::get('adminEmail');

        if (!$sender) {
            $this->loggingHelper->logError(
                sprintf('Anti-SPAM: Registration module %d - no administrator e-mail address, report not sent', $moduleId),
                __METHOD__
            );

            return;
        }

        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $moduleId);
        $state = $item->isHit() && \is_array($item->get()) ? $item->get() : [];
        $now = time();

        if ($now - (int) ($state['window_start'] ?? 0) >= self::WINDOW) {
            $state['window_start'] = $now;
            $state['sent'] = 0;
            $state['limit_logged'] = false;
        }

        $suppressed = (int) ($state['suppressed'] ?? 0);

        if ($state['sent'] >= self::MAX_REPORTS) {
            $state['suppressed'] = $suppressed + 1;

            if (!$state['limit_logged']) {
                $state['limit_logged'] = true;

                $this->loggingHelper->logError(
                    sprintf('Anti-SPAM: Registration module %d - report limit (%d per hour) reached, further spam is discarded without report', $moduleId, self::MAX_REPORTS),
                    __METHOD__
                );
            }

            $this->saveState($item, $state);

            return;
        }

        // Vor dem Versand zählen: Ein fehlerhafter Mailserver hebt die Drossel nicht auf
        ++$state['sent'];
        $this->saveState($item, $state);

        $lang = $GLOBALS['TL_LANG']['MSC']['c2n_registration_report'] ?? [];

        try {
            $email = new Email();
            $email->from = $sender;
            $email->fromName = $GLOBALS['TL_ADMIN_NAME'] ?? null;
            $email->subject = sprintf(
                $lang['subject'] ?? 'Anti-SPAM: spam registration discarded (%s, module ID %d)',
                $this->clean((string) $module->name),
                $moduleId
            );
            $email->text = $this->render([
                'lang' => $lang,
                'moduleName' => $this->clean((string) $module->name),
                'moduleId' => $moduleId,
                'reason' => $this->clean($reason),
                'time' => date('Y-m-d H:i:s', $now),
                'ip' => $this->clean((string) $request->getClientIp()),
                'url' => $this->clean($request->getUri()),
                'userAgent' => $this->clean((string) $request->headers->get('User-Agent', '')),
                'fields' => $this->collectFields($module),
                'suppressed' => $suppressed,
            ]);
            // false bei synchronem Versand (4.13). Unter 5.x geht die Mail über die
            // Messenger-Queue, ein späterer Fehler landet dort im Contao-Log.
            if (!$email->sendTo($recipients)) {
                throw new \RuntimeException('Contao\Email::sendTo() returned false');
            }
        } catch (\Throwable $e) {
            $this->loggingHelper->logError(
                sprintf('Anti-SPAM: Registration module %d - report could not be sent: %s', $moduleId, $e->getMessage()),
                __METHOD__
            );

            return;
        }

        if ($suppressed > 0) {
            $state['suppressed'] = 0;
            $this->saveState($item, $state);
        }
    }

    private function saveState(CacheItemInterface $item, array $state): void
    {
        $item->set($state);
        $item->expiresAfter(self::STATE_LIFETIME);
        $this->cache->save($item);
    }

    /**
     * Eingaben der editierbaren Felder, ohne Passwort, Passwort-Wiederholung
     * (nicht in "editable") und Uploads
     *
     * @return list<array{0: string, 1: string}>
     */
    private function collectFields(ModuleModel $module): array
    {
        $fields = [];

        foreach (StringUtil::deserialize($module->editable, true) as $field) {
            $config = $GLOBALS['TL_DCA']['tl_member']['fields'][$field] ?? null;

            if ('password' === $field || !\is_array($config) || \in_array($config['inputType'] ?? null, self::SKIPPED_INPUT_TYPES, true)) {
                continue;
            }

            $value = $_POST[$field] ?? null;

            if (\is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }

            if (!\is_scalar($value) || '' === (string) $value) {
                continue;
            }

            $fields[] = [$this->clean((string) ($config['label'][0] ?? $field)), $this->clean((string) $value)];
        }

        return $fields;
    }

    /**
     * Entfernt Steuerzeichen (auch Zeilenumbrüche) und Bidi-Steuerzeichen, kürzt lange Werte
     */
    private function clean(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = preg_replace('/[\p{Cc}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/ {2,}/', ' ', $value) ?? '');

        if (mb_strlen($value) > self::MAX_VALUE_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_VALUE_LENGTH) . ' [...]';
        }

        return $value;
    }

    /**
     * Bindet das (überschreibbare) Template ein, ohne Insert-Tags und parseTemplate-Hooks
     */
    private function render(array $data): string
    {
        $template = Controller::getTemplate(self::TEMPLATE);

        ob_start();

        try {
            (static function (string $__template, array $__data): void {
                extract($__data, EXTR_SKIP);
                include $__template;
            })($template, $data);
        } finally {
            $text = ob_get_clean();
        }

        return (string) $text;
    }
}
