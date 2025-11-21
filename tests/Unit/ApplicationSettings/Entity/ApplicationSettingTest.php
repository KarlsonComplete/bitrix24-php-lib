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
    public function testCanCreateGlobalSetting(): void
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
        $this->assertNull($setting->getB24UserId());
        $this->assertNull($setting->getB24DepartmentId());
        $this->assertTrue($setting->isGlobal());
        $this->assertFalse($setting->isPersonal());
        $this->assertFalse($setting->isDepartmental());
    }

    public function testCanCreatePersonalSetting(): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'user.preference',
            'dark_mode',
            123 // b24UserId
        );

        $this->assertEquals(123, $setting->getB24UserId());
        $this->assertNull($setting->getB24DepartmentId());
        $this->assertFalse($setting->isGlobal());
        $this->assertTrue($setting->isPersonal());
        $this->assertFalse($setting->isDepartmental());
    }

    public function testCanCreateDepartmentalSetting(): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'dept.config',
            'enabled',
            null, // No user ID
            456   // b24DepartmentId
        );

        $this->assertNull($setting->getB24UserId());
        $this->assertEquals(456, $setting->getB24DepartmentId());
        $this->assertFalse($setting->isGlobal());
        $this->assertFalse($setting->isPersonal());
        $this->assertTrue($setting->isDepartmental());
    }

    public function testCannotCreateSettingWithBothUserAndDepartment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting cannot be both personal and departmental');

        new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'invalid.setting',
            'value',
            123, // userId
            456  // departmentId - both set, should fail
        );
    }

    public function testCanUpdateValue(): void
    {
        $setting = new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'initial.value'
        );

        $initialUpdatedAt = $setting->getUpdatedAt();
        usleep(1000);

        $setting->updateValue('new.value');

        $this->assertEquals('new.value', $setting->getValue());
        $this->assertGreaterThan($initialUpdatedAt, $setting->getUpdatedAt());
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
            'with uppercase' => ['Test.Key'],
            'with numbers' => ['test.key.123'],
            'with underscore' => ['test_key'],
            'with hyphen' => ['test-key'],
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
            'simple lowercase' => ['key'],
            'with dots' => ['app.setting.key'],
            'multiple dots' => ['a.b.c.d.e'],
            'single char' => ['a'],
            'long valid key' => ['very.long.setting.key.name'],
        ];
    }

    public function testThrowsExceptionForInvalidUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 user ID must be positive integer');

        new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            0 // Invalid: zero
        );
    }

    public function testThrowsExceptionForNegativeUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 user ID must be positive integer');

        new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            -1 // Invalid: negative
        );
    }

    public function testThrowsExceptionForInvalidDepartmentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 department ID must be positive integer');

        new ApplicationSetting(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            null,
            0 // Invalid: zero
        );
    }
}
