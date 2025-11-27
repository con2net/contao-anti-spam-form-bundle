<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/DependencyInjection/ContaoAntiSpamFormExtension.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContaoAntiSpamFormExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // ALTCHA HMAC-Key aus ENV
        $container->setParameter('con2net.antispam.altcha.hmac_key', '%env(ALTCHA_HMAC_KEY)%');

        // ALTCHA-Konfiguration als Parameter speichern
        $container->setParameter('con2net.antispam.altcha.max_number', $config['altcha']['max_number']);
        $container->setParameter('con2net.antispam.altcha.salt_length', $config['altcha']['salt_length']);
        $container->setParameter('con2net.antispam.altcha.algorithm', $config['altcha']['algorithm']);
        $container->setParameter('con2net.antispam.altcha.expires', $config['altcha']['expires']);

        // IP Blacklist Parameter
        $container->setParameter('con2net.antispam.ip_blacklist.cache_lifetime', $config['ip_blacklist']['cache_lifetime']);
        $container->setParameter('con2net.antispam.ip_blacklist.api_timeout', $config['ip_blacklist']['api_timeout']);
        $container->setParameter('con2net.antispam.ip_blacklist.whitelist', $config['ip_blacklist']['whitelist']);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yml');
    }
}