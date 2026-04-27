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

namespace Lemric\BatchProcessing\Job\Flow;

use Lemric\BatchProcessing\Repository\JobRepositoryInterface;
use Lemric\BatchProcessing\Step\{FlowStep, StepInterface};

/**
 * {@code SimpleFlowFactoryBean} parity. Composes a {@see SimpleFlow} from a list
 * of {@see StepInterface}s and/or sub-{@see FlowInterface}s, wrapping each sub-flow as a
 * {@see FlowStep} so it participates in the parent flow as a regular step.
 */
final readonly class SimpleFlowFactory
{
    public function __construct(private JobRepositoryInterface $jobRepository)
    {
    }

    /**
     * @param list<StepInterface|FlowInterface>                      $stepsOrFlows
     * @param list<array{from: string, on: string, to: string|null}> $transitions  optional explicit transitions
     */
    public function create(string $name, array $stepsOrFlows, array $transitions = []): SimpleFlow
    {
        $flow = new SimpleFlow($name);
        $resolvedSteps = [];

        foreach ($stepsOrFlows as $stepOrFlow) {
            $step = $stepOrFlow instanceof FlowInterface
                ? new FlowStep($stepOrFlow->getName(), $this->jobRepository, $stepOrFlow)
                : $stepOrFlow;
            $flow->addStep($step);
            $resolvedSteps[$step->getName()] = $step;
        }

        // Default sequential transitions when none provided.
        if ([] === $transitions && [] !== $resolvedSteps) {
            $names = array_keys($resolvedSteps);
            for ($i = 0, $n = count($names); $i < $n - 1; ++$i) {
                $flow->addTransition($names[$i], 'COMPLETED', Transition::to($names[$i + 1]));
            }
            $flow->addTransition($names[count($names) - 1], '*', Transition::end());

            return $flow;
        }

        foreach ($transitions as $t) {
            $target = $t['to'] ?? null;
            $transition = null === $target ? Transition::end() : Transition::to($target);
            $flow->addTransition($t['from'], $t['on'], $transition);
        }

        return $flow;
    }
}
