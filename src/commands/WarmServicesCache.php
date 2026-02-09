<?php

namespace pribolshoy\laravelrepository\commands;

use pribolshoy\laravelrepository\jobs\WarmServicesCacheJob;

/**
 * Команда для прогрева кеша сервисов
 *
 * Прогревает кеш всех сервисов из ServiceComponent,
 * загружая данные и сохраняя их в кеш.
 *
 * @package pribolshoy\laravelrepository\commands
 */
class WarmServicesCache extends BaseServiceCacheCommand
{
    /**
     * Название и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'services:cache:warm 
                            {--service= : Имя конкретного сервиса для прогрева} 
                            {--group= : Имя группы сервисов для прогрева}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Прогрев кеша сервисов из ServiceComponent';

    /**
     * Выполнение консольной команды
     *
     * Получает отфильтрованную конфигурацию сервисов и запускает
     * синхронное выполнение джобы для прогрева кеша.
     *
     * @return int Код возврата: 0 - успех, 1 - ошибка
     */
    public function handle(): int
    {
        $config = $this->getFilteredConfig();

        if (empty($config)) {
            $this->error('Не найдено сервисов по указанным критериям');
            return 1;
        }

        $serviceName = $this->option('service');
        $groupName = $this->option('group');

        // Выполняем синхронно через джобу
        $this->info('Начинаем прогрев кеша сервисов...');

        try {
            WarmServicesCacheJob::dispatchSync($serviceName, $groupName);
            $this->info('Прогрев кеша завершен');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Ошибка при прогреве кеша: ' . $e->getMessage());
            return 1;
        }
    }
}
