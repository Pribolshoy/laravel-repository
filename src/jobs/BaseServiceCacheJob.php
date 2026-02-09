<?php

namespace pribolshoy\laravelrepository\jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use pribolshoy\laravelrepository\ServiceComponent;

/**
 * Базовый класс для джоб работы с кешем сервисов
 *
 * @package pribolshoy\laravelrepository\jobs
 */
abstract class BaseServiceCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string|null $serviceName Имя конкретного сервиса
     * @param string|null $groupName Имя группы сервисов
     */
    public function __construct(
        protected ?string $serviceName = null,
        protected ?string $groupName = null
    ) {
    }

    /**
     * Выполнение джобы
     */
    public function handle(): void
    {
        /** @var ServiceComponent $serviceComponent */
        $serviceComponent = app(ServiceComponent::class);
        $config = $serviceComponent->getConfig();

        $config = $this->filterConfig($config);

        if (empty($config)) {
            return;
        }

        foreach ($config as $name => $serviceConfig) {
            try {
                $service = $serviceComponent->getObject($name);

                if ($service) {
                    $this->processService($service, $name);
                }
            } catch (\Throwable $e) {
                $this->logError($name, $e);
            }
        }
    }

    /**
     * Обработка конкретного сервиса
     * Должен быть реализован в дочерних классах
     *
     * @param object $service Экземпляр сервиса
     * @param string $serviceName Имя сервиса
     * @return void
     */
    abstract protected function processService(object $service, string $serviceName): void;

    /**
     * Фильтрация конфига по сервису и/или группе
     *
     * @param array $config Конфигурация сервисов
     * @return array Отфильтрованная конфигурация
     */
    protected function filterConfig(array $config): array
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

    /**
     * Прогрев кеша сервиса
     *
     * @param object $service
     * @return void
     */
    protected function warmServiceCache(object $service): void
    {
        // Используем initStorageSync если доступен (для сервисов с QueueServiceTrait)
        // Иначе используем обычный initStorage
        if (method_exists($service, 'initStorageSync')) {
            $service->initStorageSync();
        } else {
            $service->initStorage();
        }
    }

    /**
     * Логирование ошибки
     *
     * @param string $serviceName Имя сервиса
     * @param \Throwable $exception Исключение
     * @return void
     */
    protected function logError(string $serviceName, \Throwable $exception): void
    {
        Log::error("Ошибка при обработке кеша сервиса '{$serviceName}'", [
            'service' => $serviceName,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

