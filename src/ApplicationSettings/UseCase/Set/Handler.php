<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Set;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;
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
        private ApplicationSettingRepository $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Set.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
        ]);

        // Try to find existing setting
        $setting = $this->applicationSettingRepository->findByApplicationInstallationIdAndKey(
            $command->applicationInstallationId,
            $command->key
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
                $command->value
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
