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

namespace Lemric\BatchProcessing\Bridge\Laravel\Item\Writer;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * {@code RepositoryItemWriter} parity for Eloquent. Saves every model in the
 * chunk; if {@code $useUpsert} is enabled and the chunk is uniform (same model class), uses
 * Eloquent {@code upsert()} for a single-statement bulk write.
 *
 * @template TModel of Model
 *
 * @implements ItemWriterInterface<TModel>
 */
final class EloquentItemWriter implements ItemWriterInterface
{
    /**
     * @param array<int, string>|null $upsertUniqueBy required when {@code $useUpsert} is true
     * @param array<int, string>|null $upsertColumns  optional columns to update on conflict (default: all)
     */
    public function __construct(
        private readonly bool $useUpsert = false,
        private readonly ?array $upsertUniqueBy = null,
        private readonly ?array $upsertColumns = null,
    ) {
    }

    public function write(Chunk $items): void
    {
        /** @var list<TModel> $models */
        $models = $items->getOutputItems();
        if ([] === $models) {
            return;
        }

        if ($this->useUpsert) {
            $this->bulkUpsert($models);

            return;
        }

        foreach ($models as $model) {
            $model->save();
        }
    }

    /**
     * @param list<Model> $models
     */
    private function bulkUpsert(array $models): void
    {
        if (null === $this->upsertUniqueBy) {
            throw new InvalidArgumentException('upsertUniqueBy is required when $useUpsert=true.');
        }
        $first = $models[0];
        $rows = array_map(static fn (Model $m): array => $m->getAttributes(), $models);
        $first::query()->upsert($rows, $this->upsertUniqueBy, $this->upsertColumns);
    }
}
