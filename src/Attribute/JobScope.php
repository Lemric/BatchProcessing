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
 * Marks a service as job-scoped: a new instance is created for each JobExecution.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class JobScope
{
}
