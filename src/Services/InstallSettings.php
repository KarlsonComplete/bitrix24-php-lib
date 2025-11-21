<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service for creating default application settings during installation
 *
 * This service is responsible for initializing default global settings
 * when an application is installed on a Bitrix24 portal
 */
readonly class InstallSettings
{
    public function __construct(
        private ApplicationSettingRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create default settings for application installation
     *
     * @param Uuid $applicationInstallationId Application installation UUID
     * @param array<string, string> $defaultSettings Key-value pairs of default settings
     */
    public function createDefaultSettings(
        Uuid $applicationInstallationId,
        array $defaultSettings
    ): void {
        $this->logger->info('InstallSettings.createDefaultSettings.start', [
            'applicationInstallationId' => $applicationInstallationId->toRfc4122(),
            'settingsCount' => count($defaultSettings),
        ]);

        foreach ($defaultSettings as $key => $value) {
            // Check if setting already exists
            $existingSetting = $this->applicationSettingRepository->findGlobalByKey(
                $applicationInstallationId,
                $key
            );

            if (null === $existingSetting) {
                // Create new global setting
                $setting = new ApplicationSetting(
                    Uuid::v7(),
                    $applicationInstallationId,
                    $key,
                    $value,
                    null, // Global setting - no user ID
                    null  // Global setting - no department ID
                );

                $this->applicationSettingRepository->save($setting);

                $this->logger->debug('InstallSettings.settingCreated', [
                    'key' => $key,
                    'settingId' => $setting->getId()->toRfc4122(),
                ]);
            } else {
                $this->logger->debug('InstallSettings.settingAlreadyExists', [
                    'key' => $key,
                    'settingId' => $existingSetting->getId()->toRfc4122(),
                ]);
            }
        }

        $this->flusher->flush();

        $this->logger->info('InstallSettings.createDefaultSettings.finish', [
            'applicationInstallationId' => $applicationInstallationId->toRfc4122(),
        ]);
    }

    /**
     * Get recommended default settings structure
     *
     * @return array<string, string> Recommended default settings
     */
    public static function getRecommendedDefaults(): array
    {
        return [
            'app.enabled' => 'true',
            'app.version' => '1.0.0',
            'app.locale' => 'en',
            'feature.notifications' => 'true',
            'feature.analytics' => 'false',
        ];
    }
}
