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

namespace Lemric\BatchProcessing\Scope\Expression;

use Lemric\BatchProcessing\Domain\StepExecution;

/**
 * Late-binding expression resolver. Resolves
 * {@code #{jobParameters['x']}}, {@code #{stepExecutionContext['x']}} and
 * {@code #{jobExecutionContext['x']}} placeholders against the active
 * {@see StepExecution}.
 */
interface LateBindingExpressionResolverInterface
{
    public function resolve(string $expression, StepExecution $stepExecution): mixed;
}
