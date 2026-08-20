<?php
// File: src/EventListener/Notification/AbuseEmailNcV1Listener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener\Notification;

use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use NotificationCenter\Model\Gateway as GatewayModel;
use NotificationCenter\Model\Language;
use NotificationCenter\Model\Message;

/**
 * Redirects SPAM-marked NotificationCenter 1.x messages to the configured
 * abuse e-mail address instead of their normal recipients.
 *
 * Registration is by hook name string (Contao's own hook system), so this
 * class loads safely even without NotificationCenter installed - the NC
 * type-hints on __invoke() are only resolved by PHP when the method is
 * actually called, which only happens if NC 1.x fires this hook.
 */
#[AsHook('sendNotificationMessage')]
class AbuseEmailNcV1Listener
{
    public function __construct(private readonly LoggingHelper $loggingHelper)
    {
    }

    public function __invoke(Message $objMessage, array &$arrTokens, string $strLanguage, GatewayModel $objGatewayModel): bool
    {
        $abuseEmail = $GLOBALS['C2N_ABUSE_EMAIL_REDIRECT'] ?? null;

        if (!$abuseEmail) {
            return true;
        }

        $objLanguage = Language::findByMessageAndLanguageOrFallback($objMessage, $strLanguage);

        if ($objLanguage === null) {
            return true;
        }

        // Original recipient(s) in den Betreff aufnehmen, damit trotz
        // Umleitung erkennbar bleibt, wem die Nachricht eigentlich galt.
        $originalRecipients = trim((string) $objLanguage->recipients);

        if ($originalRecipients !== '') {
            $objLanguage->email_subject = '[urspr. Empfänger: ' . $originalRecipients . '] ' . $objLanguage->email_subject;
        }

        // Nur in-memory überschreiben (kein ->save()!) - Contaos Model-Registry
        // gibt bei NCs eigenem, nachfolgendem Lookup dieselbe Instanz zurück,
        // da __set() die Felder als "modified" markiert und mergeRow() diese
        // beim erneuten Laden nicht überschreibt.
        $objLanguage->recipients = $abuseEmail;
        $objLanguage->email_recipient_cc = '';
        $objLanguage->email_recipient_bcc = '';

        $this->loggingHelper->logInfo(
            sprintf('Abuse-E-Mail: NC v1 message %d redirected to %s (original: %s)', $objMessage->id, $abuseEmail, $originalRecipients),
            __METHOD__
        );

        return true;
    }
}
