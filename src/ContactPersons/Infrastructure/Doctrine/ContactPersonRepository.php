<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\Infrastructure\Doctrine;

use Bitrix24\Lib\ContactPersons\Entity\ContactPerson;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonStatus;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Exceptions\ContactPersonNotFoundException;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use libphonenumber\PhoneNumber;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;

class ContactPersonRepository implements ContactPersonRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository; // Внутренний репозиторий для базовых операций
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        $this->repository = $entityManager->getRepository(ContactPerson::class);
    }

    public function save(ContactPersonInterface $contactPerson): void
    {
        $this->entityManager->persist($contactPerson);
    }

    public function delete(Uuid $uuid): void
    {
        $contactPerson = $this->repository->find($uuid);

        if (null === $contactPerson) {
            throw new ContactPersonNotFoundException(
                sprintf('contactPerson not found by id %s', $uuid->toRfc4122())
            );
        }

        if (ContactPersonStatus::deleted !== $contactPerson->getStatus()) {
            throw new InvalidArgumentException(
                sprintf(
                    'you cannot delete contactPerson «%s», they must be in status «deleted», current status «%s»',
                    $contactPerson->getId()->toRfc4122(),
                    $contactPerson->getStatus()->name
                )
            );
        }

        $this->save($contactPerson);
    }

    public function getById(Uuid $uuid): ContactPersonInterface
    {
        $contactPerson = $this->repository
        ->createQueryBuilder('contactPerson')
        ->where('contactPerson.id = :id')
        ->andWhere('contactPerson.status != :status')
        ->setParameter('id', $uuid)
        ->setParameter('status', ContactPersonStatus::deleted)
        ->getQuery()
        ->getOneOrNullResult()
        ;

        if (null === $contactPerson) {
            throw new ContactPersonNotFoundException(
                sprintf('contactPerson account not found by id %s', $uuid->toRfc4122())
            );
        }

        return $contactPerson;
    }

    #[\Override]
    public function findByEmail(string $email, ?ContactPersonStatus $contactPersonStatus = null, ?bool $isEmailVerified = null): array
    {
        if ('' === trim($email)){
            throw new InvalidArgumentException('email cannot be an empty string');
        }

        $criteria = ['email' => $email];

        if (null !== $contactPersonStatus) {
            $criteria['contactPersonStatus'] = $contactPersonStatus->name;
        }

        if (null !== $isEmailVerified) {
            $criteria['isEmailVerified'] = $isEmailVerified;
        }

        return $this->repository->findBy($criteria);

    }

    #[\Override]
    public function findByPhone(PhoneNumber $phoneNumber, ?ContactPersonStatus $contactPersonStatus = null, ?bool $isPhoneVerified = null): array
    {
        // TODO: Implement findByPhone() method.
    }


    public function findByExternalId(string $externalId, ?ContactPersonStatus $contactPersonStatus = null): array
    {
        if ('' === trim($externalId)) {
            throw new InvalidArgumentException('external id cannot be empty');
        }

        $criteria = ['externalId' => $externalId];

        if (null !== $contactPersonStatus) {
            $criteria['contactPersonStatus'] = $contactPersonStatus->name;
        }

        return $this->repository->findBy($criteria);
    }

}
