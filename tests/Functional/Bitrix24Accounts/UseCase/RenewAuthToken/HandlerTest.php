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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\UseCase\RenewAuthToken;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken\Command;
use Bitrix24\Lib\Bitrix24Accounts\UseCase\RenewAuthToken\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Application\ApplicationStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\Response\DTO\RenewedAuthToken;
use Carbon\CarbonImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;
    private Bitrix24AccountRepositoryInterface $repository;
    private TraceableEventDispatcher $eventDispatcher;

    #[Test]
    public function testRenewAuthTokenWithoutBitrix24UserId(): void
    {
        $bitrix24Account = new Bitrix24Account(
            Uuid::v7(),
            1,
            true,
            Uuid::v7()->toRfc4122(),
            Uuid::v7()->toRfc4122() . '-test.bitrix24.com',
            Bitrix24AccountStatus::active,
            new AuthToken('old_1', 'old_2', 3600),
            new CarbonImmutable(),
            new CarbonImmutable(),
            1,
            new Scope()
        );

        $this->repository->save($bitrix24Account);

        $newAuthToken = new AuthToken('new_1', 'new_2', 3600);
        $this->handler->handle(
            new Command(
                new RenewedAuthToken(
                    $newAuthToken,
                    $bitrix24Account->getMemberId(),
                    'https://client-endpoint.com',
                    'https://server-endpoint.com',
                    ApplicationStatus::subscription(),
                    $bitrix24Account->getDomainUrl()
                )
            )
        );

        $updated = $this->repository->getById($bitrix24Account->getId());
        $this->assertEquals($newAuthToken->accessToken, $updated->getAuthToken()->accessToken);
        $this->assertEquals($newAuthToken->refreshToken, $updated->getAuthToken()->refreshToken);

        // на продлении токенов событий не бросаем
        $this->assertCount(0, $this->eventDispatcher->getOrphanedEvents());
    }


    #[Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->repository = new Bitrix24AccountRepository($entityManager);
        $this->flusher = new Flusher($entityManager);
        $this->eventDispatcher = new TraceableEventDispatcher(new EventDispatcher(), new Stopwatch());
        $this->handler = new Handler(
            $this->eventDispatcher,
            $this->repository,
            $this->flusher,
            new NullLogger()
        );
    }
}