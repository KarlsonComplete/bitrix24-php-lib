<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Delete;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepositoryInterface;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Handler for Delete command.
 *
 * Deletes global application settings only.
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingRepositoryInterface $applicationSettingRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $this->logger->info('ApplicationSettings.Delete.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
        ]);

        // Find global setting by key
        $allSettings = $this->applicationSettingRepository->findAllForInstallation(
            $command->applicationInstallationId
        );

        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === $command->key && $allSetting->isGlobal()) {
                $setting = $allSetting;

                break;
            }
        }

        if (!$setting instanceof ApplicationSettingInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Global setting with key "%s" not found for application installation "%s"',
                    $command->key,
                    $command->applicationInstallationId->toRfc4122()
                )
            );
        }

        $settingId = $setting->getId()->toRfc4122();

        // Soft-delete: mark as deleted instead of removing
        $setting->markAsDeleted();
        $this->flusher->flush();

        $this->logger->info('ApplicationSettings.Delete.finish', [
            'settingId' => $settingId,
            'softDeleted' => true,
        ]);
    }
}
