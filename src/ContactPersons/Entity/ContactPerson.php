<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\UserAgentInfo;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonDeletedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailVerifiedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonFullNameChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonMobilePhoneVerifiedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Uid\Uuid;

class ContactPerson extends AggregateRoot implements ContactPersonInterface
{
    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    private ?bool $isEmailVerified;

    private ?bool $isMobilePhoneVerified;

    public function __construct(
        private readonly Uuid $id,
        private ContactPersonStatus $status,
        private FullName $fullName,
        private ?string $email,
        private ?CarbonImmutable $emailVerifiedAt,
        private ?PhoneNumber $phoneNumber,
        private ?CarbonImmutable $phoneNumberVerifiedAt,
        private ?string $comment,
        private ?string $externalId,
        private readonly ?int $bitrix24UserId,
        private ?Uuid $bitrix24PartnerId,
        private ?UserAgentInfo $userAgentInfo,
    ) {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
        $this->isEmailVerified = false;
        $this->isMobilePhoneVerified = false;
    }

    #[\Override]
    public function getId(): Uuid
    {
        return $this->id;
    }

    #[\Override]
    public function getStatus(): ContactPersonStatus
    {
        return $this->status;
    }

    #[\Override]
    public function markAsActive(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::blocked, ContactPersonStatus::deleted], true)) {
            throw new LogicException(sprintf('you must be in status blocked or deleted , now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::active;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }
    }

    #[\Override]
    public function markAsBlocked(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::active, ContactPersonStatus::deleted], true)) {
            throw new LogicException(sprintf('you must be in status active or deleted, now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::blocked;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }

        $this->events[] = new ContactPersonBlockedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function markAsDeleted(?string $comment): void
    {
        if (!in_array($this->status, [ContactPersonStatus::active, ContactPersonStatus::blocked], true)) {
            throw new LogicException(sprintf('you must be in status active or blocked, now status is «%s»', $this->status->value));
        }

        $this->status = ContactPersonStatus::deleted;
        $this->updatedAt = new CarbonImmutable();
        if (null !== $comment) {
            $this->comment = $comment;
        }

        $this->events[] = new ContactPersonDeletedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function getFullName(): FullName
    {
        return $this->fullName;
    }

    #[\Override]
    public function changeFullName(FullName $fullName): void
    {
        if ('' === trim($fullName->name)) {
            throw new InvalidArgumentException('FullName name cannot be empty.');
        }

        $this->fullName = $fullName;
        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonFullNameChangedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getUpdatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    #[\Override]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    #[\Override]
    public function changeEmail(?string $email): void
    {
        $this->email = $email;

        $this->updatedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonEmailChangedEvent(
            $this->id,
            $this->updatedAt,
        );
    }

    #[\Override]
    public function markEmailAsVerified(): void
    {
        $this->isEmailVerified = true;
        $this->emailVerifiedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonEmailVerifiedEvent(
            $this->id,
            $this->emailVerifiedAt,
        );
    }

    #[\Override]
    public function getEmailVerifiedAt(): ?CarbonImmutable
    {
        return $this->emailVerifiedAt;
    }

    #[\Override]
    public function changeMobilePhone(?PhoneNumber $phoneNumber, ?bool $isMobilePhoneVerified = null): void
    {
        if (null !== $phoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $isValidNumber = $phoneUtil->isValidNumber($phoneNumber);

            if (!$isValidNumber) {
                throw new InvalidArgumentException('Invalid phone number.');
            }

            $this->phoneNumber = $phoneNumber;
        }

        if (null !== $isMobilePhoneVerified) {
            $this->isMobilePhoneVerified = $isMobilePhoneVerified;
            $this->markMobilePhoneAsVerified();
        }

        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getMobilePhone(): ?PhoneNumber
    {
        return $this->phoneNumber;
    }

    #[\Override]
    public function getMobilePhoneVerifiedAt(): ?CarbonImmutable
    {
        return $this->phoneNumberVerifiedAt;
    }

    #[\Override]
    public function markMobilePhoneAsVerified(): void
    {
        $this->phoneNumberVerifiedAt = new CarbonImmutable();
        $this->events[] = new ContactPersonMobilePhoneVerifiedEvent(
            $this->id,
            $this->phoneNumberVerifiedAt,
        );
    }

    #[\Override]
    public function getComment(): ?string
    {
        return $this->comment;
    }

    #[\Override]
    public function setExternalId(?string $externalId): void
    {
        if ('' === $externalId) {
            throw new InvalidArgumentException('ExternalId cannot be empty string');
        }

        if ($this->externalId === $externalId) {
            return;
        }

        $this->externalId = $externalId;
        $this->updatedAt = new CarbonImmutable();
    }

    #[\Override]
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    #[\Override]
    public function getBitrix24UserId(): ?int
    {
        return $this->bitrix24UserId;
    }

    #[\Override]
    public function getBitrix24PartnerId(): ?Uuid
    {
        return $this->bitrix24PartnerId;
    }

    #[\Override]
    public function setBitrix24PartnerId(?Uuid $uuid): void
    {
        $this->bitrix24PartnerId = $uuid;
        $this->updatedAt = new CarbonImmutable();
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function isMobilePhoneVerified(): bool
    {
        return $this->isMobilePhoneVerified;
    }

    public function getUserAgentInfo(): UserAgentInfo
    {
        return $this->userAgentInfo;
    }
}
