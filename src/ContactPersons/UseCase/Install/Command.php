<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\Install;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;

readonly class Command
{
    public function __construct(
        public FullName $fullName,
        public ?string $email,
        public ?PhoneNumber $mobilePhoneNumber,
        public ?string $comment,
        public ?string $externalId,
        public ?int $bitrix24UserId,
        public ?Uuid $bitrix24PartnerId,
        public ?IP $userAgentIp,
        public ?string $userAgent,
        public ?string $userAgentReferrer,
        public string $userAgentVersion,
        public ?string $memberId,
        public ?ContactPersonType $contactPersonType
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === $this->fullName->name) {
            throw new \InvalidArgumentException('Full name cannot be empty.');
        }

        if (null !== $this->email && '' === trim($this->email)) {
            throw new \InvalidArgumentException('Email cannot be empty if provided.');
        }

        if (null !== $this->externalId && '' === trim($this->externalId)) {
            throw new \InvalidArgumentException('External ID cannot be empty if provided.');
        }

        if ('' === $this->memberId) {
            throw new InvalidArgumentException('Member ID must be a non-empty string.');
        }
    }
}
