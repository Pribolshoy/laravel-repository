<?php

namespace pribolshoy\laravelrepository\drivers;

use pribolshoy\repository\drivers\AbstractCacheDriver;

/**
 * Базовый класс драйвера кеша для Laravel
 *
 * Предоставляет базовую функциональность для работы с контейнером приложения Laravel.
 * Дочерние классы должны реализовать методы get(), set() и delete().
 *
 * @package pribolshoy\laravelrepository\drivers
 */
abstract class BaseCacheDriver extends AbstractCacheDriver
{
    /**
     * Имя компонента в контейнере Laravel
     *
     * @var string
     */
    protected string $component = 'cache';

    /**
     * Кэшированный экземпляр контейнера приложения
     *
     * @var object|null
     */
    protected ?object $container = null;

    /**
     * Класс или callable для создания контейнера
     *
     * @var object|null
     */
    protected ?object $container_class = null;

    /**
     * Получить контейнер приложения Laravel.
     * Если container_class не задан, используется Laravel контейнер через app().
     *
     * @return object
     * @throws \Exception
     */
    protected function getContainer(): object
    {
        if (is_null($this->container)) {
            if (is_null($this->container_class)) {
                // Используем Laravel контейнер приложения
                if (function_exists('app')) {
                    $this->container = app();
                } else {
                    throw new \Exception('Laravel контейнер приложения недоступен');
                }
            } else {
                if (is_callable($this->container_class)) {
                    $this->container = call_user_func($this->container_class);
                } else {
                    $class = $this->container_class;
                    $this->container = new $class();
                }
            }
        }

        return $this->container;
    }

    /**
     * Получить компонент из контейнера.
     * В Laravel компоненты получаются через контейнер приложения.
     *
     * Метод может быть переопределен в дочерних классах для использования
     * фасадов Laravel напрямую (что является предпочтительным подходом).
     *
     * @return mixed
     */
    protected function getComponent()
    {
        $container = $this->getContainer();

        // Если это Laravel контейнер, получаем сервис по имени
        if (method_exists($container, 'make')) {
            return $container->make($this->component);
        }

        // Иначе используем старый подход через свойство
        return $container->{$this->component} ?? null;
    }

    /**
     * Получить значение из кеша.
     * Должен быть переопределен в дочерних классах для конкретной реализации.
     *
     * @param string $key
     * @param array $params
     * @return mixed
     */
    public function get(string $key, array $params = [])
    {
        // Должен быть переопределен в дочерних классах
        return null;
    }

    /**
     * Установить значение в кеш.
     * Должен быть переопределен в дочерних классах для конкретной реализации.
     *
     * @param string $key
     * @param mixed $value
     * @param int $cache_duration
     * @param array $params
     * @return object
     */
    public function set(string $key, $value, int $cache_duration = 0, array $params = []): object
    {
        // Должен быть переопределен в дочерних классах
        return $this;
    }

    /**
     * Удалить значение из кеша.
     * Должен быть переопределен в дочерних классах для конкретной реализации.
     *
     * @param string $key
     * @param array $params
     * @return object
     */
    public function delete(string $key, array $params = []): object
    {
        // Должен быть переопределен в дочерних классах
        return $this;
    }
}

