<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Set;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Command to set (create or update) application setting
 */
readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId,
        public string $key,
        public string $value
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }

        if (strlen($this->key) > 255) {
            throw new InvalidArgumentException('Setting key cannot exceed 255 characters');
        }
    }
}
