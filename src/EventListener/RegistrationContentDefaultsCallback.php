<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationContentDefaultsCallback.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

/**
 * Default-Listen der Content-Analyse in Registrierungs-Modulen
 *
 * Die Spalten sind für bestehende Module neu und daher leer (der DCA-Default greift
 * nur bei neuen Datensätzen). Bei leerem Wert wird die Default-Liste angezeigt
 * und beim Speichern übernommen.
 */
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_tempmail_domains.load')]
#[AsCallback(table: 'tl_module', target: 'fields.c2n_content_spam_keywords.load')]
class RegistrationContentDefaultsCallback
{
    public function __invoke(mixed $value, DataContainer $dc): mixed
    {
        if (null !== $value && '' !== $value) {
            return $value;
        }

        return $GLOBALS['TL_DCA']['tl_module']['fields'][$dc->field]['default'] ?? $value;
    }
}
