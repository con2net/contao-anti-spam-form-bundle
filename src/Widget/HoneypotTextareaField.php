<?php
// File: src/Widget/HoneypotTextareaField.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\Widget;

use Contao\Widget;
use Contao\Input;
use Contao\System;

class HoneypotTextareaField extends Widget
{
    protected $strTemplate = 'form_c2n_honeypot_textarea';
    protected $blnSubmitInput = true;

    public function validate()
    {
        $postValue = Input::postUnsafeRaw($this->strName);

        if (!empty($postValue) && $postValue !== '*** SPAM ***') {
            $spamPatterns = [
                'http://', 'https://', 'www.', '.com', '.net', '.org',
                '<a href', '[url=', '[link=',
                'buy now', 'click here', 'visit us',
                'casino', 'viagra', 'cialis', 'loan', 'crypto'
            ];

            $detectedPatterns = [];
            foreach ($spamPatterns as $pattern) {
                if (stripos($postValue, $pattern) !== false) {
                    $detectedPatterns[] = $pattern;
                }
            }

            $this->varValue = '*** SPAM *** ';

            $logMessage = sprintf(
                'SPAM DETECTED in Textarea-Honeypot "%s"! Length: %d chars',
                $this->strName,
                strlen($postValue)
            );

            if (!empty($detectedPatterns)) {
                $logMessage .= ' | Detected patterns: ' . implode(', ', $detectedPatterns);
            }

            $logMessage .= ' | Content preview: "' . substr($postValue, 0, 100) . '"';

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