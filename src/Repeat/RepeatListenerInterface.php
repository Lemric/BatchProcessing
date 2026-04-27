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

namespace Lemric\BatchProcessing\Repeat;

use Lemric\BatchProcessing\Step\RepeatStatus;
use Throwable;

/**
 * Lifecycle hook for {@see RepeatTemplate} iterations.
 */
interface RepeatListenerInterface
{
    public function after(RepeatContext $context, RepeatStatus $result): void;

    public function before(RepeatContext $context): void;

    public function close(RepeatContext $context): void;

    public function onError(RepeatContext $context, Throwable $throwable): void;

    public function open(RepeatContext $context): void;
}
