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

    #[\Override]
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
        $uuidV7 = Uuid::v7();
        $command = new Command(
            $uuidV7,
            'new.setting',
            '{"test":"value"}'
        );

        $this->handler->handle($command);

        EntityManagerFactory::get()->clear();

        // Find created setting
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'new.setting' && $allSetting->isGlobal()) {
                $setting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($setting);
        $this->assertEquals('new.setting', $setting->getKey());
        $this->assertEquals('{"test":"value"}', $setting->getValue());
    }

    public function testCanUpdateExistingSetting(): void
    {
        $uuidV7 = Uuid::v7();

        // Create initial setting
        $createCommand = new Command(
            $uuidV7,
            'update.test',
            'initial_value'
        );
        $this->handler->handle($createCommand);
        EntityManagerFactory::get()->clear();

        // Update the setting
        $updateCommand = new Command(
            $uuidV7,
            'update.test',
            'updated_value'
        );
        $this->handler->handle($updateCommand);
        EntityManagerFactory::get()->clear();

        // Verify update
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $setting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'update.test' && $allSetting->isGlobal()) {
                $setting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($setting);
        $this->assertEquals('updated_value', $setting->getValue());
    }

    public function testMultipleSettingsForSameInstallation(): void
    {
        $uuidV7 = Uuid::v7();

        $command1 = new Command($uuidV7, 'setting1', 'value1');
        $command2 = new Command($uuidV7, 'setting2', 'value2');

        $this->handler->handle($command1);
        $this->handler->handle($command2);
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(2, $settings);
    }
}
