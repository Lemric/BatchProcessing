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
 * Skip policy that uses a map of exception classes respecting inheritance hierarchy.
 * Each mapped class has a maximum skip count.
 *
 * {@see SkippableException} and its subclasses are skippable even when no entry matches:
 * they use an implicit high limit until an explicit map entry supplies a positive count.
 */
final class ExceptionHierarchySkipPolicy implements SkipPolicyInterface
{
    /** @var SubclassClassifier<int> */
    private SubclassClassifier $classifier;

    /**
     * @param array<class-string<Throwable>, int> $exceptionLimits class => max skip count
     */
    public function __construct(array $exceptionLimits)
    {
        /** @var SubclassClassifier<int> $classifier */
        $classifier = new SubclassClassifier([], 0);
        foreach ($exceptionLimits as $class => $limit) {
            $classifier->add($class, $limit);
        }
        $this->classifier = $classifier;
    }

    public function shouldSkip(Throwable $t, int $skipCount): bool
    {
        $limit = $this->classifier->classify($t);
        if ($limit <= 0 && $t instanceof SkippableException) {
            // Marker from {@see SkippableException}: skippable even when no explicit limit is mapped.
            $limit = PHP_INT_MAX;
        }

        return $limit > 0 && $skipCount < $limit;
    }
}
