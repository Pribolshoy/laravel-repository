<?php

namespace pribolshoy\laravelrepository;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Базовый провайдер для регистрации Infrastructure сервисов
 * 
 * Регистрирует сервисы из конфигурации через ServiceComponent.
 * 
 * @package pribolshoy\laravelrepository
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Имя конфигурационного файла
     *
     * @var string
     */
    protected string $configName = 'infrastructure_services';

    /**
     * Алиас для ServiceComponent
     *
     * @var string
     */
    protected string $serviceAlias = 'services';

    /**
     * Регистрация сервисов приложения
     *
     * Регистрирует ServiceComponent как singleton и все сервисы из конфигурации
     * для возможности прямого доступа через app() или dependency injection.
     * Сервисы регистрируются как singleton, получая объект из ServiceComponent.
     */
    public function register(): void
    {
        // Регистрируем ServiceComponent как singleton
        // ServiceComponent сам управляет жизненным циклом сервисов
        $this->app->singleton(ServiceComponent::class, function ($app) {
            $config = config($this->configName, []);
            return new ServiceComponent($config);
        });

        // Регистрируем алиас для удобного доступа
        $this->app->alias(ServiceComponent::class, $this->serviceAlias);

        // Регистрируем сервисы для возможности прямого доступа через app()
        // Используем замыкание, чтобы получить объект из ServiceComponent
        $classlist = config($this->configName . '.classlist', []);

        foreach ($classlist as $name => $config) {
            $class = is_string($config) ? $config : ($config['class'] ?? null);

            if ($class && class_exists($class)) {
                // Регистрируем как singleton, получая объект из ServiceComponent
                $this->app->singleton($class, function ($app) use ($name, $class) {
                    /** @var ServiceComponent $services */
                    $services = $app->make(ServiceComponent::class);
                    $object = $services->getObject($name);
                    
                    if (!$object) {
                        throw new \RuntimeException("Сервис '{$name}' не найден в ServiceComponent");
                    }
                    
                    return $object;
                });
            }
        }
    }

    /**
     * Загрузка сервисов приложения
     *
     * Публикует конфигурационный файл для возможности его изменения в проекте.
     * Для публикации выполнить: php artisan vendor:publish --tag=laravel-repository-config
     * Регистрирует консольные команды для управления кешем сервисов:
     * - services:cache:warm - прогрев кеша
     * - services:cache:clear - очистка кеша
     * - services:cache:refresh - очистка и прогрев кеша
     */
    public function boot(): void
    {
        // Публикуем конфигурационный файл (опционально)
        // Выполнить: php artisan vendor:publish --tag=laravel-repository-config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/infrastructure_services.php' => config_path($this->configName . '.php'),
            ], 'laravel-repository-config');

            // Регистрируем консольные команды
            $this->commands([
                \pribolshoy\laravelrepository\commands\WarmServicesCache::class,
                \pribolshoy\laravelrepository\commands\ClearServicesCache::class,
                \pribolshoy\laravelrepository\commands\RefreshServicesCache::class,
            ]);
        }
    }
}

