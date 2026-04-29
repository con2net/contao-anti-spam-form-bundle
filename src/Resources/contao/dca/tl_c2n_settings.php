<?php
// File: src/Resources/contao/dca/tl_c2n_settings.php

declare(strict_types=1);

/**
 * Shared settings table for all con2net bundles.
 *
 * Each bundle stores its settings using a unique bundle identifier
 * as namespace, allowing multiple bundles to share this table
 * without conflicts.
 *
 * Structure:
 *   bundle        - bundle identifier (e.g. 'antispam', 'activecampaign')
 *   setting_key   - setting name within the bundle namespace
 *   setting_value - setting value (text, supports JSON for complex values)
 *
 * Example rows:
 *   | bundle      | setting_key      | setting_value |
 *   | antispam    | altcha_hmac_key  | abc123...     |
 *   | activecampaign | api_key       | xyz...        |
 */
$GLOBALS['TL_DCA']['tl_c2n_settings'] = [
    'config' => [
        'dataContainer' => 'Table',
        'sql'           => [
            'keys' => [
                'id'                    => 'primary',
                'bundle'                => 'index',
                'bundle,setting_key'    => 'unique',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => [
                'type'          => 'integer',
                'unsigned'      => true,
                'autoincrement' => true,
            ],
        ],
        'bundle' => [
            'sql' => [
                'type'    => 'string',
                'length'  => 128,
                'default' => '',
            ],
        ],
        'setting_key' => [
            'sql' => [
                'type'    => 'string',
                'length'  => 128,
                'default' => '',
            ],
        ],
        'setting_value' => [
            'sql' => [
                'type'    => 'text',
                'notnull' => false,
            ],
        ],
    ],
];