<?php

namespace pribolshoy\laravelrepository\jobs;

/**
 * Джоба для очистки кеша сервисов
 *
 * Очищает кеш сервисов из ServiceComponent.
 * Может быть выполнена синхронно или через очередь.
 *
 * @package pribolshoy\laravelrepository\jobs
 */
class ClearServicesCacheJob extends BaseServiceCacheJob
{
    /**
     * Обработка конкретного сервиса
     *
     * @param object $service Экземпляр сервиса
     * @param string $serviceName Имя сервиса
     * @return void
     */
    protected function processService(object $service, string $serviceName): void
    {
        $service->clearStorage();
    }
}

