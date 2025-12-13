<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Uninstall;

use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public ?string $memberId = null,
        public ?ContactPersonType $contactPersonType = null,
        public ?Uuid $contactPersonId = null,
        public ?string $comment = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->memberId === null && $this->contactPersonId === null) {
            throw new InvalidArgumentException('Either memberId or contactPersonId must be provided.');
        }

        if ($this->memberId !== null && '' === $this->memberId) {
            throw new InvalidArgumentException('Member ID must be a non-empty string if provided.');
        }

        if ($this->memberId !== null && $this->contactPersonType === null) {
            throw new InvalidArgumentException('ContactPersonType must be provided if memberId is provided.');
        }
    }
}
