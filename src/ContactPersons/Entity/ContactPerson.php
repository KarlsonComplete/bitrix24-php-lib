<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Entity;

use Bitrix24\Lib\AggregateRoot;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\FullName;
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
        private FullName $fullName,
        private ?string $email,
        private bool $isEmailVerified,
        private ?CarbonImmutable $emailVerifiedAt,
        private ?PhoneNumber $phoneNumber,
        private bool $isPhoneNumberVerified,
        private ?CarbonImmutable $phoneNumberVerifiedAt,
        private ?string $comment,
        private ?string $externalId,
        private ?int $bitrix24AccountId,
        private ?Uuid $bitrix24PartnerId,
        private ?string $userAgent,
        private ?string $userAgentReferent,
        private ?IP $userAgentIp,
    )
    {
        $this->createdAt = new CarbonImmutable();
        $this->updatedAt = new CarbonImmutable();

    }

    public function getId(): Uuid
    {
        // TODO: Implement getId() method.
    }

    public function getStatus(): ContactPersonStatus
    {
        // TODO: Implement getStatus() method.
    }

    public function markAsActive(?string $comment): void
    {
        // TODO: Implement markAsActive() method.
    }

    public function markAsBlocked(?string $comment): void
    {
        // TODO: Implement markAsBlocked() method.
    }

    public function markAsDeleted(?string $comment): void
    {
        // TODO: Implement markAsDeleted() method.
    }

    public function getFullName(): FullName
    {
        // TODO: Implement getFullName() method.
    }

    public function changeFullName(FullName $fullName): void
    {
        // TODO: Implement changeFullName() method.
    }

    public function getCreatedAt(): CarbonImmutable
    {
        // TODO: Implement getCreatedAt() method.
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        // TODO: Implement getUpdatedAt() method.
    }

    public function getEmail(): ?string
    {
        // TODO: Implement getEmail() method.
    }

    public function changeEmail(?string $email, ?bool $isEmailVerified = null): void
    {
        // TODO: Implement changeEmail() method.
    }

    public function markEmailAsVerified(): void
    {
        // TODO: Implement markEmailAsVerified() method.
    }

    public function getEmailVerifiedAt(): ?CarbonImmutable
    {
        // TODO: Implement getEmailVerifiedAt() method.
    }

    public function changeMobilePhone(?PhoneNumber $phoneNumber, ?bool $isMobilePhoneVerified = null): void
    {
        // TODO: Implement changeMobilePhone() method.
    }

    public function getMobilePhone(): ?PhoneNumber
    {
        // TODO: Implement getMobilePhone() method.
    }

    public function getMobilePhoneVerifiedAt(): ?CarbonImmutable
    {
        // TODO: Implement getMobilePhoneVerifiedAt() method.
    }

    public function markMobilePhoneAsVerified(): void
    {
        // TODO: Implement markMobilePhoneAsVerified() method.
    }

    public function getComment(): ?string
    {
        // TODO: Implement getComment() method.
    }

    public function setExternalId(?string $externalId): void
    {
        // TODO: Implement setExternalId() method.
    }

    public function getExternalId(): ?string
    {
        // TODO: Implement getExternalId() method.
    }

    public function getBitrix24UserId(): ?int
    {
        // TODO: Implement getBitrix24UserId() method.
    }

    public function getBitrix24PartnerId(): ?Uuid
    {
        // TODO: Implement getBitrix24PartnerId() method.
    }

    public function setBitrix24PartnerId(?Uuid $uuid): void
    {
        // TODO: Implement setBitrix24PartnerId() method.
    }

    public function getUserAgent(): ?string
    {
        // TODO: Implement getUserAgent() method.
    }

    public function getUserAgentReferer(): ?string
    {
        // TODO: Implement getUserAgentReferer() method.
    }

    public function getUserAgentIp(): ?IP
    {
        // TODO: Implement getUserAgentIp() method.
    }
}