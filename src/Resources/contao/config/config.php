<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/config/config.php

declare(strict_types=1);

use Con2net\ContaoAntiSpamFormBundle\Widget\HoneypotField;
use Con2net\ContaoAntiSpamFormBundle\Widget\HoneypotTextareaField;
use Con2net\ContaoAntiSpamFormBundle\Widget\HoneypotCheckboxField;
use Con2net\ContaoAntiSpamFormBundle\Widget\AltchaFormField;

/**
 * Formularfeld-Typen für Contao registrieren
 */

// Honeypot Widgets
$GLOBALS['TL_FFL']['c2n_honeypot'] = HoneypotField::class;
$GLOBALS['TL_FFL']['c2n_honeypot_textarea'] = HoneypotTextareaField::class;
$GLOBALS['TL_FFL']['c2n_honeypot_checkbox'] = HoneypotCheckboxField::class;

// ALTCHA Widget
$GLOBALS['TL_FFL']['c2n_altcha'] = AltchaFormField::class;