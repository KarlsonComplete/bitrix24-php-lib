<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Entity;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSetting;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationSetting::class)]
class ApplicationSettingTest extends TestCase
{
    public function testCanCreateApplicationSetting(): void
    {
        $id = Uuid::v7();
        $applicationInstallationId = Uuid::v7();
        $key = 'test.setting.key';
        $value = '{"foo":"bar"}';

        $setting = new ApplicationSetting($id, $applicationInstallationId, $key, $value);

        $this->assertEquals($id, $setting->getId());
        $this->assertEquals($applicationInstallationId, $setting->getApplicationInstallationId());
        $this->assertEquals($key, $setting->getKey());
        $this->assertEquals($value, $setting->getValue());
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $setting->getCreatedAt());
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $setting->getUpdatedAt());
    }

    public function testCanUpdateValue(): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'initial_value'
        );

        $initialUpdatedAt = $setting->getUpdatedAt();

        // Small delay to ensure timestamp changes
        usleep(1000);

        $setting->updateValue('new_value');

        $this->assertEquals('new_value', $setting->getValue());
        $this->assertGreaterThan($initialUpdatedAt, $setting->getUpdatedAt());
    }

    public function testUpdateValueDoesNotChangeTimestampIfValueIsSame(): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'same_value'
        );

        $initialUpdatedAt = $setting->getUpdatedAt();

        // Small delay
        usleep(1000);

        $setting->updateValue('same_value');

        $this->assertEquals('same_value', $setting->getValue());
        $this->assertEquals($initialUpdatedAt, $setting->getUpdatedAt());
    }

    /**
     * @param string $invalidKey
     */
    #[DataProvider('invalidKeyProvider')]
    public function testThrowsExceptionForInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            $invalidKey,
            'value'
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidKeyProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ['   '],
            'too long' => [str_repeat('a', 256)],
            'invalid characters' => ['invalid key!'],
            'spaces' => ['invalid key'],
            'special chars' => ['key@#$%'],
        ];
    }

    /**
     * @param string $validKey
     */
    #[DataProvider('validKeyProvider')]
    public function testAcceptsValidKeys(string $validKey): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            $validKey,
            'value'
        );

        $this->assertEquals($validKey, $setting->getKey());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validKeyProvider(): array
    {
        return [
            'alphanumeric' => ['key123'],
            'with underscores' => ['test_key_name'],
            'with dots' => ['app.setting.key'],
            'with hyphens' => ['test-key-name'],
            'mixed' => ['app.test_key-123'],
            'uppercase' => ['TEST_KEY'],
            'single char' => ['a'],
        ];
    }
}
