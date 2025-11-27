<?php
// File: src/Widget/HoneypotField.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Widget;

use Contao\Widget;
use Contao\Input;
use Contao\System;

class HoneypotField extends Widget
{
    protected $strTemplate = 'form_c2n_honeypot';
    protected $blnSubmitInput = true;

    public function validate()
    {
        $postValue = Input::postUnsafeRaw($this->strName);

        if (!empty($postValue) && $postValue !== '*** SPAM ***') {
            $spamPatterns = [
                'http://', 'https://', 'www.', '.com', '.net', '.org',
                '<a href', '[url=', '[link=',
                'buy now', 'click here', 'visit us'
            ];

            $detectedPatterns = [];
            foreach ($spamPatterns as $pattern) {
                if (stripos($postValue, $pattern) !== false) {
                    $detectedPatterns[] = $pattern;
                }
            }

            $this->varValue = '*** SPAM *** ';

            $logMessage = sprintf(
                'SPAM DETECTED in Text-Honeypot "%s"!',
                $this->strName
            );

            if (!empty($detectedPatterns)) {
                $logMessage .= ' | Detected patterns: ' . implode(', ', $detectedPatterns);
            }

            $logMessage .= ' | Content: "' . substr($postValue, 0, 100) . '"';

            $loggingHelper = System::getContainer()->get('con2net.antispam.logging_helper');
            $loggingHelper->logError($logMessage, __METHOD__);

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