<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for ApplicationSetting entity
 *
 * @extends EntityRepository<ApplicationSetting>
 */
class ApplicationSettingRepository extends EntityRepository implements ApplicationSettingRepositoryInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ApplicationSetting::class));
    }

    #[\Override]
    public function save(ApplicationSettingInterface $applicationSetting): void
    {
        $this->getEntityManager()->persist($applicationSetting);
    }

    #[\Override]
    public function delete(ApplicationSettingInterface $applicationSetting): void
    {
        $this->getEntityManager()->remove($applicationSetting);
    }

    #[\Override]
    public function findById(Uuid $id): ?ApplicationSettingInterface
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[\Override]
    public function findGlobalByKey(Uuid $applicationInstallationId, string $key): ?ApplicationSettingInterface
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.b24UserId IS NULL')
            ->andWhere('s.b24DepartmentId IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[\Override]
    public function findPersonalByKey(
        Uuid $applicationInstallationId,
        string $key,
        int $b24UserId
    ): ?ApplicationSettingInterface {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.b24UserId = :b24UserId')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('b24UserId', $b24UserId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[\Override]
    public function findDepartmentalByKey(
        Uuid $applicationInstallationId,
        string $key,
        int $b24DepartmentId
    ): ?ApplicationSettingInterface {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.b24DepartmentId = :b24DepartmentId')
            ->andWhere('s.b24UserId IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('b24DepartmentId', $b24DepartmentId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[\Override]
    public function findByKey(
        Uuid $applicationInstallationId,
        string $key,
        ?int $b24UserId = null,
        ?int $b24DepartmentId = null
    ): ?ApplicationSettingInterface {
        $qb = $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('status', ApplicationSettingStatus::Active);

        if (null !== $b24UserId) {
            $qb->andWhere('s.b24UserId = :b24UserId')
                ->setParameter('b24UserId', $b24UserId);
        } else {
            $qb->andWhere('s.b24UserId IS NULL');
        }

        if (null !== $b24DepartmentId) {
            $qb->andWhere('s.b24DepartmentId = :b24DepartmentId')
                ->setParameter('b24DepartmentId', $b24DepartmentId);
        } else {
            $qb->andWhere('s.b24DepartmentId IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    #[\Override]
    public function findAllGlobal(Uuid $applicationInstallationId): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.b24UserId IS NULL')
            ->andWhere('s.b24DepartmentId IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function findAllPersonal(Uuid $applicationInstallationId, int $b24UserId): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.b24UserId = :b24UserId')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('b24UserId', $b24UserId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function findAllDepartmental(Uuid $applicationInstallationId, int $b24DepartmentId): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.b24DepartmentId = :b24DepartmentId')
            ->andWhere('s.b24UserId IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('b24DepartmentId', $b24DepartmentId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function findAll(Uuid $applicationInstallationId): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function deleteByApplicationInstallationId(Uuid $applicationInstallationId): void
    {
        $this->getEntityManager()
            ->createQueryBuilder()
            ->delete(ApplicationSetting::class, 's')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->getQuery()
            ->execute();
    }

    /**
     * Soft-delete all settings for application installation
     */
    public function softDeleteByApplicationInstallationId(Uuid $applicationInstallationId): void
    {
        $this->getEntityManager()
            ->createQueryBuilder()
            ->update(ApplicationSetting::class, 's')
            ->set('s.status', ':status')
            ->set('s.updatedAt', ':updatedAt')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.status = :activeStatus')
            ->setParameter('status', ApplicationSettingStatus::Deleted)
            ->setParameter('updatedAt', new \Carbon\CarbonImmutable())
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('activeStatus', ApplicationSettingStatus::Active)
            ->getQuery()
            ->execute();
    }
}
