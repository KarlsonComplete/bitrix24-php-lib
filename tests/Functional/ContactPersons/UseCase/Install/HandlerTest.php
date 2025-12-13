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

namespace Bitrix24\Lib\Tests\Functional\ContactPersons\UseCase\Install;

use Bitrix24\Lib\ApplicationInstallations\Infrastructure\Doctrine\ApplicationInstallationRepository;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\ContactPersons\UseCase\Install\Handler;
use Bitrix24\Lib\ContactPersons\UseCase\Install\Command;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\Functional\ApplicationInstallations\Builders\ApplicationInstallationBuilder;
use Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Builders\Bitrix24AccountBuilder;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Entity\ApplicationInstallationStatus;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBitrix24PartnerContactPersonLinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationBitrix24PartnerLinkedEvent;
use Bitrix24\SDK\Application\Contracts\ApplicationInstallations\Events\ApplicationInstallationContactPersonLinkedEvent;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonCreatedEvent;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonEmailChangedEvent;
use Bitrix24\SDK\Application\PortalLicenseFamily;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use Bitrix24\Lib\Tests\EntityManagerFactory;
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
use Bitrix24\Lib\ContactPersons\Enum\ContactPersonType;

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

    /**
     * @throws InvalidArgumentException|\Random\RandomException
     */
    #[Test]
    public function testNewContactPerson(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $bitrix24UserId = random_int(1, 1_000_000);

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId($bitrix24UserId)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build();


        $this->handler->handle(
            new Command(
            $contactPerson->getFullName(),
            $contactPerson->getEmail(),
            $contactPerson->getMobilePhone(),
            $contactPerson->getComment(),
            $contactPerson->getExternalId(),
            $contactPerson->getBitrix24UserId(),
            $contactPerson->getBitrix24PartnerId(),
            $contactPerson->getUserAgentInfo()->ip,
            $contactPerson->getUserAgentInfo()->userAgent,
            $contactPerson->getUserAgentInfo()->referrer,
            '1.0',
            null,
            null
            )
        );

        $contactPersonFromRepo = $this->repository->findByExternalId($contactPerson->getExternalId());

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonCreatedEvent::class, $dispatchedEvents);
        $this->assertNotContains(ApplicationInstallationContactPersonLinkedEvent::class, $dispatchedEvents);
        $this->assertNotContains(ApplicationInstallationBitrix24PartnerLinkedEvent::class, $dispatchedEvents);

        $this->assertCount(1, $contactPersonFromRepo);

        $foundContactPerson = reset($contactPersonFromRepo);

        $this->assertInstanceOf(ContactPersonInterface::class, $foundContactPerson);
        $this->assertEquals($contactPerson->getFullName()->name, $foundContactPerson->getFullName()->name);
        $this->assertEquals($contactPerson->getEmail(), $foundContactPerson->getEmail());
        $this->assertEquals($contactPerson->getMobilePhone(), $foundContactPerson->getMobilePhone());
        $this->assertEquals(ContactPersonStatus::active, $foundContactPerson->getStatus());
    }

    #[Test]
    public function testNewContactPersonAndLinkApp(): void
    {
        // Load account and application installation into database for uninstallation.
        $applicationToken = Uuid::v7()->toRfc4122();
        $memberId = Uuid::v4()->toRfc4122();
        $externalId = Uuid::v7()->toRfc4122();

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
            ->withBitrix24PartnerContactPersonId(null)
            ->withExternalId($externalId)
            ->build();

        $this->applicationInstallationRepository->save($applicationInstallation);

        $this->flusher->flush();

        $contactPersonBuilder = new ContactPersonBuilder();

        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($applicationInstallation->getExternalId())
            ->withBitrix24UserId($bitrix24Account->getBitrix24UserId())
            ->withBitrix24PartnerId($applicationInstallation->getBitrix24PartnerId())
            ->build();

        $this->handler->handle(
            new Command(
                $contactPerson->getFullName(),
                $contactPerson->getEmail(),
                $contactPerson->getMobilePhone(),
                $contactPerson->getComment(),
                $contactPerson->getExternalId(),
                $contactPerson->getBitrix24UserId(),
                $contactPerson->getBitrix24PartnerId(),
                $contactPerson->getUserAgentInfo()->ip,
                $contactPerson->getUserAgentInfo()->userAgent,
                $contactPerson->getUserAgentInfo()->referrer,
                '1.0',
                $bitrix24Account->getMemberId(),
                ContactPersonType::partner
            )
        );

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $foundInstallation = $this->applicationInstallationRepository->findByBitrix24AccountMemberId($bitrix24Account->getMemberId());
        $foundContactPerson = $this->repository->getById($foundInstallation->getBitrix24PartnerContactPersonId());

        $this->assertContains(ApplicationInstallationBitrix24PartnerContactPersonLinkedEvent::class, $dispatchedEvents);
        $this->assertNotNull($foundContactPerson);
    }


    /*
     * Что такое externalId? Вроде бы это подпись. Тогда по сути у нас может на 1 подпись быть 2 контактных лица.
     */
    #[Test]
    public function testContactPersonWithDuplicateExternalId(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $contactPerson = $contactPersonBuilder
            ->withEmail('alice.cooper@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991112222'))
            ->withExternalId('duplicate-ext')
            ->withBitrix24UserId(789)
            ->withBitrix24PartnerId(Uuid::v7())
            ->build();

        $this->repository->save($contactPerson);

        $this->flusher->flush();

        $command = new Command(
            $contactPerson->getFullName(),
            $contactPerson->getEmail(),
            $contactPerson->getMobilePhone(),
            $contactPerson->getComment(),
            $contactPerson->getExternalId(),
            $contactPerson->getBitrix24UserId(),
            $contactPerson->getBitrix24PartnerId(),
            $contactPerson->getUserAgentInfo()->ip,
            $contactPerson->getUserAgentInfo()->userAgent,
            $contactPerson->getUserAgentInfo()->referrer,
            '1.0',
            null,
            null
        );

        $this->expectException(InvalidArgumentException::class);
        $this->handler->handle($command);
    }

    private function createPhoneNumber(string $number): PhoneNumber
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }
}