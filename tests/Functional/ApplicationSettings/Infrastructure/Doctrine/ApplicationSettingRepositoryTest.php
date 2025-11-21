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
            'test_value'
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
            'value123'
        );

        $this->repository->save($setting);
        EntityManagerFactory::get()->flush();
        EntityManagerFactory::get()->clear();

        $foundSetting = $this->repository->findByApplicationInstallationIdAndKey(
            $applicationInstallationId,
            'find.by.key'
        );

        $this->assertNotNull($foundSetting);
        $this->assertEquals('find.by.key', $foundSetting->getKey());
        $this->assertEquals('value123', $foundSetting->getValue());
    }

    public function testReturnsNullForNonExistentKey(): void
    {
        $foundSetting = $this->repository->findByApplicationInstallationIdAndKey(
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
            'value1'
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'key2',
            'value2'
        );

        $setting3 = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(), // Different installation
            'key3',
            'value3'
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
            'value'
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
            'value1'
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'bulk.delete.2',
            'value2'
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
            'value1'
        );

        $setting2 = new ApplicationSetting(
            Uuid::v7(),
            $applicationInstallationId,
            'unique.key', // Same key
            'value2'
        );

        $this->repository->save($setting1);
        EntityManagerFactory::get()->flush();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->repository->save($setting2);
        EntityManagerFactory::get()->flush();
    }
}
