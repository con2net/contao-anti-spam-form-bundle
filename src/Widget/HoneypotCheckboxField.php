<?php
// File: src/Widget/HoneypotCheckboxField.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Widget;

use Contao\Widget;
use Contao\Input;
use Contao\System;

class HoneypotCheckboxField extends Widget
{
    protected $strTemplate = 'form_c2n_honeypot_checkbox';
    protected $blnSubmitInput = true;

    public function validate()
    {
        $postValue = Input::post($this->strName);

        if (!empty($postValue) && $postValue !== '*** SPAM ***') {
            $this->varValue = '*** SPAM *** ';

            $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');
            $loggingHelper->logError(
                sprintf(
                    'SPAM DETECTED in Checkbox-Honeypot "%s"! Bot checked the box (value: "%s")',
                    $this->strName,
                    $postValue
                ),
                __METHOD__
            );

            $this->blnSubmitInput = true;
        } else {
            $this->varValue = '';
            $this->blnSubmitInput = true;
        }
    }

    public function generate()
    {
        return $this->parse();
    }
}