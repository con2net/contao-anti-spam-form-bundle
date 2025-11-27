<?php
// File: src/ContaoAntiSpamFormBundle.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Contao Anti-SPAM Form Bundle
 *
 * Universal SPAM protection for Contao forms
 */
class ContaoAntiSpamFormBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return __DIR__;
    }
}