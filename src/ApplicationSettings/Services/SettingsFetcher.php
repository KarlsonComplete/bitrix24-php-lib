<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Bitrix24\Lib\ApplicationSettings\Services\Exception\SettingsItemNotFoundException;
use Symfony\Component\Uid\Uuid;

/**
 * Service for fetching settings with cascading resolution.
 *
 * Priority order:
 * 1. Personal setting (if userId provided)
 * 2. Departmental setting (if departmentId provided)
 * 3. Global setting (fallback)
 */
readonly class SettingsFetcher
{
    public function __construct(
        private ApplicationSettingsItemRepositoryInterface $repository
    ) {}

    /**
     * Get setting item with cascading resolution.
     *
     * Tries to find setting in following order:
     * 1. Personal (if userId provided)
     * 2. Departmental (if departmentId provided)
     * 3. Global (always as fallback)
     *
     * @throws SettingsItemNotFoundException if setting not found at any level
     */
    public function getItem(
        Uuid $uuid,
        string $key,
        ?int $userId = null,
        ?int $departmentId = null
    ): ApplicationSettingsItemInterface {
        $allSettings = $this->repository->findAllForInstallationByKey($uuid, $key);

        // Try to find personal setting (highest priority)
        if (null !== $userId) {
            foreach ($allSettings as $allSetting) {
                if ($allSetting->isPersonal()
                    && $allSetting->getB24UserId() === $userId
                ) {
                    return $allSetting;
                }
            }
        }

        // Try to find departmental setting (medium priority)
        if (null !== $departmentId) {
            foreach ($allSettings as $allSetting) {
                if ($allSetting->isDepartmental()
                    && $allSetting->getB24DepartmentId() === $departmentId
                ) {
                    return $allSetting;
                }
            }
        }

        // Fallback to global setting (lowest priority)
        foreach ($allSettings as $allSetting) {
            if ($allSetting->isGlobal()) {
                return $allSetting;
            }
        }

        throw SettingsItemNotFoundException::byKey($key);
    }

    /**
     * Get setting value as string (shortcut method).
     *
     * @throws SettingsItemNotFoundException if setting not found at any level
     */
    public function getSettingValue(
        Uuid $uuid,
        string $key,
        ?int $userId = null,
        ?int $departmentId = null
    ): string {
        $applicationSetting = $this->getItem($uuid, $key, $userId, $departmentId);

        return $applicationSetting->getValue();
    }
}
