<?php

namespace Tests\Unit\Repositories;

use pribolshoy\laravelrepository\repositories\AbstractEloquentRepository;
use Tests\TestCase;

/**
 * Тесты для абстрактного класса репозитория AbstractEloquentRepository
 *
 * @package Tests\Unit\Repositories
 */
class AbstractEloquentRepositoryTest extends TestCase
{
    /**
     * Тест проверки использования трейта EloquentHelper
     */
    public function testUsesEloquentHelper(): void
    {
        $reflection = new \ReflectionClass(AbstractEloquentRepository::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'pribolshoy\laravelrepository\helpers\EloquentHelper',
            $traits
        );
    }

    /**
     * Тест проверки наследования от AbstractCachebleRepository
     */
    public function testExtendsAbstractCachebleRepository(): void
    {
        $this->assertTrue(
            is_subclass_of(
                AbstractEloquentRepository::class,
                'pribolshoy\repository\AbstractCachebleRepository'
            )
        );
    }

    /**
     * Тест проверки абстрактности класса
     */
    public function testIsAbstract(): void
    {
        $reflection = new \ReflectionClass(AbstractEloquentRepository::class);
        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Тест проверки пути к драйверам
     */
    public function testDriverPath(): void
    {
        $reflection = new \ReflectionClass(AbstractEloquentRepository::class);
        $property = $reflection->getProperty('driver_path');
        $property->setAccessible(true);
        
        $defaultProperties = $reflection->getDefaultProperties();
        $driverPath = $defaultProperties['driver_path'] ?? '';

        $this->assertEquals('\\pribolshoy\\laravelrepository\\drivers\\', $driverPath);
    }

    /**
     * Тест метода getLimit с perPage
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testGetLimitWithPerPage(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода getLimit с limit
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testGetLimitWithLimit(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода getLimit с дефолтным значением
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testGetLimitWithDefault(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода getLimit с приоритетом perPage > limit
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testGetLimitPriority(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром id
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithId(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром ids
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithIds(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром code
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithCode(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром exclude_ids
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithExcludeIds(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром status
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithStatus(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода addQueries с фильтром status_id
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testAddQueriesWithStatusId(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода getTableName
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testGetTableName(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }

    /**
     * Тест метода defaultFilter
     * 
     * Примечание: Требует реальное подключение к БД для Eloquent, пропускаем
     */
    public function testDefaultFilter(): void
    {
        $this->markTestSkipped('Требует реальное подключение к БД для Eloquent');
    }
}
