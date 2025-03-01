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

namespace Bitrix24\Lib\Tests\Functional\Bitrix24Accounts\Infrastructure\Doctrine;

use Bitrix24\Lib\Bitrix24Accounts\Entity\Bitrix24Account;
use Bitrix24\Lib\Bitrix24Accounts\Infrastructure\Doctrine\Bitrix24AccountRepository;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\Lib\Tests\Functional\FlusherDecorator;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountInterface;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Entity\Bitrix24AccountStatus;
use Bitrix24\SDK\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterface;
use Bitrix24\SDK\Core\Credentials\AuthToken;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Tests\Application\Contracts\Bitrix24Accounts\Repository\Bitrix24AccountRepositoryInterfaceTest;
use Bitrix24\SDK\Tests\Application\Contracts\TestRepositoryFlusherInterface;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Bitrix24AccountRepository::class)]
class Bitrix24AccountRepositoryTest extends Bitrix24AccountRepositoryInterfaceTest
{
    #[\Override]
    protected function createBitrix24AccountImplementation(
        Uuid $uuid,
        int $bitrix24UserId,
        bool $isBitrix24UserAdmin,
        string $memberId,
        string $domainUrl,
        Bitrix24AccountStatus $bitrix24AccountStatus,
        AuthToken $authToken,
        CarbonImmutable $createdAt,
        CarbonImmutable $updatedAt,
        int $applicationVersion,
        Scope $applicationScope
    ): Bitrix24AccountInterface {
        return new Bitrix24Account(
            $uuid,
            $bitrix24UserId,
            $isBitrix24UserAdmin,
            $memberId,
            $domainUrl,
            $bitrix24AccountStatus,
            $authToken,
            $createdAt,
            $updatedAt,
            $applicationVersion,
            $applicationScope
        );
    }

    #[\Override]
    protected function createBitrix24AccountRepositoryImplementation(): Bitrix24AccountRepositoryInterface
    {
        $entityManager = EntityManagerFactory::get();

        return new Bitrix24AccountRepository($entityManager);
    }

    #[\Override]
    protected function createRepositoryFlusherImplementation(): TestRepositoryFlusherInterface
    {
        $entityManager = EntityManagerFactory::get();
        $eventDispatcher = new EventDispatcher();

        return new FlusherDecorator(new Flusher($entityManager, $eventDispatcher));
    }
}
