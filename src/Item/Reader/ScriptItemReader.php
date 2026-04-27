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

namespace Lemric\BatchProcessing\Item\Reader;

use Lemric\BatchProcessing\Item\ItemReaderInterface;

/**
 * Reader that executes a closure/callable for each read() call.
 * Returns null (end-of-data) when the callable returns null.
 *
 * @template T
 *
 * @implements ItemReaderInterface<T>
 */
final class ScriptItemReader implements ItemReaderInterface
{
    /** @var callable(): (T|null) */
    private $callable;

    /**
     * @param callable(): (T|null) $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function read(): mixed
    {
        return ($this->callable)();
    }
}
