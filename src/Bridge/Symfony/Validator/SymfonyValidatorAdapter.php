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

namespace Lemric\BatchProcessing\Bridge\Symfony\Validator;

use Symfony\Component\Validator\Validator\ValidatorInterface as SymfonyValidator;

/**
 * Adapter exposing a Symfony Validator as the {@code callable(T): list<string>} contract
 * expected by {@see \Lemric\BatchProcessing\Item\Processor\BeanValidatingItemProcessor}.
 *
 * Usage:
 *   $processor = new BeanValidatingItemProcessor(new SymfonyValidatorAdapter($validator));
 *
 * Requires {@code symfony/validator} (suggested dependency).
 */
final readonly class SymfonyValidatorAdapter
{
    /**
     * @param array<string>|null $groups validation groups passed verbatim to {@see SymfonyValidator::validate()}
     */
    public function __construct(
        private SymfonyValidator $validator,
        private ?array $groups = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(mixed $item): array
    {
        $violations = $this->validator->validate($item, null, $this->groups);
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        return $messages;
    }
}
