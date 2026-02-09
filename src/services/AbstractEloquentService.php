<?php

namespace pribolshoy\laravelrepository\services;

use pribolshoy\repository\services\AbstractCachebleService;

/**
 * Абстрактный класс для создания сервисов, работающих с Eloquent репозиториями
 *
 * Предоставляет базовую функциональность для работы с сущностями
 * через репозитории с использованием кеширования.
 *
 * Использует трейт EloquentServiceTrait для работы с Eloquent моделями.
 *
 * @package pribolshoy\laravelrepository\services
 */
abstract class AbstractEloquentService extends AbstractCachebleService
{
    use EloquentServiceTrait;
}

