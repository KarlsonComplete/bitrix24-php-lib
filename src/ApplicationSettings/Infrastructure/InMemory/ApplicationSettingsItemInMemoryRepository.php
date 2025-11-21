<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory implementation of ApplicationSettingsItemRepository for testing.
 */
class ApplicationSettingsItemInMemoryRepository implements ApplicationSettingsItemRepositoryInterface
{
    /** @var array<string, ApplicationSettingsItemInterface> */
    private array $settings = [];

    #[\Override]
    public function save(ApplicationSettingsItemInterface $applicationSetting): void
    {
        $this->settings[$applicationSetting->getId()->toRfc4122()] = $applicationSetting;
    }

    #[\Override]
    public function delete(ApplicationSettingsItemInterface $applicationSetting): void
    {
        unset($this->settings[$applicationSetting->getId()->toRfc4122()]);
    }

    #[\Override]
    public function findById(Uuid $uuid): ?ApplicationSettingsItemInterface
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
     * @return ApplicationSettingsItemInterface[]
     */
    public function getAllIncludingDeleted(): array
    {
        return array_values($this->settings);
    }
}
