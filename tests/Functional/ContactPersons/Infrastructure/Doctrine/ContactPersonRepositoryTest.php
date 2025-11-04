<?php

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\Infrastructure\Doctrine;

use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Tests\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterfaceTest;
use Carbon\CarbonImmutable;
use Darsyn\IP\Version\Multi as IP;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;

class ContactPersonRepositoryTest extends ContactPersonRepositoryInterfaceTest
{

    protected function createContactPersonImplementation(Uuid $uuid, CarbonImmutable $createdAt, CarbonImmutable $updatedAt, ContactPersonStatus $contactPersonStatus, string $name, ?string $surname, ?string $patronymic, ?string $email, ?CarbonImmutable $emailVerifiedAt, ?string $comment, ?PhoneNumber $phoneNumber, ?CarbonImmutable $mobilePhoneVerifiedAt, ?string $externalId, ?int $bitrix24UserId, ?Uuid $bitrix24PartnerId, ?string $userAgent, ?string $userAgentReferer, ?IP $userAgentIp): ContactPersonInterface
    {
        // TODO: Implement createContactPersonImplementation() method.
    }

    protected function createContactPersonRepositoryImplementation(): ContactPersonRepositoryInterface
    {
        // TODO: Implement createContactPersonRepositoryImplementation() method.
    }
}