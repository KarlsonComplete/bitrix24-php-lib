<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Delete;

use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Command to delete application setting.
 */
readonly class Command
{
    public function __construct(
        public Uuid $applicationInstallationId,
        public string $key,
        public ?int $b24UserId = null,
        public ?int $b24DepartmentId = null
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->key)) {
            throw new InvalidArgumentException('Setting key cannot be empty');
        }

        if (null !== $this->b24UserId && $this->b24UserId <= 0) {
            throw new InvalidArgumentException('Bitrix24 user ID must be positive integer');
        }

        if (null !== $this->b24DepartmentId && $this->b24DepartmentId <= 0) {
            throw new InvalidArgumentException('Bitrix24 department ID must be positive integer');
        }

        if (null !== $this->b24UserId && null !== $this->b24DepartmentId) {
            throw new InvalidArgumentException(
                'Setting cannot be both personal and departmental. Choose one scope.'
            );
        }
    }
}
