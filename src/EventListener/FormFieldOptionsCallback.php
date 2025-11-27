<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/FormFieldOptionsCallback.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\DataContainer;
use Contao\FormFieldModel;

/**
 * Callback-Klasse für die Auswahl von Formularfeldern in der Content-Analyse
 *
 * Lädt alle Felder des aktuellen Formulars und gibt sie als Options-Array zurück.
 * FILTERT automatisch Feldtypen die für Content-Analyse nicht sinnvoll sind.
 *
 * @author con2net / Stefan Meise
 */
class FormFieldOptionsCallback
{
    /**
     * Feldtypen die NICHT für Content-Analyse geeignet sind
     */
    private const EXCLUDED_FIELD_TYPES = [
        'submit',                    // Submit-Button
        'checkbox',                  // Checkboxen
        'radio',                     // Radio-Buttons
        'select',                    // Dropdowns
        'hidden',                    // Versteckte Felder
        'captcha',                   // Standard-Captcha
        'c2n_honeypot',             // Honeypot-Felder
        'c2n_honeypot_textarea',
        'c2n_honeypot_checkbox',
        'c2n_altcha',               // ALTCHA
        'upload',                    // Upload-Felder
        'password',                  // Passwort-Felder
        'explanation',               // Erklärungstexte
        'html',                      // HTML-Felder
        'range',                     // Range-Slider
        'fieldset',                  // Fieldset
        'fieldsetStart',
        'fieldsetStop'
    ];

    /**
     * Lädt alle Formularfelder des aktuellen Formulars
     * FILTERT automatisch ungeeignete Feldtypen!
     *
     * @param DataContainer $dc
     * @return array Options-Array: ['feldname' => 'Label (feldname) - Typ']
     */
    public function getFormFields(DataContainer $dc): array
    {
        // Prüfen ob DataContainer gültig ist
        if (!$dc || !$dc->id) {
            return [];
        }

        // Alle Formularfelder dieses Formulars laden
        $fields = FormFieldModel::findBy('pid', $dc->id, ['order' => 'sorting']);

        if (!$fields) {
            return [
                '_none' => '(Noch keine Formularfelder vorhanden)'
            ];
        }

        $options = [];
        $filteredCount = 0;

        foreach ($fields as $field) {
            // ===== Feldtypen filtern =====
            if (in_array($field->type, self::EXCLUDED_FIELD_TYPES)) {
                $filteredCount++;
                continue; // Überspringen!
            }
            // ==================================

            // Label oder Feldname als Anzeige
            $label = $field->label ?: $field->name;

            // Feldtyp ermitteln (für bessere Übersicht)
            $type = $this->getFieldTypeLabel($field->type);

            // Format: "Label (feldname) - Typ"
            $displayText = sprintf(
                '%s (%s)%s',
                $label,
                $field->name,
                $type ? ' - ' . $type : ''
            );

            $options[$field->name] = $displayText;
        }

        // Wenn ALLE Felder gefiltert wurden
        if (empty($options)) {
            return [
                '_none' => sprintf(
                    '(Keine geeigneten Felder gefunden - %d Feld(er) ausgefiltert: Submit, Checkbox, Select, etc.)',
                    $filteredCount
                )
            ];
        }

        return $options;
    }

    /**
     * Gibt den deutschen Namen des Feldtyps zurück
     *
     * @param string $type Contao Feldtyp
     * @return string Deutscher Name
     */
    private function getFieldTypeLabel(string $type): string
    {
        $typeLabels = [
            'text' => 'Text',
            'textarea' => 'Textarea',
            'email' => 'E-Mail',

            // Diese sollten eigentlich gefiltert sein, aber zur Sicherheit ;-)
            'select' => 'Select',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio',
            'upload' => 'Upload',
            'hidden' => 'Hidden',
            'captcha' => 'Captcha',
            'submit' => 'Submit',
            'explanation' => 'Erklärung',
            'html' => 'HTML',
            'password' => 'Passwort',
            'range' => 'Range'
        ];

        return $typeLabels[$type] ?? '';
    }
}