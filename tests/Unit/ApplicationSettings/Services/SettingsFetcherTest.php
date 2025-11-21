<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Tests\Unit\ApplicationSettings\Services;

use Bitrix24\Lib\ApplicationSettings\Entity\ApplicationSettingsItem;
use Bitrix24\Lib\ApplicationSettings\Infrastructure\InMemory\ApplicationSettingsItemInMemoryRepository;
use Bitrix24\Lib\ApplicationSettings\Services\Exception\SettingsItemNotFoundException;
use Bitrix24\Lib\ApplicationSettings\Services\SettingsFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Test DTO class for deserialization tests.
 */
class TestConfigDto
{
    public function __construct(
        public string $endpoint = '',
        public int $timeout = 30,
        public bool $enabled = true
    ) {}
}

/**
 * @internal
 */
#[CoversClass(SettingsFetcher::class)]
class SettingsFetcherTest extends TestCase
{
    private ApplicationSettingsItemInMemoryRepository $repository;

    private SettingsFetcher $fetcher;

    private Uuid $installationId;

    /** @var SerializerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private SerializerInterface $serializer;

    /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private LoggerInterface $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = new ApplicationSettingsItemInMemoryRepository();
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->fetcher = new SettingsFetcher($this->repository, $this->serializer, $this->logger);
        $this->installationId = Uuid::v7();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testReturnsGlobalSettingWhenNoOverrides(): void
    {
        // Create only global setting
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSettingsItem);

        $result = $this->fetcher->getItem($this->installationId, 'app.theme');

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalOverridesGlobal(): void
    {
        // Create global and departmental settings
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // When requesting for department 456, should get departmental setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testPersonalOverridesGlobalAndDepartmental(): void
    {
        // Create all three levels
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $personalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);
        $this->repository->save($personalSetting);

        // When requesting for user 123 and department 456, should get personal setting
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123, 456);

        $this->assertEquals('dark', $result->getValue());
        $this->assertTrue($result->isPersonal());
    }

    public function testFallsBackToGlobalWhenPersonalNotFound(): void
    {
        // Only global setting exists
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $this->repository->save($applicationSettingsItem);

        // Request for user 123, should fallback to global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 123);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testFallsBackToDepartmentalWhenPersonalNotFound(): void
    {
        // Global and departmental settings exist
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for user 999 (no personal setting) but department 456
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 999, 456);

        $this->assertEquals('blue', $result->getValue());
        $this->assertTrue($result->isDepartmental());
    }

    public function testThrowsExceptionWhenNoSettingFound(): void
    {
        $this->expectException(SettingsItemNotFoundException::class);
        $this->expectExceptionMessage('Setting with key "non.existent.key" not found');

        $this->fetcher->getItem($this->installationId, 'non.existent.key');
    }

    public function testGetValueReturnsStringValue(): void
    {
        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.version',
            '1.2.3',
            false
        );

        $this->repository->save($applicationSettingsItem);

        $result = $this->fetcher->getValue($this->installationId, 'app.version');

        $this->assertEquals('1.2.3', $result);
    }

    public function testGetValueThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(SettingsItemNotFoundException::class);
        $this->expectExceptionMessage('Setting with key "non.existent" not found');

        $this->fetcher->getValue($this->installationId, 'non.existent');
    }

    public function testGetValueDeserializesToObject(): void
    {
        $jsonValue = json_encode([
            'endpoint' => 'https://api.example.com',
            'timeout' => 60,
            'enabled' => true,
        ]);

        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'api.config',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $testConfigDto = new TestConfigDto(
            endpoint: 'https://api.example.com',
            timeout: 60,
            enabled: true
        );

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonValue, TestConfigDto::class, 'json')
            ->willReturn($testConfigDto);

        $result = $this->fetcher->getValue(
            $this->installationId,
            'api.config',
            class: TestConfigDto::class
        );

        $this->assertInstanceOf(TestConfigDto::class, $result);
        $this->assertEquals('https://api.example.com', $result->endpoint);
        $this->assertEquals(60, $result->timeout);
        $this->assertTrue($result->enabled);
    }

    public function testGetValueWithoutClassReturnsRawString(): void
    {
        $jsonValue = '{"foo":"bar","baz":123}';

        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'raw.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        // Serializer should NOT be called when class is not specified
        $this->serializer->expects($this->never())
            ->method('deserialize');

        $result = $this->fetcher->getValue($this->installationId, 'raw.setting');

        $this->assertIsString($result);
        $this->assertEquals($jsonValue, $result);
    }

    public function testGetValueLogsDeserializationFailure(): void
    {
        $jsonValue = 'invalid json{';

        $applicationSettingsItem = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'broken.setting',
            $jsonValue,
            false
        );

        $this->repository->save($applicationSettingsItem);

        $exception = new \Exception('Deserialization failed');

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonValue, TestConfigDto::class, 'json')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('SettingsFetcher.getValue.deserializationFailed', $this->callback(fn($context): bool => isset($context['key'], $context['class'], $context['error'])
                && 'broken.setting' === $context['key']
                && TestConfigDto::class === $context['class']));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Deserialization failed');

        $this->fetcher->getValue(
            $this->installationId,
            'broken.setting',
            class: TestConfigDto::class
        );
    }

    public function testPersonalSettingForDifferentUserNotUsed(): void
    {
        // Create global and personal for user 123
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $personalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'dark',
            false,
            123 // user ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($personalSetting);

        // Request for user 456 (different user), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', 456);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }

    public function testDepartmentalSettingForDifferentDepartmentNotUsed(): void
    {
        // Create global and departmental for dept 456
        $globalSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'light',
            false
        );

        $deptSetting = new ApplicationSettingsItem(
            Uuid::v7(),
            $this->installationId,
            'app.theme',
            'blue',
            false,
            null,
            456 // department ID
        );

        $this->repository->save($globalSetting);
        $this->repository->save($deptSetting);

        // Request for dept 789 (different department), should get global
        $result = $this->fetcher->getItem($this->installationId, 'app.theme', null, 789);

        $this->assertEquals('light', $result->getValue());
        $this->assertTrue($result->isGlobal());
    }
}
