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

namespace Lemric\BatchProcessing\Bridge\Laravel\Item\Reader;

use Closure;
use Illuminate\Database\Eloquent\{Builder, Model};
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};

use function is_array;
use function is_string;

/**
 * {@code RepositoryItemReader} parity for Eloquent. Pages through query results
 * with {@code limit}/{@code offset}, checkpointing the offset for restart safety.
 *
 * Requires {@code laravel/framework} (suggested dependency).
 *
 * @template TModel of Model
 *
 * @implements ItemReaderInterface<TModel>
 */
final class EloquentItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private const string CTX_OFFSET = 'eloquent.reader.offset';

    /** @var list<TModel> */
    private array $buffer = [];

    private int $offset = 0;

    /**
     * @param class-string<TModel>|Builder<TModel>                                            $modelOrBuilder
     * @param array<int, array{column: string, value: mixed}|callable(Builder<TModel>): void> $where
     * @param array<string, 'asc'|'desc'>                                                     $orderBy
     */
    public function __construct(
        private readonly string|Builder $modelOrBuilder,
        private readonly array $where = [],
        private readonly array $orderBy = ['id' => 'asc'],
        private readonly int $pageSize = 100,
    ) {
    }

    public function close(): void
    {
        $this->buffer = [];
        $this->offset = 0;
    }

    public function open(ExecutionContext $executionContext): void
    {
        $this->offset = $executionContext->getInt(self::CTX_OFFSET, 0);
        $this->buffer = [];
    }

    public function read(): mixed
    {
        if ([] === $this->buffer) {
            $this->loadNextPage();
        }
        if ([] === $this->buffer) {
            return null;
        }

        return array_shift($this->buffer);
    }

    public function update(ExecutionContext $executionContext): void
    {
        $executionContext->put(self::CTX_OFFSET, $this->offset);
    }

    /**
     * @return Builder<TModel>
     */
    private function buildBaseQuery(): Builder
    {
        if ($this->modelOrBuilder instanceof Builder) {
            return clone $this->modelOrBuilder;
        }
        $class = $this->modelOrBuilder;

        /** @var Builder<TModel> $builder */
        $builder = $class::query();

        return $builder;
    }

    private function loadNextPage(): void
    {
        $query = $this->buildBaseQuery();
        foreach ($this->where as $clause) {
            if ($clause instanceof Closure) {
                $clause($query);
                continue;
            }
            if (!is_array($clause)) {
                continue;
            }
            $column = $clause['column'] ?? null;
            if (!is_string($column)) {
                continue;
            }
            $query->where($column, $clause['value'] ?? null);
        }
        foreach ($this->orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        /** @var list<TModel> $rows */
        $rows = $query->limit($this->pageSize)->offset($this->offset)->get()->all();
        if ([] === $rows) {
            return;
        }
        $this->buffer = $rows;
        $this->offset += count($rows);
    }
}
