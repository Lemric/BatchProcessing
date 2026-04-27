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

namespace Lemric\BatchProcessing\Skip;

use Lemric\BatchProcessing\Classifier\SubclassClassifier;
use Lemric\BatchProcessing\Exception\SkippableException;
use Throwable;

use const PHP_INT_MAX;

/**
 * Dispatches to a per-exception-class {@see SkipPolicyInterface} using a {@see SubclassClassifier}
 * for hierarchy-aware matching. Falls back to a default policy when no class matches.
 *
 * When the default policy would not skip, {@see SkippableException} is still honored via
 * {@see AlwaysSkipItemSkipPolicy} with an unbounded skip budget (subject to caller-side limits).
 */
final class ExceptionClassifierSkipPolicy implements SkipPolicyInterface
{
    /** @var SubclassClassifier<SkipPolicyInterface> */
    private SubclassClassifier $classifier;

    /**
     * @param array<class-string<Throwable>, SkipPolicyInterface> $policies
     */
    public function __construct(
        array $policies,
        private readonly SkipPolicyInterface $defaultPolicy = new NeverSkipItemSkipPolicy(),
        private readonly SkipPolicyInterface $skippableMarkerFallback = new AlwaysSkipItemSkipPolicy(PHP_INT_MAX),
    ) {
        /** @var SubclassClassifier<SkipPolicyInterface> $classifier */
        $classifier = new SubclassClassifier([], $this->defaultPolicy);
        foreach ($policies as $class => $policy) {
            $classifier->add($class, $policy);
        }
        $this->classifier = $classifier;
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        $policy = $this->classifier->classify($t);
        if ($policy->shouldSkip($t, $skipCount)) {
            return true;
        }
        if ($t instanceof SkippableException) {
            return $this->skippableMarkerFallback->shouldSkip($t, $skipCount);
        }

        return false;
    }
}
