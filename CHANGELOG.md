


## 0.1.2
### Added
- **ApplicationSettings bounded context** for application configuration management — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
  - Full CRUD functionality with CQRS pattern (Create, Update, Delete use cases)
  - Multi-scope support: Global, Departmental, and Personal settings with cascading resolution
  - **SettingsFetcher service** with automatic deserialization support
    - Cascading resolution logic (Personal → Departmental → Global)
    - JSON deserialization to objects using Symfony Serializer
    - Comprehensive logging with LoggerInterface
  - **InstallSettings service** for bulk creation of default settings
  - Soft-delete support with `ApplicationSettingStatus` enum (Active/Deleted)
  - Event system with `ApplicationSettingsItemChangedEvent` for change tracking
  - CLI command `app:settings:list` for viewing settings with scope filtering
  - InMemory repository implementation for fast unit testing
  - Unique constraint on (installation_id, key, user_id, department_id)
  - Tracking fields: `changedByBitrix24UserId`, `isRequired`
- Database schema updates
  - Table `application_settings` with UUID v7 IDs
  - Scope fields: `b24_user_id`, `b24_department_id`
  - Status field with index for query optimization
  - Timestamp tracking: `created_at_utc`, `updated_at_utc`
- Comprehensive test coverage
  - Unit tests for entity validation and business logic
  - Functional tests for repository operations and use case handlers
  - Tests for all scope types and soft-delete behavior

### Changed
- **Refactored ApplicationSettings entity naming**
  - Renamed `ApplicationSetting` → `ApplicationSettingsItem`
  - Renamed all interfaces and events accordingly
  - Updated table name from `application_setting` → `application_settings`
- **Separated Create/Update use cases**
  - Create UseCase now only creates new settings (throws exception if exists)
  - Update UseCase for modifying existing settings (throws exception if not found)
  - Update automatically emits `ApplicationSettingsItemChangedEvent`
- **Simplified repository API**
  - Removed 6 redundant methods, kept only `findAllForInstallation()`
  - Renamed `findAll()` → `findAllForInstallationByKey()` to avoid conflicts
  - All find methods now filter by `status=Active` by default
  - Added optimized `findAllForInstallationByKey()` method
- **Enhanced SettingsFetcher**
  - Renamed `getSetting()` → `getItem()`
  - Renamed `getSettingValue()` → `getValue()`
  - Added automatic deserialization with type-safe generics
  - Non-nullable return types with exception throwing
- **ApplicationSettingsItem improvements**
  - UUID v7 generation moved inside entity constructor
  - Key validation: only lowercase latin letters and dots
  - Scope methods: `isGlobal()`, `isPersonal()`, `isDepartmental()`
  - `updateValue()` method emits change events
- **Makefile improvements**
  - Updated to use Docker for `composer-license-checker`
  - Aligns with other linting and analysis workflows
- **Code quality improvements**
  - Applied Rector automatic refactoring (arrow functions, type hints, naming)
  - Added `#[\Override]` attributes to overridden methods
  - Applied PHP-CS-Fixer formatting consistently
  - Added symfony/property-access dependency for ObjectNormalizer
- **Documentation improvements**
  - Translated ApplicationSettings documentation to English
  - Updated all code examples to reflect current codebase
  - Corrected exception class names (SettingsItemAlreadyExistsException, SettingsItemNotFoundException)
  - Improved best practices and security sections
- **Test infrastructure improvements**
  - Created contract tests for ApplicationSettingsItemRepositoryInterface
  - Moved ApplicationSettingsItemInMemoryRepository from src to tests/Helpers
  - Added contract test implementations for both InMemory and Doctrine repositories
  - Refactored existing repository tests to focus on implementation-specific behavior

### Fixed
- **PHPStan level 5 errors related to SDK interface compatibility** — [#67](https://github.com/mesilov/bitrix24-php-lib/issues/67)
  - Removed invalid `#[\Override]` attributes from extension methods in `ApplicationInstallationRepository`
  - Fixed `findByMemberId()` call with incorrect parameter count in `OnAppInstall\Handler`
  - Added `@phpstan-ignore-next-line` comments for methods not yet available in SDK interface
  - Added TODO comments to track SDK interface extension requirements
- **Doctrine XML mapping**
  - Fixed `enumType` → `enum-type` syntax for Doctrine ORM 3 compatibility
- **Repository method naming conflicts**
  - Renamed methods to avoid conflicts with EntityRepository base class
- **Exception handling**
  - Added `SettingsItemAlreadyExistsException` for Create use case
  - Added `SettingsItemNotFoundException` for Get/Delete operations
  - Updated all handlers to throw specific exceptions

### Removed
- **Get UseCase** - replaced with `SettingsFetcher` service (UseCases now only for data modification)
- **Redundant repository methods**
  - `findGlobalByKey()`, `findPersonalByKey()`, `findDepartmentalByKey()`
  - `findAllGlobal()`, `findAllPersonal()`, `findAllDepartmental()`
  - `deleteByApplicationInstallationId()`
  - `softDeleteByApplicationInstallationId()`
- **Hard delete from Delete UseCase** - replaced with soft-delete pattern
- **Entity getStatus() method** - use `isActive()` instead for better encapsulation
- **Static getRecommendedDefaults()** - developers should define their own defaults

## 0.1.1
### Added
- Change php version requirements — [#44](https://github.com/mesilov/bitrix24-php-lib/pull/44)

## 0.1.0

### By [@mesilov](https://github.com/mesilov)
- Add initial project setup with CI configuration — [#2](https://github.com/mesilov/bitrix24-php-lib/pull/2)
- Fix incorrect annotation syntax from `#[\Override]` to `#[Override]` — [#3](https://github.com/mesilov/bitrix24-php-lib/pull/3)
- Rename package and namespaces to `bitrix24-php-lib` — [#4](https://github.com/mesilov/bitrix24-php-lib/pull/4)
- Add docker structure — [#13](https://github.com/mesilov/bitrix24-php-lib/pull/13)
- Add application install — [#43](https://github.com/mesilov/bitrix24-php-lib/pull/43)

---

### By [@KarlsonComplete](https://github.com/KarlsonComplete)
- Add docker containers — [#12](https://github.com/mesilov/bitrix24-php-lib/pull/12)
- Add docker structure —  [#14](https://github.com/mesilov/bitrix24-php-lib/pull/14), [#15](https://github.com/mesilov/bitrix24-php-lib/pull/15),   [#16](https://github.com/mesilov/bitrix24-php-lib/pull/16), [#17](https://github.com/mesilov/bitrix24-php-lib/pull/17),  [#19](https://github.com/mesilov/bitrix24-php-lib/pull/19), [#27](https://github.com/mesilov/bitrix24-php-lib/pull/27),   [#29](https://github.com/mesilov/bitrix24-php-lib/pull/29), [#32](https://github.com/mesilov/bitrix24-php-lib/pull/32),   [#34](https://github.com/mesilov/bitrix24-php-lib/pull/34), [#36](https://github.com/mesilov/bitrix24-php-lib/pull/36),   [#37](https://github.com/mesilov/bitrix24-php-lib/pull/37),  [#38](https://github.com/mesilov/bitrix24-php-lib/pull/38)
- Added mapping, fixing functional tests — [#18](https://github.com/mesilov/bitrix24-php-lib/pull/18)
- Removed attributes in the account — [#20](https://github.com/mesilov/bitrix24-php-lib/pull/20)
- Fixed some errors in functional tests — [#21](https://github.com/mesilov/bitrix24-php-lib/pull/21)
- Added fetcher test and removed more comments — [#22](https://github.com/mesilov/bitrix24-php-lib/pull/22)
- Fixes — [#23](https://github.com/mesilov/bitrix24-php-lib/pull/23)
- Fixes for scope — [#24](https://github.com/mesilov/bitrix24-php-lib/pull/24)
- Update fetcher and flusher — [#25](https://github.com/mesilov/bitrix24-php-lib/pull/25)
- Add application install — [#40](https://github.com/mesilov/bitrix24-php-lib/pull/40)  
