<?php

namespace pribolshoy\laravelrepository\jobs;

use Illuminate\Support\Facades\Log;
use pribolshoy\laravelrepository\ServiceComponent;

/**
 * Джоба для очистки и прогрева кеша сервисов
 *
 * Сначала очищает кеш сервисов из ServiceComponent,
 * затем прогревает его, загружая данные заново.
 * Может быть выполнена синхронно или через очередь.
 *
 * @package pribolshoy\laravelrepository\jobs
 */
class RefreshServicesCacheJob extends BaseServiceCacheJob
{
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

        // Сначала очищаем кеш
        foreach ($config as $name => $serviceConfig) {
            try {
                $service = $serviceComponent->getObject($name);

                if ($service) {
                    $service->clearStorage();
                }
            } catch (\Throwable $e) {
                $this->logError($name, $e);
            }
        }

        // Затем прогреваем кеш
        foreach ($config as $name => $serviceConfig) {
            try {
                $service = $serviceComponent->getObject($name);

                if ($service) {
                    $this->warmServiceCache($service);
                }
            } catch (\Throwable $e) {
                $this->logError($name, $e);
            }
        }
    }

    /**
     * Обработка конкретного сервиса
     *
     * Не используется в этой джобе, так как handle() переопределен.
     * Метод оставлен для соответствия абстрактному методу базового класса.
     *
     * @param object $service Экземпляр сервиса
     * @param string $serviceName Имя сервиса
     * @return void
     */
    protected function processService(object $service, string $serviceName): void
    {
        // Не используется, так как handle() переопределен
    }
}

