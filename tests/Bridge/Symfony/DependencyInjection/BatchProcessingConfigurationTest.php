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

namespace Lemric\BatchProcessing\Tests\Bridge\Symfony\DependencyInjection;

use Lemric\BatchProcessing\Bridge\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class BatchProcessingConfigurationTest extends TestCase
{
    public function testAsyncLauncherAcceptsNonEmptySecret(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(), [[
            'async_launcher' => [
                'enabled' => true,
                'message_secret' => 'symfony-config-test-secret',
            ],
        ]]);
        self::assertArrayHasKey('async_launcher', $config);
        $asyncLauncher = $config['async_launcher'];
        self::assertIsArray($asyncLauncher);
        self::assertArrayHasKey('message_secret', $asyncLauncher);
        $secret = $asyncLauncher['message_secret'];
        self::assertIsString($secret);
        self::assertSame('symfony-config-test-secret', $secret);
    }

    public function testAsyncLauncherRequiresMessageSecretWhenEnabled(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), [[
            'async_launcher' => [
                'enabled' => true,
                'transport' => 'batch',
            ],
        ]]);
    }
}
