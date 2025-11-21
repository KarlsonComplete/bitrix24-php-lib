<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for ApplicationSetting entity
 *
 * @extends EntityRepository<ApplicationSetting>
 */
class ApplicationSettingRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ApplicationSetting::class));
    }

    /**
     * Save application setting
     */
    public function save(ApplicationSetting $applicationSetting): void
    {
        $this->getEntityManager()->persist($applicationSetting);
    }

    /**
     * Delete application setting
     */
    public function delete(ApplicationSetting $applicationSetting): void
    {
        $this->getEntityManager()->remove($applicationSetting);
    }

    /**
     * Find setting by ID
     */
    public function findById(Uuid $id): ?ApplicationSetting
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find setting by application installation ID and key
     */
    public function findByApplicationInstallationIdAndKey(
        Uuid $applicationInstallationId,
        string $key
    ): ?ApplicationSetting {
        return $this->getEntityManager()
            ->getRepository(ApplicationSetting::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->setParameter('applicationInstallationId', $applicationInstallationId)
            ->setParameter('key', $key)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all settings for application installation
     *
     * @return ApplicationSetting[]
     */
    public function findByApplicationInstallationId(Uuid $applicationInstallationId): array
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

    /**
     * Delete all settings for application installation
     */
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
