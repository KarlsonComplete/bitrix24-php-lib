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

        // Find global setting by filtering
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $foundSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'find.by.key' && $allSetting->isGlobal()) {
                $foundSetting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($foundSetting);
        $this->assertEquals('find.by.key', $foundSetting->getKey());
        $this->assertEquals('value123', $foundSetting->getValue());
    }

    public function testReturnsNullForNonExistentKey(): void
    {
        $uuidV7 = Uuid::v7();
        $allSettings = $this->repository->findAllForInstallation($uuidV7);

        $foundSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'non.existent.key' && $allSetting->isGlobal()) {
                $foundSetting = $allSetting;
                break;
            }
        }

        $this->assertNull($foundSetting);
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

        // Find personal setting by filtering
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $foundSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'personal.key' && $allSetting->isPersonal() && $allSetting->getB24UserId() === $userId) {
                $foundSetting = $allSetting;
                break;
            }
        }

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

        // Find departmental setting by filtering
        $allSettings = $this->repository->findAllForInstallation($uuidV7);
        $foundSetting = null;
        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'dept.key' && $allSetting->isDepartmental() && $allSetting->getB24DepartmentId() === $departmentId) {
                $foundSetting = $allSetting;
                break;
            }
        }

        $this->assertNotNull($foundSetting);
        $this->assertEquals('dept.key', $foundSetting->getKey());
        $this->assertEquals('dept_value', $foundSetting->getValue());
        $this->assertEquals($departmentId, $foundSetting->getB24DepartmentId());
        $this->assertTrue($foundSetting->isDepartmental());
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
        $allSettingsAfterDelete = $this->repository->findAllForInstallation($uuidV7);
        $foundDeleted = null;
        foreach ($allSettingsAfterDelete as $allSettingAfterDelete) {
            if ($allSettingAfterDelete->getKey() === 'deleted.key' && $allSettingAfterDelete->isGlobal()) {
                $foundDeleted = $allSettingAfterDelete;
                break;
            }
        }

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
        $allSettings = $this->repository->findAllForInstallation($uuidV7);

        $foundGlobal = null;
        $foundPersonal = null;
        $foundDept = null;

        foreach ($allSettings as $allSetting) {
            if ($allSetting->getKey() === 'same.key') {
                if ($allSetting->isGlobal()) {
                    $foundGlobal = $allSetting;
                } elseif ($allSetting->isPersonal() && $allSetting->getB24UserId() === $userId) {
                    $foundPersonal = $allSetting;
                } elseif ($allSetting->isDepartmental() && $allSetting->getB24DepartmentId() === $departmentId) {
                    $foundDept = $allSetting;
                }
            }
        }

        $this->assertNotNull($foundGlobal);
        $this->assertEquals('global_value', $foundGlobal->getValue());

        $this->assertNotNull($foundPersonal);
        $this->assertEquals('personal_value', $foundPersonal->getValue());

        $this->assertNotNull($foundDept);
        $this->assertEquals('dept_value', $foundDept->getValue());
    }

    public function testFindAllForInstallationByKeyReturnsOnlyMatchingKey(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.theme', 'light', false);
        $setting2 = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.version', '1.0.0', false);
        $setting3 = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.theme', 'dark', false, 123);

        $this->repository->save($setting1);
        $this->repository->save($setting2);
        $this->repository->save($setting3);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'app.theme');

        $this->assertCount(2, $result);
        foreach ($result as $applicationSetting) {
            $this->assertEquals('app.theme', $applicationSetting->getKey());
        }
    }

    public function testFindAllForInstallationByKeyFiltersDeletedSettings(): void
    {
        $uuidV7 = Uuid::v7();

        $activeSetting = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.theme', 'light', false);
        $deletedSetting = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.theme', 'dark', false);

        $this->repository->save($activeSetting);
        $this->repository->save($deletedSetting);
        EntityManagerFactory::get()->flush();

        $deletedSetting->markAsDeleted();
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'app.theme');

        $this->assertCount(1, $result);
        $this->assertEquals('light', $result[0]->getValue());
    }

    public function testFindAllForInstallationByKeyReturnsEmptyArrayWhenNoMatch(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSetting = new ApplicationSetting(Uuid::v7(), $uuidV7, 'app.theme', 'light', false);
        $this->repository->save($applicationSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $result = $this->repository->findAllForInstallationByKey($uuidV7, 'non.existent.key');

        $this->assertCount(0, $result);
    }
}
