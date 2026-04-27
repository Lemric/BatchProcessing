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

namespace Lemric\BatchProcessing\Listener;

use Lemric\BatchProcessing\Domain\{BatchStatus, ExitStatus, StepExecution};

use RuntimeException;

/**
 * Promotes keys from the StepExecution's ExecutionContext to the JobExecution's ExecutionContext
 * at the end of a step (afterStep). Optionally only on specific statuses.
 */
final class ExecutionContextPromotionListener implements StepExecutionListenerInterface
{
    /**
     * @param list<string>           $keys     Keys to promote
     * @param list<BatchStatus>|null $statuses Only promote if step ended in one of these; null = any
     * @param bool                   $strict   If true: throw when a key is not present in step context
     */
    public function __construct(
        private readonly array $keys,
        private readonly ?array $statuses = null,
        private readonly bool $strict = false,
    ) {
    }

    public function afterStep(StepExecution $stepExecution): ?ExitStatus
    {
        if (null !== $this->statuses) {
            $match = false;
            foreach ($this->statuses as $status) {
                if ($stepExecution->getStatus() === $status) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                return null;
            }
        }

        $stepCtx = $stepExecution->getExecutionContext();
        $jobCtx = $stepExecution->getJobExecution()->getExecutionContext();

        foreach ($this->keys as $key) {
            if (!$stepCtx->containsKey($key)) {
                if ($this->strict) {
                    throw new RuntimeException("Key '{$key}' not found in step execution context for promotion.");
                }
                continue;
            }
            $jobCtx->putMixed($key, $stepCtx->get($key));
        }

        return null;
    }

    public function beforeStep(StepExecution $stepExecution): void
    {
    }
}
