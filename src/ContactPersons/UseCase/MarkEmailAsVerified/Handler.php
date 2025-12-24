<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkEmailAsVerified;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
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
            'email' => $command->email,
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
        $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

        $actualEmail = $contactPerson->getEmail();
        if (mb_strtolower((string)$actualEmail) !== mb_strtolower($command->email)) {
            $this->logger->warning('ContactPerson.ConfirmEmailVerification.emailMismatch', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'actualEmail' => $actualEmail,
                'expectedEmail' => $command->email,
            ]);
            throw new InvalidArgumentException(sprintf(
                'Email mismatch for contact person %s: actual="%s", expected="%s"',
                $command->contactPersonId->toRfc4122(),
                $actualEmail,
                $command->email
            ));
        }

        $contactPerson->markEmailAsVerified($command->emailVerifiedAt);

        $this->contactPersonRepository->save($contactPerson);
        $this->flusher->flush($contactPerson);

        $this->logger->info('ContactPerson.ConfirmEmailVerification.finish', [
            'contactPersonId' => $contactPerson->getId()->toRfc4122(),
            'emailVerifiedAt' => $contactPerson->getEmailVerifiedAt()?->toIso8601String(),
        ]);
    }
}
