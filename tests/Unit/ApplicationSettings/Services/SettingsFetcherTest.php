<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory\ApplicationSettingInMemoryRepository;
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
    private ApplicationSettingInMemoryRepository $repository;

    private SettingsFetcher $fetcher;

    private Uuid $installationId;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingInMemoryRepository();
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
        $applicationSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSetting);

        $result = $this->fetcher->getSetting($this->installationId, 'app.theme');

        $this->assertNotNull($result);
        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalOverridesGlobal(): void
    {
        // Create global and departmental settings
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSetting(
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
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', null, 456);

        $this->assertNotNull($result);
        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testPersonalOverridesGlobalAndDepartmental(): void
    {
        // Create all three levels
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $personalSetting = new ApplicationSetting(
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
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', 123, 456);

        $this->assertNotNull($result);
        $this->assertEquals('dark', $result->getValue());
        $this->assertTrue($result->isPersonal());
    }

    public function testFallsBackToGlobalWhenPersonalNotFound(): void
    {
        // Only global setting exists
        $applicationSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSetting);

        // Request for user 123, should fallback to global
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', 123);

        $this->assertNotNull($result);
        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testFallsBackToDepartmentalWhenPersonalNotFound(): void
    {
        // Global and departmental settings exist
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSetting(
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
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', 999, 456);

        $this->assertNotNull($result);
        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testReturnsNullWhenNoSettingFound(): void
    {
        $result = $this->fetcher->getSetting($this->installationId, 'non.existent.key');

        $this->assertNull($result);
    }

    public function testGetSettingValueReturnsStringValue(): void
    {
        $applicationSetting = new ApplicationSetting(
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

    public function testGetSettingValueReturnsNullWhenNotFound(): void
    {
        $result = $this->fetcher->getSettingValue($this->installationId, 'non.existent');

        $this->assertNull($result);
    }

    public function testPersonalSettingForDifferentUserNotUsed(): void
    {
        // Create global and personal for user 123
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $personalSetting = new ApplicationSetting(
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
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', 456);

        $this->assertNotNull($result);
        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalSettingForDifferentDepartmentNotUsed(): void
    {
        // Create global and departmental for dept 456
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSetting(
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
        $result = $this->fetcher->getSetting($this->installationId, 'app.theme', null, 789);

        $this->assertNotNull($result);
        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }
}
