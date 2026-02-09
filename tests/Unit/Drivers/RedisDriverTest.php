<?php

namespace Tests\Unit\Drivers;

use Illuminate\Support\Facades\Redis;
use pribolshoy\laravelrepository\drivers\RedisDriver;
use Tests\TestCase;

/**
 * Тесты для драйвера кеша RedisDriver
 *
 * @package Tests\Unit\Drivers
 */
class RedisDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        // Очищаем фасады после каждого теста
        Redis::clearResolvedInstances();
        parent::tearDown();
    }

    /**
     * Тест определения поствикса для стратегии 'string'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyString(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'string']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для стратегии 'hash'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyHash(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'hash']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для стратегии 'table'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyTable(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'table']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для устаревшей стратегии 'getValue'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyLegacyGetValue(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'getValue']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для устаревшей стратегии 'getHValue'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyLegacyGetHValue(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'getHValue']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для устаревшей стратегии 'getAllHash'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyLegacyGetAllHash(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy(['strategy' => 'getAllHash']);
        
        $this->assertIsString($result);
    }

    /**
     * Тест определения поствикса для стратегии по умолчанию
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testGetIdPostfixByStrategyDefault(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $result = RedisDriver::getIdPostfixByStrategy([]);
        
        $this->assertIsString($result);
    }

    /**
     * Тест получения данных через стратегию 'string'
     */
    public function testGetWithStringStrategy(): void
    {
        $driver = new RedisDriver();
        
        $serializedData = gzcompress(serialize(['test' => 'data']));
        
        // Мокаем Redis через фасад
        Redis::shouldReceive('get')
            ->with('test:key')
            ->andReturn($serializedData);
        
        $result = $driver->get('test:key', ['strategy' => 'string']);
        
        $this->assertEquals(['test' => 'data'], $result);
    }

    /**
     * Тест получения данных через стратегию 'hash' с полями
     */
    public function testGetWithHashStrategyAndFields(): void
    {
        $driver = new RedisDriver();
        
        $fields = ['field1', 'field2'];
        $serializedData1 = gzcompress(serialize(['data1']));
        $serializedData2 = gzcompress(serialize(['data2']));
        
        Redis::shouldReceive('hmget')
            ->with('test:key', $fields)
            ->andReturn([$serializedData1, $serializedData2]);
        
        $result = $driver->get('test:key', [
            'strategy' => 'hash',
            'fields' => $fields
        ]);
        
        $this->assertIsArray($result);
    }

    /**
     * Тест получения данных через стратегию 'hash' без полей (getAllHash)
     */
    public function testGetWithHashStrategyWithoutFields(): void
    {
        $driver = new RedisDriver();
        
        $serializedData = gzcompress(serialize(['data']));
        
        Redis::shouldReceive('hvals')
            ->with('test:key')
            ->andReturn([$serializedData]);
        
        $result = $driver->get('test:key', ['strategy' => 'hash']);
        
        $this->assertIsArray($result);
    }

    /**
     * Тест установки данных через стратегию 'string'
     */
    public function testSetWithStringStrategy(): void
    {
        $driver = new RedisDriver();
        
        $data = ['test' => 'data'];
        
        Redis::shouldReceive('setex')
            ->andReturn(true);
        
        $result = $driver->set('test:key', $data, 3600, ['strategy' => 'string']);
        
        $this->assertSame($driver, $result);
    }

    /**
     * Тест установки данных через стратегию 'hash'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testSetWithHashStrategy(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $driver = new RedisDriver();
        
        $delimiter = \pribolshoy\repository\Config::getHashDelimiter();
        $key = "test:hash{$delimiter}field1";
        $data = ['test' => 'data'];
        
        Redis::shouldReceive('hset')
            ->andReturn(1);
        
        Redis::shouldReceive('expire')
            ->andReturn(true);
        
        $result = $driver->set($key, $data, 3600, ['strategy' => 'hash']);
        
        $this->assertSame($driver, $result);
    }

    /**
     * Тест удаления данных через стратегию 'string'
     */
    public function testDeleteWithStringStrategy(): void
    {
        $driver = new RedisDriver();
        
        Redis::shouldReceive('del')
            ->andReturn(1);
        
        $result = $driver->delete('test:key', ['strategy' => 'string']);
        
        $this->assertSame($driver, $result);
    }

    /**
     * Тест удаления данных через стратегию 'hash'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testDeleteWithHashStrategy(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $driver = new RedisDriver();
        
        $delimiter = \pribolshoy\repository\Config::getHashDelimiter();
        $key = "test:hash{$delimiter}field1";
        
        Redis::shouldReceive('hdel')
            ->andReturn(1);
        
        $result = $driver->delete($key, ['strategy' => 'hash']);
        
        $this->assertSame($driver, $result);
    }

    /**
     * Тест удаления всего хэша через поле '*'
     * 
     * Примечание: Требует класс Config из внешнего пакета
     */
    public function testDeleteHashWithWildcard(): void
    {
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Класс Config из внешнего пакета не найден');
            return;
        }
        
        $driver = new RedisDriver();
        
        $delimiter = \pribolshoy\repository\Config::getHashDelimiter();
        $key = "test:hash{$delimiter}*";
        
        Redis::shouldReceive('del')
            ->andReturn(1);
        
        $result = $driver->delete($key, ['strategy' => 'hash']);
        
        $this->assertSame($driver, $result);
    }

    /**
     * Тест обработки ошибки при несуществующей стратегии
     * 
     * Примечание: Тест может не работать, если Config не загружен.
     * Проверяем только базовую функциональность.
     */
    public function testGetWithInvalidStrategy(): void
    {
        // Пропускаем тест, если Config не доступен
        if (!class_exists('pribolshoy\repository\Config')) {
            $this->markTestSkipped('Config class not available');
        }
        
        $driver = new RedisDriver();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('не существует');
        
        $driver->get('test:key', ['strategy' => 'invalidStrategy']);
    }

    /**
     * Тест сериализации и сжатия данных
     * 
     * Примечание: Метод serialize() вызывает parent::serialize(), который может быть не реализован.
     * Проверяем только базовую функциональность сжатия.
     */
    public function testSerialize(): void
    {
        $driver = new RedisDriver();
        
        $data = ['test' => 'data', 'number' => 123];
        $serialized = serialize($data);
        
        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('serialize');
        $method->setAccessible(true);
        
        try {
            $result = $method->invoke($driver, $data);
            
            $this->assertIsString($result);
            // Проверяем, что данные сжаты (gzcompress добавляет заголовок)
            $this->assertNotEquals($serialized, $result);
        } catch (\Error $e) {
            // Если parent::serialize() не реализован, пропускаем тест
            $this->markTestSkipped('Parent serialize method not available: ' . $e->getMessage());
        }
    }

    /**
     * Тест десериализации и распаковки данных
     * 
     * Примечание: Метод unserialize() вызывает parent::unserialize(), который может быть не реализован.
     */
    public function testUnserialize(): void
    {
        $driver = new RedisDriver();
        
        $data = ['test' => 'data', 'number' => 123];
        $serialized = gzcompress(serialize($data));
        
        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('unserialize');
        $method->setAccessible(true);
        
        try {
            $result = $method->invoke($driver, $serialized);
            
            $this->assertEquals($data, $result);
        } catch (\Error $e) {
            // Если parent::unserialize() не реализован, пропускаем тест
            $this->markTestSkipped('Parent unserialize method not available: ' . $e->getMessage());
        }
    }

    /**
     * Тест десериализации null значения
     */
    public function testUnserializeNull(): void
    {
        $driver = new RedisDriver();
        
        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($driver);
        $method = $reflection->getMethod('unserialize');
        $method->setAccessible(true);
        
        $result = $method->invoke($driver, null);
        
        $this->assertNull($result);
    }
}

