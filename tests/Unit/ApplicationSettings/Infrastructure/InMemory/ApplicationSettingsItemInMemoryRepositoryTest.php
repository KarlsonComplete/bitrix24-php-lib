<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Infrastructure\InMemory;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory\ApplicationSettingsItemInMemoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationSettingsItemInMemoryRepository::class)]
class ApplicationSettingsItemInMemoryRepositoryTest extends TestCase
{
    private ApplicationSettingsItemInMemoryRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingsItemInMemoryRepository();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testCanSaveAndFindById(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'test.key',
            'test_value',
            false
        );

        $this->repository->save($applicationSettingsItem);

        $found = $this->repository->findById($applicationSettingsItem->getId());

        $this->assertNotNull($found);
        $this->assertEquals($applicationSettingsItem->getId()->toRfc4122(), $found->getId()->toRfc4122());
        $this->assertEquals('test.key', $found->getKey());
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById(Uuid::v7());

        $this->assertNull($result);
    }

    public function testFindByIdReturnsNullForDeletedSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $installationId = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem($installationId, 'deleted.key', 'value', false);
        $applicationSettingsItem->markAsDeleted();

        $this->repository->save($applicationSettingsItem);

        $result = $this->repository->findById($uuidV7);

        $this->assertNull($result);
    }

    public function testCanDeleteSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $installationId = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem($installationId, 'to.delete', 'value', false);

        $this->repository->save($applicationSettingsItem);
        $this->repository->delete($applicationSettingsItem);

        $result = $this->repository->findById($uuidV7);

        $this->assertNull($result);
    }

    public function testFindAllForInstallationReturnsOnlyActiveSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem($uuidV7, 'active.key', 'value1', false);
        $deletedSetting = new ApplicationSettingsItem($uuidV7, 'deleted.key', 'value2', false);
        $deletedSetting->markAsDeleted();

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);

        $result = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(1, $result);
        $this->assertEquals('active.key', $result[0]->getKey());
    }

    public function testFindAllForInstallationFiltersByInstallation(): void
    {
        $uuidV7 = Uuid::v7();
        $installationId2 = Uuid::v7();

        $setting1 = new ApplicationSettingsItem($uuidV7, 'key.one', 'value1', false);
        $setting2 = new ApplicationSettingsItem($installationId2, 'key.two', 'value2', false);

        $this->repository->save($setting1);
        $this->repository->save($setting2);

        $result = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(1, $result);
        $this->assertEquals('key.one', $result[0]->getKey());
    }

    public function testCanStoreMultipleScopes(): void
    {
        $uuidV7 = Uuid::v7();

        $globalSetting = new ApplicationSettingsItem($uuidV7, 'theme', 'light', false);
        $personalSetting = new ApplicationSettingsItem($uuidV7, 'theme', 'dark', false, 123);
        $deptSetting = new ApplicationSettingsItem($uuidV7, 'theme', 'blue', false, null, 456);

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);
        $this->repository->save($deptSetting);

        $allSettings = $this->repository->findAllForInstallation($uuidV7);

        $this->assertCount(3, $allSettings);

        // Verify each scope is present
        $hasGlobal = false;
        $hasPersonal = false;
        $hasDept = false;

        foreach ($allSettings as $allSetting) {
            if ($allSetting->isGlobal()) {
                $hasGlobal = true;
                $this->assertEquals('light', $allSetting->getValue());
            } elseif ($allSetting->isPersonal() && 123 === $allSetting->getB24UserId()) {
                $hasPersonal = true;
                $this->assertEquals('dark', $allSetting->getValue());
            } elseif ($allSetting->isDepartmental() && 456 === $allSetting->getB24DepartmentId()) {
                $hasDept = true;
                $this->assertEquals('blue', $allSetting->getValue());
            }
        }

        $this->assertTrue($hasGlobal);
        $this->assertTrue($hasPersonal);
        $this->assertTrue($hasDept);
    }

    public function testClearRemovesAllSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSettingsItem($uuidV7, 'key.one', 'value1', false);
        $setting2 = new ApplicationSettingsItem($uuidV7, 'key.two', 'value2', false);

        $this->repository->save($setting1);
        $this->repository->save($setting2);

        $this->assertCount(2, $this->repository->findAllForInstallation($uuidV7));

        $this->repository->clear();

        $this->assertCount(0, $this->repository->findAllForInstallation($uuidV7));
    }

    public function testGetAllIncludingDeletedReturnsDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem($uuidV7, 'active.key', 'value1', false);
        $deletedSetting = new ApplicationSettingsItem($uuidV7, 'deleted.key', 'value2', false);
        $deletedSetting->markAsDeleted();

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);

        $allIncludingDeleted = $this->repository->getAllIncludingDeleted();

        $this->assertCount(2, $allIncludingDeleted);
    }

    public function testFindAllForInstallationByKeyReturnsOnlyMatchingKey(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSettingsItem($uuidV7, 'app.theme', 'light', false);
        $setting2 = new ApplicationSettingsItem($uuidV7, 'app.version', '1.0.0', false);
        $setting3 = new ApplicationSettingsItem($uuidV7, 'app.theme', 'dark', false, 123); // Personal for user 123

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        $this->repository->save($setting3);

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'app.theme');

        $this->assertCount(2, $result);
        foreach ($result as $applicationSetting) {
            $this->assertEquals('app.theme', $applicationSetting->getKey());
        }
    }

    public function testFindAllForInstallationByKeyFiltersDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSettingsItem($uuidV7, 'app.theme', 'light', false);
        $deletedSetting = new ApplicationSettingsItem($uuidV7, 'app.theme', 'dark', false);
        $deletedSetting->markAsDeleted();

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'app.theme');

        $this->assertCount(1, $result);
        $this->assertEquals('light', $result[0]->getValue());
    }

    public function testFindAllForInstallationByKeyReturnsEmptyArrayWhenNoMatch(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem($uuidV7, 'app.theme', 'light', false);
        $this->repository->save($applicationSettingsItem);

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'non.existent.key');

        $this->assertCount(0, $result);
    }
}
