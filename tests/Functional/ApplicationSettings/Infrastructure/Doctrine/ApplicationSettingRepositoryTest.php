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

    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->repository = new ApplicationSettingRepository($entityManager);
    }

    public function testCanSaveAndFindById(): void
    {
        $id = Uuid::v7();
        $applicationInstallationId = Uuid::v7();

        $setting = new ApplicationSetting(
            $id,
            $applicationInstallationId,
            'test.key',
            'test_value',
            false
        );

        $this->repository->save($setting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findById($id);

        $this->assertNotNull($foundSetting);
        $this->assertEquals($id->toRfc4122(), $foundSetting->getId()->toRfc4122());
        $this->assertEquals('test.key', $foundSetting->getKey());
        $this->assertEquals('test_value', $foundSetting->getValue());
    }

    public function testCanFindByApplicationInstallationIdAndKey(): void
    {
        $applicationInstallationId = Uuid::v7();

        $setting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'find.by.key',
            'value123',
            false
        );

        $this->repository->save($setting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findGlobalByKey(
            $applicationInstallationId,
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
        $applicationInstallationId = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'key1',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
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

        $settings = $this->repository->findByApplicationInstallationId($applicationInstallationId);

        $this->assertCount(2, $settings);
        $this->assertEquals('key1', $settings[0]->getKey());
        $this->assertEquals('key2', $settings[1]->getKey());
    }

    public function testCanDeleteSetting(): void
    {
        $id = Uuid::v7();
        $setting = new ApplicationSetting(
            $id,
            Uuid::v7(),
            'delete.test',
            'value',
            false
        );

        $this->repository->save($setting);
        EntityManagerFactory::get()->flush();

        $this->repository->delete($setting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findById($id);
        $this->assertNull($foundSetting);
    }

    public function testCanDeleteAllByApplicationInstallationId(): void
    {
        $applicationInstallationId = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'bulk.delete.1',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'bulk.delete.2',
            'value2',
            false
        );

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();

        $this->repository->deleteByApplicationInstallationId($applicationInstallationId);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $settings = $this->repository->findByApplicationInstallationId($applicationInstallationId);
        $this->assertCount(0, $settings);
    }

    public function testUniqueConstraintOnApplicationInstallationIdAndKey(): void
    {
        $applicationInstallationId = Uuid::v7();

        $setting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'unique.key',
            'value1',
            false
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
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
        $applicationInstallationId = Uuid::v7();
        $userId = 123;

        $personalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'personal.key',
            'personal_value',
            false,
            $userId
        );

        $this->repository->save($personalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findPersonalByKey(
            $applicationInstallationId,
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
        $applicationInstallationId = Uuid::v7();
        $departmentId = 456;

        $departmentalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'dept.key',
            'dept_value',
            false,
            null,
            $departmentId
        );

        $this->repository->save($departmentalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findDepartmentalByKey(
            $applicationInstallationId,
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
        $applicationInstallationId = Uuid::v7();

        $globalSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'global.key1',
            'value1',
            false
        );

        $globalSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'global.key2',
            'value2',
            false
        );

        $personalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
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

        $globalSettings = $this->repository->findAllGlobal($applicationInstallationId);

        $this->assertCount(2, $globalSettings);
        foreach ($globalSettings as $setting) {
            $this->assertTrue($setting->isGlobal());
        }
    }

    public function testCanFindAllPersonalSettings(): void
    {
        $applicationInstallationId = Uuid::v7();
        $userId = 123;

        $personalSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'personal.key1',
            'value1',
            false,
            $userId
        );

        $personalSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'personal.key2',
            'value2',
            false,
            $userId
        );

        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'global.key',
            'value',
            false
        );

        $this->repository->save($personalSetting1);
        $this->repository->save($personalSetting2);
        $this->repository->save($globalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $personalSettings = $this->repository->findAllPersonal($applicationInstallationId, $userId);

        $this->assertCount(2, $personalSettings);
        foreach ($personalSettings as $setting) {
            $this->assertTrue($setting->isPersonal());
            $this->assertEquals($userId, $setting->getB24UserId());
        }
    }

    public function testCanFindAllDepartmentalSettings(): void
    {
        $applicationInstallationId = Uuid::v7();
        $departmentId = 456;

        $deptSetting1 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'dept.key1',
            'value1',
            false,
            null,
            $departmentId
        );

        $deptSetting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'dept.key2',
            'value2',
            false,
            null,
            $departmentId
        );

        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'global.key',
            'value',
            false
        );

        $this->repository->save($deptSetting1);
        $this->repository->save($deptSetting2);
        $this->repository->save($globalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $deptSettings = $this->repository->findAllDepartmental($applicationInstallationId, $departmentId);

        $this->assertCount(2, $deptSettings);
        foreach ($deptSettings as $setting) {
            $this->assertTrue($setting->isDepartmental());
            $this->assertEquals($departmentId, $setting->getB24DepartmentId());
        }
    }

    public function testSoftDeletedSettingsAreNotReturnedByFindMethods(): void
    {
        $applicationInstallationId = Uuid::v7();

        $activeSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'active.key',
            'active_value',
            false
        );

        $deletedSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
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
        $allSettings = $this->repository->findAllForInstallation($applicationInstallationId);
        $this->assertCount(1, $allSettings);
        $this->assertEquals('active.key', $allSettings[0]->getKey());

        // Find by key should not return deleted
        $foundDeleted = $this->repository->findGlobalByKey($applicationInstallationId, 'deleted.key');
        $this->assertNull($foundDeleted);

        // Find by ID should not return deleted
        $foundDeletedById = $this->repository->findById($deletedSetting->getId());
        $this->assertNull($foundDeletedById);
    }

    public function testFindByKeySeparatesScopes(): void
    {
        $applicationInstallationId = Uuid::v7();
        $userId = 123;
        $departmentId = 456;

        // Same key, different scopes
        $globalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'same.key',
            'global_value',
            false
        );

        $personalSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'same.key',
            'personal_value',
            false,
            $userId
        );

        $deptSetting = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
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
        $foundGlobal = $this->repository->findGlobalByKey($applicationInstallationId, 'same.key');
        $foundPersonal = $this->repository->findPersonalByKey($applicationInstallationId, 'same.key', $userId);
        $foundDept = $this->repository->findDepartmentalByKey($applicationInstallationId, 'same.key', $departmentId);

        $this->assertNotNull($foundGlobal);
        $this->assertEquals('global_value', $foundGlobal->getValue());

        $this->assertNotNull($foundPersonal);
        $this->assertEquals('personal_value', $foundPersonal->getValue());

        $this->assertNotNull($foundDept);
        $this->assertEquals('dept_value', $foundDept->getValue());
    }
}
