<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/dca/tl_form.php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * tl_form DCA - Anti-SPAM Einstellungen + Content-Analyse
 */

// ===== Palette für Anti-SPAM =====
PaletteManipulator::create()
    ->addLegend('antispam_legend', 'store_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('c2n_enable_antispam', 'antispam_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_form');

// Sub-Palette wenn Anti-SPAM aktiviert
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_enable_antispam';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_enable_antispam'] =
    'c2n_min_submit_time,c2n_max_submit_time,c2n_spam_prefix,c2n_block_spam,c2n_enable_altcha,c2n_enable_ip_blacklist,c2n_debug';

// ===== Palette für Content-Analyse =====
PaletteManipulator::create()
    ->addLegend('content_analysis_legend', 'antispam_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('c2n_enable_content_analysis', 'content_analysis_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_form');

// ===== Sub-Palette Level 1 - Content-Analyse aktiviert =====
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_enable_content_analysis';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_enable_content_analysis'] =
    'c2n_content_spam_threshold,' .
    'c2n_content_check_urls,' .
    'c2n_content_check_special_chars,' .
    'c2n_content_check_tempmail,' .
    'c2n_content_check_short_message,' .
    'c2n_content_check_repetitive,' .
    'c2n_content_check_uppercase,' .
    'c2n_content_check_keywords';

// ===== Sub-Palette Level 2 - Einzelne Tests =====

// URLs im Text
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_urls';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_urls'] =
    'c2n_content_score_urls,c2n_content_fields_urls';

// Nur Sonderzeichen
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_special_chars';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_special_chars'] =
    'c2n_content_score_special_chars,c2n_content_fields_special_chars';

// Tempmail-Adressen
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_tempmail';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_tempmail'] =
    'c2n_content_score_tempmail,c2n_content_tempmail_domains';

// Nachricht zu kurz
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_short_message';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_short_message'] =
    'c2n_content_score_short_message,c2n_content_min_message_length,c2n_content_fields_short_message';

// Repetitive Zeichen
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_repetitive';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_repetitive'] =
    'c2n_content_score_repetitive,c2n_content_fields_repetitive';

// Großbuchstaben
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_uppercase';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_uppercase'] =
    'c2n_content_score_uppercase,c2n_content_max_uppercase_ratio,c2n_content_fields_uppercase';

// SPAM-Keywords
$GLOBALS['TL_DCA']['tl_form']['palettes']['__selector__'][] = 'c2n_content_check_keywords';
$GLOBALS['TL_DCA']['tl_form']['subpalettes']['c2n_content_check_keywords'] =
    'c2n_content_score_keywords,c2n_content_spam_keywords,c2n_content_fields_keywords';

/**
 * ===== FELDER DEFINIEREN =====
 */

// ========== Anti-SPAM Felder==========

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_enable_antispam'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_enable_antispam'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_min_submit_time'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_min_submit_time'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '10',
    'eval' => [
        'mandatory' => true,
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '10'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_max_submit_time'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_max_submit_time'],
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

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_spam_prefix'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_spam_prefix'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '*** SPAM ***&nbsp;',
    'eval' => [
        'maxlength' => 255,
        'tl_class' => 'w50',
        'placeholder' => '*** SPAM ***&nbsp;'
    ],
    'sql' => "varchar(255) NOT NULL default '*** SPAM ***&nbsp;'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_block_spam'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_block_spam'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_enable_altcha'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_enable_altcha'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_enable_ip_blacklist'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_enable_ip_blacklist'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_debug'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_debug'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ],
    'sql' => "char(1) NOT NULL default ''"
];

// ========== Content-Analyse Felder  ==========

// Hauptschalter
$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_enable_content_analysis'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_enable_content_analysis'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

// Allgemein: SPAM-Schwellwert
$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_spam_threshold'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_threshold'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '50',
    'eval' => [
        'mandatory' => true,
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'long'
    ],
    'sql' => "int(3) unsigned NOT NULL default '50'"
];

// ===== Test 1: URLs im Text =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_urls'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_urls'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_urls'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_urls'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '50',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '50'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_urls'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_urls'],
    'exclude' => true,
    'inputType' => 'select',  // GEÄNDERT: checkbox → select
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

// ===== Test 2: Nur Sonderzeichen =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_special_chars'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_special_chars'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_special_chars'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_special_chars'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '40',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '40'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_special_chars'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_special_chars'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

// ===== Test 3: Tempmail-Adressen =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_tempmail'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_tempmail'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_tempmail'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_tempmail'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '30',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '30'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_tempmail_domains'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_tempmail_domains'],
    'exclude' => true,
    'inputType' => 'textarea',
    'default' => "tempmail.com\n10minutemail.com\nguerrillamail.com\nmailinator.com\nthrowaway.email\ntrashmail.com\nyopmail.com\ngetnada.com\ntemp-mail.org\nmaildrop.cc\nfakeinbox.com\nsharklasers.com",
    'eval' => [
        'style' => 'height:120px',
        'tl_class' => 'clr long'
    ],
    'sql' => "text NULL"
];

// ===== Test 4: Nachricht zu kurz =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_short_message'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_short_message'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_short_message'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_short_message'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '25',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '25'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_min_message_length'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_min_message_length'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '10',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '10'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_short_message'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_short_message'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

// ===== Test 5: Repetitive Zeichen =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_repetitive'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_repetitive'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_repetitive'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_repetitive'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '20',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '20'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_repetitive'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_repetitive'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

// ===== Test 6: Großbuchstaben =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_uppercase'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_uppercase'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_uppercase'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_uppercase'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '15',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '15'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_max_uppercase_ratio'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_max_uppercase_ratio'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '60',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '60'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_uppercase'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_uppercase'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];

// ===== Test 7: SPAM-Keywords =====

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_check_keywords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_check_keywords'],
    'exclude' => true,
    'inputType' => 'checkbox',
    'default' => '',
    'eval' => [
        'tl_class' => 'clr w50 m12',
        'submitOnChange' => true
    ],
    'sql' => "char(1) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_score_keywords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_score_keywords'],
    'exclude' => true,
    'inputType' => 'text',
    'default' => '10',
    'eval' => [
        'rgxp' => 'natural',
        'maxlength' => 3,
        'tl_class' => 'w50'
    ],
    'sql' => "int(3) unsigned NOT NULL default '10'"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_spam_keywords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_spam_keywords'],
    'exclude' => true,
    'inputType' => 'textarea',
    'default' => "viagra, cialis, casino, poker, lottery, crypto, bitcoin, forex, loan, seo, backlink",
    'eval' => [
        'style' => 'height:80px',
        'tl_class' => 'clr long'
    ],
    'sql' => "text NULL"
];

$GLOBALS['TL_DCA']['tl_form']['fields']['c2n_content_fields_keywords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_form']['c2n_content_fields_keywords'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => ['con2net.antispam.form_field_callback', 'getFormFields'],
    'eval' => [
        'multiple' => true,
        'chosen' => true,
        'tl_class' => 'clr long'
    ],
    'sql' => "blob NULL"
];