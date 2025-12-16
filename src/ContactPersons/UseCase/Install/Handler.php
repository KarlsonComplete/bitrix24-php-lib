<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Install;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private ApplicationInstallationRepositoryInterface $applicationInstallationRepository,
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ContactPerson.Install.start', [
            'externalId' => $command->externalId,
            'memberId' => $command->memberId,
            'contactPersonType' => $command->contactPersonType,
        ]);

        $userAgentInfo = new UserAgentInfo($command->userAgentIp, $command->userAgent, $command->userAgentReferrer);

        $uuidV7 = Uuid::v7();

        $entitiesToFlush = [];

        $contactPerson = new ContactPerson(
            $uuidV7,
            ContactPersonStatus::active,
            $command->fullName,
            $command->email,
            null,
            $command->mobilePhoneNumber,
            null,
            $command->comment,
            $command->externalId,
            $command->bitrix24UserId,
            $command->bitrix24PartnerId,
            $userAgentInfo,
            true
        );

        $this->contactPersonRepository->save($contactPerson);

        $entitiesToFlush[] = $contactPerson;

        if (null !== $command->memberId) {
            /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
            $activeInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

            if (ContactPersonType::personal == $command->contactPersonType) {
                $activeInstallation->linkContactPerson($uuidV7);
            }

            if (ContactPersonType::partner == $command->contactPersonType) {
                $activeInstallation->linkBitrix24PartnerContactPerson($uuidV7);
            }

            $this->applicationInstallationRepository->save($activeInstallation);
            $entitiesToFlush[] = $activeInstallation;
        }

        $this->flusher->flush($activeInstallation,$contactPerson);

        $this->logger->info('ContactPerson.Install.finish', [
            'contact_person_id' => $uuidV7,
            'externalId' => $command->externalId,
        ]);
    }
}
