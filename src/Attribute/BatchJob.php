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

namespace Lemric\BatchProcessing\Attribute;

use Attribute;

/**
 * Marks a class as a batch job for auto-discovery by framework integrations (Symfony, Laravel).
 * The {@code name} parameter is used as the job registry key.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class BatchJob
{
    public function __construct(public string $name)
    {
    }
}
