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

namespace Lemric\BatchProcessing\Bridge\Symfony\Item\Writer;

use Doctrine\ORM\EntityManagerInterface;
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * {@code RepositoryItemWriter} parity for Doctrine ORM. Persists every item in
 * the chunk and flushes once per {@see write()}; optionally clears the EM to release memory
 * (recommended for long-running batches).
 *
 * @template TEntity of object
 *
 * @implements ItemWriterInterface<TEntity>
 */
final class DoctrineRepositoryItemWriter implements ItemWriterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $clearEntityManager = true,
    ) {
    }

    public function write(Chunk $items): void
    {
        $count = 0;
        foreach ($items->getOutputItems() as $entity) {
            /* @var TEntity $entity */
            $this->entityManager->persist($entity);
            ++$count;
        }
        if ($count > 0) {
            $this->entityManager->flush();
            if ($this->clearEntityManager) {
                $this->entityManager->clear();
            }
        }
    }
}
