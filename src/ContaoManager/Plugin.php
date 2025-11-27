<?php
// File: src/ContaoManager/Plugin.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Con2net\ContaoAntiSpamFormBundle\ContaoAntiSpamFormBundle;

/**
 * Plugin für den Contao Manager
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoAntiSpamFormBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
        ];
    }
}