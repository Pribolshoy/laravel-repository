# Laravel Repository

Библиотека для реализации паттерна Repository в Laravel приложениях. Адаптация пакета `pribolshoy/yii2-repository` для работы с Laravel Eloquent.

## Установка

```bash
composer require pribolshoy/laravel-repository
```

## Требования

- PHP >= 8.1
- Laravel >= 10.0
- Пакет `pribolshoy/repository`

## Основные компоненты

### AbstractEloquentRepository

Абстрактный класс для создания репозиториев, работающих с Eloquent моделями.

Пример использования:

```php
<?php

namespace App\Repositories;

use pribolshoy\laravelrepository\AbstractEloquentRepository;
use App\Models\User;

class UserRepository extends AbstractEloquentRepository
{
    protected ?string $model_class = User::class;

    protected function defaultFilter()
    {
        $this->addFilterValueByParams('status', 'active');
        $this->addFilterValueByParams('limit', 25);
        $this->addFilterValueByParams('page', 1);
    }

    protected function addQueries()
    {
        if ($this->existsFilter('status')) {
            $this->model->where('status', $this->getFilter('status'));
        }

        if ($this->existsFilter('email')) {
            $this->model->where('email', 'like', '%' . $this->getFilter('email') . '%');
        }

        return $this;
    }
}
```

### AbstractEloquentService

Абстрактный класс для создания сервисов, работающих с репозиториями.

Пример использования:

```php
<?php

namespace App\Services;

use pribolshoy\laravelrepository\AbstractEloquentService;
use App\Repositories\UserRepository;

class UserService extends AbstractEloquentService
{
    protected ?string $repository_class = UserRepository::class;

    protected function init()
    {
        // Инициализация сервиса
    }
}
```

## Драйверы кеша

Библиотека поддерживает несколько драйверов кеша:

### RedisDriver

Использует Redis для кеширования данных.

```php
protected ?string $driver = 'redis';
protected ?string $driver_path = "\\pribolshoy\\laravelrepository\\drivers\\";
```

### FileDriver

Использует файловый кеш Laravel.

```php
protected ?string $driver = 'file';
protected ?string $driver_path = "\\pribolshoy\\laravelrepository\\drivers\\";
```

### MysqlDriver

Использует MySQL таблицу для кеширования.

```php
protected ?string $driver = 'mysql';
protected ?string $driver_path = "\\pribolshoy\\laravelrepository\\drivers\\";
```

## Использование

### Создание репозитория

```php
$repository = new UserRepository([
    'status' => 'active',
    'limit' => 10,
    'page' => 1
]);

$users = $repository->search();
```

### Работа с сервисом

```php
$service = new UserService();
$items = $service->getItems();
```

## Особенности

- Поддержка пагинации через Laravel Paginator
- Кеширование результатов запросов
- Гибкая система фильтрации
- Поддержка сортировки
- Работа с Eloquent моделями

## Лицензия

BSD-3-Clause

## Автор

Nikolay Pribolshoy <pribolshoy@gmail.com>

