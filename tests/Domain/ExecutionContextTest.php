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

namespace Lemric\BatchProcessing\Tests\Domain;

use Lemric\BatchProcessing\Domain\ExecutionContext;
use PHPUnit\Framework\TestCase;

final class ExecutionContextTest extends TestCase
{
    public function testGetIntCoerces(): void
    {
        $ctx = ExecutionContext::fromArray(['n' => '42']);
        self::assertSame(42, $ctx->getInt('n'));
    }

    public function testPutGetRemove(): void
    {
        $ctx = new ExecutionContext();
        $ctx->put('foo', 1);
        self::assertSame(1, $ctx->getInt('foo'));
        self::assertTrue($ctx->isDirty());

        $ctx->clearDirtyFlag();
        self::assertFalse($ctx->isDirty());

        $ctx->remove('foo');
        self::assertFalse($ctx->containsKey('foo'));
        self::assertTrue($ctx->isDirty());
    }

    public function testPutSameValueDoesNotMarkDirty(): void
    {
        $ctx = ExecutionContext::fromArray(['k' => 'v']);
        $ctx->clearDirtyFlag();
        $ctx->put('k', 'v');
        self::assertFalse($ctx->isDirty());
    }
}
