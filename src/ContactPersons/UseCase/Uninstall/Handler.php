<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Uninstall;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationInterface;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Repository\ApplicationInstallationRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
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
        $this->logger->info('ContactPerson.Uninstall.start', [
            'memberId' => $command->memberId,
            'contactPersonType' => $command->contactPersonType?->value,
            'contactPersonId' => $command->contactPersonId?->toRfc4122(),
        ]);

        $entitiesToFlush = []; // Объявляем переменную

        // Если передан memberId, пытаемся найти установку и отвязать контактное лицо нужного типа
        if ($command->memberId !== null && $command->contactPersonType !== null) {
            /** @var null|AggregateRootEventsEmitterInterface|ApplicationInstallationInterface $activeInstallation */
            $activeInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($command->memberId);

            if ($activeInstallation !== null) {
                $contactPersonId = null;

                if ($command->contactPersonType === ContactPersonType::personal) {
                    $contactPersonId = $activeInstallation->getContactPersonId();
                    $activeInstallation->unlinkContactPerson();
                }

                if ($command->contactPersonType === ContactPersonType::partner) {
                    $contactPersonId = $activeInstallation->getBitrix24PartnerContactPersonId();
                    $activeInstallation->unlinkBitrix24PartnerContactPerson();
                }

                $entitiesToFlush[] = $activeInstallation;
                $this->applicationInstallationRepository->save($activeInstallation);


                // Если у установки был контакт, помечаем его как удалённый
                if ($contactPersonId !== null) {
                    /** @var null|AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
                    $contactPerson = $this->contactPersonRepository->getById($contactPersonId);
                    if ($contactPerson !== null) {
                        $this->logger->info('ContactPerson.Uninstall.deletingContactPersonFromInstallation', [
                            'contactPersonId' => $contactPersonId->toRfc4122(),
                        ]);
                        $contactPerson->markAsDeleted($command->comment);
                        $this->contactPersonRepository->save($contactPerson);
                        $entitiesToFlush[] = $contactPerson;
                    }
                }
                $this->flusher->flush(...array_filter($entitiesToFlush, fn ($entity): bool => $entity instanceof AggregateRootEventsEmitterInterface));
            }
        }

        // Если передан ID контактного лица, удаляем его
        if ($command->contactPersonId !== null) {
            $alreadyDeleted = false;
            foreach ($entitiesToFlush as $entity) {
                if ($entity instanceof ContactPersonInterface && $entity->getId()->equals($command->contactPersonId)) {
                    $alreadyDeleted = true;
                    break;
                }
            }

            if (!$alreadyDeleted) {
                $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);
                if ($contactPerson !== null) {
                    $contactPerson->markAsDeleted($command->comment);
                    $this->contactPersonRepository->save($contactPerson);
                    $this->flusher->flush($contactPerson);
                }
            }
        }

        $this->logger->info('ContactPerson.Uninstall.finish', [
            'memberId' => $command->memberId,
            'contactPersonType' => $command->contactPersonType?->value,
            'contactPersonId' => $command->contactPersonId?->toRfc4122(),
        ]);
    }
}
