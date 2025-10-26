<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class ContactPersonRepository extends EntityRepository implements ContactPersonRepositoryInterface
{
    #[\Override]
    public function save(ContactPersonInterface $contactPerson): void
    {
        // TODO: Implement save() method.
    }

    #[\Override]
    public function delete(Uuid $uuid): void
    {
        // TODO: Implement delete() method.
    }

    #[\Override]
    public function getById(Uuid $uuid): ContactPersonInterface
    {
        // TODO: Implement getById() method.
    }

    #[\Override]
    public function findByEmail(string $email, ?ContactPersonStatus $contactPersonStatus = null, ?bool $isEmailVerified = null): array
    {
        // TODO: Implement findByEmail() method.
    }

    #[\Override]
    public function findByPhone(PhoneNumber $phoneNumber, ?ContactPersonStatus $contactPersonStatus = null, ?bool $isPhoneVerified = null): array
    {
        // TODO: Implement findByPhone() method.
    }

    #[\Override]
    public function findByExternalId(string $externalId, ?ContactPersonStatus $contactPersonStatus = null): array
    {
        // TODO: Implement findByExternalId() method.
    }
}
