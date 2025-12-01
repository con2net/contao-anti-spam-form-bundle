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

        // WICHTIG: $_POST direkt nutzen, nicht $this->varValue!
        // Contao\Input korruptiert den Base64-Payload (fügt Zeichen hinzu, ersetzt =)
        $payload = isset($_POST[$this->name]) ? $_POST[$this->name] : '';

        // Sicherstellen dass Wert IMMER ein String ist
        if (is_array($payload)) {
            $payload = implode('', $payload);
        }

        $this->varValue = (string)$payload;  // Für Notification Center

        $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');

        // Payload muss vorhanden sein
        if (empty($payload)) {
            // Fehler loggen (auch ohne Debug-Modus)
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
            // Fehler loggen (auch ohne Debug-Modus)
            $loggingHelper->logError(
                'ALTCHA FAILED: Invalid solution for field "' . $this->name . '"',
                __METHOD__
            );

            $this->addError('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.');
            $this->varValue = '';
        }
        // Erfolg nur im Debug-Modus loggen
        else if (isset($GLOBALS['TL_CONFIG']['c2n_antispam_debug']) && $GLOBALS['TL_CONFIG']['c2n_antispam_debug']) {
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