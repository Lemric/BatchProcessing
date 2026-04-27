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

namespace Lemric\BatchProcessing\Tests\Retry;

use Lemric\BatchProcessing\Retry\{RetryCallback, RetryContext};
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetryCallbackTest extends TestCase
{
    public function testCallbackCanAccessContext(): void
    {
        $callback = new class implements RetryCallback {
            public function doWithRetry(RetryContext $context): mixed
            {
                return $context->getRetryCount();
            }
        };

        $context = new RetryContext();
        $context->registerThrowable(new RuntimeException('test'));

        self::assertSame(1, $callback->doWithRetry($context));
    }

    public function testCallbackIsInvoked(): void
    {
        $callback = new class implements RetryCallback {
            public int $called = 0;

            public function doWithRetry(RetryContext $context): mixed
            {
                ++$this->called;

                return 'result';
            }
        };

        $context = new RetryContext();
        $result = $callback->doWithRetry($context);

        self::assertSame('result', $result);
        self::assertSame(1, $callback->called);
    }
}
