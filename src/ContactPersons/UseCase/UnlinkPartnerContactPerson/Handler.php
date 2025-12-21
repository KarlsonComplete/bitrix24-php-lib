<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\UnlinkPartnerContactPerson;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use Psr\Log\LoggerInterface;

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
        $this->logger->info('ContactPerson.UninstallPartnerContactPerson.start', [
            'applicationInstallationId' => $command->applicationInstallationId,
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $applicationInstallation */
        $applicationInstallation = $this->applicationInstallationRepository->getById($command->applicationInstallationId);

        $contactPersonId = $applicationInstallation->getBitrix24PartnerContactPersonId();

        // unlink from installation first
        $applicationInstallation->unlinkBitrix24PartnerContactPerson();
        $this->applicationInstallationRepository->save($applicationInstallation);

        // если партнёрский контакт не был привязан — просто логируем и флашим установку
        if (null === $contactPersonId) {
            $this->logger->info('ContactPerson.UninstallPartnerContactPerson.noLinkedContact', [
                'applicationInstallationId' => $command->applicationInstallationId,
            ]);
            $this->flusher->flush($applicationInstallation);
        } else {
            /** @var null|AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
            $contactPerson = $this->contactPersonRepository->getById($contactPersonId);

            // если ID есть, но сущность не нашли в репозитории — логируем warning и флашим только установку
            if (null === $contactPerson) {
                $this->logger->warning('ContactPerson.UninstallPartnerContactPerson.linkedContactNotFoundInRepo', [
                    'contact_person_id' => $contactPersonId->toRfc4122(),
                    'applicationInstallationId' => $command->applicationInstallationId,
                ]);
                $this->flusher->flush($applicationInstallation);
            } else {
                // нормальный сценарий: помечаем контакт удалённым, сохраняем и флашим обе сущности
                $contactPerson->markAsDeleted($command->comment);
                $this->contactPersonRepository->save($contactPerson);
                $this->flusher->flush($applicationInstallation, $contactPerson);
            }
        }

        $this->logger->info('ContactPerson.UninstallPartnerContactPerson.finish', [
            'contact_person_id' => $contactPersonId?->toRfc4122(),
        ]);
    }
}
