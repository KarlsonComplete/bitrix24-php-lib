<?php

namespace Bitrix24\Lib\Services\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberType extends Type
{
    public const PHONE_NUMBER = 'phone_number'; // Тип, используемый в маппинге

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    /**
     * @param null|string $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value || $value instanceof PhoneNumber) {
            return $value;
        }

        try {
            return PhoneNumberUtil::getInstance()->parse($value, null);
        } catch (NumberParseException $numberParseException) {
            throw new \InvalidArgumentException('Invalid phone number format: '.$numberParseException->getMessage(), $numberParseException->getCode(), $numberParseException);
        }
    }

    /**
     * @param null|PhoneNumber $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PhoneNumber) {
            throw new \InvalidArgumentException('Expected '.PhoneNumber::class.', got '.gettype($value));
        }

        return PhoneNumberUtil::getInstance()->format($value, PhoneNumberFormat::E164);
    }

    public function getName(): string
    {
        return self::PHONE_NUMBER;
    }
}
