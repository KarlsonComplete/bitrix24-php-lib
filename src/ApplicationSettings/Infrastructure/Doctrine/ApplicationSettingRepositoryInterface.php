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
     * Find global setting by key
     * Returns setting that is not tied to user or department.
     */
    public function findGlobalByKey(Uuid $uuid, string $key): ?ApplicationSettingInterface;

    /**
     * Find personal setting by key and user ID.
     */
    public function findPersonalByKey(
        Uuid $uuid,
        string $key,
        int $b24UserId
    ): ?ApplicationSettingInterface;

    /**
     * Find departmental setting by key and department ID.
     */
    public function findDepartmentalByKey(
        Uuid $uuid,
        string $key,
        int $b24DepartmentId
    ): ?ApplicationSettingInterface;

    /**
     * Find setting by key with optional user and department filters
     * Provides flexible search based on scope.
     */
    public function findByKey(
        Uuid $uuid,
        string $key,
        ?int $b24UserId = null,
        ?int $b24DepartmentId = null
    ): ?ApplicationSettingInterface;

    /**
     * Find all settings for application installation (all scopes).
     *
     * @return ApplicationSettingInterface[]
     */
    public function findAllForInstallation(Uuid $uuid): array;
}
