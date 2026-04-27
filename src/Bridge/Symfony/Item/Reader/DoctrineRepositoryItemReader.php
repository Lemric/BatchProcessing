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

namespace Lemric\BatchProcessing\Bridge\Symfony\Item\Reader;

use Doctrine\ORM\EntityManagerInterface;
use Lemric\BatchProcessing\Domain\ExecutionContext;
use Lemric\BatchProcessing\Item\{ItemReaderInterface, ItemStreamInterface};

/**
 * {@code RepositoryItemReader} parity for Doctrine ORM. Pages through results
 * via {@code findBy()} with limit/offset; the offset is checkpointed into the {@see ExecutionContext}
 * so a restart resumes from the last successful page boundary.
 *
 * Requires {@code doctrine/orm} (suggested dependency).
 *
 * @template TEntity of object
 *
 * @implements ItemReaderInterface<TEntity>
 */
final class DoctrineRepositoryItemReader implements ItemReaderInterface, ItemStreamInterface
{
    private const string CTX_OFFSET = 'doctrine.repository.reader.offset';

    /** @var list<TEntity> */
    private array $buffer = [];

    private int $offset = 0;

    /**
     * @param class-string<TEntity>       $entityClass
     * @param array<string, mixed>        $criteria
     * @param array<string, 'ASC'|'DESC'> $orderBy
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
        private readonly array $criteria = [],
        private readonly array $orderBy = [],
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

    private function loadNextPage(): void
    {
        $repository = $this->entityManager->getRepository($this->entityClass);
        /** @var list<TEntity> $results */
        $results = $repository->findBy($this->criteria, $this->orderBy, $this->pageSize, $this->offset);
        if ([] === $results) {
            return;
        }
        $this->buffer = $results;
        $this->offset += count($results);
        $this->entityManager->clear();
    }
}
