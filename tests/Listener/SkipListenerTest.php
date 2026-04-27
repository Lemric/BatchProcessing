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

namespace Lemric\BatchProcessing\Tests\Listener;

use Lemric\BatchProcessing\Listener\{CompositeListener, SkipListenerInterface};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SkipListenerTest extends TestCase
{
    public function testCompositeDispatchesSkipInProcess(): void
    {
        $state = new SkipListenerTestState();
        $listener = new class($state) implements SkipListenerInterface {
            public function __construct(private SkipListenerTestState $state)
            {
            }

            public function onSkipInRead(Throwable $t): void
            {
            }

            public function onSkipInProcess(mixed $item, Throwable $t): void
            {
                $this->state->capturedItem = $item;
            }

            public function onSkipInWrite(mixed $item, Throwable $t): void
            {
            }
        };

        $composite = new CompositeListener();
        $composite->register($listener);
        $composite->onSkipInProcess('myItem', new RuntimeException('test'));

        self::assertSame('myItem', $state->capturedItem);
    }

    public function testCompositeDispatchesSkipInRead(): void
    {
        $state = new SkipListenerTestState();
        $listener = new class($state) implements SkipListenerInterface {
            public function __construct(private SkipListenerTestState $state)
            {
            }

            public function onSkipInRead(Throwable $t): void
            {
                $this->state->readCalled = true;
            }

            public function onSkipInProcess(mixed $item, Throwable $t): void
            {
            }

            public function onSkipInWrite(mixed $item, Throwable $t): void
            {
            }
        };

        $composite = new CompositeListener();
        $composite->register($listener);
        $composite->onSkipInRead(new RuntimeException('test'));

        self::assertTrue($state->readCalled);
    }

    public function testCompositeDispatchesSkipInWrite(): void
    {
        $state = new SkipListenerTestState();
        $listener = new class($state) implements SkipListenerInterface {
            public function __construct(private SkipListenerTestState $state)
            {
            }

            public function onSkipInRead(Throwable $t): void
            {
            }

            public function onSkipInProcess(mixed $item, Throwable $t): void
            {
            }

            public function onSkipInWrite(mixed $item, Throwable $t): void
            {
                $this->state->capturedItem = $item;
            }
        };

        $composite = new CompositeListener();
        $composite->register($listener);
        $composite->onSkipInWrite('writeItem', new RuntimeException('test'));

        self::assertSame('writeItem', $state->capturedItem);
    }
}

final class SkipListenerTestState
{
    public mixed $capturedItem = null;

    public bool $readCalled = false;
}
