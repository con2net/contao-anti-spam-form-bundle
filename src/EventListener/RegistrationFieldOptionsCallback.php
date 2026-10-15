<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationFieldOptionsCallback.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Feldauswahl der Content-Analyse in Registrierungs-Modulen
 *
 * Gegenstück zu FormFieldOptionsCallback: liefert die editierbaren Felder des
 * Moduls (gespeicherter Stand), ohne Feldtypen, die für die Analyse nicht taugen.
 */
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_urls.options')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_special_chars.options')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_short_message.options')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_repetitive.options')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_uppercase.options')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_fields_keywords.options')]
class RegistrationFieldOptionsCallback
{
    private const EXCLUDED_INPUT_TYPES = [
        'password',
        'upload',
        'fileTree',
        'checkbox',
        'checkboxWizard',
        'radio',
        'select',
    ];

    public function __invoke(DataContainer $dc): array
    {
        $module = $dc->id ? ModuleModel::findByPk($dc->id) : null;

        if (null === $module) {
            return [];
        }

        Controller::loadDataContainer('tl_member');
        System::loadLanguageFile('tl_member');

        $options = [];

        foreach (StringUtil::deserialize($module->editable, true) as $field) {
            $config = $GLOBALS['TL_DCA']['tl_member']['fields'][$field] ?? null;

            if (!\is_array($config) || \in_array($config['inputType'] ?? null, self::EXCLUDED_INPUT_TYPES, true)) {
                continue;
            }

            $label = $config['label'][0] ?? $GLOBALS['TL_LANG']['tl_member'][$field][0] ?? $field;
            $options[$field] = sprintf('%s (%s)', $label, $field);
        }

        return $options;
    }
}
