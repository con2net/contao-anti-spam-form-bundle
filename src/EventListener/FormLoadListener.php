<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/FormLoadListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Con2net\ContaoAntiSpamFormBundle\Service\LoggingHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FormModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form Load Listener
 *
 * Setzt den Server-Timestamp in der Session wenn ein Formular geladen wird.
 * Dieser Timestamp wird später vom AntiSpamFormListener verwendet um die
 * Absende-Zeit zu validieren (zu schnell = Bot, zu langsam = verdächtig).
 */
#[AsHook('compileFormFields', priority: 100)]
class FormLoadListener
{
    private RequestStack $requestStack;
    private LoggingHelper $loggingHelper;

    public function __construct(RequestStack $requestStack, LoggingHelper $loggingHelper)
    {
        $this->requestStack = $requestStack;
        $this->loggingHelper = $loggingHelper;
    }

    /**
     * Hook: compileFormFields
     * Wird aufgerufen wenn das Formular kompiliert/gerendert wird
     */
    public function __invoke(array $fields, string $formId, Form $form): array
    {
        $formIdInt = (int)$formId;

        // Formular-Konfiguration laden
        $formModel = FormModel::findByPk($formIdInt);

        if (!$formModel || !$formModel->c2n_enable_antispam) {
            return $fields;
        }

        // ===== Session über Request holen (Contao 4.13 + 5.3 kompatibel) =====
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            // Kein Request oder keine Session - abbrechen
            if ($formModel->c2n_debug) {
                $this->loggingHelper->logError(
                    sprintf('⚠️ Anti-SPAM: No session available for form %d', $formIdInt),
                    __METHOD__
                );
            }
            return $fields;
        }

        $session = $request->getSession();
        // ====================================================================

        $sessionKey = 'c2n_form_timestamp_' . $formIdInt;

        // Nur setzen wenn noch nicht vorhanden (bei Page-Reload nicht überschreiben!)
        if (!$session->has($sessionKey)) {
            $timestamp = time();
            $session->set($sessionKey, $timestamp);

            if ($formModel->c2n_debug) {
                $this->loggingHelper->logInfo(
                    sprintf('⏱️ Anti-SPAM: Timestamp (%d) stored in SESSION for form %d', $timestamp, $formIdInt),
                    __METHOD__
                );
            }
        } else {
            $existingTimestamp = $session->get($sessionKey);

            if ($formModel->c2n_debug) {
                $this->loggingHelper->logInfo(
                    sprintf('⏱️ Anti-SPAM: Using existing SESSION timestamp (%d) for form %d', $existingTimestamp, $formIdInt),
                    __METHOD__
                );
            }
        }

        return $fields;
    }
}