<?php

namespace pribolshoy\laravelrepository\jobs;

/**
 * Джоба для прогрева кеша сервисов
 *
 * Прогревает кеш сервисов из ServiceComponent.
 * Может быть выполнена синхронно или через очередь.
 *
 * @package pribolshoy\laravelrepository\jobs
 */
class WarmServicesCacheJob extends BaseServiceCacheJob
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
        $this->warmServiceCache($service);
    }
}

