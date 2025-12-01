<?php
// File: src/EventListener/CleanupFormDataListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;

/**
 * Cleanup Listener - Entfernt technische Felder aus raw_data
 */
#[AsHook('prepareFormData', priority: 0)]
class CleanupFormDataListener
{
    public function __invoke(array &$submittedData, array &$labels, array $fields, Form $form): void
    {
        // Technische Felder entfernen AUSSER spam_marker
        $this->removeInternalFields($submittedData, $fields);

        // spam_marker MUSS in $submittedData bleiben für SimpleToken!
        // Aber wir entfernen das Label, damit es nicht in raw_data erscheint
        unset($labels['spam_marker']);
    }

    private function removeInternalFields(array &$submittedData, array $fields): void
    {
        // 1. Alle ALTCHA Felder entfernen
        foreach ($fields as $field) {
            if ($field->type === 'c2n_altcha') {
                unset($submittedData[$field->name]);
            }
        }

        // 2. Alle Honeypot Felder entfernen
        foreach ($fields as $field) {
            if (in_array($field->type, ['c2n_honeypot', 'c2n_honeypot_textarea', 'c2n_honeypot_checkbox'])) {
                unset($submittedData[$field->name]);
            }
        }

        // 3. Challenge-Persistenz Feld entfernen
        foreach ($fields as $field) {
            if ($field->type === 'c2n_altcha') {
                unset($submittedData[$field->name . '_challenge_data']);
            }
        }

        // spam_marker bleibt in $submittedData (für SimpleToken)
        // wird aber über $labels aus raw_data gefiltert
    }
}