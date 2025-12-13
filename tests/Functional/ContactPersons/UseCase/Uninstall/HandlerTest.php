<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\UseCase\Uninstall;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;
use Bitrix24\Lib\ContactPersons\UseCase\Uninstall\Handler;
use Bitrix24\Lib\ContactPersons\UseCase\Uninstall\Command;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumber;
use Bitrix24\Lib\Tests\Functional\ContactPersons\Builders\ContactPersonBuilder;


/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;

    private Flusher $flusher;

    private ContactPersonRepository $repository;
    private ApplicationInstallationRepository $applicationInstallationRepository;
    private Bitrix24AccountRepository $bitrix24accountRepository;

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->applicationInstallationRepository = new ApplicationInstallationRepository($entityManager);
        $this->bitrix24accountRepository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->applicationInstallationRepository,
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }
    #[Test]
    public function testUninstallContactPersonByMemberIdPersonal(): void
    {

        $contactPersonBuilder = new ContactPersonBuilder();

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->build();

        $this->repository->save($contactPerson);

        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v4()->toRfc4122();
        $externalId = Uuid::v7()->toRfc4122();
        $contactPersonId = $contactPerson->getId();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);

        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->withContactPersonId($contactPersonId)
            ->withBitrix24PartnerContactPersonId(null)
            ->withExternalId($externalId)
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new Command($memberId, ContactPersonType::personal)
        );

        $updatedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($memberId);
        $this->assertNull($updatedInstallation->getContactPersonId());

        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPersonId);
    }

    #[Test]
    public function testUninstallContactPersonByMemberIdPartner(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->build();

        $this->repository->save($contactPerson);

        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v4()->toRfc4122();
        $externalId = Uuid::v7()->toRfc4122();
        $contactPersonId = $contactPerson->getId();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);

        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->withContactPersonId(null)
            ->withBitrix24PartnerContactPersonId($contactPersonId)
            ->withExternalId($externalId)
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new Command($memberId, ContactPersonType::partner)
        );

        $updatedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($memberId);
        $this->assertNull($updatedInstallation->getBitrix24PartnerContactPersonId());

        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPersonId);
    }

    #[Test]
    public function testUninstallContactPersonById(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->build();

        $this->repository->save($contactPerson);
        $this->flusher->flush();

        $this->handler->handle(
            new Command(null, null, $contactPerson->getId())
        );

        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPerson->getId());
    }

    #[Test]
    public function testUninstallContactPersonByMemberIdAndId(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->build();

        $this->repository->save($contactPerson);

        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v4()->toRfc4122();
        $externalId = Uuid::v7()->toRfc4122();
        $contactPersonId = $contactPerson->getId();

        $bitrix24Account = (new Bitrix24AccountBuilder())
            ->withApplicationScope(new Scope(['crm']))
            ->withStatus(Bitrix24AccountStatus::new)
            ->withApplicationToken($applicationToken)
            ->withMemberId($memberId)
            ->withMaster(true)
            ->withSetToken()
            ->withInstalled()
            ->build();

        $this->bitrix24accountRepository->save($bitrix24Account);

        $applicationInstallation = (new ApplicationInstallationBuilder())
            ->withApplicationStatus(new ApplicationStatus('F'))
            ->withPortalLicenseFamily(PortalLicenseFamily::free)
            ->withBitrix24AccountId($bitrix24Account->getId())
            ->withApplicationStatusInstallation(ApplicationInstallationStatus::active)
            ->withApplicationToken($applicationToken)
            ->withContactPersonId($contactPersonId)
            ->withBitrix24PartnerContactPersonId(null)
            ->withExternalId($externalId)
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        $this->flusher->flush();

        $this->handler->handle(
            new Command($memberId, ContactPersonType::personal, $contactPersonId)
        );

        $updatedInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($memberId);
        $this->assertNull($updatedInstallation->getContactPersonId());

        $this->expectException(ContactPersonNotFoundException::class);
        $this->repository->getById($contactPersonId);
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }
}