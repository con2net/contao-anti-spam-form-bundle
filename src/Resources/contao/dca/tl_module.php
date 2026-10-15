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

// ===== Content-Analyse =====
// Gleiche Felder, Defaults und Sub-Paletten wie in tl_form. Die Texte kommen aus
// der Sprachdatei von tl_form (lädt RegistrationPaletteListener), die Feldauswahl
// aus RegistrationFieldOptionsCallback (editierbare Felder des Moduls).

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_enable_content_analysis';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_enable_content_analysis'] =
    'c2n_content_spam_threshold,' .
    'c2n_content_check_urls,' .
    'c2n_content_check_special_chars,' .
    'c2n_content_check_tempmail,' .
    'c2n_content_check_short_message,' .
    'c2n_content_check_repetitive,' .
    'c2n_content_check_uppercase,' .
    'c2n_content_check_keywords';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_urls';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_urls'] =
    'c2n_content_score_urls,c2n_content_fields_urls';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_special_chars';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_special_chars'] =
    'c2n_content_score_special_chars,c2n_content_fields_special_chars';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_tempmail';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_tempmail'] =
    'c2n_content_score_tempmail,c2n_content_tempmail_domains';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_short_message';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_short_message'] =
    'c2n_content_score_short_message,c2n_content_min_message_length,c2n_content_fields_short_message';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_repetitive';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_repetitive'] =
    'c2n_content_score_repetitive,c2n_content_fields_repetitive';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_uppercase';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_uppercase'] =
    'c2n_content_score_uppercase,c2n_content_max_uppercase_ratio,c2n_content_fields_uppercase';

$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'c2n_content_check_keywords';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['c2n_content_check_keywords'] =
    'c2n_content_score_keywords,c2n_content_spam_keywords,c2n_content_fields_keywords';

$c2nCheckbox = static fn (string $tlClass): array => [
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => $tlClass,
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$c2nNumber = static fn (int $default, bool $mandatory = false, string $tlClass = 'w50'): array => [
    'exclude' => true,
    'inputType' => 'text',
    'default' => (string) $default,
    'eval' => [
        'mandatory' => $mandatory,
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => $tlClass
    ],
    'sql' => "int(3) unsigned NOT NULL default '$default'"
];

$c2nFieldSelect = [
    'exclude' => true,
    'inputType' => 'select',
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

$c2nContentFields = [
    'c2n_enable_content_analysis' => $c2nCheckbox('w50 m12'),
    'c2n_content_spam_threshold' => $c2nNumber(50, true, 'long'),

    'c2n_content_check_urls' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_urls' => $c2nNumber(50),
    'c2n_content_fields_urls' => $c2nFieldSelect,

    'c2n_content_check_special_chars' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_special_chars' => $c2nNumber(40),
    'c2n_content_fields_special_chars' => $c2nFieldSelect,

    'c2n_content_check_tempmail' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_tempmail' => $c2nNumber(30),
    'c2n_content_tempmail_domains' => [
        'exclude' => true,
        'inputType' => 'textarea',
        'default' => "tempmail.com\n10minutemail.com\nguerrillamail.com\nmailinator.com\nthrowaway.email\ntrashmail.com\nyopmail.com\ngetnada.com\ntemp-mail.org\nmaildrop.cc\nfakeinbox.com\nsharklasers.com",
        'eval' => [
            'style' => 'height:120px',
            'tl_class' => 'clr long'
        ],
        'sql' => "text NULL"
    ],

    'c2n_content_check_short_message' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_short_message' => $c2nNumber(25),
    'c2n_content_min_message_length' => $c2nNumber(10),
    'c2n_content_fields_short_message' => $c2nFieldSelect,

    'c2n_content_check_repetitive' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_repetitive' => $c2nNumber(20),
    'c2n_content_fields_repetitive' => $c2nFieldSelect,

    'c2n_content_check_uppercase' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_uppercase' => $c2nNumber(15),
    'c2n_content_max_uppercase_ratio' => $c2nNumber(60),
    'c2n_content_fields_uppercase' => $c2nFieldSelect,

    'c2n_content_check_keywords' => $c2nCheckbox('clr w50 m12'),
    'c2n_content_score_keywords' => $c2nNumber(10),
    'c2n_content_spam_keywords' => [
        'exclude' => true,
        'inputType' => 'textarea',
        'default' => "viagra, cialis, casino, poker, lottery, crypto, bitcoin, forex, loan, seo, backlink",
        'eval' => [
            'style' => 'height:80px',
            'tl_class' => 'clr long'
        ],
        'sql' => "text NULL"
    ],
    'c2n_content_fields_keywords' => $c2nFieldSelect,
];

foreach ($c2nContentFields as $c2nName => $c2nConfig) {
    $GLOBALS['TL_DCA']['tl_module']['fields'][$c2nName] = ['label' => &$GLOBALS['TL_LANG']['tl_form'][$c2nName]] + $c2nConfig;
}

unset($c2nCheckbox, $c2nNumber, $c2nFieldSelect, $c2nContentFields, $c2nName, $c2nConfig);
