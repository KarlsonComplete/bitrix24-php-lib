<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ContactPersons\UseCase\MarkPhoneAsVerified;

use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Uid\Uuid;

readonly class Command
{
    public function __construct(
        public Uuid $contactPersonId,
        public PhoneNumber $phone,
        public ?CarbonImmutable $phoneVerifiedAt = null,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        $isValidNumber = $phoneNumberUtil->isValidNumber($this->phone);
        if (!$isValidNumber) {
            throw new \InvalidArgumentException('Invalid phone number.');
        }

        $numberType = $phoneNumberUtil->getNumberType($this->phone);
        if (PhoneNumberType::MOBILE !== $numberType) {
            throw new \InvalidArgumentException('Phone number must be mobile.');
        }
    }
}
