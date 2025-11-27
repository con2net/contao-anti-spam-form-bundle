<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/Resources/contao/dca/tl_form_field.php

declare(strict_types=1);

/**
 * tl_form_field DCA - Honeypot & ALTCHA Widgets
 */

// ===== HONEYPOT PALETTEN =====

$GLOBALS['TL_DCA']['tl_form_field']['palettes']['c2n_honeypot'] =
    '{type_legend},type,name,label;'
    . '{text_legend},placeholder;'
    . '{expert_legend:hide},class,accesskey,tabindex;'
    . '{template_legend:hide},customTpl;'
    . '{invisible_legend:hide},invisible';

$GLOBALS['TL_DCA']['tl_form_field']['palettes']['c2n_honeypot_textarea'] =
    '{type_legend},type,name,label;'
    . '{text_legend},placeholder;'
    . '{expert_legend:hide},class,accesskey,tabindex;'
    . '{template_legend:hide},customTpl;'
    . '{invisible_legend:hide},invisible';

$GLOBALS['TL_DCA']['tl_form_field']['palettes']['c2n_honeypot_checkbox'] =
    '{type_legend},type,name,label;'
    . '{expert_legend:hide},class,accesskey,tabindex;'
    . '{template_legend:hide},customTpl;'
    . '{invisible_legend:hide},invisible';

// ===== ALTCHA PALETTE =====

$GLOBALS['TL_DCA']['tl_form_field']['palettes']['c2n_altcha'] =
    '{type_legend},type,name,label;'
    . '{expert_legend:hide},class;'
    . '{template_legend:hide},customTpl;'
    . '{invisible_legend:hide},invisible';