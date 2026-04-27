<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

namespace Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function is_array;

/**
 * `batch_processing:` configuration tree.
 *
 * Mirrors the YAML schema documented in spec.md §15.1: connection name,
 * default retry / skip / backoff policies and Messenger transport for
 * the asynchronous launcher.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('batch_processing');
        $rootNode = $tree->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('table_prefix')->defaultValue('batch_')->end()
                ->scalarNode('data_source')->defaultValue('default')->end()
                ->arrayNode('default_retry_policy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_attempts')->defaultValue(3)->min(1)->end()
                        ->arrayNode('retryable_exceptions')
                            ->scalarPrototype()->end()
                            ->defaultValue(['\\RuntimeException'])
                        ->end()
                        ->arrayNode('backoff')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('type')
                                    ->values(['none', 'fixed', 'exponential', 'exponential_random', 'uniform_random'])
                                    ->defaultValue('exponential')
                                ->end()
                                ->integerNode('initial_interval')->defaultValue(200)->min(0)->end()
                                ->floatNode('multiplier')->defaultValue(2.0)->end()
                                ->integerNode('max_interval')->defaultValue(10000)->min(0)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('default_skip_policy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('skip_limit')->defaultValue(0)->min(0)->end()
                        ->arrayNode('skippable_exceptions')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('async_launcher')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('transport')->defaultValue('async_batch')->end()
                        ->scalarNode('message_secret')->defaultNull()->end()
                        ->integerNode('message_ttl_seconds')
                            ->defaultValue(604800)
                            ->min(60)
                            ->info('Maximum age in seconds for async RunJobMessage signatures (replay window).')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
            ->always(static function (array $config): array {
                $asyncRaw = $config['async_launcher'] ?? [];
                $async = is_array($asyncRaw) ? $asyncRaw : [];
                if (true === ($async['enabled'] ?? false)) {
                    $secret = $async['message_secret'] ?? null;
                    if (!is_string($secret) || '' === mb_trim($secret)) {
                        throw new InvalidConfigurationException('When "batch_processing.async_launcher.enabled" is true, "batch_processing.async_launcher.message_secret" must be a non-empty string.');
                    }
                }

                return $config;
            })
            ->end()
        ;

        return $tree;
    }
}
