<?php

namespace pribolshoy\laravelrepository\services\traits;

use pribolshoy\repository\interfaces\CachebleServiceInterface;
use pribolshoy\repository\interfaces\RepositoryInterface;

/**
 * Трейт для выполнения инициализации сервисов через очередь
 *
 * Встраивается в сервисы и меняет поведение методов инициализации.
 * Главная задача трейта - дать возможность передавать выполнение
 * инициализации сервиса в очередь вместо синхронного выполнения.
 *
 * Пример использования:
 * ```php
 * use pribolshoy\laravelrepository\services\traits\QueueServiceTrait;
 *
 * class MyService extends CachebleService
 * {
 *     use QueueServiceTrait;
 *
 *     // Оригинальный метод initStorage переименовываем в initStorageSync
 *     protected function initStorageSync(?RepositoryInterface $repository = null, bool $refresh = false)
 *     {
 *         // Ваша логика инициализации
 *         return $this;
 *     }
 *
 *     // Можно отключить очередь для конкретного сервиса
 *     protected function shouldUseQueueForInit(): bool
 *     {
 *         return false;
 *     }
 *
 *     // Можно использовать кастомную джобу
 *     protected function getInitJobClass(): string
 *     {
 *         return 'App\Jobs\Custom\CustomServiceInitJob';
 *     }
 * }
 *
 * // Использование:
 * $service = app(MyService::class);
 * $service->initStorage(); // Отправится в очередь автоматически
 * ```
 *
 * @package pribolshoy\laravelrepository\services\traits
 */
trait QueueServiceTrait
{
    /**
     * Флаг, указывающий использовать ли очередь для инициализации
     * Можно переопределить в классе сервиса
     */
    protected ?bool $useQueueForInit = null;

    /**
     * Класс джобы для выполнения инициализации
     * Можно переопределить в классе сервиса для использования кастомной джобы
     */
    protected ?string $initJobClass = null;

    /**
     * Получить значение флага использования очереди
     * По умолчанию возвращает true, но можно переопределить в классе
     *
     * @return bool
     */
    protected function shouldUseQueueForInit(): bool
    {
        return $this->useQueueForInit ?? true;
    }

    /**
     * Получить класс джобы для инициализации
     * По умолчанию возвращает стандартную джобу из пакета, но можно переопределить в классе
     *
     * @return string
     */
    protected function getInitJobClass(): string
    {
        // Проверяем, существует ли джоба в проекте (с поддержкой батчинга)
        // TODO: убрать в будущем
        $projectJobClass = 'App\Jobs\Service\ServiceInitJob';
        if (class_exists($projectJobClass)) {
            return $this->initJobClass ?? $projectJobClass;
        }

        // Иначе используем базовую джобу из пакета
        // Используем строку вместо ::class, чтобы избежать загрузки класса при парсинге
        return $this->initJobClass ?? 'pribolshoy\laravelrepository\jobs\ServiceInitJob';
    }

    /**
     * Инициализация хранилища через очередь
     *
     * Вместо синхронного выполнения отправляет задачу в очередь.
     * Оригинальный метод должен быть переименован в initStorageSync()
     * или реализован в родительском классе.
     *
     * @param RepositoryInterface|null $repository Репозиторий для инициализации
     * @param bool $refresh_repository_cache Обновить ли кеш репозитория
     * @return CachebleServiceInterface
     */
    public function initStorage(?RepositoryInterface $repository = null, bool $refresh_repository_cache = false): CachebleServiceInterface
    {
        if (!$this->shouldUseQueueForInit()) {
            return $this->initStorageSync($repository, $refresh_repository_cache);
        }

        $this->dispatchInitJob('initStorageSync', [$repository, $refresh_repository_cache]);

        return $this;
    }

    /**
     * Инициализация кеша сущности через очередь
     *
     * @param mixed $primaryKey Первичный ключ сущности
     * @param mixed ...$parameters Дополнительные параметры
     * @return bool
     */
    public function initEntityCache($primaryKey, ...$parameters): bool
    {
        if (!$this->shouldUseQueueForInit()) {
            return $this->initEntityCacheSync($primaryKey, ...$parameters);
        }

        return $this->dispatchInitJob('initEntityCacheSync', [$primaryKey, ...$parameters]);
    }

    /**
     * Инициализация всего кеша через очередь
     *
     * @param mixed ...$parameters Параметры для передачи в оригинальный метод
     * @return static
     */
    public function initAllCache(...$parameters): static
    {
        if (!$this->shouldUseQueueForInit()) {
            return $this->initAllCacheSync(...$parameters);
        }

        return $this->dispatchInitJob('initAllCacheSync', $parameters);
    }

    /**
     * Отправка задачи инициализации в очередь
     *
     * @param string $method Название метода для выполнения
     * @param array $parameters Параметры для передачи в метод
     * @return CachebleServiceInterface|bool|static
     */
    protected function dispatchInitJob(string $method, array $parameters)
    {
        // Проверяем, нужно ли отправлять в очередь
        if (!$this->shouldQueueInit($method, $parameters)) {
            // Если не нужно, выполняем синхронно
            return $this->callSyncMethod($method, $parameters);
        }

        $jobClass = $this->getInitJobClass();
        $jobClass::dispatch(
            static::class,
            $method,
            $parameters
        );

        // Возвращаем значение по умолчанию в зависимости от типа метода
        if (str_contains($method, 'Cache')) {
            return true;
        }

        return $this;
    }

    /**
     * Проверка, нужно ли отправлять инициализацию в очередь
     *
     * Можно переопределить в классе сервиса для кастомной логики
     *
     * @param string $method Название метода
     * @param array $parameters Параметры
     * @return bool
     */
    protected function shouldQueueInit(string $method, array $parameters): bool
    {
        return $this->shouldUseQueueForInit();
    }

    /**
     * Вызов синхронного метода
     *
     * @param string $method Название метода
     * @param array $parameters Параметры
     * @return mixed
     */
    protected function callSyncMethod(string $method, array $parameters)
    {
        return call_user_func_array([$this, $method . 'Sync'], $parameters);
    }

    /**
     * Синхронная инициализация хранилища
     *
     * Этот метод должен быть реализован в классе сервиса
     * или вызывать родительский метод initStorage()
     *
     * @param RepositoryInterface|null $repository Репозиторий для инициализации
     * @param bool $refresh_repository_cache Обновить ли кеш репозитория
     * @return CachebleServiceInterface
     */
    public function initStorageSync(?RepositoryInterface $repository = null, bool $refresh_repository_cache = false): CachebleServiceInterface
    {
        // Если метод не переопределен, вызываем родительский (если есть)
        if (method_exists(parent::class, 'initStorage')) {
            return parent::initStorage($repository, $refresh_repository_cache);
        }

        return $this;
    }

    /**
     * Синхронная инициализация кеша сущности
     *
     * @param mixed $primaryKey
     * @param mixed ...$parameters
     * @return bool
     */
    public function initEntityCacheSync($primaryKey, ...$parameters): bool
    {
        // Если метод не переопределен, вызываем родительский (если есть)
        if (method_exists(parent::class, 'initEntityCache')) {
            return parent::initEntityCache($primaryKey, ...$parameters);
        }

        return false;
    }

    /**
     * Синхронная инициализация всего кеша
     *
     * @param mixed ...$parameters
     * @return static
     */
    public function initAllCacheSync(...$parameters): static
    {
        // Если метод не переопределен, вызываем родительский (если есть)
        if (method_exists(parent::class, 'initAllCache')) {
            return parent::initAllCache(...$parameters);
        }

        return $this;
    }
}

