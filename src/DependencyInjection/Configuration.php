<?php
// File: src/DependencyInjection/Configuration.php

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
            ->arrayNode('altcha')
            ->addDefaultsIfNotSet()
            ->children()
            ->integerNode('max_number')
            ->defaultValue(100000)
            ->min(1000)
            ->max(1000000)
            ->info('Challenge difficulty (higher = harder for bots, slower for users). Maps to PBKDF2 iterations for pbkdf2/* algorithms.')
            ->end()
            ->integerNode('salt_length')
            ->defaultValue(16)
            ->min(8)
            ->max(32)
            ->info('Salt length in characters (16 = 128 bit entropy). Only used for legacy SHA-256/384/512 algorithms, ignored for pbkdf2/argon2id/scrypt.')
            ->end()
            ->scalarNode('algorithm')
            ->defaultValue('pbkdf2')
            ->validate()
            ->ifNotInArray([
                'pbkdf2',
                'pbkdf2-sha384',
                'pbkdf2-sha512',
                'SHA-256',
                'SHA-384',
                'SHA-512',
                'argon2id',
                'scrypt',
            ])
            ->thenInvalid('Invalid algorithm "%s". Allowed: pbkdf2, pbkdf2-sha384, pbkdf2-sha512, SHA-256, SHA-384, SHA-512, argon2id, scrypt')
            ->end()
            ->info('Hash algorithm. pbkdf2 (default), pbkdf2-sha384, pbkdf2-sha512, argon2id, scrypt, or legacy SHA-256/384/512.')
            ->end()
            ->end()
            ->end()

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

            ->end();

        return $treeBuilder;
    }
}