<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Entity;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\SDK\Core\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApplicationSettingsItem::class)]
class ApplicationSettingsItemTest extends TestCase
{
    public function testCanCreateGlobalSetting(): void
    {
        $uuidV7 = Uuid::v7();
        $applicationInstallationId = Uuid::v7();
        $key = 'test.setting.key';
        $value = '{"foo":"bar"}';

        $applicationSetting = new ApplicationSettingsItem($uuidV7, $applicationInstallationId, $key, $value, false);

        $this->assertEquals($uuidV7, $applicationSetting->getId());
        $this->assertEquals($applicationInstallationId, $applicationSetting->getApplicationInstallationId());
        $this->assertEquals($key, $applicationSetting->getKey());
        $this->assertEquals($value, $applicationSetting->getValue());
        $this->assertNull($applicationSetting->getB24UserId());
        $this->assertNull($applicationSetting->getB24DepartmentId());
        $this->assertTrue($applicationSetting->isGlobal());
        $this->assertFalse($applicationSetting->isPersonal());
        $this->assertFalse($applicationSetting->isDepartmental());
        $this->assertFalse($applicationSetting->isRequired());
    }

    public function testCanCreatePersonalSetting(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'user.preference',
            'dark_mode',
            false, // isRequired
            123 // b24UserId
        );

        $this->assertEquals(123, $applicationSetting->getB24UserId());
        $this->assertNull($applicationSetting->getB24DepartmentId());
        $this->assertFalse($applicationSetting->isGlobal());
        $this->assertTrue($applicationSetting->isPersonal());
        $this->assertFalse($applicationSetting->isDepartmental());
    }

    public function testCanCreateDepartmentalSetting(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'dept.config',
            'enabled',
            false, // isRequired
            null,  // No user ID
            456    // b24DepartmentId
        );

        $this->assertNull($applicationSetting->getB24UserId());
        $this->assertEquals(456, $applicationSetting->getB24DepartmentId());
        $this->assertFalse($applicationSetting->isGlobal());
        $this->assertFalse($applicationSetting->isPersonal());
        $this->assertTrue($applicationSetting->isDepartmental());
    }

    public function testCannotCreateSettingWithBothUserAndDepartment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting cannot be both personal and departmental');

        new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'invalid.setting',
            'value',
            false, // isRequired
            123,   // userId
            456    // departmentId - both set, should fail
        );
    }

    public function testCanUpdateValue(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'initial.value',
            false
        );

        $initialUpdatedAt = $applicationSetting->getUpdatedAt();
        usleep(1000);

        $applicationSetting->updateValue('new.value');

        $this->assertEquals('new.value', $applicationSetting->getValue());
        $this->assertGreaterThan($initialUpdatedAt, $applicationSetting->getUpdatedAt());
    }

    #[DataProvider('invalidKeyProvider')]
    public function testThrowsExceptionForInvalidKey(string $invalidKey): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            $invalidKey,
            'value',
            false
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

    #[DataProvider('validKeyProvider')]
    public function testAcceptsValidKeys(string $validKey): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            $validKey,
            'value',
            false
        );

        $this->assertEquals($validKey, $applicationSetting->getKey());
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

        new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            0      // Invalid: zero
        );
    }

    public function testThrowsExceptionForNegativeUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 user ID must be positive integer');

        new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            -1     // Invalid: negative
        );
    }

    public function testThrowsExceptionForInvalidDepartmentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bitrix24 department ID must be positive integer');

        new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'test.key',
            'value',
            false, // isRequired
            null,  // No user ID
            0      // Invalid: zero
        );
    }

    public function testCanCreateRequiredSetting(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'required.setting',
            'value',
            true // isRequired
        );

        $this->assertTrue($applicationSetting->isRequired());
    }

    public function testCanTrackWhoChangedSetting(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'tracking.test',
            'initial.value',
            false,
            null,
            null,
            123 // changedByBitrix24UserId
        );

        $this->assertEquals(123, $applicationSetting->getChangedByBitrix24UserId());

        // Update value with different user
        $applicationSetting->updateValue('new.value', 456);

        $this->assertEquals(456, $applicationSetting->getChangedByBitrix24UserId());
        $this->assertEquals('new.value', $applicationSetting->getValue());
    }

    public function testDefaultStatusIsActive(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'status.test',
            'value',
            false
        );

        $this->assertTrue($applicationSetting->isActive());
    }

    public function testCanMarkAsDeleted(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'delete.test',
            'value',
            false
        );

        $this->assertTrue($applicationSetting->isActive());

        $initialUpdatedAt = $applicationSetting->getUpdatedAt();
        usleep(1000);
        $applicationSetting->markAsDeleted();

        $this->assertFalse($applicationSetting->isActive());
        $this->assertGreaterThan($initialUpdatedAt, $applicationSetting->getUpdatedAt());
    }

    public function testMarkAsDeletedIsIdempotent(): void
    {
        $applicationSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            Uuid::v7(),
            'idempotent.test',
            'value',
            false
        );

        $applicationSetting->markAsDeleted();

        $firstUpdatedAt = $applicationSetting->getUpdatedAt();

        usleep(1000);
        $applicationSetting->markAsDeleted(); // Second call should not change updatedAt

        $this->assertEquals($firstUpdatedAt, $applicationSetting->getUpdatedAt());
    }
}
