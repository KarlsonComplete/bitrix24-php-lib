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

use Bitrix24\Lib\ContactPersons\UseCase\Install\Handler;
use Bitrix24\Lib\ContactPersons\UseCase\Install\Command;
use Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine\ContactPersonRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Events\ContactPersonCreatedEvent;
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

    private TraceableEventDispatcher $eventDispatcher;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->repository = new ContactPersonRepository($entityManager);
        $this->flusher = new Flusher($entityManager, $this->eventDispatcher);
        $this->handler = new Handler(
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testNewContactPerson(): void
    {
        $contactPersonBuilder = new ContactPersonBuilder();
        $externalId = Uuid::v7()->toRfc4122();
        $contactPerson = $contactPersonBuilder
            ->withEmail('john.doe@example.com')
            ->withMobilePhoneNumber($this->createPhoneNumber('+79991234567'))
            ->withComment('Test comment')
            ->withExternalId($externalId)
            ->withBitrix24UserId(123)
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
            '1.0'
        )
    );

        $contactPersonFromRepo = $this->repository->findByExternalId($contactPerson->getExternalId());
        $this->assertCount(1, $contactPersonFromRepo);
        $this->assertInstanceOf(ContactPersonInterface::class, $contactPersonFromRepo[0]);
        $this->assertEquals($contactPerson->getFullName()->name, $contactPersonFromRepo[0]->getFullName()->name);
        $this->assertEquals($contactPerson->getEmail(), $contactPersonFromRepo[0]->getEmail());
        $this->assertEquals($contactPerson->getMobilePhone(), $contactPersonFromRepo[0]->getMobilePhone());
        $this->assertEquals(ContactPersonStatus::active, $contactPersonFromRepo[0]->getStatus());

        $dispatchedEvents = $this->eventDispatcher->getOrphanedEvents();
        $this->assertContains(ContactPersonCreatedEvent::class, $dispatchedEvents);
    }

    /**
     * @throws InvalidArgumentException
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
            '1.0'
        );

        $this->handler->handle($command);

        $this->expectException(InvalidArgumentException::class);
        $this->handler->handle($command);
    }

    private function createPhoneNumber(string $number): \libphonenumber\PhoneNumber
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        return $phoneNumberUtil->parse($number, 'RU');
    }
}