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

namespace Lemric\BatchProcessing\Bridge\Laravel\Validator;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * Adapter exposing Laravel's Validator as the {@code callable(T): list<string>} contract
 * expected by {@see \Lemric\BatchProcessing\Item\Processor\BeanValidatingItemProcessor}.
 *
 * Items must be array-shaped (or castable via {@code $toArray}) — typical for ETL pipelines.
 */
final readonly class LaravelValidatorAdapter
{
    /**
     * @param array<string, mixed>               $rules    laravel-style rules array
     * @param array<string, string>              $messages
     * @param callable(mixed): array<mixed>|null $toArray  optional callback turning items into array<string, mixed>
     */
    public function __construct(
        private ValidatorFactory $factory,
        private array $rules,
        private array $messages = [],
        private mixed $toArray = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(mixed $item): array
    {
        $data = null !== $this->toArray ? ($this->toArray)($item) : (is_array($item) ? $item : (array) $item);
        $validator = $this->factory->make($data, $this->rules, $this->messages);

        if (!$validator->fails()) {
            return [];
        }

        $messages = [];
        /** @var array<string, list<string>> $bag */
        $bag = $validator->errors()->toArray();
        foreach ($bag as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $field, $error);
            }
        }

        return $messages;
    }
}
