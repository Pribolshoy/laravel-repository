<?php

namespace Tests\Unit\Services;

use pribolshoy\laravelrepository\services\AbstractEloquentService;
use Tests\TestCase;

/**
 * Тесты для абстрактного класса AbstractEloquentService
 *
 * @package Tests\Unit\Services
 */
class AbstractEloquentServiceTest extends TestCase
{
    /**
     * Тест проверки использования трейта EloquentServiceTrait
     * 
     * Примечание: Требует загрузку класса AbstractCachebleService из внешнего пакета
     */
    public function testUsesEloquentServiceTrait(): void
    {
        if (!class_exists('pribolshoy\repository\services\AbstractCachebleService')) {
            $this->markTestSkipped('Класс AbstractCachebleService из внешнего пакета не найден');
            return;
        }
        
        $reflection = new \ReflectionClass(AbstractEloquentService::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'pribolshoy\laravelrepository\services\EloquentServiceTrait',
            $traits
        );
    }

    /**
     * Тест проверки наследования от AbstractCachebleService
     * 
     * Примечание: Требует загрузку класса AbstractCachebleService из внешнего пакета
     */
    public function testExtendsAbstractCachebleService(): void
    {
        if (!class_exists('pribolshoy\repository\services\AbstractCachebleService')) {
            $this->markTestSkipped('Класс AbstractCachebleService из внешнего пакета не найден');
            return;
        }
        
        $parentClass = get_parent_class(AbstractEloquentService::class);
        $this->assertEquals('pribolshoy\repository\services\AbstractCachebleService', $parentClass);
    }

    /**
     * Тест проверки абстрактности класса
     * 
     * Примечание: Требует загрузку класса AbstractCachebleService из внешнего пакета
     */
    public function testIsAbstract(): void
    {
        if (!class_exists('pribolshoy\repository\services\AbstractCachebleService')) {
            $this->markTestSkipped('Класс AbstractCachebleService из внешнего пакета не найден');
            return;
        }
        
        $reflection = new \ReflectionClass(AbstractEloquentService::class);
        $this->assertTrue($reflection->isAbstract());
    }
}

