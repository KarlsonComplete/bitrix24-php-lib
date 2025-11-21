<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;
use Bitrix24\Lib\Services\Flusher;
use Psr\Log\LoggerInterface;

/**
 * Handler for OnApplicationDelete command
 *
 * Soft-deletes all settings when application is uninstalled.
 * Settings are marked as deleted rather than removed from database
 * to maintain history and enable recovery if needed.
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
        $this->logger->info('ApplicationSettings.OnApplicationDelete.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
        ]);

        // Soft-delete all settings for this installation
        $this->applicationSettingRepository->softDeleteByApplicationInstallationId(
            $command->applicationInstallationId
        );

        $this->flusher->flush();

        $this->logger->info('ApplicationSettings.OnApplicationDelete.finish', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
        ]);
    }
}
