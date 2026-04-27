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

namespace Lemric\BatchProcessing\Tests\Chunk;

use Lemric\BatchProcessing\Chunk\ChunkListener;
use Lemric\BatchProcessing\Listener\ChunkListenerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ChunkListenerTest extends TestCase
{
    public function testChunkListenerExtendsChunkListenerInterface(): void
    {
        $ref = new ReflectionClass(ChunkListener::class);
        self::assertTrue($ref->isSubclassOf(ChunkListenerInterface::class));
    }
}
