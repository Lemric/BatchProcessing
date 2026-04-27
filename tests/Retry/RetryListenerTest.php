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

use Lemric\BatchProcessing\Listener\RetryListenerInterface;
use Lemric\BatchProcessing\Retry\Policy\SimpleRetryPolicy;
use Lemric\BatchProcessing\Retry\{RetryContext, RetryTemplate};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RetryListenerTest extends TestCase
{
    public function testListenerOnErrorCalled(): void
    {
        $errorCount = 0;
        $listener = new class($errorCount) implements RetryListenerInterface {
            public function __construct(private int &$errorCount)
            {
            }

            public function open(RetryContext $context): bool
            {
                return true;
            }

            public function close(RetryContext $context): void
            {
            }

            public function onError(RetryContext $context, Throwable $t): void
            {
                ++$this->errorCount;
            }
        };

        $attempts = 0;
        $template = new RetryTemplate(new SimpleRetryPolicy(3));
        $template->registerListener($listener);

        try {
            $template->execute(function () use (&$attempts) {
                ++$attempts;
                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(3, $errorCount);
    }

    public function testListenerOpenCloseCalledOnSuccess(): void
    {
        $trace = new RetryListenerTestTrace();
        $listener = new class($trace) implements RetryListenerInterface {
            public function __construct(private RetryListenerTestTrace $trace)
            {
            }

            public function open(RetryContext $context): bool
            {
                $this->trace->entries[] = 'open';

                return true;
            }

            public function close(RetryContext $context): void
            {
                $this->trace->entries[] = 'close';
            }

            public function onError(RetryContext $context, Throwable $t): void
            {
                $this->trace->entries[] = 'error';
            }
        };

        $template = new RetryTemplate(new SimpleRetryPolicy(3));
        $template->registerListener($listener);
        $result = $template->execute(fn () => 'ok');

        self::assertSame('ok', $result);
        self::assertSame(['open', 'close'], $trace->entries);
    }

    public function testListenerVetoStopsRetry(): void
    {
        $listener = new class implements RetryListenerInterface {
            public function open(RetryContext $context): bool
            {
                return false;
            } // veto

            public function close(RetryContext $context): void
            {
            }

            public function onError(RetryContext $context, Throwable $t): void
            {
            }
        };

        $template = new RetryTemplate(new SimpleRetryPolicy(3));
        $template->registerListener($listener);

        $this->expectException(\Lemric\BatchProcessing\Exception\ExhaustedRetryException::class);
        $template->execute(fn () => 'never');
    }
}

final class RetryListenerTestTrace
{
    /** @var list<string> */
    public array $entries = [];
}
