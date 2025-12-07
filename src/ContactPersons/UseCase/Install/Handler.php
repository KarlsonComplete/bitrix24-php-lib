<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Install;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): ContactPerson
    {
        $this->logger->info('ContactPerson.Install.start', [
            'externalId' => $command->externalId,
        ]);

        // Проверяем, существует ли контакт с таким externalId
        if (null !== $command->externalId) {
            $existing = $this->contactPersonRepository->findByExternalId($command->externalId);
            if ([] !== $existing) {
                throw new InvalidArgumentException('Contact with this external ID already exists.');
            }
        }

        $userAgentInfo = new UserAgentInfo($command->userAgentIp, $command->userAgent, $command->userAgentReferrer);

        $uuidV7 = Uuid::v7();

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
        $this->flusher->flush($contactPerson);

        $this->logger->info('ContactPerson.Install.finish', [
            'contact_person_id' => $uuidV7,
            'externalId' => $command->externalId,
        ]);

        return $contactPerson;
    }
}
