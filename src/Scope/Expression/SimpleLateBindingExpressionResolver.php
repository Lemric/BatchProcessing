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

use DateTimeInterface;
use InvalidArgumentException;
use Lemric\BatchProcessing\Domain\StepExecution;

use function mb_strlen;

use const DATE_ATOM;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Zero-dependency reference {@see LateBindingExpressionResolverInterface}. Recognises only
 * the three documented prefixes; any other syntax is returned unchanged so callers can chain
 * a more powerful resolver (e.g. {@see ExpressionLanguageLateBindingResolver}).
 *
 * Supported syntax (anywhere in the input string — multiple placeholders allowed):
 *   - #{jobParameters['name']}
 *   - #{jobParameters[name]}
 *   - #{stepExecutionContext['key']}
 *   - #{jobExecutionContext['key']}
 *
 * Security: treat {@code $expression} as trusted configuration only — never feed raw user input.
 */
final class SimpleLateBindingExpressionResolver implements LateBindingExpressionResolverInterface
{
    private const int MAX_EXPRESSION_LENGTH = 32_768;

    public function resolve(string $expression, StepExecution $stepExecution): mixed
    {
        if (mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new InvalidArgumentException('Late-binding expression exceeds maximum allowed length.');
        }
        $jobExecution = $stepExecution->getJobExecution();
        $jobParameters = $jobExecution->getJobParameters();

        $callback = static function (array $matches) use ($stepExecution, $jobExecution, $jobParameters): string {
            /** @var string $source */
            $source = $matches[1];
            /** @var string $key */
            $key = $matches[2];

            $value = match ($source) {
                'jobParameters' => $jobParameters->get($key)?->getValue(),
                'stepExecutionContext' => $stepExecution->getExecutionContext()->get($key),
                'jobExecutionContext' => $jobExecution->getExecutionContext()->get($key),
                default => null,
            };

            if (null === $value) {
                return '';
            }
            if ($value instanceof DateTimeInterface) {
                return $value->format(DATE_ATOM);
            }
            if (is_scalar($value)) {
                return (string) $value;
            }

            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };

        $result = preg_replace_callback(
            "/#\\{(jobParameters|stepExecutionContext|jobExecutionContext)\\[\\s*['\"]?([^'\"\\]]+)['\"]?\\s*\\]\\}/",
            $callback,
            $expression,
        );

        return $result ?? $expression;
    }
}
