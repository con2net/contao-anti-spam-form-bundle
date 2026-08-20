<?php
// File: src/EventListener/Notification/AbuseEmailNcV2Listener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener\Notification;

use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Symfony\Contracts\EventDispatcher\Event;
use Terminal42\NotificationCenterBundle\Event\CreateParcelEvent;
use Terminal42\NotificationCenterBundle\Parcel\Stamp\LanguageConfigStamp;

/**
 * Redirects SPAM-marked NotificationCenter 2.x messages to the configured
 * abuse e-mail address instead of their normal recipients.
 *
 * Deliberately NOT using #[AsEventListener] here: that attribute infers the
 * event class from the parameter type at container-compile time, which would
 * require Terminal42\NotificationCenterBundle\Event\CreateParcelEvent to
 * exist even on installs without NotificationCenter 2.x. Instead this class
 * is registered manually and conditionally in ContaoAntiSpamFormExtension,
 * guarded by class_exists(). The CreateParcelEvent import above is therefore
 * only used inside the method body, which is only ever reached once NC 2.x
 * has actually dispatched such an event.
 */
class AbuseEmailNcV2Listener
{
    public function __construct(private readonly LoggingHelper $loggingHelper)
    {
    }

    public function __invoke(Event $event): void
    {
        if (!$event instanceof CreateParcelEvent) {
            return;
        }

        $abuseEmail = $GLOBALS['C2N_ABUSE_EMAIL_REDIRECT'] ?? null;

        if (!$abuseEmail) {
            return;
        }

        $parcel = $event->getParcel();
        $languageStamp = $parcel->getStamp(LanguageConfigStamp::class);

        if ($languageStamp === null) {
            return;
        }

        $languageConfig = $languageStamp->languageConfig;

        // Original recipient(s) in den Betreff aufnehmen, damit trotz
        // Umleitung erkennbar bleibt, wem die Nachricht eigentlich galt.
        $originalRecipients = trim($languageConfig->getString('recipients'));
        $subject = $languageConfig->getString('email_subject');

        if ($originalRecipients !== '') {
            $subject = '[urspr. Empfänger: ' . $originalRecipients . '] ' . $subject;
        }

        $newLanguageConfig = $languageConfig
            ->withParameter('recipients', $abuseEmail)
            ->withParameter('email_subject', $subject)
            ->withParameter('email_recipient_cc', '')
            ->withParameter('email_recipient_bcc', '');

        $event->setParcel($parcel->withStamp(new LanguageConfigStamp($newLanguageConfig)));

        $this->loggingHelper->logInfo(
            sprintf('Abuse-E-Mail: NC v2 message redirected to %s (original: %s)', $abuseEmail, $originalRecipients),
            __METHOD__
        );
    }
}
