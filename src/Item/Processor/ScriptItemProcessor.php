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

namespace Lemric\BatchProcessing\Item\Processor;

use Lemric\BatchProcessing\Item\ItemProcessorInterface;

/**
 * Processor that delegates to a closure/callable.
 *
 * @template TIn
 * @template TOut
 *
 * @implements ItemProcessorInterface<TIn, TOut>
 */
final class ScriptItemProcessor implements ItemProcessorInterface
{
    /** @var callable(TIn): (TOut|null) */
    private $callable;

    /**
     * @param callable(TIn): (TOut|null) $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function process(mixed $item): mixed
    {
        return ($this->callable)($item);
    }
}
