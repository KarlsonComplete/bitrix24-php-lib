<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Functional\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingsItemRepository;
use Bitrix24\Lib\Tests\EntityManagerFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests for Doctrine-specific functionality (not covered by contract tests).
 *
 * @internal
 */
#[CoversClass(ApplicationSettingsItemRepository::class)]
class ApplicationSettingsItemRepositoryTest extends TestCase
{
    private ApplicationSettingsItemRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        $entityManager = EntityManagerFactory::get();
        $this->repository = new ApplicationSettingsItemRepository($entityManager);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();
    }

    /**
     * Test Doctrine-specific unique constraint on (installation_id, key, user_id, department_id).
     */
    public function testUniqueConstraintOnApplicationInstallationIdAndKeyAndScope(): void
    {
        $uuidV7 = Uuid::v7();

        $setting1 = new ApplicationSettingsItem(
            $uuidV7,
            'unique.key',
            'value1',
            false
        );

        $setting2 = new ApplicationSettingsItem(
            $uuidV7,
            'unique.key', // Same key, same scope (global)
            'value2',
            false
        );

        $this->repository->save($setting1);
        EntityManagerFactory::get()->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();
    }

    /**
     * Test that different scopes with same key don't violate unique constraint.
     */
    public function testDifferentScopesWithSameKeyAreAllowed(): void
    {
        $uuidV7 = Uuid::v7();

        $globalSetting = new ApplicationSettingsItem(
            $uuidV7,
            'shared.key',
            'global_value',
            false
        );

        $personalSetting = new ApplicationSettingsItem(
            $uuidV7,
            'shared.key',
            'personal_value',
            false,
            b24UserId: 123
        );

        $departmentalSetting = new ApplicationSettingsItem(
            $uuidV7,
            'shared.key',
            'departmental_value',
            false,
            b24DepartmentId: 456
        );

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);
        $this->repository->save($departmentalSetting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // All three should be saved successfully
        $allSettings = $this->repository->findAllForInstallationByKey($uuidV7, 'shared.key');

        $this->assertCount(3, $allSettings);
    }

    /**
     * Test that entity manager persistence and flushing works correctly.
     */
    public function testPersistenceAcrossFlushAndClear(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'persistence.test',
            'test_value',
            false
        );

        $uuid = $applicationSettingsItem->getId();

        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // After clear, entity should still be retrievable from database
        $retrieved = $this->repository->findById($uuid);

        $this->assertNotNull($retrieved);
        $this->assertEquals('persistence.test', $retrieved->getKey());
        $this->assertEquals('test_value', $retrieved->getValue());
    }

    /**
     * Test that soft-deleted settings persist in database but are not returned by queries.
     */
    public function testSoftDeletePersistsInDatabase(): void
    {
        $uuidV7 = Uuid::v7();

        $applicationSettingsItem = new ApplicationSettingsItem(
            $uuidV7,
            'to.soft.delete',
            'value',
            false
        );

        $uuid = $applicationSettingsItem->getId();

        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();

        // Soft delete
        $applicationSettingsItem->markAsDeleted();
        $this->repository->save($applicationSettingsItem);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        // Should not be returned by findById (filters deleted)
        $retrieved = $this->repository->findById($uuid);
        $this->assertNull($retrieved);

        // Verify it still exists in database using DQL (bypasses soft-delete filtering)
        $entityManager = EntityManagerFactory::get();
        $dql = 'SELECT COUNT(s.id) FROM Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem s WHERE s.id = :id';
        $query = $entityManager->createQuery($dql);
        $query->setParameter('id', $uuid);

        $count = $query->getSingleScalarResult();

        $this->assertEquals(1, $count, 'Soft-deleted setting should still exist in database');
    }
}
