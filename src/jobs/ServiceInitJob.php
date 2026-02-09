<?php

namespace pribolshoy\laravelrepository\jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Джоба для выполнения инициализации сервиса в очереди
 *
 * Позволяет выполнять методы инициализации сервисов асинхронно через очередь
 * вместо синхронного выполнения.
 *
 * @package pribolshoy\laravelrepository\jobs
 */
class ServiceInitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Максимальное количество попыток выполнения джобы
     *
     * @var int
     */
    public int $tries = 5;

    /**
     * Таймаут выполнения джобы в секундах (10 минут)
     *
     * @var int
     */
    public int $timeout = 600;

    /**
     * @param string $serviceClass Класс сервиса
     * @param string $method Метод для выполнения
     * @param array $parameters Параметры для передачи в метод
     */
    public function __construct(
        protected string $serviceClass,
        protected string $method,
        protected array $parameters = []
    ) {
    }

    /**
     * Выполнение джобы
     */
    public function handle(): void
    {
        try {
            $service = app($this->serviceClass);

            if (!method_exists($service, $this->method)) {
                throw new \BadMethodCallException(
                    "Method {$this->method} does not exist in {$this->serviceClass}"
                );
            }

            // Вызываем метод сервиса с переданными параметрами
            call_user_func_array([$service, $this->method], $this->parameters);
        } catch (\Throwable $exception) {
            $this->logError($exception);

            throw $exception;
        } finally {
            $this->afterHandle();
        }
    }

    /**
     * Логирование ошибки выполнения инициализации
     *
     * Можно переопределить в дочерних классах для кастомной логики логирования
     *
     * @param \Throwable $exception Исключение, которое произошло
     * @return void
     */
    protected function logError(\Throwable $exception): void
    {

        Log::error('Ошибка выполнения инициализации сервиса в очереди', [
            'service_class' => $this->serviceClass,
            'method'        => $this->method,
            'parameters'    => $this->parameters,
            'error'         => $exception->getMessage(),
            'trace'         => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Метод, вызываемый после выполнения handle()
     *
     * Может быть переопределен в дочерних классах для выполнения
     * дополнительных действий после успешного выполнения инициализации.
     * Например, логирование времени выполнения или очистка ресурсов.
     *
     * @return void
     */
    protected function afterHandle(): void
    {
    }
}
