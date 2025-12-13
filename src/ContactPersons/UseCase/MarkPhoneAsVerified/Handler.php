<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkPhoneAsVerified;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.ConfirmEmailVerification.start', [
            'contactPersonId' => $command->contactPersonId->toRfc4122(),
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
        $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);
        $contactPerson->markMobilePhoneAsVerified();

        $this->contactPersonRepository->save($contactPerson);
        $this->flusher->flush($contactPerson);

        $this->logger->info('ContactPerson.ConfirmEmailVerification.finish', [
            'contactPersonId' => $contactPerson->getId()->toRfc4122(),
        ]);
    }
}