<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory\ApplicationSettingsItemInMemoryRepository;
use Bitrix24\Lib\ApplicationSettings\Services\Exception\SettingsItemNotFoundException;
use Bitrix24\Lib\ApplicationSettings\Services\SettingsFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(SettingsFetcher::class)]
class SettingsFetcherTest extends TestCase
{
    private ApplicationSettingsItemInMemoryRepository $repository;

    private SettingsFetcher $fetcher;

    private Uuid $installationId;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingsItemInMemoryRepository();
        $this->fetcher = new SettingsFetcher($this->repository);
        $this->installationId = Uuid::v7();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testReturnsGlobalSettingWhenNoOverrides(): void
    {
        // Create only global setting
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSetting);

        $result = $this->fetcher->getItem($this->installationId, 'app.theme');

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalOverridesGlobal(): void
    {
        // Create global and departmental settings
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // When requesting for department 456, should get departmental setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testPersonalOverridesGlobalAndDepartmental(): void
    {
        // Create all three levels
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $personalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);
        $this->repository->save($personalSetting);

        // When requesting for user 123 and department 456, should get personal setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123, 456);

        $this->assertEquals('dark', $result->getValue());
        $this->assertTrue($result->isPersonal());
    }

    public function testFallsBackToGlobalWhenPersonalNotFound(): void
    {
        // Only global setting exists
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSetting);

        // Request for user 123, should fallback to global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testFallsBackToDepartmentalWhenPersonalNotFound(): void
    {
        // Global and departmental settings exist
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for user 999 (no personal setting) but department 456
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 999, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testThrowsExceptionWhenNoSettingFound(): void
    {
        $this->expectException(SettingsItemNotFoundException::class);
        $this->expectExceptionMessage('Setting with key "non.existent.key" not found');

        $this->fetcher->getItem($this->installationId, 'non.existent.key');
    }

    public function testGetSettingValueReturnsStringValue(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.version',
            '1.2.3',
            false
        );

        $this->repository->save($applicationSetting);

        $result = $this->fetcher->getSettingValue($this->installationId, 'app.version');

        $this->assertEquals('1.2.3', $result);
    }

    public function testGetSettingValueThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(SettingsItemNotFoundException::class);
        $this->expectExceptionMessage('Setting with key "non.existent" not found');

        $this->fetcher->getSettingValue($this->installationId, 'non.existent');
    }

    public function testPersonalSettingForDifferentUserNotUsed(): void
    {
        // Create global and personal for user 123
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $personalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);

        // Request for user 456 (different user), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 456);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalSettingForDifferentDepartmentNotUsed(): void
    {
        // Create global and departmental for dept 456
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for dept 789 (different department), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 789);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }
}
