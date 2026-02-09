<?php

namespace pribolshoy\laravelrepository\commands;

use Illuminate\Console\Command;
use pribolshoy\laravelrepository\ServiceComponent;

/**
 * Базовый класс для команд управления кешем сервисов
 *
 * Содержит общую логику фильтрации и работы с сервисами.
 *
 * @package pribolshoy\laravelrepository\commands
 */
abstract class BaseServiceCacheCommand extends Command
{
    /**
     * Выполнение команды
     *
     * Должен быть реализован в дочерних классах для выполнения
     * конкретной операции с кешем сервисов (прогрев, очистка, обновление).
     *
     * @return int Код возврата: 0 - успех, 1 - ошибка
     */
    abstract public function handle(): int;

    /**
     * Получение конфигурации сервисов с фильтрацией
     *
     * @return array
     */
    protected function getFilteredConfig(): array
    {
        /** @var ServiceComponent $serviceComponent */
        $serviceComponent = app(ServiceComponent::class);
        $config = $serviceComponent->getConfig();

        $serviceName = $this->option('service');
        $groupName = $this->option('group');

        return $this->filterConfig($config, $serviceName, $groupName);
    }

    /**
     * Фильтрация конфига по сервису и/или группе
     *
     * @param array $config Конфигурация сервисов
     * @param string|null $serviceName Имя сервиса
     * @param string|null $groupName Имя группы
     * @return array Отфильтрованная конфигурация
     */
    protected function filterConfig(array $config, ?string $serviceName, ?string $groupName): array
    {
        // Если указан конкретный сервис
        if ($serviceName) {
            if (!isset($config[$serviceName])) {
                $this->error("Сервис '{$serviceName}' не найден в ServiceComponent");
                return [];
            }

            $serviceConfig = $config[$serviceName];

            // Если также указана группа, проверяем соответствие
            if ($groupName && ($serviceConfig['group'] ?? null) !== $groupName) {
                $this->error("Сервис '{$serviceName}' не принадлежит группе '{$groupName}'");
                return [];
            }

            return [$serviceName => $serviceConfig];
        }

        // Если указана только группа
        if ($groupName) {
            $filtered = [];
            foreach ($config as $name => $serviceConfig) {
                if (($serviceConfig['group'] ?? null) === $groupName) {
                    $filtered[$name] = $serviceConfig;
                }
            }

            if (empty($filtered)) {
                $this->error("Группа '{$groupName}' не найдена или не содержит сервисов");
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
     * Получение сервиса из ServiceComponent
     *
     * @param ServiceComponent $serviceComponent
     * @param string $name Имя сервиса
     * @return object|null
     */
    protected function getService(ServiceComponent $serviceComponent, string $name): ?object
    {
        return $serviceComponent->getObject($name);
    }
}

