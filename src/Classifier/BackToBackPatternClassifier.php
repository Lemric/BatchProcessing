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

/**
 * Two-stage classifier: the first classifier maps the input to a "router" key
 * (typically a class-string or string code), and the second mapping resolves
 * the router key to the final value.
 *
 * @template T
 * @template R of array-key|null
 * @template C
 *
 * @implements ClassifierInterface<T, C>
 */
final class BackToBackPatternClassifier implements ClassifierInterface
{
    /**
     * @param ClassifierInterface<T, R> $router
     * @param array<array-key, C>       $matcherMap   keyed by the router output
     * @param C                         $defaultValue
     */
    public function __construct(
        private readonly ClassifierInterface $router,
        private readonly array $matcherMap = [],
        private readonly mixed $defaultValue = null,
    ) {
    }

    /**
     * @return C
     */
    public function classify(mixed $classifiable): mixed
    {
        $key = $this->router->classify($classifiable);
        if (null === $key) {
            return $this->defaultValue;
        }

        return $this->matcherMap[$key] ?? $this->defaultValue;
    }
}
