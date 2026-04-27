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

namespace Lemric\BatchProcessing\Item\FlatFile;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Extracts field values from an object by property names using getter methods or reflection.
 */
final class BeanWrapperFieldExtractor implements FieldExtractorInterface
{
    /**
     * @param list<string> $names property names to extract
     */
    public function __construct(
        private readonly array $names,
    ) {
        if ([] === $names) {
            throw new InvalidArgumentException('At least one field name must be specified.');
        }
    }

    public function extract(mixed $item): array
    {
        if (is_array($item)) {
            return array_map(static fn (string $name): mixed => $item[$name] ?? null, $this->names);
        }

        if (!is_object($item)) {
            throw new InvalidArgumentException(sprintf('Expected object or array, got %s', get_debug_type($item)));
        }

        $values = [];
        $reflection = new ReflectionClass($item);

        foreach ($this->names as $name) {
            $getter = 'get'.ucfirst($name);
            if ($reflection->hasMethod($getter) && $reflection->getMethod($getter)->isPublic()) {
                $values[] = $item->$getter();
                continue;
            }

            $isser = 'is'.ucfirst($name);
            if ($reflection->hasMethod($isser) && $reflection->getMethod($isser)->isPublic()) {
                $values[] = $item->$isser();
                continue;
            }

            try {
                $prop = $reflection->getProperty($name);
                $values[] = $prop->getValue($item);
            } catch (ReflectionException) {
                throw new InvalidArgumentException(sprintf('Cannot extract field "%s" from %s', $name, $item::class));
            }
        }

        return $values;
    }
}
