<?php

namespace pribolshoy\laravelrepository\commands;

use pribolshoy\laravelrepository\jobs\RefreshServicesCacheJob;

/**
 * Команда для очистки и прогрева кеша сервисов
 *
 * Сначала очищает кеш всех сервисов из ServiceComponent,
 * затем прогревает его, загружая данные заново.
 *
 * @package pribolshoy\laravelrepository\commands
 */
class RefreshServicesCache extends BaseServiceCacheCommand
{
    /**
     * Название и сигнатура консольной команды
     *
     * @var string
     */
    protected $signature = 'services:cache:refresh 
                            {--service= : Имя конкретного сервиса для обновления} 
                            {--group= : Имя группы сервисов для обновления}';

    /**
     * Описание консольной команды
     *
     * @var string
     */
    protected $description = 'Очистка и прогрев кеша сервисов из ServiceComponent';

    /**
     * Выполнение консольной команды
     *
     * Получает отфильтрованную конфигурацию сервисов и запускает
     * синхронное выполнение джобы для очистки и прогрева кеша.
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
            if (!$this->confirm('Вы уверены, что хотите очистить и прогреть кеш всех сервисов?')) {
                $this->info('Обновление кеша отменено.');
                return 0;
            }
        }

        $serviceName = $this->option('service');
        $groupName = $this->option('group');

        // Выполняем синхронно через джобу
        $this->info('Начинаем очистку и прогрев кеша сервисов...');

        try {
            RefreshServicesCacheJob::dispatchSync($serviceName, $groupName);
            $this->info('Обновление кеша завершено');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Ошибка при обновлении кеша: ' . $e->getMessage());
            return 1;
        }
    }
}
