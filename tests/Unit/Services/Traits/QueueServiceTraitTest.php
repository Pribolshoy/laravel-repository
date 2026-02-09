<?php

namespace Tests\Unit\Services\Traits;

use pribolshoy\laravelrepository\services\traits\QueueServiceTrait;
use pribolshoy\repository\interfaces\CachebleServiceInterface;
use pribolshoy\repository\interfaces\RepositoryInterface;
use Tests\TestCase;

/**
 * Тесты для трейта QueueServiceTrait
 *
 * @package Tests\Unit\Services\Traits
 */
class QueueServiceTraitTest extends TestCase
{
    /**
     * Тест проверки значения флага использования очереди по умолчанию
     */
    public function testShouldUseQueueForInitDefault(): void
    {
        $service = new TestServiceWithQueueTrait();

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('shouldUseQueueForInit');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        $this->assertTrue($result); // По умолчанию true
    }

    /**
     * Тест проверки значения флага использования очереди при установке false
     */
    public function testShouldUseQueueForInitFalse(): void
    {
        $service = new TestServiceWithQueueTrait();
        $service->setUseQueueForInit(false);

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('shouldUseQueueForInit');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        $this->assertFalse($result);
    }

    /**
     * Тест получения класса джобы по умолчанию
     * 
     * Примечание: Требует Laravel трейты для ServiceInitJob, пропускаем
     */
    public function testGetInitJobClassDefault(): void
    {
        $this->markTestSkipped('Требует Laravel трейты для ServiceInitJob::class');
    }

    /**
     * Тест получения кастомного класса джобы
     */
    public function testGetInitJobClassCustom(): void
    {
        $service = new TestServiceWithQueueTrait();
        $service->setInitJobClass('Custom\Job\Class');

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getInitJobClass');
        $method->setAccessible(true);

        $result = $method->invoke($service);

        $this->assertEquals('Custom\Job\Class', $result);
    }


    /**
     * Тест проверки необходимости отправки в очередь
     */
    public function testShouldQueueInit(): void
    {
        $service = new TestServiceWithQueueTrait();

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('shouldQueueInit');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'initStorage', []);

        $this->assertTrue($result);
    }

    /**
     * Тест проверки необходимости отправки в очередь с отключенным флагом
     */
    public function testShouldQueueInitWithDisabledFlag(): void
    {
        $service = new TestServiceWithQueueTrait();
        $service->setUseQueueForInit(false);

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('shouldQueueInit');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'initStorage', []);

        $this->assertFalse($result);
    }

    /**
     * Тест вызова синхронного метода
     */
    public function testCallSyncMethod(): void
    {
        $service = new TestServiceWithParent();

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('callSyncMethod');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'initStorage', []);

        $this->assertSame($service, $result);
        $this->assertTrue($service->initStorageSyncCalled);
    }

    /**
     * Тест синхронной инициализации хранилища с родительским классом
     */
    public function testInitStorageSyncWithParent(): void
    {
        $service = new TestServiceWithParent();

        $result = $service->initStorageSync();

        $this->assertSame($service, $result);
        $this->assertTrue($service->parentInitStorageCalled);
    }

    /**
     * Тест синхронной инициализации хранилища без родительского класса
     */
    public function testInitStorageSyncWithoutParent(): void
    {
        $service = new TestServiceWithQueueTrait();

        $result = $service->initStorageSync();

        $this->assertSame($service, $result);
    }

    /**
     * Тест синхронной инициализации кеша сущности с родительским классом
     */
    public function testInitEntityCacheSyncWithParent(): void
    {
        $service = new TestServiceWithParent();

        $result = $service->initEntityCacheSync(123);

        $this->assertTrue($result);
        $this->assertTrue($service->parentInitEntityCacheCalled);
    }

    /**
     * Тест синхронной инициализации кеша сущности без родительского класса
     */
    public function testInitEntityCacheSyncWithoutParent(): void
    {
        $service = new TestServiceWithQueueTrait();

        $result = $service->initEntityCacheSync(123);

        $this->assertFalse($result);
    }

    /**
     * Тест синхронной инициализации всего кеша с родительским классом
     */
    public function testInitAllCacheSyncWithParent(): void
    {
        $service = new TestServiceWithParent();

        $result = $service->initAllCacheSync();

        $this->assertSame($service, $result);
        $this->assertTrue($service->parentInitAllCacheCalled);
    }

    /**
     * Тест синхронной инициализации всего кеша без родительского класса
     */
    public function testInitAllCacheSyncWithoutParent(): void
    {
        $service = new TestServiceWithQueueTrait();

        $result = $service->initAllCacheSync();

        $this->assertSame($service, $result);
    }

    /**
     * Тест инициализации хранилища с отключенной очередью
     */
    public function testInitStorageWithoutQueue(): void
    {
        $service = new TestServiceWithParent();
        $service->setUseQueueForInit(false);

        $result = $service->initStorage();

        $this->assertSame($service, $result);
        $this->assertTrue($service->initStorageSyncCalled);
    }

    /**
     * Тест инициализации кеша сущности с отключенной очередью
     */
    public function testInitEntityCacheWithoutQueue(): void
    {
        $service = new TestServiceWithParent();
        $service->setUseQueueForInit(false);

        $result = $service->initEntityCache(123);

        $this->assertTrue($result);
        $this->assertTrue($service->initEntityCacheSyncCalled);
    }

    /**
     * Тест инициализации всего кеша с отключенной очередью
     */
    public function testInitAllCacheWithoutQueue(): void
    {
        $service = new TestServiceWithParent();
        $service->setUseQueueForInit(false);

        $result = $service->initAllCache();

        $this->assertSame($service, $result);
        $this->assertTrue($service->initAllCacheSyncCalled);
    }

    /**
     * Тест dispatchInitJob с отключенной очередью (синхронное выполнение)
     */
    public function testDispatchInitJobSync(): void
    {
        $service = new TestServiceWithParent();
        $service->setUseQueueForInit(false);

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('dispatchInitJob');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'initStorage', []);

        $this->assertSame($service, $result);
        $this->assertTrue($service->initStorageSyncCalled);
    }

    /**
     * Тест dispatchInitJob возвращает правильное значение для Cache методов
     * 
     * Примечание: Требует Laravel очередь для dispatch, пропускаем
     */
    public function testDispatchInitJobReturnsTrueForCacheMethods(): void
    {
        $this->markTestSkipped('Требует Laravel очередь для dispatch');
    }

    /**
     * Тест dispatchInitJob возвращает $this для не-Cache методов
     * 
     * Примечание: Требует Laravel очередь для dispatch, пропускаем
     */
    public function testDispatchInitJobReturnsThisForNonCacheMethods(): void
    {
        $this->markTestSkipped('Требует Laravel очередь для dispatch');
    }
}

/**
 * Родительский класс для тестирования parent:: методов
 * 
 * Упрощенная реализация с заглушками для всех методов интерфейса
 */
class TestParentService implements CachebleServiceInterface
{
    public bool $parentInitStorageCalled = false;
    public bool $parentInitEntityCacheCalled = false;
    public bool $parentInitAllCacheCalled = false;

    public function initStorage(?RepositoryInterface $repository = null, bool $refresh_repository_cache = false): CachebleServiceInterface
    {
        $this->parentInitStorageCalled = true;
        return $this;
    }

    public function initEntityCache($primaryKey, ...$parameters): bool
    {
        $this->parentInitEntityCacheCalled = true;
        return true;
    }

    public function initAllCache(...$parameters): static
    {
        $this->parentInitAllCacheCalled = true;
        return $this;
    }

    // Методы *Sync для тестирования
    public function initStorageSync(?RepositoryInterface $repository = null, bool $refresh_repository_cache = false): CachebleServiceInterface
    {
        return $this->initStorage($repository, $refresh_repository_cache);
    }

    public function initEntityCacheSync($primaryKey, ...$parameters): bool
    {
        return $this->initEntityCache($primaryKey, ...$parameters);
    }

    public function initAllCacheSync(...$parameters): static
    {
        return $this->initAllCache(...$parameters);
    }

    // Реализация методов интерфейса CachebleServiceInterface (заглушки)
    public function getItems(): array { return []; }
    public function getItem($id) { return null; }
    public function isUseCache(): bool { return false; }
    public function setUseCache(bool $use_cache): CachebleServiceInterface { return $this; }
    public function useAliasCache(): bool { return false; }
    public function setUseAliasCache(bool $use_alias_cache): CachebleServiceInterface { return $this; }
    public function getByAliasStructure($value) { return null; }
    public function isCacheExists(?\pribolshoy\repository\interfaces\CachebleRepositoryInterface $repository = null): bool { return false; }
    public function clearStorage(?\pribolshoy\repository\interfaces\CachebleRepositoryInterface $repository = null, array $params = []): bool { return false; }
    public function initStorageEvent(): bool { return false; }
    public function setHashPrefix(string $hash_prefix): self { return $this; }
    public function getHashPrefix(): string { return ''; }
    public function isFromCache(): bool { return false; }
    public function setIsFromCache(bool $is_from_cache): CachebleServiceInterface { return $this; }
    public function getFetchingStep(): int { return 0; }
    public function setFetchingStep(int $fetching_step): self { return $this; }
    public function refreshItem(array $primaryKeyArray): bool { return false; }
    public function prepareItem($item) { return $item; }
    public function setAliasPostfix(string $alias_postfix): CachebleServiceInterface { return $this; }
    public function getAliasPostfix(): string { return ''; }
    public function getAliasAttribute(): string { return ''; }
    public function getByAlias(string $alias, array $attributes = []) { return null; }
    public function addCacheParams(string $name, array $param): CachebleServiceInterface { return $this; }
    public function setCacheParams(array $cache_params): CachebleServiceInterface { return $this; }
    public function getCacheParams(string $name = ''): array { return []; }
    public function deleteItem(string $primaryKey): bool { return false; }
    public function getIdPostfix(): string { return ''; }
    public function getItemIdValue($item) { return null; }
    public function afterRefreshItem(array $primaryKeyArray): void {}
    public function getByHashtable($key, ?string $structureName) { return null; }
    public function sort(array $items): array { return $items; }
    public function resort(): \pribolshoy\repository\interfaces\ServiceInterface { return $this; }
    public function collectItemsPrimaryKeys(array $items): array { return []; }
    public function getHashByItem($item) { return null; }
    public function getItemAttribute($item, string $name) { return null; }
    // BaseServiceInterface методы
    public function clearEntityCache($primaryKey, ...$parameters): bool { return false; }
    public function clearAllCache(...$parameters): static { return $this; }
    public function getItemPrimaryKey($item) { return null; }
    public function hasItemAttribute($item, string $attribute): bool { return false; }
    public function collectRelationsFromArray(array $items, array $relations = []): array { return $items; }
    public function isMultiplePrimaryKey(): bool { return false; }
    public function setPrimaryKeys(array $primaryKeys): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function getItemStructure(bool $refresh = false): \pribolshoy\repository\interfaces\StructureInterface { throw new \Exception('Not implemented'); }
    public function getBasicHashtableStructure(bool $refresh = false): \pribolshoy\repository\structures\HashtableStructure { throw new \Exception('Not implemented'); }
    public function getNamedStructures(): array { return []; }
    public function getNamedStructure(string $name): ?\pribolshoy\repository\interfaces\StructureInterface { return null; }
    public function getRepository(array $params = []): \pribolshoy\repository\interfaces\RepositoryInterface { throw new \Exception('Not implemented'); }
    public function setRepositoryClass(string $repository_class): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function getHashtable() { return null; }
    public function updateHashtable(): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function setFilterClass(string $filter_class): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function getFilter(bool $refresh = false): \pribolshoy\repository\interfaces\FilterInterface { throw new \Exception('Not implemented'); }
    public function setSorting(array $sorting): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function getList(array $params = [], bool $cache_to = true): ?array { return []; }
    public function getByExp(array $attributes): array { return []; }
    public function getByMulti(array $attributes): array { return []; }
    public function getBy(array $attributes) { return null; }
    public function getById(int $id, array $attributes = []) { return null; }
    public function getByIds(array $ids, array $attributes = []): array { return []; }
    public function setItems(array $items): void {}
    public function addItem($item, bool $replace_if_exists = true): \pribolshoy\repository\interfaces\BaseServiceInterface { return $this; }
    public function getItemHash($item) { return null; }
    public function hash($value): string { return ''; }
}

/**
 * Тестовый сервис с трейтом QueueServiceTrait
 * 
 * Используем мок для избежания реализации всех методов интерфейса
 */
class TestServiceWithQueueTrait
{
    use QueueServiceTrait;

    protected ?bool $useQueueForInit = null;
    protected ?string $initJobClass = null;

    public function setUseQueueForInit(?bool $value): void
    {
        $this->useQueueForInit = $value;
    }

    public function setInitJobClass(?string $value): void
    {
        $this->initJobClass = $value;
    }
}

/**
 * Тестовый сервис с родительским классом для тестирования parent:: методов
 */
class TestServiceWithParent extends TestParentService
{
    use QueueServiceTrait;

    protected ?bool $useQueueForInit = null;
    protected ?string $initJobClass = null;

    public bool $initStorageSyncCalled = false;
    public bool $initEntityCacheSyncCalled = false;
    public bool $initAllCacheSyncCalled = false;

    public function setUseQueueForInit(?bool $value): void
    {
        $this->useQueueForInit = $value;
    }

    public function setInitJobClass(?string $value): void
    {
        $this->initJobClass = $value;
    }

    public function initStorageSync(?RepositoryInterface $repository = null, bool $refresh_repository_cache = false): CachebleServiceInterface
    {
        $this->initStorageSyncCalled = true;
        return parent::initStorageSync($repository, $refresh_repository_cache);
    }

    public function initEntityCacheSync($primaryKey, ...$parameters): bool
    {
        $this->initEntityCacheSyncCalled = true;
        return parent::initEntityCacheSync($primaryKey, ...$parameters);
    }

    public function initAllCacheSync(...$parameters): static
    {
        $this->initAllCacheSyncCalled = true;
        return parent::initAllCacheSync(...$parameters);
    }
}
