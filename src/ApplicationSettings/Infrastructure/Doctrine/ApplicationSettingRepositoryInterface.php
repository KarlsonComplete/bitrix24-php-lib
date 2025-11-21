<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Interface for ApplicationSetting repository.
 *
 * @todo Move this interface to b24-php-sdk contracts after stabilization
 */
interface ApplicationSettingRepositoryInterface
{
    /**
     * Save application setting.
     */
    public function save(ApplicationSettingInterface $applicationSetting): void;

    /**
     * Delete application setting.
     */
    public function delete(ApplicationSettingInterface $applicationSetting): void;

    /**
     * Find setting by ID.
     */
    public function findById(Uuid $uuid): ?ApplicationSettingInterface;

    /**
     * Find all settings for application installation (all scopes).
     *
     * @return ApplicationSettingInterface[]
     */
    public function findAllForInstallation(Uuid $uuid): array;

    /**
     * Find all settings for application installation by key (all scopes with same key).
     *
     * @return ApplicationSettingInterface[]
     */
    public function findAllForInstallationByKey(Uuid $uuid, string $key): array;
}
