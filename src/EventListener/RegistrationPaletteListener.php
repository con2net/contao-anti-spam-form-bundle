<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationPaletteListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\System;

/**
 * Registration Palette Listener
 *
 * Fügt die Anti-SPAM Legende in die Registrierungs-Paletten von tl_module ein.
 * Läuft per loadDataContainer-Hook erst nach allen DCA-Dateien, damit auch die
 * palette "registrationNotificationCenter" (NotificationCenter 2.x) erfasst wird -
 * unser Bundle wird nur nach dem Core geladen, nicht nach dem NotificationCenter.
 *
 * Die Felder der Content-Analyse verwenden die Texte von tl_form, daher wird dessen
 * Sprachdatei mitgeladen.
 */
#[AsHook('loadDataContainer')]
class RegistrationPaletteListener
{
    private const PALETTES = ['registration', 'registrationNotificationCenter'];

    public function __invoke(string $table): void
    {
        if ('tl_module' !== $table) {
            return;
        }

        System::loadLanguageFile('tl_form');

        foreach (self::PALETTES as $palette) {
            if (!isset($GLOBALS['TL_DCA']['tl_module']['palettes'][$palette])) {
                continue;
            }

            PaletteManipulator::create()
                ->addLegend('antispam_legend', 'config_legend', PaletteManipulator::POSITION_AFTER)
                ->addField('c2n_enable_antispam', 'antispam_legend', PaletteManipulator::POSITION_APPEND)
                ->addLegend('content_analysis_legend', 'antispam_legend', PaletteManipulator::POSITION_AFTER)
                ->addField('c2n_enable_content_analysis', 'content_analysis_legend', PaletteManipulator::POSITION_APPEND)
                ->applyToPalette($palette, 'tl_module');
        }
    }
}
