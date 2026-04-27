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

namespace Lemric\BatchProcessing\Tests\Repeat;

use Lemric\BatchProcessing\Chunk\SimpleCompletionPolicy;
use Lemric\BatchProcessing\Repeat\RepeatTemplate;
use Lemric\BatchProcessing\Step\RepeatStatus;
use PHPUnit\Framework\TestCase;

final class RepeatTemplateTest extends TestCase
{
    public function testIteratesUntilFinished(): void
    {
        $count = 0;
        $template = new RepeatTemplate(new SimpleCompletionPolicy(100));
        $result = $template->iterate(function () use (&$count): RepeatStatus {
            ++$count;

            return $count >= 5 ? RepeatStatus::FINISHED : RepeatStatus::CONTINUABLE;
        });

        self::assertSame(RepeatStatus::FINISHED, $result);
        self::assertSame(5, $count);
    }

    public function testStopsAtCompletionPolicy(): void
    {
        $count = 0;
        $template = new RepeatTemplate(new SimpleCompletionPolicy(3));
        $template->iterate(function () use (&$count): RepeatStatus {
            ++$count;

            return RepeatStatus::CONTINUABLE;
        });

        self::assertSame(3, $count);
    }
}
