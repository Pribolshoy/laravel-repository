<?php

namespace pribolshoy\laravelrepository\commands;

use pribolshoy\laravelrepository\jobs\ClearServicesCacheJob;

/**
 * Команда для очистки кеша сервисов
 *
 * Очищает кеш всех сервисов из ServiceComponent.
 *
 * @package pribolshoy\laravelrepository\commands
 */
class ClearServicesCache extends BaseServiceCacheCommand
{
    /**
     * Название и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'services:cache:clear 
                            {--service= : Имя конкретного сервиса для очистки} 
                            {--group= : Имя группы сервисов для очистки}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Очистка кеша сервисов из ServiceComponent';

    /**
     * Выполнение консольной команды
     *
     * Получает отфильтрованную конфигурацию сервисов и запускает
     * синхронное выполнение джобы для очистки кеша.
     * При отсутствии фильтров запрашивает подтверждение у пользователя.
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

        // Если не указан конкретный сервис или группа, запрашиваем подтверждение
        if (!$this->option('service') && !$this->option('group')) {
            if (!$this->confirm('Вы уверены, что хотите очистить кеш всех сервисов?')) {
                $this->info('Очистка кеша отменена.');
                return 0;
            }
        }

        $serviceName = $this->option('service');
        $groupName = $this->option('group');

        // Выполняем синхронно через джобу
        $this->info('Начинаем очистку кеша сервисов...');

        try {
            ClearServicesCacheJob::dispatchSync($serviceName, $groupName);
            $this->info('Очистка кеша завершена');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Ошибка при очистке кеша: ' . $e->getMessage());
            return 1;
        }
    }
}
