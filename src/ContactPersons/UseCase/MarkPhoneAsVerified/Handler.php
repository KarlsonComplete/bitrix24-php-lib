<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkPhoneAsVerified;

use Bitrix24\Lib\Services\Flusher;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Entity\ContactPersonInterface;
use Bitrix24\SDK\Application\Contracts\ContactPersons\Repository\ContactPersonRepositoryInterface;
use Bitrix24\SDK\Application\Contracts\Events\AggregateRootEventsEmitterInterface;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

readonly class Handler
{
    public function __construct(
        private ContactPersonRepositoryInterface $contactPersonRepository,
        private Flusher $flusher,
        private LoggerInterface $logger
    ) {}

    public function handle(Command $command): void
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $this->logger->info('ContactPerson.ConfirmPhoneVerification.start', [
            'contactPersonId' => $command->contactPersonId->toRfc4122(),
            'phone' => $phoneNumberUtil->format($command->phone, PhoneNumberFormat::E164),
        ]);

        /** @var null|AggregateRootEventsEmitterInterface|ContactPersonInterface $contactPerson */
        $contactPerson = $this->contactPersonRepository->getById($command->contactPersonId);

        $actualPhone = $contactPerson->getMobilePhone();
        $expectedE164 = $phoneNumberUtil->format($command->phone, PhoneNumberFormat::E164);
        $actualE164 = null !== $actualPhone ? $phoneNumberUtil->format($actualPhone, PhoneNumberFormat::E164) : null;

        if ($expectedE164 !== $actualE164) {
            $this->logger->warning('ContactPerson.ConfirmPhoneVerification.phoneMismatch', [
                'contactPersonId' => $command->contactPersonId->toRfc4122(),
                'actualPhone' => $actualE164,
                'expectedPhone' => $expectedE164,
            ]);

            throw new \InvalidArgumentException(sprintf(
                'Phone mismatch for contact person %s: actual="%s", expected="%s"',
                $command->contactPersonId->toRfc4122(),
                $actualE164,
                $expectedE164
            ));
        }

        $contactPerson->markMobilePhoneAsVerified($command->phoneVerifiedAt);

        $this->contactPersonRepository->save($contactPerson);
        $this->flusher->flush($contactPerson);

        $this->logger->info('ContactPerson.ConfirmPhoneVerification.finish', [
            'contactPersonId' => $contactPerson->getId()->toRfc4122(),
            'mobilePhoneVerifiedAt' => $contactPerson->getMobilePhoneVerifiedAt()?->toIso8601String(),
        ]);
    }
}
