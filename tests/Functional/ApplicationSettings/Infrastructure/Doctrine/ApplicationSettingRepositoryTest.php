<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationSettingRepository::class)]
class ApplicationSettingRepositoryTest extends TestCase
{
    private ApplicationSettingRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->repository = new ApplicationSettingRepository($entityManager);
    }

    public function testCanSaveAndFindById(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $applicationSetting = new ApplicationSetting(
            $uuidV7,
            $applicationInstallationId,
            'test.key',
            'test_value',
            false
        );

        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findById($uuidV7);

        $this->assertNotNull($foundSetting);
        $this->assertEquals($uuidV7->toRfc4122(), $foundSetting->getId()->toRfc4122());
        $this->assertEquals('test.key', $foundSetting->getKey());
        $this->assertEquals('test_value', $foundSetting->getValue());
    }

    public function testCanFindByApplicationInstallationIdAndKey(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'find.by.key',
            'value123',
            false
        );

        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findGlobalByKey(
            $uuidV7,
            'find.by.key'
        );

        $this->assertNotNull($foundSetting);
        $this->assertEquals('find.by.key', $foundSetting->getKey());
        $this->assertEquals('value123', $foundSetting->getValue());
    }

    public function testReturnsNullForNonExistentKey(): void
    {
        $foundSetting = $this->repository->findGlobalByKey(
            Uuid::v7(),
            'non.existent.key'
        );

        $this->assertNull($foundSetting);
    }

    public function testCanFindAllByApplicationInstallationId(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'key1',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'key2',
            'value2',
            false
        );

        $setting3 = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(), // Different installation
            'key3',
            'value3',
            false
        );

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        $this->repository->save($setting3);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findByApplicationInstallationId($uuidV7);

        $this->assertCount(2, $settings);
        $this->assertEquals('key1', $settings[0]->getKey());
        $this->assertEquals('key2', $settings[1]->getKey());
    }

    public function testCanDeleteSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationSetting = new ApplicationSetting(
            $uuidV7,
            Uuid::v7(),
            'delete.test',
            'value',
            false
        );

        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();

        $this->repository->delete($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findById($uuidV7);
        $this->assertNull($foundSetting);
    }

    public function testCanDeleteAllByApplicationInstallationId(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'bulk.delete.1',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'bulk.delete.2',
            'value2',
            false
        );

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();

        $this->repository->deleteByApplicationInstallationId($uuidV7);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findByApplicationInstallationId($uuidV7);
        $this->assertCount(0, $settings);
    }

    public function testUniqueConstraintOnApplicationInstallationIdAndKey(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'unique.key',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'unique.key', // Same key
            'value2',
            false
        );

        $this->repository->save($setting1);
        EntityManagerFactory::get()->flush();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();
    }

    public function testCanFindPersonalSettingByKey(): void
    {
        $uuidV7 = Uuid::v7();
        $userId = 123;

        $applicationSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'personal.key',
            'personal_value',
            false,
            $userId
        );

        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findPersonalByKey(
            $uuidV7,
            'personal.key',
            $userId
        );

        $this->assertNotNull($foundSetting);
        $this->assertEquals('personal.key', $foundSetting->getKey());
        $this->assertEquals('personal_value', $foundSetting->getValue());
        $this->assertEquals($userId, $foundSetting->getB24UserId());
        $this->assertTrue($foundSetting->isPersonal());
    }

    public function testCanFindDepartmentalSettingByKey(): void
    {
        $uuidV7 = Uuid::v7();
        $departmentId = 456;

        $applicationSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'dept.key',
            'dept_value',
            false,
            null,
            $departmentId
        );

        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findDepartmentalByKey(
            $uuidV7,
            'dept.key',
            $departmentId
        );

        $this->assertNotNull($foundSetting);
        $this->assertEquals('dept.key', $foundSetting->getKey());
        $this->assertEquals('dept_value', $foundSetting->getValue());
        $this->assertEquals($departmentId, $foundSetting->getB24DepartmentId());
        $this->assertTrue($foundSetting->isDepartmental());
    }

    public function testCanFindAllGlobalSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $globalSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'global.key1',
            'value1',
            false
        );

        $globalSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'global.key2',
            'value2',
            false
        );

        $personalSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'personal.key',
            'value',
            false,
            123
        );

        $this->repository->save($globalSetting1);
        $this->repository->save($globalSetting2);
        $this->repository->save($personalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $globalSettings = $this->repository->findAllGlobal($uuidV7);

        $this->assertCount(2, $globalSettings);
        foreach ($globalSettings as $globalSetting) {
            $this->assertTrue($globalSetting->isGlobal());
        }
    }

    public function testCanFindAllPersonalSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $userId = 123;

        $personalSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'personal.key1',
            'value1',
            false,
            $userId
        );

        $personalSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'personal.key2',
            'value2',
            false,
            $userId
        );

        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'global.key',
            'value',
            false
        );

        $this->repository->save($personalSetting1);
        $this->repository->save($personalSetting2);
        $this->repository->save($globalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $personalSettings = $this->repository->findAllPersonal($uuidV7, $userId);

        $this->assertCount(2, $personalSettings);
        foreach ($personalSettings as $personalSetting) {
            $this->assertTrue($personalSetting->isPersonal());
            $this->assertEquals($userId, $personalSetting->getB24UserId());
        }
    }

    public function testCanFindAllDepartmentalSettings(): void
    {
        $uuidV7 = Uuid::v7();
        $departmentId = 456;

        $deptSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'dept.key1',
            'value1',
            false,
            null,
            $departmentId
        );

        $deptSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'dept.key2',
            'value2',
            false,
            null,
            $departmentId
        );

        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'global.key',
            'value',
            false
        );

        $this->repository->save($deptSetting1);
        $this->repository->save($deptSetting2);
        $this->repository->save($globalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $deptSettings = $this->repository->findAllDepartmental($uuidV7, $departmentId);

        $this->assertCount(2, $deptSettings);
        foreach ($deptSettings as $deptSetting) {
            $this->assertTrue($deptSetting->isDepartmental());
            $this->assertEquals($departmentId, $deptSetting->getB24DepartmentId());
        }
    }

    public function testSoftDeletedSettingsAreNotReturnedByFindMethods(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'active.key',
            'active_value',
            false
        );

        $deletedSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'deleted.key',
            'deleted_value',
            false
        );

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);
        EntityManagerFactory::get()->flush();

        // Mark one as deleted
        $deletedSetting->markAsDeleted();
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Find all should only return active
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $this->assertCount(1, $allSettings);
        $this->assertEquals('active.key', $allSettings[0]->getKey());

        // Find by key should not return deleted
        $foundDeleted = $this->repository->findGlobalByKey($uuidV7, 'deleted.key');
        $this->assertNull($foundDeleted);

        // Find by ID should not return deleted
        $foundDeletedById = $this->repository->findById($deletedSetting->getId());
        $this->assertNull($foundDeletedById);
    }

    public function testFindByKeySeparatesScopes(): void
    {
        $uuidV7 = Uuid::v7();
        $userId = 123;
        $departmentId = 456;

        // Same key, different scopes
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'same.key',
            'global_value',
            false
        );

        $personalSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'same.key',
            'personal_value',
            false,
            $userId
        );

        $deptSetting = new ApplicationSetting(
            Uuid::v7(),
            $uuidV7,
            'same.key',
            'dept_value',
            false,
            null,
            $departmentId
        );

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);
        $this->repository->save($deptSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Each scope should return its own setting
        $foundGlobal = $this->repository->findGlobalByKey($uuidV7, 'same.key');
        $foundPersonal = $this->repository->findPersonalByKey($uuidV7, 'same.key', $userId);
        $foundDept = $this->repository->findDepartmentalByKey($uuidV7, 'same.key', $departmentId);

        $this->assertNotNull($foundGlobal);
        $this->assertEquals('global_value', $foundGlobal->getValue());

        $this->assertNotNull($foundPersonal);
        $this->assertEquals('personal_value', $foundPersonal->getValue());

        $this->assertNotNull($foundDept);
        $this->assertEquals('dept_value', $foundDept->getValue());
    }
}
