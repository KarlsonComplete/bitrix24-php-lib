<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Carbon\CarbonImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Application setting entity
 *
 * Stores key-value settings for application installations.
 * Each ApplicationInstallation can have multiple settings identified by unique keys.
 */
class ApplicationSetting extends AggregateRoot
{
    private readonly CarbonImmutable $createdAt;
    private CarbonImmutable $updatedAt;
    private string $value;

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $applicationInstallationId,
        private readonly string $key,
        string $value
    ) {
        $this->validateKey($key);
        $this->validateValue($value);

        $this->value = $value;
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getApplicationInstallationId(): Uuid
    {
        return $this->applicationInstallationId;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update setting value
     */
    public function updateValue(string $value): void
    {
        $this->validateValue($value);

        if ($this->value !== $value) {
            $this->value = $value;
            $this->updatedAt = new CarbonImmutable();
        }
    }

    /**
     * Validate setting key
     */
    private function validateKey(string $key): void
    {
        if ('' === trim($key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }

        if (strlen($key) > 255) {
            throw new InvalidArgumentException('Setting key cannot exceed 255 characters');
        }

        // Key should contain only alphanumeric characters, underscores, dots, and hyphens
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
            throw new InvalidArgumentException(
                'Setting key can only contain alphanumeric characters, underscores, dots, and hyphens'
            );
        }
    }

    /**
     * Validate setting value
     */
    private function validateValue(string $value): void
    {
        // Value can be empty but not null (handled by type hint)
        // We store value as string, could be JSON or plain text
        // No specific validation needed here, can be extended if needed
    }
}
