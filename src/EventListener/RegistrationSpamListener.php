<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationSpamListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Con2net\ContaoAntiSpamFormBundle\Service\RegistrationReportMailer;
use Con2net\ContaoAntiSpamFormBundle\Service\RegistrationSpamChecker;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Registration Spam Listener
 *
 * Zentrale Prüfung im onload_callback von tl_member. Er läuft in
 * ModuleRegistration::compile() als Erstes, also vor dem Contao-Captcha, vor dem
 * erneuten Versand der Aktivierungsmail und vor dem Insert.
 * Spam wird verworfen oder am Formular als Fehler gemeldet, ohne dass ein Mitglied
 * angelegt wird.
 *
 * Der Callback läuft auch im Backend und in "Persönliche Daten" (tl_member_<ID>),
 * daher Filter auf Frontend und FORM_SUBMIT-Präfix tl_registration_.
 */
#[AsCallback(table: 'tl_member', target: 'config.onload')]
#[AsHook('createNewUser', method: 'onCreateNewUser')]
class RegistrationSpamListener
{
    private const MODULE_TYPES = ['registration', 'registrationNotificationCenter'];
    private const UPLOAD_INPUT_TYPES = ['upload', 'fileTree'];
    private const CHECKED_ATTRIBUTE = '_c2n_registration_checked';

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private RegistrationSpamChecker $checker;
    private LoggingHelper $loggingHelper;
    private RegistrationReportMailer $reportMailer;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher,
        RegistrationSpamChecker $checker,
        LoggingHelper $loggingHelper,
        RegistrationReportMailer $reportMailer
    )
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->checker = $checker;
        $this->loggingHelper = $loggingHelper;
        $this->reportMailer = $reportMailer;
    }

    /**
     * onload_callback: im Frontend ohne Argument, im Backend mit DataContainer
     */
    public function __invoke($dc = null): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        $formSubmit = $_POST['FORM_SUBMIT'] ?? null;

        if (!\is_string($formSubmit) || !str_starts_with($formSubmit, RegistrationMarkupListener::FORM_ID_PREFIX)) {
            return;
        }

        // NC 2.x rendert sein Modul als Fragment (Sub-Request): Merker und Reload-URL
        // gehören an den Main-Request
        $mainRequest = $this->requestStack->getMainRequest() ?? $request;

        // Stehen mehrere Registrierungs-Module auf einer Seite, läuft der Callback mehrfach
        if ($mainRequest->attributes->get(self::CHECKED_ATTRIBUTE)) {
            return;
        }

        $mainRequest->attributes->set(self::CHECKED_ATTRIBUTE, true);

        $moduleId = (int) substr($formSubmit, \strlen(RegistrationMarkupListener::FORM_ID_PREFIX));
        $module = ModuleModel::findByPk($moduleId);

        if (null === $module || !\in_array($module->type, self::MODULE_TYPES, true) || !$module->c2n_enable_antispam) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $reason = $this->checker->check($module, $session, $mainRequest->getClientIp());

        if (null === $reason) {
            if ($module->c2n_debug) {
                $this->loggingHelper->logInfo(
                    sprintf('Anti-SPAM: Registration module %d passed all checks', $moduleId),
                    __METHOD__
                );
            }

            return;
        }

        // Neuer Timestamp, wie im Formular-Pfad
        if (null !== $session) {
            $session->set(RegistrationMarkupListener::SESSION_KEY_PREFIX . $moduleId, time());
        }

        if ($module->c2n_debug) {
            $message = sprintf('Anti-SPAM: Registration module "%s" (ID: %d) - SPAM DETECTED: %s', $module->name, $moduleId, $reason);
        } else {
            $message = sprintf('Anti-SPAM: Registration module "%s" (ID: %d) - SPAM DETECTED', $module->name, $moduleId);
        }

        $this->loggingHelper->logError($message, __METHOD__);

        if ('error' === $module->c2n_spam_reaction) {
            // Die Prüfung auf eine Folge-Registrierung läuft in compile() vor jedem
            // save_callback und verschickt die Aktivierungsmail erneut (alle Versionen).
            // In dem Fall ist ein Feldfehler zu spät, also still verwerfen.
            if ($this->wouldResendActivationMail($module)) {
                if ($module->c2n_debug) {
                    $this->loggingHelper->logInfo(
                        sprintf('Anti-SPAM: Registration module %d - e-mail belongs to an unactivated member, discarding instead of showing an error', $moduleId),
                        __METHOD__
                    );
                }
            } elseif ($this->addFieldError($module)) {
                return;
            }
        }

        if ('report' === $module->c2n_spam_reaction) {
            $this->reportMailer->send($module, $reason, $mainRequest);
        }

        // "discard" und "report": stillschweigend verwerfen und wie bei Erfolg weiterleiten
        throw new RedirectResponseException($this->getDiscardUrl($module, $mainRequest));
    }

    /**
     * createNewUser: Timestamp nach dem Insert entfernen (der onload_callback darf das
     * nicht, weil die Feldvalidierung danach noch scheitern kann).
     */
    public function onCreateNewUser($userId, array $data, $module = null): void
    {
        if (!\is_object($module) || empty($module->id)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request && $request->hasSession()) {
            $request->getSession()->remove(RegistrationMarkupListener::SESSION_KEY_PREFIX . (int) $module->id);
        }
    }

    /**
     * Gleiche Bedingung wie die Folge-Registrierung in ModuleRegistration::compile().
     * Das Modul von NC 2.x erzwingt reg_activate.
     */
    private function wouldResendActivationMail(ModuleModel $module): bool
    {
        if (!$module->reg_activate && 'registrationNotificationCenter' !== $module->type) {
            return false;
        }

        $email = Input::post('email', true);

        return $email && null !== MemberModel::findUnactivatedByEmail($email);
    }

    /**
     * Hängt zur Laufzeit einen save_callback an die editierbaren Felder (ohne Uploads).
     * Die Feldschleife in compile() liest das DCA erst nach dem onload_callback. Der
     * erste Callback, der läuft, wirft: Contao macht daraus einen Fehler an diesem
     * Feld und legt kein Mitglied an. Ein Callback läuft nur bei einem Feld ohne
     * eigenen Fehler, sonst verhindert dieser Fehler den Insert ohnehin.
     *
     * @return bool false, wenn es kein passendes Feld gibt
     */
    private function addFieldError(ModuleModel $module): bool
    {
        $message = $GLOBALS['TL_LANG']['ERR']['c2nSpamBlocked']
            ?? 'Your request could not be processed. Please try again later.';

        $thrown = false;

        $callback = static function ($value) use (&$thrown, $message) {
            if ($thrown) {
                return $value;
            }

            $thrown = true;

            throw new \RuntimeException($message);
        };

        $added = false;

        foreach (StringUtil::deserialize($module->editable, true) as $field) {
            $config = $GLOBALS['TL_DCA']['tl_member']['fields'][$field] ?? null;

            if (!\is_array($config) || \in_array($config['inputType'] ?? null, self::UPLOAD_INPUT_TYPES, true)) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['save_callback'][] = $callback;
            $added = true;
        }

        return $added;
    }

    /**
     * Weiterleitungsseite des Moduls, sonst die aktuelle Seite (wie jumpToOrReload())
     */
    private function getDiscardUrl(ModuleModel $module, Request $request): string
    {
        if ($module->jumpTo && null !== ($page = PageModel::findPublishedById((int) $module->jumpTo))) {
            return $page->getAbsoluteUrl();
        }

        return $request->getUri();
    }
}
