<?php

declare(strict_types=1);

namespace Bitrix24\Lib\ApplicationSettings\Services\Exception;

class SettingsItemNotFoundException extends \Exception
{
    public static function byKey(string $key): self
    {
        return new self(sprintf('Setting with key "%s" not found', $key));
    }
}
