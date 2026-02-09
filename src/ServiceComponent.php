<?php

namespace pribolshoy\laravelrepository;

use Illuminate\Support\Str;
use pribolshoy\repository\services\AbstractCachebleService;
use pribolshoy\repository\interfaces\BaseServiceInterface;

/**
 * Компонент для управления Infrastructure сервисами
 *
 * Предоставляет единую точку доступа к сервисам через конфигурацию.
 * Аналог ServiceComponent из Yii2 проекта bobamoba.
 *
 * @package pribolshoy\laravelrepository
 */
class ServiceComponent
{
    /**
     * Конфигурация по умолчанию для всех сервисов
     *
     * @var array
     */
    protected array $defaultConfig = [];

    /**
     * Принудительная конфигурация, которая переопределяет все остальные настройки
     *
     * @var array
     */
    protected array $forceConfig = [];

    /**
     * Список классов сервисов для регистрации
     *
     * @var array
     */
    protected array $classlist = [];

    /**
     * Кэш созданных объектов сервисов
     *
     * @var array
     */
    protected array $objects = [];

    /**
     * Конструктор компонента
     *
     * Инициализирует компонент с переданной конфигурацией и создает объекты сервисов.
     *
     * @param array $config Конфигурация компонента, может содержать:
     *                      - 'defaultConfig' - конфигурация по умолчанию для всех сервисов
     *                      - 'forceConfig' - принудительная конфигурация, переопределяющая все остальные
     *                      - 'classlist' - список классов сервисов для регистрации
     */
    public function __construct(array $config = [])
    {
        $this->defaultConfig = $config['defaultConfig'] ?? [];
        $this->forceConfig = $config['forceConfig'] ?? [];
        $this->classlist = $config['classlist'] ?? [];

        $this->init();
    }

    /**
     * Инициализация компонента - создание объектов сервисов
     *
     * Проходит по списку классов из classlist и создает объекты сервисов,
     * применяя к ним конфигурацию и сохраняя в кеш объектов.
     */
    protected function init(): void
    {
        if ($this->classlist) {
            /** @var AbstractCachebleService $object */
            foreach ($this->classlist as $object_name => $config) {
                $class = is_string($config) ? $config : $config['class'];
                $object = $this->createObject($class, is_array($config) ? $config : []);

                if ($object) {
                    $this->setObject($object_name, $object);
                }
            }
        }
    }

    /**
     * Получить конфигурацию компонента
     *
     * Возвращает список классов сервисов (classlist) с их конфигурацией.
     *
     * @return array Массив конфигурации сервисов [имя => конфигурация]
     */
    public function getConfig(): array
    {
        return $this->classlist;
    }

    /**
     * Создать объект сервиса
     *
     * Создает экземпляр сервиса и применяет к нему конфигурацию в следующем порядке:
     * 1. Конфигурация по умолчанию (defaultConfig)
     * 2. Переданная конфигурация
     * 3. Принудительная конфигурация (forceConfig)
     *
     * @param string|array $class Класс сервиса (строка) или массив с конфигурацией ['class' => 'ClassName', ...]
     * @param array $config Дополнительная конфигурация для применения к сервису
     * @return BaseServiceInterface|null Экземпляр сервиса или null, если не удалось создать
     */
    protected function createObject($class, array $config = []): ?BaseServiceInterface
    {
        $object = null;

        if (is_string($class) && class_exists($class)) {
            // Создаем объект напрямую через new, как в примере из bobamoba
            // Конфигурация передается в конструктор (если поддерживается)
            $object = new $class($config);
        } elseif (is_array($class) && array_key_exists('class', $class)) {
            $config = array_merge($class, $config);
            $class = $class['class'];
            unset($config['class']);

            $object = $this->createObject($class, $config);
        }

        if ($object instanceof BaseServiceInterface) {
            // Применяем конфигурацию по умолчанию
            if ($this->defaultConfig) {
                $this->setConfig($object, $this->defaultConfig);
            }

            // Применяем переданную конфигурацию
            if ($config) {
                $this->setConfig($object, $config);
            }

            // Применяем принудительную конфигурацию
            if ($this->forceConfig) {
                $this->setConfig($object, $this->forceConfig);
            }
        }

        return $object;
    }

    /**
     * Применить конфигурацию к сервису
     *
     * Применяет конфигурацию к сервису через сеттеры вида set{PropertyName}().
     * Если значение является колбеком (Closure или callable), вызывает его с сервисом
     * в качестве параметра и использует возвращаемое значение.
     *
     * @param BaseServiceInterface $service Сервис для применения конфигурации
     * @param array $config Конфигурация [имя_свойства => значение]
     */
    public function setConfig(BaseServiceInterface $service, array $config): void
    {
        foreach ($config as $name => $value) {
            if (is_null($value)) {
                continue;
            }

            // Если значение является колбеком (closure или callable объект), вызываем колбек
            // Колбек получает сервис в качестве параметра и должен вернуть bool
            if (($value instanceof \Closure || (is_object($value) && is_callable($value)))) {
                $value = call_user_func($value, $service);
            }

            $methodName = 'set' . Str::studly($name);
            if (method_exists($service, $methodName)) {
                $service->$methodName($value);
            }
        }
    }

    /**
     * Установить объект в хранилище
     *
     * Сохраняет объект сервиса в кеш объектов для последующего доступа по имени.
     *
     * @param string $name Имя сервиса (ключ для доступа)
     * @param object $object Объект сервиса для сохранения
     * @return $this Возвращает $this для цепочки вызовов
     */
    protected function setObject(string $name, object $object): self
    {
        $this->objects[$name] = $object;
        return $this;
    }

    /**
     * Получить объект сервиса по имени
     *
     * Возвращает ранее созданный объект сервиса из кеша объектов.
     *
     * @param string $name Имя сервиса
     * @return object|false Экземпляр сервиса или false, если сервис не найден
     */
    public function getObject(string $name)
    {
        if (array_key_exists($name, $this->objects)) {
            return $this->objects[$name];
        }

        return false;
    }

    /**
     * Магический метод для получения сервисов через свойство
     *
     * Позволяет получать сервисы через обращение к свойству компонента:
     * $services->votingService вместо $services->getObject('votingService')
     *
     * @param string $name Имя сервиса
     * @return object|null Экземпляр сервиса или null, если сервис не найден
     */
    public function __get(string $name)
    {
        if ($object = $this->getObject($name)) {
            return $object;
        }

        return null;
    }
}

