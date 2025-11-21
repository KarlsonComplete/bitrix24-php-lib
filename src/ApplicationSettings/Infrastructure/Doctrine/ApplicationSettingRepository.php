<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingInterface;
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
            ->setParameter('id', $id)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('b24UserId', $b24UserId)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->setParameter('b24DepartmentId', $b24DepartmentId)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key);

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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('b24UserId', $b24UserId)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('b24DepartmentId', $b24DepartmentId)
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
            ->setParameter('applicationInstallationId', $applicationInstallationId)
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
}
