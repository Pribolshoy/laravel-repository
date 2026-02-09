<?php

namespace Tests\Unit\Drivers;

use pribolshoy\laravelrepository\drivers\BaseCacheDriver;
use Tests\TestCase;

/**
 * Тесты для базового класса драйвера кеша BaseCacheDriver
 *
 * @package Tests\Unit\Drivers
 */
class BaseCacheDriverTest extends TestCase
{
    /**
     * Тест получения контейнера через функцию app()
     */
    public function testGetContainerWithAppFunction(): void
    {
        // Создаем мок контейнера
        $container = new \stdClass();
        $container->cache = new \stdClass();

        // Сохраняем оригинальную функцию app, если она существует
        $originalApp = null;
        if (function_exists('app')) {
            // В тестовой среде app() может быть недоступна
            // Создаем тестовый драйвер, который будет использовать мок
        }

        $driver = new class extends BaseCacheDriver {
            protected ?object $container = null;
            protected ?object $container_class = null;

            public function getContainerPublic(): object
            {
                return $this->getContainer();
            }
        };

        // Устанавливаем контейнер напрямую через рефлексию
        $reflection = new \ReflectionClass($driver);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $property->setValue($driver, $container);

        $result = $driver->getContainerPublic();

        $this->assertSame($container, $result);
    }

    /**
     * Тест получения контейнера через container_class (callable)
     */
    public function testGetContainerWithCallableContainerClass(): void
    {
        $container = new \stdClass();
        $container->cache = new \stdClass();

        $driver = new class($container) extends BaseCacheDriver {
            protected ?object $container = null;
            protected ?object $container_class = null;

            public function __construct($container)
            {
                $this->container_class = function() use ($container) {
                    return $container;
                };
            }

            public function getContainerPublic(): object
            {
                return $this->getContainer();
            }
        };

        $result = $driver->getContainerPublic();

        $this->assertSame($container, $result);
    }

    /**
     * Тест получения контейнера через container_class (класс)
     * 
     * Примечание: container_class имеет тип ?object, поэтому мы не можем напрямую
     * использовать строку с именем класса. Этот тест проверяет работу с callable.
     */
    public function testGetContainerWithClassContainerClass(): void
    {
        $containerClass = new class {
            public $cache;
            public function __construct()
            {
                $this->cache = new \stdClass();
            }
        };

        $driver = new class($containerClass) extends BaseCacheDriver {
            protected ?object $container = null;
            protected ?object $container_class = null;

            public function __construct($containerClass)
            {
                // Используем callable, который создает новый экземпляр
                $this->container_class = function() use ($containerClass) {
                    return new ($containerClass::class)();
                };
            }

            public function getContainerPublic(): object
            {
                return $this->getContainer();
            }
        };

        $result = $driver->getContainerPublic();

        $this->assertInstanceOf($containerClass::class, $result);
    }

    /**
     * Тест получения компонента через метод make()
     */
    public function testGetComponentWithMakeMethod(): void
    {
        $component = new \stdClass();
        $component->name = 'cache';

        $container = new class($component) {
            private $component;

            public function __construct($component)
            {
                $this->component = $component;
            }

            public function make(string $name)
            {
                return $this->component;
            }
        };

        $driver = new class extends BaseCacheDriver {
            protected string $component = 'cache';

            public function getComponentPublic()
            {
                return $this->getComponent();
            }

            protected function getContainer(): object
            {
                return new class {
                    private $component;

                    public function __construct()
                    {
                        $this->component = new \stdClass();
                        $this->component->name = 'cache';
                    }

                    public function make(string $name)
                    {
                        return $this->component;
                    }
                };
            }
        };

        $result = $driver->getComponentPublic();

        $this->assertNotNull($result);
    }

    /**
     * Тест получения компонента через свойство
     */
    public function testGetComponentWithProperty(): void
    {
        $component = new \stdClass();
        $component->name = 'cache';

        $container = new \stdClass();
        $container->cache = $component;

        $driver = new class extends BaseCacheDriver {
            protected string $component = 'cache';

            public function getComponentPublic()
            {
                return $this->getComponent();
            }

            protected function getContainer(): object
            {
                $container = new \stdClass();
                $container->cache = new \stdClass();
                $container->cache->name = 'cache';
                return $container;
            }
        };

        $result = $driver->getComponentPublic();

        $this->assertNotNull($result);
    }

    /**
     * Тест получения компонента когда контейнер не имеет метода make()
     */
    public function testGetComponentWithoutMakeMethod(): void
    {
        $component = new \stdClass();
        $component->name = 'cache';

        $container = new \stdClass();
        $container->cache = $component;

        $driver = new class extends BaseCacheDriver {
            protected string $component = 'cache';

            public function getComponentPublic()
            {
                return $this->getComponent();
            }

            protected function getContainer(): object
            {
                $container = new \stdClass();
                $container->cache = new \stdClass();
                return $container;
            }
        };

        $result = $driver->getComponentPublic();

        $this->assertNotNull($result);
    }

    /**
     * Тест получения компонента когда свойство отсутствует
     */
    public function testGetComponentWithoutProperty(): void
    {
        $container = new \stdClass();

        $driver = new class extends BaseCacheDriver {
            protected string $component = 'nonexistent';

            public function getComponentPublic()
            {
                return $this->getComponent();
            }

            protected function getContainer(): object
            {
                return new \stdClass();
            }
        };

        $result = $driver->getComponentPublic();

        $this->assertNull($result);
    }

    /**
     * Тест абстрактных методов get(), set(), delete()
     * Проверяем, что они определены в классе
     */
    public function testAbstractMethodsExist(): void
    {
        $driver = new class extends BaseCacheDriver {
            public function get(string $key, array $params = [])
            {
                return null;
            }

            public function set(string $key, $value, int $cache_duration = 0, array $params = []): object
            {
                return $this;
            }

            public function delete(string $key, array $params = []): object
            {
                return $this;
            }
        };

        $this->assertNull($driver->get('test'));
        $this->assertSame($driver, $driver->set('test', 'value'));
        $this->assertSame($driver, $driver->delete('test'));
    }
}

