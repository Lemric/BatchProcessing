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

namespace Lemric\BatchProcessing\Classifier;

use function get_class;
use function is_a;
use function is_object;

/**
 * Classifier that resolves a value to the entry whose key is the most specific
 * (closest in inheritance hierarchy) parent of the candidate's class.
 *
 * @template C
 *
 * @implements ClassifierInterface<mixed, C>
 */
final class SubclassClassifier implements ClassifierInterface
{
    /**
     * @param array<string, C> $typeMap      class or interface names as map keys
     * @param C                $defaultValue
     */
    public function __construct(
        private array $typeMap = [],
        private mixed $defaultValue = null,
    ) {
    }

    /**
     * @param class-string $type
     * @param C            $value
     */
    public function add(string $type, mixed $value): void
    {
        $this->typeMap[$type] = $value;
    }

    /**
     * @return C
     */
    public function classify(mixed $classifiable): mixed
    {
        if (!is_object($classifiable)) {
            return $this->defaultValue;
        }

        $class = get_class($classifiable);
        if (isset($this->typeMap[$class])) {
            return $this->typeMap[$class];
        }

        $bestMatch = null;
        $bestMatchClass = null;
        foreach ($this->typeMap as $candidate => $value) {
            if (is_a($classifiable, $candidate)) {
                if (null === $bestMatchClass || is_subclass_of($candidate, $bestMatchClass)) {
                    $bestMatchClass = $candidate;
                    $bestMatch = $value;
                }
            }
        }

        return $bestMatch ?? $this->defaultValue;
    }
}
