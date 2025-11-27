<?php
// File: vendor/con2net/contao-anti-spam-form-bundle/src/DependencyInjection/Configuration.php

declare(strict_types=1);

namespace Con2net\ContaoAntiSpamFormBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('contao_anti_spam_form');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            // ALTCHA Konfiguration (UNVERÄNDERT!)
            ->arrayNode('altcha')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('max_number')
            ->defaultValue(100000)
            ->min(1000)
            ->max(1000000)
            ->info('Maximum number for the challenge (higher = harder)')
            ->end()
            ->integerNode('salt_length')
            ->defaultValue(16)
            ->min(8)
            ->max(32)
            ->info('Length of the salt (16 = 128 Bit entropy, recommended)')
            ->end()
            ->scalarNode('algorithm')
            ->defaultValue('SHA-256')
            ->validate()
            ->ifNotInArray(['SHA-256', 'SHA-384', 'SHA-512'])
            ->thenInvalid('Invalid algorithm %s')
            ->end()
            ->info('Hash algorithm to use')
            ->end()
            ->integerNode('expires')
            ->defaultValue(900)
            ->min(0)
            ->info('Challenge expiration in seconds (0 = no expiration, recommended: 300-900)')
            ->end()
            ->end()
            ->end()

            // ========== IP Blacklist Konfiguration ==========
            ->arrayNode('ip_blacklist')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('cache_lifetime')
            ->defaultValue(86400)
            ->min(0)
            ->info('Cache lifetime in seconds (0 = no cache, recommended: 86400 = 24h)')
            ->end()
            ->integerNode('api_timeout')
            ->defaultValue(3)
            ->min(1)
            ->max(10)
            ->info('API request timeout in seconds (recommended: 3)')
            ->end()
            ->arrayNode('whitelist')
            ->prototype('scalar')->end()
            ->defaultValue([])
            ->info('IP addresses that will never be blocked (supports CIDR notation like 192.168.1.0/24)')
            ->end()
            ->end()
            ->end()
            // =====================================================

            ->end();

        return $treeBuilder;
    }
}