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

namespace Lemric\BatchProcessing\Bridge\Symfony\Messenger;

use Lemric\BatchProcessing\Domain\JobParameters;
use Lemric\BatchProcessing\Security\{AsyncJobMessageSigner, AsyncJobMessageSigningRequirement};
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Callable adapter bridging {@see \Lemric\BatchProcessing\Launcher\AsyncJobLauncher} with
 * Symfony Messenger. The {@see AsyncJobLauncher} invokes this object as a callable, which
 * dispatches a {@see RunJobMessage} onto the configured transport.
 */
final readonly class MessengerJobDispatcher
{
    public function __construct(
        private MessageBusInterface $bus,
        private string $transport,
        private string $messageSecret = '',
    ) {
    }

    public function __invoke(int $jobExecutionId, string $jobName, JobParameters $parameters): void
    {
        AsyncJobMessageSigningRequirement::assertSecretForDispatch($this->messageSecret, 'Symfony MessengerJobDispatcher');
        $issuedAt = time();
        $parametersJobKey = $parameters->toJobKey();
        $signature = AsyncJobMessageSigner::sign($this->messageSecret, $jobExecutionId, $jobName, $issuedAt, $parametersJobKey);
        $envelope = new Envelope(new RunJobMessage($jobExecutionId, $jobName, $issuedAt, $signature, $parametersJobKey));
        if ('' !== $this->transport) {
            $envelope = $envelope->with(new TransportNamesStamp([$this->transport]));
        }
        $this->bus->dispatch($envelope);
    }
}
