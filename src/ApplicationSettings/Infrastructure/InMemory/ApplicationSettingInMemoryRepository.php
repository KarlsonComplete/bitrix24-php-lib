<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of ApplicationSettingRepository for testing.
 */
class ApplicationSettingInMemoryRepository implements ApplicationSettingRepositoryInterface
{
    /** @var array<string, ApplicationSettingInterface> */
    private array $settings = [];

    #[\Override]
    public function save(ApplicationSettingInterface $applicationSetting): void
    {
        $this->settings[$applicationSetting->getId()->toRfc4122()] = $applicationSetting;
    }

    #[\Override]
    public function delete(ApplicationSettingInterface $applicationSetting): void
    {
        unset($this->settings[$applicationSetting->getId()->toRfc4122()]);
    }

    #[\Override]
    public function findById(Uuid $uuid): ?ApplicationSettingInterface
    {
        foreach ($this->settings as $setting) {
            if ($setting->getId()->toRfc4122() === $uuid->toRfc4122() && $setting->isActive()) {
                return $setting;
            }
        }

        return null;
    }

    #[\Override]
    public function findAllForInstallation(Uuid $uuid): array
    {
        $result = [];
        foreach ($this->settings as $setting) {
            if ($setting->getApplicationInstallationId()->toRfc4122() === $uuid->toRfc4122()
                && $setting->isActive()
            ) {
                $result[] = $setting;
            }
        }

        return $result;
    }

    #[\Override]
    public function findAllForInstallationByKey(Uuid $uuid, string $key): array
    {
        $result = [];
        foreach ($this->settings as $setting) {
            if ($setting->getApplicationInstallationId()->toRfc4122() === $uuid->toRfc4122()
                && $setting->getKey() === $key
                && $setting->isActive()
            ) {
                $result[] = $setting;
            }
        }

        return $result;
    }

    /**
     * Clear all settings (for testing).
     */
    public function clear(): void
    {
        $this->settings = [];
    }

    /**
     * Get all settings including deleted (for testing).
     *
     * @return ApplicationSettingInterface[]
     */
    public function getAllIncludingDeleted(): array
    {
        return array_values($this->settings);
    }
}
