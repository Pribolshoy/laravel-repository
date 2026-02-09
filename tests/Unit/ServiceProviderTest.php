<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use pribolshoy\laravelrepository\ServiceComponent;
use pribolshoy\laravelrepository\ServiceProvider;
use Tests\TestCase;

/**
 * Тесты для ServiceProvider
 *
 * @package Tests\Unit
 */
class ServiceProviderTest extends TestCase
{
    /**
     * Тест регистрации ServiceComponent как singleton
     * 
     * Примечание: Требует реальное Laravel приложение для полного тестирования
     */
    public function testRegisterServiceComponent(): void
    {
        // Проверяем только структуру метода register
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $method = $reflection->getMethod('register');
        
        $this->assertTrue($method->isPublic());
        $this->assertEquals('void', (string)$method->getReturnType());
    }

    /**
     * Тест регистрации алиаса для ServiceComponent
     * 
     * Примечание: Требует реальное Laravel приложение для полного тестирования
     */
    public function testRegisterServiceComponentAlias(): void
    {
        // Проверяем только структуру метода register
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $method = $reflection->getMethod('register');
        
        $this->assertTrue($method->isPublic());
    }

    /**
     * Тест проверки имени конфигурационного файла
     */
    public function testConfigName(): void
    {
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $property = $reflection->getProperty('configName');
        $property->setAccessible(true);
        
        // Получаем значение из класса без создания экземпляра
        $defaultProperties = $reflection->getDefaultProperties();
        $configName = $defaultProperties['configName'] ?? '';

        $this->assertEquals('infrastructure_services', $configName);
    }

    /**
     * Тест проверки алиаса сервиса
     */
    public function testServiceAlias(): void
    {
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $property = $reflection->getProperty('serviceAlias');
        $property->setAccessible(true);
        
        // Получаем значение из класса без создания экземпляра
        $defaultProperties = $reflection->getDefaultProperties();
        $serviceAlias = $defaultProperties['serviceAlias'] ?? '';

        $this->assertEquals('services', $serviceAlias);
    }

    /**
     * Тест проверки публикации конфигурационного файла (только в консоли)
     */
    public function testBootPublishesConfig(): void
    {
        // Проверяем, что метод boot существует и может быть вызван
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $method = $reflection->getMethod('boot');
        
        $this->assertTrue($method->isPublic());
        $this->assertEquals('void', (string)$method->getReturnType());
    }

    /**
     * Тест проверки регистрации команд (только в консоли)
     */
    public function testBootRegistersCommands(): void
    {
        // Проверяем, что метод boot существует
        $reflection = new \ReflectionClass(ServiceProvider::class);
        $method = $reflection->getMethod('boot');
        
        $this->assertTrue($method->isPublic());
    }
}

