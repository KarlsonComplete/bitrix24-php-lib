<?php

namespace Bitrix24\Lib\Services\Doctrine;

use Darsyn\IP\IpInterface;
use Darsyn\IP\Version\Multi;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class IpAddressType extends Type
{
    public const IP_ADDRESS = 'ip_address';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // Длина 45 символов для хранения IPv6 (максимальная длина)
        return $platform->getStringTypeDeclarationSQL(array_merge($column, ['length' => 45]));
    }

    /**
     * @param null|string $value
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?IpInterface
    {
        if (null === $value || $value instanceof IpInterface) {
            return $value;
        }

        try {
            // Используем фабрику Multi, которая сама определит IPv4 или IPv6
            return Multi::factory($value);
        } catch (\Exception) {
            throw new ConversionException(sprintf(
                'Conversion failed for value "%s" to Doctrine type %s',
                $value,
                $this->getName()
            ));
        }
    }

    /**
     * @param null|IpInterface $value
     */
    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof IpInterface) {
            throw new \InvalidArgumentException('Expected instance of '.IpInterface::class.', got '.gettype($value));
        }

        // Для хранения используем представление в протокольно-адекватной форме
        return $value->getProtocolAppropriateAddress();
    }

    public function getName(): string
    {
        return self::IP_ADDRESS;
    }
}
