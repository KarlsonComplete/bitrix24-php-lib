<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Handler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for creating default application settings during installation.
 *
 * This service is responsible for initializing default global settings
 * when an application is installed on a Bitrix24 portal
 */
readonly class InstallSettings
{
    public function __construct(
        private Handler $setHandler,
        private LoggerInterface $logger
    ) {}

    /**
     * Create default settings for application installation.
     *
     * @param Uuid                                                $applicationInstallationId Application installation UUID
     * @param array<string, array{value: string, required: bool}> $defaultSettings           Settings with value and required flag
     */
    public function createDefaultSettings(
        Uuid $applicationInstallationId,
        array $defaultSettings
    ): void {
        $this->logger->info('InstallSettings.createDefaultSettings.start', [
            'applicationInstallationId' => $applicationInstallationId->toRfc4122(),
            'settingsCount' => count($defaultSettings),
        ]);

        foreach ($defaultSettings as $key => $config) {
            // Use Set UseCase to create or update setting
            $command = new Command(
                applicationInstallationId: $applicationInstallationId,
                key: $key,
                value: $config['value'],
                isRequired: $config['required']
            );

            $this->setHandler->handle($command);

            $this->logger->debug('InstallSettings.settingProcessed', [
                'key' => $key,
                'isRequired' => $config['required'],
            ]);
        }

        $this->logger->info('InstallSettings.createDefaultSettings.finish', [
            'applicationInstallationId' => $applicationInstallationId->toRfc4122(),
        ]);
    }
}
