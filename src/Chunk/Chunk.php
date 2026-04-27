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

namespace Lemric\BatchProcessing\Chunk;

use ArrayIterator;
use Countable;
use IteratorAggregate;

use Traversable;

use function count;

/**
 * Immutable container holding the inputs read from the {@see ItemReaderInterface} and the
 * outputs produced by the {@see ItemProcessorInterface}.
 *
 * Inputs and outputs may differ in count when the processor filters items by returning null.
 *
 * @template-covariant TInput
 * @template-covariant TOutput
 *
 * @implements IteratorAggregate<int, TOutput>
 */
final readonly class Chunk implements Countable, IteratorAggregate
{
    /**
     * @param list<TInput>  $inputs
     * @param list<TOutput> $outputs
     */
    public function __construct(
        private array $inputs = [],
        private array $outputs = [],
    ) {
    }

    public function count(): int
    {
        return count($this->outputs);
    }

    /**
     * @return self<mixed, mixed>
     */
    public static function empty(): self
    {
        return new self([], []);
    }

    public function getInputCount(): int
    {
        return count($this->inputs);
    }

    /**
     * @return list<TInput>
     */
    public function getInputItems(): array
    {
        return $this->inputs;
    }

    /**
     * @return Traversable<int, TOutput>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->outputs);
    }

    public function getOutputCount(): int
    {
        return count($this->outputs);
    }

    /**
     * @return list<TOutput>
     */
    public function getOutputItems(): array
    {
        return $this->outputs;
    }

    public function isBusy(): bool
    {
        return [] !== $this->inputs;
    }

    public function isEmpty(): bool
    {
        return [] === $this->inputs && [] === $this->outputs;
    }
}
