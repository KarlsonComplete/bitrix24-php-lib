<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Get;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepositoryInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Handler for Get command
 */
readonly class Handler
{
    public function __construct(
        private ApplicationSettingRepositoryInterface $applicationSettingRepository,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Command $command): ApplicationSettingInterface
    {
        $this->logger->debug('ApplicationSettings.Get.start', [
            'applicationInstallationId' => $command->applicationInstallationId->toRfc4122(),
            'key' => $command->key,
            'b24UserId' => $command->b24UserId,
            'b24DepartmentId' => $command->b24DepartmentId,
        ]);

        $setting = $this->applicationSettingRepository->findByKey(
            $command->applicationInstallationId,
            $command->key,
            $command->b24UserId,
            $command->b24DepartmentId
        );

        if (null === $setting) {
            throw new InvalidArgumentException(
                sprintf(
                    'Setting with key "%s" not found for application installation "%s"',
                    $command->key,
                    $command->applicationInstallationId->toRfc4122()
                )
            );
        }

        $this->logger->debug('ApplicationSettings.Get.finish', [
            'settingId' => $setting->getId()->toRfc4122(),
        ]);

        return $setting;
    }
}
