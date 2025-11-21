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
 * Repository for ApplicationSetting entity.
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
    public function findById(Uuid $uuid): ?ApplicationSettingInterface
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.status = :status')
            ->setParameter('id', $uuid)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findGlobalByKey(Uuid $uuid, string $key): ?ApplicationSettingInterface
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.b24UserId IS NULL')
            ->andWhere('s.b24DepartmentId IS NULL')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('key', $key)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findPersonalByKey(
        Uuid $uuid,
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
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('key', $key)
            ->setParameter('b24UserId', $b24UserId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findDepartmentalByKey(
        Uuid $uuid,
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
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('key', $key)
            ->setParameter('b24DepartmentId', $b24DepartmentId)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    #[\Override]
    public function findByKey(
        Uuid $uuid,
        string $key,
        ?int $b24UserId = null,
        ?int $b24DepartmentId = null
    ): ?ApplicationSettingInterface {
        $queryBuilder = $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('key', $key)
            ->setParameter('status', ApplicationSettingStatus::Active)
        ;

        if (null !== $b24UserId) {
            $queryBuilder->andWhere('s.b24UserId = :b24UserId')
                ->setParameter('b24UserId', $b24UserId)
            ;
        } else {
            $queryBuilder->andWhere('s.b24UserId IS NULL');
        }

        if (null !== $b24DepartmentId) {
            $queryBuilder->andWhere('s.b24DepartmentId = :b24DepartmentId')
                ->setParameter('b24DepartmentId', $b24DepartmentId)
            ;
        } else {
            $queryBuilder->andWhere('s.b24DepartmentId IS NULL');
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    #[\Override]
    public function findAllForInstallation(Uuid $uuid): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->orderBy('s.key', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
