<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\UseCase\Create\Exception;

class SettingsItemAlreadyExistsException extends \Exception
{
    public static function byKey(string $key): self
    {
        return new self(
            sprintf(
                'Setting with key "%s" already exists for this scope. Use Update command to modify it.',
                $key
            )
        );
    }
}
