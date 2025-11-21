<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\UseCase\Set;

use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Handler;
use Bitrix24\Lib\Services\Flusher;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
        $eventDispatcher = new EventDispatcher();
        $this->repository = new ApplicationSettingRepository($entityManager);
        $flusher = new Flusher($entityManager, $eventDispatcher);

        $this->handler = new Handler(
            $this->repository,
            $flusher,
            new NullLogger()
        );
    }

    public function testCanCreateNewSetting(): void
    {
        $applicationInstallationId = Uuid::v7();
        $command = new Command(
            $applicationInstallationId,
            'new.setting',
            '{"test":"value"}'
        );

        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        $setting = $this->repository->findGlobalByKey(
            $applicationInstallationId,
            'new.setting'
        );

        $this->assertNotNull($setting);
        $this->assertEquals('new.setting', $setting->getKey());
        $this->assertEquals('{"test":"value"}', $setting->getValue());
    }

    public function testCanUpdateExistingSetting(): void
    {
        $applicationInstallationId = Uuid::v7();

        // Create initial setting
        $createCommand = new Command(
            $applicationInstallationId,
            'update.test',
            'initial_value'
        );
        $this->handler->handle($createCommand);
        EntityManagerFactory::get()->clear();

        // Update the setting
        $updateCommand = new Command(
            $applicationInstallationId,
            'update.test',
            'updated_value'
        );
        $this->handler->handle($updateCommand);
        EntityManagerFactory::get()->clear();

        // Verify update
        $setting = $this->repository->findGlobalByKey(
            $applicationInstallationId,
            'update.test'
        );

        $this->assertNotNull($setting);
        $this->assertEquals('updated_value', $setting->getValue());
    }

    public function testMultipleSettingsForSameInstallation(): void
    {
        $applicationInstallationId = Uuid::v7();

        $command1 = new Command($applicationInstallationId, 'setting1', 'value1');
        $command2 = new Command($applicationInstallationId, 'setting2', 'value2');

        $this->handler->handle($command1);
        $this->handler->handle($command2);
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findByApplicationInstallationId($applicationInstallationId);

        $this->assertCount(2, $settings);
    }
}
