<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItemInterface;
use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for ApplicationSettingsItem entity.
 *
 * @extends EntityRepository<ApplicationSettingsItem>
 */
class ApplicationSettingsItemRepository extends EntityRepository implements ApplicationSettingsItemRepositoryInterface
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(ApplicationSettingsItem::class));
    }

    #[\Override]
    public function save(ApplicationSettingsItemInterface $applicationSetting): void
    {
        $this->getEntityManager()->persist($applicationSetting);
    }

    #[\Override]
    public function delete(ApplicationSettingsItemInterface $applicationSetting): void
    {
        $this->getEntityManager()->remove($applicationSetting);
    }

    #[\Override]
    public function findById(Uuid $uuid): ?ApplicationSettingsItemInterface
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSettingsItem::class)
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
    public function findAllForInstallation(Uuid $uuid): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSettingsItem::class)
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

    #[\Override]
    public function findAllForInstallationByKey(Uuid $uuid, string $key): array
    {
        return $this->getEntityManager()
            ->getRepository(ApplicationSettingsItem::class)
            ->createQueryBuilder('s')
            ->where('s.applicationInstallationId = :applicationInstallationId')
            ->andWhere('s.key = :key')
            ->andWhere('s.status = :status')
            ->setParameter('applicationInstallationId', $uuid)
            ->setParameter('key', $key)
            ->setParameter('status', ApplicationSettingStatus::Active)
            ->getQuery()
            ->getResult()
        ;
    }
}
