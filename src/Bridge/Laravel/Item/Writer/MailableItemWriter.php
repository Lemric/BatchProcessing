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

namespace Lemric\BatchProcessing\Bridge\Laravel\Item\Writer;

use Illuminate\Contracts\Mail\{Factory as MailFactory, Mailable};
use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;

/**
 * Laravel Mail counterpart of {@see \Lemric\BatchProcessing\Bridge\Symfony\Item\Writer\SimpleMailMessageItemWriter}.
 *
 * Each chunk item must be a {@see Mailable}.
 *
 * @implements ItemWriterInterface<Mailable>
 */
final readonly class MailableItemWriter implements ItemWriterInterface
{
    public function __construct(private MailFactory $mailer)
    {
    }

    public function write(Chunk $items): void
    {
        foreach ($items->getOutputItems() as $item) {
            /* @var Mailable $item */
            $this->mailer->mailer()->send($item);
        }
    }
}
