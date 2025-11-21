<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Set;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Handler for Set command
 *
 * Creates new setting or updates existing one
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Set.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
            'b24UserId' => $command->b24UserId,
            'b24DepartmentId' => $command->b24DepartmentId,
        ]);

        // Try to find existing setting with the same scope
        $setting = $this->applicationSettingRepository->findByKey(
            $command->applicationInstallationId,
            $command->key,
            $command->b24UserId,
            $command->b24DepartmentId
        );

        if (null !== $setting) {
            // Update existing setting
            $setting->updateValue($command->value);
            $this->logger->debug('ApplicationSettings.Set.updated', [
                'settingId' => $setting->getId()->toRfc4122(),
            ]);
        } else {
            // Create new setting
            $setting = new ApplicationSetting(
                Uuid::v7(),
                $command->applicationInstallationId,
                $command->key,
                $command->value,
                $command->b24UserId,
                $command->b24DepartmentId
            );
            $this->applicationSettingRepository->save($setting);
            $this->logger->debug('ApplicationSettings.Set.created', [
                'settingId' => $setting->getId()->toRfc4122(),
            ]);
        }

        $this->flusher->flush($setting);

        $this->logger->info('ApplicationSettings.Set.finish', [
            'settingId' => $setting->getId()->toRfc4122(),
        ]);
    }
}
