<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Widget/AltchaFormField.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Widget;

use Contao\Widget;
use Contao\System;

/**
 * ALTCHA Form Field Widget
 *
 * Modernes, barrierefreies Captcha ohne externe Tracking-Dienste
 */
class AltchaFormField extends Widget
{
    protected $strTemplate = 'form_c2n_altcha';
    protected $blnSubmitInput = true;

    /**
     * Validate - ALTCHA Challenge prüfen
     */
    public function validate()
    {
        parent::validate();

        $payload = $this->varValue;

        // Sicherstellen dass Wert IMMER ein String ist (wichtig für Notification Center!)
        if (is_array($payload)) {
            $payload = implode('', $payload);
        }

        $this->varValue = (string)$payload;

        // Payload muss vorhanden sein
        if (empty($payload)) {
            $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');
            $loggingHelper->logError(
                'ALTCHA FAILED: No payload received for field "' . $this->name . '"',
                __METHOD__
            );

            $this->addError('Bitte lösen Sie die Sicherheitsaufgabe.');
            return;
        }

        // ALTCHA Service holen
        $altchaService = System::getContainer()->get('con2net.antispam.altcha_service');

        // Payload validieren
        $isValid = $altchaService->validate($payload);

        if (!$isValid) {
            $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');
            $loggingHelper->logError(
                'ALTCHA FAILED: Invalid solution for field "' . $this->name . '"',
                __METHOD__
            );

            $this->addError('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            $this->varValue = '';
        } else {
            $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');
            $loggingHelper->logInfo(
                'ALTCHA validated successfully for field "' . $this->name . '"',
                __METHOD__
            );
        }
    }

    /**
     * Generate
     */
    public function generate()
    {
        return $this->parse();
    }
}