<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Services\InstallSettings;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Handler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(InstallSettings::class)]
class InstallSettingsTest extends TestCase
{
    private Handler $setHandler;
    private LoggerInterface $logger;
    private InstallSettings $service;

    protected function setUp(): void
    {
        $this->setHandler = $this->createMock(Handler::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new InstallSettings($this->setHandler, $this->logger);
    }

    public function testCanCreateDefaultSettings(): void
    {
        $applicationInstallationId = Uuid::v7();
        $defaultSettings = [
            'app.name' => ['value' => 'Test App', 'required' => true],
            'app.language' => ['value' => 'ru', 'required' => false],
        ];

        // Expect Set Handler to be called twice (once for each setting)
        $this->setHandler->expects($this->exactly(2))
            ->method('handle')
            ->with($this->callback(function (Command $command) use ($applicationInstallationId, $defaultSettings) {
                // Verify command has correct application installation ID
                if ($command->applicationInstallationId->toRfc4122() !== $applicationInstallationId->toRfc4122()) {
                    return false;
                }

                // Verify key and value match one of the settings
                if ($command->key === 'app.name') {
                    return $command->value === 'Test App' && true === $command->isRequired;
                }

                if ($command->key === 'app.language') {
                    return $command->value === 'ru' && false === $command->isRequired;
                }

                return false;
            }));

        $this->service->createDefaultSettings($applicationInstallationId, $defaultSettings);
    }

    public function testLogsStartAndFinish(): void
    {
        $applicationInstallationId = Uuid::v7();
        $defaultSettings = [
            'test.key' => ['value' => 'test', 'required' => false],
        ];

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use ($applicationInstallationId) {
                if ('InstallSettings.createDefaultSettings.start' === $message) {
                    $this->assertEquals($applicationInstallationId->toRfc4122(), $context['applicationInstallationId']);
                    $this->assertEquals(1, $context['settingsCount']);

                    return true;
                }

                if ('InstallSettings.createDefaultSettings.finish' === $message) {
                    $this->assertEquals($applicationInstallationId->toRfc4122(), $context['applicationInstallationId']);

                    return true;
                }

                return false;
            });

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('InstallSettings.settingProcessed', $this->arrayHasKey('key'));

        $this->service->createDefaultSettings($applicationInstallationId, $defaultSettings);
    }

    public function testCreatesGlobalSettings(): void
    {
        $applicationInstallationId = Uuid::v7();
        $defaultSettings = [
            'global.setting' => ['value' => 'value', 'required' => true],
        ];

        // Verify that created commands are for global settings (no user/department ID)
        $this->setHandler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Command $command) {
                return null === $command->b24UserId && null === $command->b24DepartmentId;
            }));

        $this->service->createDefaultSettings($applicationInstallationId, $defaultSettings);
    }

    public function testHandlesEmptySettingsArray(): void
    {
        $applicationInstallationId = Uuid::v7();
        $defaultSettings = [];

        // Set Handler should not be called
        $this->setHandler->expects($this->never())
            ->method('handle');

        // But logging should still happen
        $this->logger->expects($this->exactly(2))
            ->method('info');

        $this->service->createDefaultSettings($applicationInstallationId, $defaultSettings);
    }
}
