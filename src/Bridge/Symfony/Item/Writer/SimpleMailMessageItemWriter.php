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

namespace Lemric\BatchProcessing\Bridge\Symfony\Item\Writer;

use Lemric\BatchProcessing\Chunk\Chunk;
use Lemric\BatchProcessing\Item\ItemWriterInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * {@code SimpleMailMessageItemWriter} parity backed by Symfony Mailer. Each item
 * in the chunk MUST be a {@see RawMessage} (typically {@see \Symfony\Component\Mime\Email}).
 *
 * Requires {@code symfony/mailer} (suggested dependency).
 *
 * @implements ItemWriterInterface<RawMessage>
 */
final readonly class SimpleMailMessageItemWriter implements ItemWriterInterface
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function write(Chunk $items): void
    {
        foreach ($items->getOutputItems() as $message) {
            $this->mailer->send($message);
        }
    }
}
