<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\Get;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\Get\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Get\Handler;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(Handler::class)]
class HandlerTest extends TestCase
{
    private Handler $handler;
    private ApplicationSettingRepository $repository;

    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->repository = new ApplicationSettingRepository($entityManager);

        $this->handler = new Handler(
            $this->repository,
            new NullLogger()
        );
    }

    public function testCanGetExistingSetting(): void
    {
        $applicationInstallationId = Uuid::v7();
        $setting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'get.test',
            'test_value'
        );

        $this->repository->save($setting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $command = new Command($applicationInstallationId, 'get.test');
        $result = $this->handler->handle($command);

        $this->assertEquals('get.test', $result->getKey());
        $this->assertEquals('test_value', $result->getValue());
    }

    public function testThrowsExceptionForNonExistentSetting(): void
    {
        $command = new Command(Uuid::v7(), 'non.existent');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting with key "non.existent" not found');

        $this->handler->handle($command);
    }
}
