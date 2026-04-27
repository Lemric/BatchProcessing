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

use Throwable;

use function is_int;
use function is_string;

/**
 * Classifies exceptions to a boolean (true = match, false = no match).
 *
 * @implements ClassifierInterface<mixed, bool>
 */
final class BinaryExceptionClassifier implements ClassifierInterface
{
    /** @var SubclassClassifier<bool> */
    private SubclassClassifier $delegate;

    /**
     * @param array<class-string<Throwable>, bool>|list<class-string<Throwable>> $exceptionTypes
     */
    public function __construct(array $exceptionTypes, private readonly bool $defaultValue = false)
    {
        /** @var array<string, bool> $map */
        $map = [];
        foreach ($exceptionTypes as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value)) {
                    continue;
                }
                $map[$value] = true;
            } elseif (is_string($key)) {
                $map[$key] = (bool) $value;
            }
        }
        $this->delegate = new SubclassClassifier($map, $defaultValue);
    }

    public function classify(mixed $classifiable): bool
    {
        if (!$classifiable instanceof Throwable) {
            return $this->defaultValue;
        }

        return (bool) $this->delegate->classify($classifiable);
    }
}
