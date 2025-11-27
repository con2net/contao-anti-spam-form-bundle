<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/languages/de/tl_form_field.php

declare(strict_types=1);

/**
 * Formularfeld-Typen
 */

// ===== HONEYPOT WIDGETS =====

$GLOBALS['TL_LANG']['FFL']['c2n_honeypot'] = [
    'Honeypot (Text)',
    'Verstecktes Textfeld zum Erkennen von Bots. Empfohlene Labels: "Website", "Firma", "Telefon"'
];

$GLOBALS['TL_LANG']['FFL']['c2n_honeypot_textarea'] = [
    'Honeypot (Textarea)',
    'Verstecktes Textarea-Feld zum Erkennen von Bots. Empfohlene Labels: "Weitere Informationen", "Kommentar", "Anmerkungen"'
];

$GLOBALS['TL_LANG']['FFL']['c2n_honeypot_checkbox'] = [
    'Honeypot (Checkbox)',
    'Versteckte Checkbox zum Erkennen von Bots. Empfohlene Labels: "Newsletter abonnieren", "Updates erhalten"'
];

// ===== ALTCHA WIDGET =====

$GLOBALS['TL_LANG']['FFL']['c2n_altcha'] = [
    'ALTCHA Anti-SPAM Widget',
    'Modernes, barrierefreies Captcha-System ohne externe Tracking-Dienste. WICHTIG: "ALTCHA Captcha aktivieren" muss im Formular aktiviert sein! Konfiguration erfolgt in der config.yml.'
];
