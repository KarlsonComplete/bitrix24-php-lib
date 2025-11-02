<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonBlockedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonDeletedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailVerifiedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonMobilePhoneVerifiedEvent;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\SDK\Core\Exceptions\LogicException;
use Carbon\CarbonImmutable;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class ContactPerson extends AggregateRoot implements ContactPersonInterface
{
    private readonly CarbonImmutable $createdAt;

    private CarbonImmutable $updatedAt;

    public function __construct(
        private readonly Uuid $id,
        private ContactPersonStatus $status,
        private readonly FullName $fullName,
        private ?string $email,
        private ?bool $isEmailVerified,
        private ?CarbonImmutable $emailVerifiedAt,
        private readonly ?PhoneNumber $phoneNumber,
        private ?CarbonImmutable $phoneNumberVerifiedAt,
        private ?string $comment,
        private ?string $externalId,
        private readonly ?int $bitrix24UserId,
        private ?Uuid $bitrix24PartnerId,
        private readonly ?string $userAgent,
        private readonly ?string $userAgentReferer,
        private readonly ?IP $userAgentIp,
    ) {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();
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
        // TODO: Implement changeFullName() method.
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
    public function changeEmail(?string $email, ?bool $isEmailVerified = null): void
    {
        $this->email = $email;
        $this->isEmailVerified = $isEmailVerified;

        $this->emailVerifiedAt = null;
        if (true === $isEmailVerified) {
            $this->emailVerifiedAt = new CarbonImmutable();
        }

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
        // TODO: Implement changeMobilePhone() method.
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

    #[\Override]
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    #[\Override]
    public function getUserAgentReferer(): ?string
    {
        return $this->userAgentReferer;
    }

    #[\Override]
    public function getUserAgentIp(): ?IP
    {
        return $this->userAgentIp;
    }
}
