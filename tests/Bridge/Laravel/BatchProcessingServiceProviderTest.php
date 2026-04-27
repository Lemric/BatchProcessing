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

namespace Lemric\BatchProcessing\Tests\Bridge\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepositoryContract;
use Illuminate\Contracts\Queue\{Factory as QueueFactory, Queue};
use InvalidArgumentException;
use Lemric\BatchProcessing\Bridge\Laravel\BatchProcessingServiceProvider;
use Lemric\BatchProcessing\Bridge\Laravel\Queue\{QueueJobDispatcher, RunJobQueueJob};
use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Explorer\JobExplorerInterface;
use Lemric\BatchProcessing\Launcher\{AsyncJobLauncher, JobLauncherInterface, SimpleJobLauncher};
use Lemric\BatchProcessing\Operator\JobOperatorInterface;
use Lemric\BatchProcessing\Registry\JobRegistryInterface;
use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Security\AsyncJobMessageSigner;
use PHPUnit\Framework\TestCase;

final class BatchProcessingServiceProviderTest extends TestCase
{
    public function testAsyncEnabledWithoutMessageSecretThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $app = $this->bootApp(['async' => ['enabled' => true, 'queue' => 'batch', 'connection' => 'sync']]);
        $app->instance(QueueFactory::class, new FakeQueue());
        $app->make(JobLauncherInterface::class);
    }

    public function testAsyncLauncherUsedWhenConfigEnabled(): void
    {
        $app = $this->bootApp(['async' => [
            'enabled' => true,
            'queue' => 'batch',
            'connection' => 'sync',
            'message_secret' => 'laravel-async-test-secret-value',
        ]]);
        $app->instance(QueueFactory::class, new FakeQueue());

        self::assertInstanceOf(AsyncJobLauncher::class, $app->make(JobLauncherInterface::class));
    }

    public function testCoreServicesAreSingletons(): void
    {
        $app = $this->bootApp();

        self::assertInstanceOf(JobRepositoryInterface::class, $app->make(JobRepositoryInterface::class));
        self::assertInstanceOf(JobRegistryInterface::class, $app->make(JobRegistryInterface::class));
        self::assertInstanceOf(JobExplorerInterface::class, $app->make(JobExplorerInterface::class));
        self::assertInstanceOf(SimpleJobLauncher::class, $app->make(JobLauncherInterface::class));
        self::assertInstanceOf(JobOperatorInterface::class, $app->make(JobOperatorInterface::class));

        self::assertSame($app->make(JobRepositoryInterface::class), $app->make(JobRepositoryInterface::class));
    }

    public function testQueueJobDispatcherPushesRunJobQueueJob(): void
    {
        $factory = new FakeQueue();
        $secret = 'queue-dispatcher-test-secret';
        $dispatcher = new QueueJobDispatcher($factory, 'sync', 'batch', $secret);

        ($dispatcher)(123, 'jobName', new JobParameters());

        self::assertCount(1, $factory->pushed);
        self::assertSame('batch', $factory->pushed[0]['queue']);
        self::assertSame(123, $factory->pushed[0]['job']->jobExecutionId);
        self::assertSame('jobName', $factory->pushed[0]['job']->jobName);
        $job = $factory->pushed[0]['job'];
        $expectedKey = new JobParameters()->toJobKey();
        self::assertSame($expectedKey, $job->parametersJobKey);
        self::assertSame(
            AsyncJobMessageSigner::sign($secret, 123, 'jobName', $job->messageIssuedAt, $expectedKey),
            $job->messageSignature,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bootApp(array $config = []): Container
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance(ConfigRepositoryContract::class, new InMemoryConfigRepository(['batch_processing' => $config]));

        /** @var \Illuminate\Contracts\Foundation\Application $appProxy */
        $appProxy = $app; // @phpstan-ignore varTag.nativeType
        new BatchProcessingServiceProvider($appProxy)->register();

        return $app;
    }
}

final class InMemoryConfigRepository implements ConfigRepositoryContract
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = [])
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param array<int|string, mixed>|string $key
     */
    public function get($key, $default = null): mixed
    {
        if (is_array($key)) {
            $out = [];
            foreach ($key as $k => $v) {
                /** @var string $name */
                $name = is_int($k) ? $v : $k;
                $fallback = is_int($k) ? null : $v;
                $out[$name] = $this->get($name, $fallback);
            }

            return $out;
        }
        $node = $this->items;
        foreach (explode('.', (string) $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    public function has($key): bool
    {
        return null !== $this->get($key);
    }

    public function prepend($key, $value): void
    {
    }

    public function push($key, $value): void
    {
    }

    /**
     * @param array<string, mixed>|string $key
     */
    public function set($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->items[(string) $k] = $v;
            }

            return;
        }
        $this->items[(string) $key] = $value;
    }
}

final class FakeQueue implements QueueFactory
{
    /** @var list<array{queue:string,job:RunJobQueueJob}> */
    public array $pushed = [];

    public function connection($name = null): Queue
    {
        return new FakeQueueConnection($this);
    }
}

final class FakeQueueConnection implements Queue
{
    public function __construct(private FakeQueue $parent)
    {
    }

    /** @param iterable<mixed> $jobs */
    public function bulk($jobs, $data = '', $queue = null): mixed
    {
        return null;
    }

    public function getConnectionName(): string
    {
        return 'fake';
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->pushOn($queue ?? 'default', $job, $data);
    }

    public function laterOn($queue, $delay, $job, $data = ''): mixed
    {
        return $this->pushOn($queue, $job, $data);
    }

    public function pop($queue = null): mixed
    {
        return null;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->pushOn($queue ?? 'default', $job, $data);
    }

    public function pushOn($queue, $job, $data = ''): mixed
    {
        if ($job instanceof RunJobQueueJob) {
            $this->parent->pushed[] = ['queue' => (string) $queue, 'job' => $job];
        }

        return null;
    }

    /** @param array<string, mixed> $options */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        return null;
    }

    public function setConnectionName($name): self
    {
        return $this;
    }

    public function size($queue = null): int
    {
        return count($this->parent->pushed);
    }
}
