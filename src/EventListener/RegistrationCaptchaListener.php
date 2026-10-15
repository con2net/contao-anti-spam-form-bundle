<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/EventListener/RegistrationCaptchaListener.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Model;
use Contao\ModuleModel;

/**
 * Registration Captcha Listener
 *
 * Ersetzt das Contao-Captcha durch ALTCHA: Ist in einem geschützten Registrierungs-Modul
 * ALTCHA aktiv, wird disableCaptcha NUR im Speicher gesetzt (nie gespeichert), bevor das
 * Modul instanziiert wird. $GLOBALS['TL_FFL']['captcha'] bleibt unangetastet.
 *
 * Bekannte Einschränkung (Contao 4.13): Das Inhaltselement "Modul" ruft isVisibleElement
 * nicht für das Modul auf und baut es aus cloneOriginal(). Dort bleibt das Contao-Captcha
 * zusätzlich sichtbar (redundant, nicht unsicher).
 */
#[AsHook('isVisibleElement')]
class RegistrationCaptchaListener
{
    private const MODULE_TYPES = ['registration', 'registrationNotificationCenter'];

    /**
     * Läuft für jedes Element, daher zuerst die billigen Prüfungen.
     *
     * Gibt $isVisible in allen Zweigen unverändert zurück: ein pauschales true würde von
     * Contao versteckte Elemente (geschützt, "Nur für Gäste" usw.) sichtbar machen.
     */
    public function __invoke(Model $element, bool $isVisible): bool
    {
        if (!$element instanceof ModuleModel || !\in_array($element->type, self::MODULE_TYPES, true)) {
            return $isVisible;
        }

        // Neue Spalten können null sein (Migration ausstehend): lose vergleichen
        if ($element->c2n_enable_antispam && $element->c2n_enable_altcha) {
            $element->disableCaptcha = true;
        }

        return $isVisible;
    }
}
