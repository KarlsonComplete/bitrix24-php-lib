# ApplicationSettings - Подсистема хранения настроек приложения

## Обзор

Подсистема ApplicationSettings предназначена для хранения и управления настройками приложений Bitrix24 с использованием паттерна Domain-Driven Design и CQRS.

## Основные концепции

### 1. Bounded Context

ApplicationSettings - это отдельный bounded context, который инкапсулирует всю логику работы с настройками приложения.

### 2. Уровни настроек (Scopes)

Система поддерживает три уровня настроек:

#### Глобальные настройки (Global)
Применяются ко всей установке приложения, доступны всем пользователям.

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Command as SetCommand;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Handler as SetHandler;
use Symfony\Component\Uid\Uuid;

// Создание глобальной настройки
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'app.language',
    value: 'ru',
    isRequired: true  // Обязательная настройка
);

$handler->handle($command);
```

#### Персональные настройки (Personal)
Привязаны к конкретному пользователю Bitrix24.

```php
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'user.theme',
    value: 'dark',
    isRequired: false,
    b24UserId: 123  // ID пользователя
);

$handler->handle($command);
```

#### Департаментские настройки (Departmental)
Привязаны к конкретному отделу.

```php
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'department.workingHours',
    value: '9:00-18:00',
    isRequired: false,
    b24UserId: null,
    b24DepartmentId: 456  // ID отдела
);

$handler->handle($command);
```

### 3. Статусы настроек

Каждая настройка имеет статус (enum `ApplicationSettingStatus`):

- **Active** - активная настройка, доступна для использования
- **Deleted** - мягко удаленная настройка (soft-delete)

### 4. Soft Delete

Система использует паттерн soft-delete:
- Настройки не удаляются физически из БД
- При удалении статус меняется на `Deleted`
- Это позволяет сохранить историю и восстановить данные при необходимости

### 5. Инварианты (ограничения)

**Уникальность ключа:** Комбинация полей `applicationInstallationId + key + b24UserId + b24DepartmentId` должна быть уникальной.

Это означает:
- ✅ Можно иметь глобальную настройку `app.theme`
- ✅ Можно иметь персональную настройку `app.theme` для пользователя 123
- ✅ Можно иметь персональную настройку `app.theme` для пользователя 456
- ✅ Можно иметь департаментскую настройку `app.theme` для отдела 789
- ❌ Нельзя создать две глобальные настройки с ключом `app.theme` для одной инсталляции
- ❌ Нельзя создать две персональные настройки с ключом `app.theme` для одного пользователя

Это ограничение обеспечивается:
- На уровне базы данных через UNIQUE INDEX
- На уровне приложения через валидацию в UseCase\Set\Handler

## Структура данных

### Поля сущности ApplicationSetting

```php
class ApplicationSetting
{
    private Uuid $id;                           // UUID v7
    private Uuid $applicationInstallationId;     // Связь с установкой
    private string $key;                         // Ключ (только a-z и точки)
    private string $value;                       // Значение (любая строка, JSON)
    private bool $isRequired;                    // Обязательная ли настройка
    private ?int $b24UserId;                     // ID пользователя (для personal)
    private ?int $b24DepartmentId;               // ID отдела (для departmental)
    private ?int $changedByBitrix24UserId;       // Кто последний изменил
    private ApplicationSettingStatus $status;    // Статус (active/deleted)
    private CarbonImmutable $createdAt;         // Дата создания
    private CarbonImmutable $updatedAt;         // Дата обновления
}
```

### Правила валидации ключей

- Только строчные латинские буквы (a-z) и точки
- Максимальная длина 255 символов
- Рекомендуемый формат: `category.subcategory.name`

Примеры валидных ключей:
```php
'app.version'
'user.interface.theme'
'notification.email.enabled'
'integration.api.timeout'
```

## Use Cases (Команды)

### Set - Создание/Обновление настройки

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Set\Handler;

$command = new Command(
    applicationInstallationId: $installationId,
    key: 'feature.analytics',
    value: 'enabled',
    isRequired: true,
    b24UserId: null,
    b24DepartmentId: null,
    changedByBitrix24UserId: 100  // Кто вносит изменение
);

$handler->handle($command);
```

### Delete - Мягкое удаление настройки

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\Delete\Handler;

$command = new Command(
    applicationInstallationId: $installationId,
    key: 'deprecated.setting',
    b24UserId: null,        // Опционально
    b24DepartmentId: null   // Опционально
);

$handler->handle($command);
// Настройка помечена как deleted, но остается в БД
```

### OnApplicationDelete - Удаление всех настроек при деинсталляции

```php
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Command;
use Bitrix24\Lib\ApplicationSettings\UseCase\OnApplicationDelete\Handler;

// При деинсталляции приложения
$command = new Command(
    applicationInstallationId: $installationId
);

$handler->handle($command);
// Все настройки помечены как deleted
```

## Работа с Repository

### Поиск настроек

```php
use Bitrix24\Lib\ApplicationSettings\Infrastructure\Doctrine\ApplicationSettingRepository;

/** @var ApplicationSettingRepository $repository */

// Получить все активные настройки для инсталляции
$allSettings = $repository->findAllForInstallation($installationId);

// Найти глобальную настройку по ключу
$globalSetting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'app.version' && $s->isGlobal()) {
        $globalSetting = $s;
        break;
    }
}

// Найти персональную настройку пользователя
$personalSetting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'user.theme' && $s->isPersonal() && $s->getB24UserId() === $userId) {
        $personalSetting = $s;
        break;
    }
}

// Отфильтровать все глобальные настройки
$globalSettings = array_filter($allSettings, fn ($s): bool => $s->isGlobal());

// Отфильтровать персональные настройки пользователя
$personalSettings = array_filter($allSettings, fn ($s): bool => $s->isPersonal() && $s->getB24UserId() === $userId);

// Отфильтровать настройки отдела
$deptSettings = array_filter($allSettings, fn ($s): bool => $s->isDepartmental() && $s->getB24DepartmentId() === $deptId);
```

**Важно:** Все методы find* возвращают только настройки со статусом `Active`. Удаленные настройки не возвращаются.

## Events (События)

### ApplicationSettingChangedEvent

Генерируется при изменении значения настройки:

```php
class ApplicationSettingChangedEvent
{
    public Uuid $settingId;
    public string $key;
    public string $oldValue;
    public string $newValue;
    public ?int $changedByBitrix24UserId;
    public CarbonImmutable $changedAt;
}
```

События можно перехватывать для логирования, аудита или триггера других действий:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SettingChangeLogger implements EventSubscriberInterface
{
    public function onSettingChanged(ApplicationSettingChangedEvent $event): void
    {
        $this->logger->info('Setting changed', [
            'key' => $event->key,
            'old' => $event->oldValue,
            'new' => $event->newValue,
            'changedBy' => $event->changedByBitrix24UserId,
        ]);
    }
}
```

## Сервис InstallSettings

Утилита для создания набора настроек по умолчанию при установке приложения:

```php
use Bitrix24\Lib\ApplicationSettings\Services\InstallSettings;

// Создать все настройки для новой установки
$installer = new InstallSettings(
    $repository,
    $flusher,
    $logger
);

$installer->createDefaultSettings(
    applicationInstallationId: $installationId,
    defaultSettings: [
        'app.name' => ['value' => 'My App', 'required' => true],
        'app.language' => ['value' => 'ru', 'required' => true],
        'features.notifications' => ['value' => 'true', 'required' => false],
    ]
);
```

## CLI команды

### Просмотр настроек

```bash
# Все настройки установки
php bin/console app:settings:list <installation-id>

# Только глобальные
php bin/console app:settings:list <installation-id> --global-only

# Персональные пользователя
php bin/console app:settings:list <installation-id> --user-id=123

# Департаментские
php bin/console app:settings:list <installation-id> --department-id=456
```

## Примеры использования

### Пример 1: Хранение JSON-конфигурации

```php
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'integration.api.config',
    value: json_encode([
        'endpoint' => 'https://api.example.com',
        'timeout' => 30,
        'retries' => 3,
    ]),
    isRequired: true
);
$handler->handle($command);

// Чтение
$allSettings = $repository->findAllForInstallation($installationId);
$setting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'integration.api.config' && $s->isGlobal()) {
        $setting = $s;
        break;
    }
}
$config = $setting ? json_decode($setting->getValue(), true) : [];
```

### Пример 2: Персонализация интерфейса

```php
// Сохранить предпочтения пользователя
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'ui.preferences',
    value: json_encode([
        'theme' => 'dark',
        'language' => 'ru',
        'dashboard_layout' => 'compact',
    ]),
    isRequired: false,
    b24UserId: $currentUserId,
    changedByBitrix24UserId: $currentUserId
);
$handler->handle($command);

// Получить предпочтения
$allSettings = $repository->findAllForInstallation($installationId);
$setting = null;
foreach ($allSettings as $s) {
    if ($s->getKey() === 'ui.preferences' && $s->isPersonal() && $s->getB24UserId() === $currentUserId) {
        $setting = $s;
        break;
    }
}
$preferences = $setting ? json_decode($setting->getValue(), true) : [];
```

### Пример 3: Каскадное разрешение настроек

```php
/**
 * Получить значение настройки с учетом приоритетов:
 * 1. Персональная (если есть)
 * 2. Департаментская (если есть)
 * 3. Глобальная (fallback)
 */
function getSetting(
    ApplicationSettingRepository $repository,
    Uuid $installationId,
    string $key,
    ?int $userId = null,
    ?int $deptId = null
): ?string {
    $allSettings = $repository->findAllForInstallation($installationId);

    // Попробовать найти персональную
    if ($userId) {
        foreach ($allSettings as $s) {
            if ($s->getKey() === $key && $s->isPersonal() && $s->getB24UserId() === $userId) {
                return $s->getValue();
            }
        }
    }

    // Попробовать найти департаментскую
    if ($deptId) {
        foreach ($allSettings as $s) {
            if ($s->getKey() === $key && $s->isDepartmental() && $s->getB24DepartmentId() === $deptId) {
                return $s->getValue();
            }
        }
    }

    // Fallback на глобальную
    foreach ($allSettings as $s) {
        if ($s->getKey() === $key && $s->isGlobal()) {
            return $s->getValue();
        }
    }

    return null;
}
```

### Пример 4: Аудит изменений

```php
// При изменении настройки указываем, кто внес изменение
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'security.two_factor',
    value: 'enabled',
    isRequired: true,
    changedByBitrix24UserId: $adminUserId
);
$handler->handle($command);

// События автоматически логируются с информацией о том, кто изменил
```

## Рекомендации

### 1. Именование ключей

Используйте понятные, иерархические имена:

```php
// Хорошо
'app.feature.notifications.email'
'user.interface.theme'
'integration.crm.enabled'

// Плохо
'notif'
'th'
'crm1'
```

### 2. Типизация значений

Храните JSON для сложных структур:

```php
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'feature.limits',
    value: json_encode([
        'users' => 100,
        'storage_gb' => 50,
        'api_calls_per_day' => 10000,
    ]),
    isRequired: true
);
```

### 3. Обязательные настройки

Помечайте критичные настройки как `isRequired`:

```php
$command = new SetCommand(
    applicationInstallationId: $installationId,
    key: 'app.license_key',
    value: $licenseKey,
    isRequired: true  // Приложение не работает без этого
);
```

### 4. Мягкое удаление

Используйте soft-delete вместо физического удаления:

```php
// Вместо физического удаления
// $repository->delete($setting);

// Используйте мягкое удаление
$deleteCommand = new DeleteCommand($installationId, 'old.setting');
$deleteHandler->handle($deleteCommand);
```

## Безопасность

1. **Валидация ключей** - автоматическая, только разрешенные символы
2. **Изоляция данных** - настройки привязаны к `applicationInstallationId`
3. **Аудит** - отслеживание кто и когда изменил (`changedByBitrix24UserId`)
4. **История** - soft-delete сохраняет историю для расследований

## Производительность

1. **Индексы** - все ключевые поля индексированы (installation_id, key, user_id, department_id, status)
2. **Кэширование** - рекомендуется кэшировать часто используемые настройки
3. **Batch операции** - используйте `InstallSettings` для массового создания

## Миграция схемы БД

После внесения изменений в код необходимо обновить схему БД:

```bash
# Создать схему (первый раз)
make schema-create

# Или сгенерировать миграцию
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Тестирование

Система полностью покрыта тестами:

```bash
# Unit-тесты
make test-run-unit

# Functional-тесты (требует БД)
make test-run-functional
```

---

**Дополнительные материалы:**
- [Tech Stack](./tech-stack.md)
- [CLAUDE.md](../CLAUDE.md) - Основные команды и архитектура проекта
