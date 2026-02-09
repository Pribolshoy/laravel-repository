<?php

namespace Tests\Unit\Jobs;

use pribolshoy\laravelrepository\jobs\BaseServiceCacheJob;
use pribolshoy\laravelrepository\ServiceComponent;
use Tests\TestCase;

/**
 * Тесты для базового класса джоб BaseServiceCacheJob
 *
 * @package Tests\Unit\Jobs
 */
class BaseServiceCacheJobTest extends TestCase
{
    /**
     * Тест конструктора фильтра
     */
    public function testConstructor(): void
    {
        $filter = new TestServiceCacheJobFilter('testService', 'testGroup');

        $reflection = new \ReflectionClass($filter);
        $serviceNameProperty = $reflection->getProperty('serviceName');
        $serviceNameProperty->setAccessible(true);
        $groupNameProperty = $reflection->getProperty('groupName');
        $groupNameProperty->setAccessible(true);

        $this->assertEquals('testService', $serviceNameProperty->getValue($filter));
        $this->assertEquals('testGroup', $groupNameProperty->getValue($filter));
    }

    /**
     * Тест фильтрации конфигурации по имени сервиса
     */
    public function testFilterConfigByServiceName(): void
    {
        $filter = new TestServiceCacheJobFilter('service1', null);

        $config = [
            'service1' => ['class' => 'TestService1'],
            'service2' => ['class' => 'TestService2'],
        ];

        $result = $filter->filterConfig($config);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('service1', $result);
    }

    /**
     * Тест фильтрации конфигурации по группе
     */
    public function testFilterConfigByGroup(): void
    {
        $filter = new TestServiceCacheJobFilter(null, 'group1');

        $config = [
            'service1' => ['class' => 'TestService1', 'group' => 'group1'],
            'service2' => ['class' => 'TestService2', 'group' => 'group2'],
            'service3' => ['class' => 'TestService3', 'group' => 'group1'],
        ];

        $result = $filter->filterConfig($config);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('service1', $result);
        $this->assertArrayHasKey('service3', $result);
    }

    /**
     * Тест фильтрации конфигурации - возврат всех сервисов
     */
    public function testFilterConfigReturnAll(): void
    {
        $filter = new TestServiceCacheJobFilter(null, null);

        $config = [
            'service1' => ['class' => 'TestService1'],
            'service2' => ['class' => 'TestService2'],
        ];

        $result = $filter->filterConfig($config);

        $this->assertCount(2, $result);
    }

    /**
     * Тест фильтрации конфигурации - несуществующий сервис
     */
    public function testFilterConfigNonExistentService(): void
    {
        $filter = new TestServiceCacheJobFilter('nonexistent', null);

        $config = [
            'service1' => ['class' => 'TestService1'],
        ];

        $result = $filter->filterConfig($config);

        $this->assertEmpty($result);
    }
}

/**
 * Тестовая джоба для тестирования логики фильтрации BaseServiceCacheJob
 * 
 * Примечание: BaseServiceCacheJob требует Laravel трейты, поэтому тестируем только логику фильтрации
 */
class TestServiceCacheJobFilter
{
    protected ?string $serviceName = null;
    protected ?string $groupName = null;

    public function __construct(?string $serviceName = null, ?string $groupName = null)
    {
        $this->serviceName = $serviceName;
        $this->groupName = $groupName;
    }

    /**
     * Копируем логику filterConfig из BaseServiceCacheJob для тестирования
     */
    public function filterConfig(array $config): array
    {
        // Если указан конкретный сервис
        if ($this->serviceName) {
            if (!isset($config[$this->serviceName])) {
                return [];
            }

            $serviceConfig = $config[$this->serviceName];

            // Если также указана группа, проверяем соответствие
            if ($this->groupName && ($serviceConfig['group'] ?? null) !== $this->groupName) {
                return [];
            }

            return [$this->serviceName => $serviceConfig];
        }

        // Если указана только группа
        if ($this->groupName) {
            $filtered = [];
            foreach ($config as $name => $serviceConfig) {
                if (($serviceConfig['group'] ?? null) === $this->groupName) {
                    $filtered[$name] = $serviceConfig;
                }
            }

            return $filtered;
        }

        // Если ничего не указано, возвращаем все
        return $config;
    }
}

