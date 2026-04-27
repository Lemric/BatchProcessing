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

namespace Lemric\BatchProcessing\Bridge\Symfony\Scope;

use InvalidArgumentException;
use Lemric\BatchProcessing\Domain\StepExecution;
use Lemric\BatchProcessing\Scope\Expression\{LateBindingExpressionResolverInterface, SimpleLateBindingExpressionResolver};
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use function mb_strlen;

/**
 * Symfony ExpressionLanguage bridge for late binding. Inside an expression the following
 * variables are exposed:
 *   - {@code jobParameters}        ⇒ array<string, mixed>
 *   - {@code stepExecutionContext} ⇒ array<string, mixed>
 *   - {@code jobExecutionContext}  ⇒ array<string, mixed>.
 *
 * If the expression starts with {@code @} or contains {@code #{...}} placeholders it falls
 * back to {@see SimpleLateBindingExpressionResolver}
 *
 * Security: expressions must come from trusted configuration only. Do not pass HTTP input,
 * job parameters, or other attacker-controlled strings into ExpressionLanguage — register
 * custom ExpressionLanguage functions only with extreme care.
 */
final class ExpressionLanguageLateBindingResolver implements LateBindingExpressionResolverInterface
{
    private const int MAX_EXPRESSION_LENGTH = 32_768;

    private SimpleLateBindingExpressionResolver $fallback;

    public function __construct(private readonly ExpressionLanguage $expressionLanguage = new ExpressionLanguage())
    {
        $this->fallback = new SimpleLateBindingExpressionResolver();
    }

    public function resolve(string $expression, StepExecution $stepExecution): mixed
    {
        if (mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new InvalidArgumentException('Late-binding expression exceeds maximum allowed length.');
        }
        self::rejectUnsafeExpressionConstructs($expression);
        if (str_contains($expression, '#{')) {
            return $this->fallback->resolve($expression, $stepExecution);
        }

        $jobExecution = $stepExecution->getJobExecution();
        $variables = [
            'jobParameters' => $this->jobParametersToArray($jobExecution->getJobParameters()),
            'stepExecutionContext' => $stepExecution->getExecutionContext()->toArray(),
            'jobExecutionContext' => $jobExecution->getExecutionContext()->toArray(),
        ];

        return $this->expressionLanguage->evaluate($expression, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobParametersToArray(\Lemric\BatchProcessing\Domain\JobParameters $params): array
    {
        $out = [];
        foreach ($params->getParameters() as $name => $param) {
            $out[$name] = $param->getValue();
        }

        return $out;
    }

    /**
     * Blocks obvious host-escape patterns in expression *source* (defense-in-depth; trusted config only).
     */
    private static function rejectUnsafeExpressionConstructs(string $expression): void
    {
        $lower = mb_strtolower($expression);
        foreach ([
            '`', '${', '<?', '<?php',
            'eval(', 'exec(', 'shell_exec', 'system(', 'passthru(', 'proc_open(',
            'create_function', 'assert(',
            'include(', 'require(', 'include_once(', 'require_once(',
        ] as $needle) {
            if (str_contains($lower, mb_strtolower($needle))) {
                throw new InvalidArgumentException('Late-binding expression contains a disallowed construct.');
            }
        }
    }
}
