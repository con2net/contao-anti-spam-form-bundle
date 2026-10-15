<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/dca/tl_module.php

declare(strict_types=1);

/**
 * tl_module DCA - Anti-SPAM Einstellungen für Registrierungs-Module
 *
 * Die Legende wird per loadDataContainer-Hook in die Paletten "registration"
 * und (falls vorhanden) "registrationNotificationCenter" eingefügt, siehe
 * RegistrationPaletteListener.
 */

// Sub-Palette wenn Anti-SPAM aktiviert
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_enable_antispam';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_enable_antispam'] =
    'c2n_min_submit_time,c2n_max_submit_time,c2n_spam_reaction,c2n_enable_altcha,c2n_enable_ip_blacklist,c2n_debug';

// Sub-Palette für die Spam-Reaktion "Bericht per E-Mail" (Select: Schlüssel <feld>_<wert>)
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_spam_reaction';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_spam_reaction_report'] = 'c2n_report_email';

// ===== Anti-SPAM Felder =====

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_enable_antispam'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_enable_antispam'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_min_submit_time'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_min_submit_time'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '10',
    'eval' => [
        'mandatory' => true,
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'clr w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '10'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_max_submit_time'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_max_submit_time'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '0',
    'eval' => [
        'mandatory' => true,
        'rgxp' => 'natural',
        'maxlength' => 4,
        'tl_class' => 'w50'
    ],
    'sql' => "int(4) unsigned NOT NULL default '0'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_spam_reaction'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction'],
    'explanation' => 'c2n_spam_reaction',
    'exclude' => true,
    'inputType' => 'select',
    'default' => 'error',
    'options' => ['discard', 'error', 'report'],
    'reference' => &$GLOBALS['TL_LANG']['tl_module']['c2n_spam_reaction_options'],
    'eval' => [
        'chosen' => true,
        'submitOnChange' => true,
        'tl_class' => 'clr w50',
        'helpwizard' => true
    ],
    'sql' => "varchar(16) NOT NULL default 'error'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_report_email'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_report_email'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => [
        'mandatory' => true,
        'rgxp' => 'emails',
        'maxlength' => 255,
        'decodeEntities' => true,
        'tl_class' => 'w50'
    ],
    'sql' => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_enable_altcha'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_enable_altcha'],
    'explanation' => 'c2n_registration_altcha',
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '1',
    'eval' => [
        'tl_class' => 'w50 m12',
        'helpwizard' => true
    ],
    'sql' => "char(1) NOT NULL default '1'"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_enable_ip_blacklist'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_enable_ip_blacklist'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['c2n_debug'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_module']['c2n_debug'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'clr w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];
