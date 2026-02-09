<?php

namespace pribolshoy\laravelrepository\drivers;

use pribolshoy\repository\Config;
use pribolshoy\repository\Logger;
use Illuminate\Support\Facades\Redis;

/**
 * Драйвер кеша для работы с Redis
 *
 * Предоставляет функциональность для работы с Redis через Laravel фасады.
 * Поддерживает различные стратегии работы с кешем: строковый кеш и хэш-таблицы.
 *
 * Redis имеет множество способов получения информации, поэтому используются стратегии
 * для выбора конкретного способа. Класс также имеет автоматическое определение
 * правильного способа на основе формата ключа и параметров.
 *
 * Стратегии задаются через параметр 'strategy' в cache_params['get'] для метода get()
 * и cache_params['set'] для метода set().
 *
 * Упрощенные стратегии (рекомендуемые):
 * - 'string' - Использование строкового кеша (getValue, setex, del)
 * - 'hash' или 'table' - Использование хэш-таблицы (getHValue/getHValues/getAllHash, hset, hdel)
 *   Для get() с 'hash'/'table': автоматически выбирается getHValue, getHValues или getAllHash
 *   на основе формата ключа и параметра fields
 *
 * Устаревшие стратегии (для обратной совместимости):
 * - getValue - Получить значение ключа (аналог get($key))
 * - getHValue - Получить значение поля хэша (аналог hget($key, $field))
 * - getHValues - Получить значения всех указанных полей хэша (аналог hmget($key, ...$fields))
 * - getAllHash - Получить все значения из хэша (аналог hvals($key))
 * - hset - Установить поле хэша (аналог hset($key, $field, $value))
 * - setex - Установить ключ с временем жизни (аналог setex($key, $ttl, $value))
 * - hdel - Удалить поле хэша (аналог hdel($key, $field))
 * - del - Удалить ключ (аналог del($key))
 *
 * @package pribolshoy\laravelrepository\drivers
 */
class RedisDriver extends BaseCacheDriver
{
    /**
     * Имя компонента Redis в Laravel приложении.
     *
     * @var string
     */
    protected string $component = 'redis';

    /**
     * Максимальное количество полей для операции hmget за один запрос.
     *
     * @var int
     */
    protected int $maxHMgetLimit = 50;

    /**
     * Определяет поствикс (делимитер) для ключа кеша на основе параметров стратегии.
     * Анализирует cache_params['get'] и возвращает соответствующий делимитер.
     *
     * Simplified strategies (recommended):
     * 'string' - Use string cache (getValue, setex, del).
     * 'hash' or 'table' - Use hash table (getHValue/getHValues/getAllHash, hset, hdel).
     *
     * Legacy strategies (for backward compatibility):
     * getAllHash, getHValue, getHValues, hset, hdel - hash table
     * getValue, setex, del - string cache
     *
     * @param array $cacheParamsGet Параметры из cache_params['get']
     * @return string Делимитер для использования в ключе кеша
     */
    public static function getIdPostfixByStrategy(array $cacheParamsGet = []): string
    {
        $strategy = $cacheParamsGet['strategy'] ?? 'getValue';

        // Новые упрощенные стратегии
        if ($strategy === 'string') {
            return Config::getStringDelimiter();
        } else if ($strategy === 'hash' || $strategy === 'table') {
            return Config::getHashDelimiter();
        }

        // Обратная совместимость: старые стратегии для хэш-таблицы
        $hashStrategies = ['getAllHash', 'getHValue', 'getHValues', 'hset', 'hdel'];

        if (in_array($strategy, $hashStrategies)) {
            return Config::getHashDelimiter();
        }

        // Стратегии для строкового кеша
        return Config::getStringDelimiter();
    }

    /**
     * Получить данные из кеша
     *
     * Параметры должны приходить из cache_params['get'] сервиса.
     * Выбирает стратегию получения данных на основе параметра 'strategy'.
     *
     * Упрощенные стратегии (рекомендуемые):
     * - 'string' - Использование строкового кеша (getValue)
     * - 'hash' или 'table' - Использование хэш-таблицы (getHValue/getHValues/getAllHash)
     *   Для 'hash'/'table': автоматически выбирается getHValue, getHValues или getAllHash
     *   на основе формата ключа и параметра fields
     *
     * Устаревшие стратегии (для обратной совместимости):
     * - getValue, getHValue, getHValues, getAllHash
     *
     * @param string $key Ключ кеша
     * @param array $params Параметры из cache_params['get']
     * @return array|mixed Десериализованные данные из кеша или пустой массив
     * @throws \RuntimeException Если указанная стратегия не существует
     */
    public function get(string $key, array $params = [])
    {
        $strategy = $params['strategy'] ?? null;
        $fields = $params['fields'] ?? [];

        // Упрощенная логика: если strategy = 'string' или 'hash'/'table', определяем метод автоматически
        if ($strategy === 'string') {
            $strategy = 'getValue';
        } elseif ($strategy === 'hash' || $strategy === 'table') {
            // Автоматический выбор метода для хэш-таблицы
            if ($fields) {
                $strategy = 'getHValues';
            } else {
                $delimiter = Config::getHashDelimiter();
                $delimiterEscaped = preg_quote($delimiter, '#');
                if (preg_match('#' . $delimiterEscaped . '#i', $key)) {
                    $strategy = 'getHValue';
                } else {
                    $strategy = 'getAllHash';
                }
            }
        } else {
            // Обратная совместимость: старые стратегии
            if ($strategy == 'getAllHash') {
                $strategy = 'getAllHash';
            } elseif ($fields && $strategy !== 'getHValues') {
                $strategy = 'getHValues';
            } else {
                $delimiter = Config::getHashDelimiter();
                $delimiterEscaped = preg_quote($delimiter, '#');
                if (preg_match('#' . $delimiterEscaped . '#i', $key)
                    && $strategy !== 'getHValue'
                ) {
                    $strategy = 'getHValue';
                } else {
                    $strategy = $strategy ?? 'getValue';
                }
            }
        }

        $strategy = $params['force_strategy'] ?? $strategy;

        if (!method_exists($this, $strategy)) {
            throw new \RuntimeException("Метод $strategy не существует в " . __CLASS__);
        }

        $result = $this->{$strategy}($key, $params) ?? [];
        Logger::log('get', $key, 'cache', $result);

        return $result;
    }

    /**
     * Получить все значения из хэш-таблицы.
     * Использует команду Redis HVALS для получения всех значений по ключу.
     *
     * @param string $key Ключ хэш-таблицы (без вложенностей через делимитер)
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return array Массив десериализованных значений из хэш-таблицы
     */
    protected function getAllHash(string $key, array $params = [])
    {
        if ($items = Redis::hvals($key)) {
            foreach ($items as $item) {
                $data[] = $this->unserialize($item);
            }
        }

        $result = $data ?? [];
        Logger::log('getAllHash', $key, 'cache', $result);

        return $result;
    }

    /**
     * Получить значение ключа (строковый кеш).
     * Использует команду Redis GET для получения значения по ключу.
     *
     * @param string $key Ключ кеша (может быть простым ключом или с полем через делимитер)
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return array|mixed Десериализованное значение или пустой массив, если ключ не найден
     */
    protected function getValue(string $key, array $params = [])
    {
        $data = Redis::get($key);
        $result = $data ? $this->unserialize($data) : [];
        Logger::log('getValue', $key, 'cache', $result);

        return $result;
    }

    /**
     * Получить значение поля из хэш-таблицы.
     * Использует команду Redis HGET для получения значения поля по ключу хэша и имени поля.
     * Делимитер отделяет только ИД (последняя часть ключа), остальное - ключ хэша.
     *
     * @param string $key Полный ключ в формате "hashKey{delimiter}fieldId"
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return array|mixed Десериализованное значение поля или пустой массив, если поле не найдено
     */
    protected function getHValue(string $key, array $params = [])
    {
        $originalKey = $key;
        $delimiter = Config::getHashDelimiter();
        $delimiterEscaped = preg_quote($delimiter, '#');

        // Делимитер отделяет только ИД (последняя часть), остальное - ключ хеша
        if (preg_match('#' . $delimiterEscaped . '(.*)$#', $key, $matches)) {
            $field = $matches[1];
            $key = substr($key, 0, -strlen($delimiter . $field));
        } else {
            // Если делимитер не найден, используем стандартный разбор по последнему ':'
            $key_parts = explode(':', $key);
            $field = array_pop($key_parts);
            $key = implode(':', $key_parts);
        }

        $data = Redis::hget($key, $field);
        $result = $data ? $this->unserialize($data): [];
        Logger::log('getHValue', $originalKey, 'cache', $result);

        return $result;
    }

    /**
     * Получить значения нескольких полей из хэш-таблицы.
     * Использует команду Redis HMGET для получения значений нескольких полей за один запрос.
     * Если полей больше maxHMgetLimit, запросы разбиваются на части.
     *
     * @param string $key Ключ хэш-таблицы
     * @param array $params Параметры, должны содержать ключ 'fields' с массивом имен полей
     *
     * @return array Массив десериализованных значений полей
     */
    protected function getHValues(string $key, array $params = [])
    {
        if ($fields = $params['fields'] ?? []) {
            $fieldsChunks = array_chunk($fields, $this->maxHMgetLimit);

            foreach ($fieldsChunks as $fieldsChunk) {
                if ($items = Redis::hmget($key, $fieldsChunk)) {
                    $items = array_filter($items);
                    foreach ($items as $item) {
                        $data[] = $this->unserialize($item);
                    }
                }
            }
        }

        $result = $data ?? [];
        Logger::log('getHValues', $key, 'cache', $result);

        return $result;
    }

    /**
     * Установить данные в кеш
     *
     * Параметры должны приходить из cache_params['set'] сервиса.
     * Выбирает стратегию сохранения данных на основе параметра 'strategy'.
     *
     * Упрощенные стратегии (рекомендуемые):
     * - 'string' - Использование строкового кеша (setex)
     * - 'hash' или 'table' - Использование хэш-таблицы (hset)
     *
     * Устаревшие стратегии (для обратной совместимости):
     * - hset, setex
     *
     * @param string $key Ключ кеша
     * @param mixed $value Значение для сохранения (будет сериализовано и сжато)
     * @param int $cache_duration Время жизни кеша в секундах
     * @param array $params Параметры из cache_params['set']
     * @return object Возвращает $this для цепочки вызовов
     * @throws \RuntimeException Если указанная стратегия не существует
     */
    public function set(string $key, $value, int $cache_duration = 0, array $params = []): object
    {
        $strategy = $params['strategy'] ?? null;

        // Упрощенная логика: если strategy = 'string' или 'hash'/'table', выбираем метод
        if ($strategy === 'string') {
            $strategy = 'setex';
        } else if ($strategy === 'hash' || $strategy === 'table') {
            $strategy = 'hset';
        } else {
            // Обратная совместимость: по умолчанию хеш таблица
            $strategy = $strategy ?? 'hset';
        }

        if (!method_exists($this, $strategy)) {
            throw new \RuntimeException("Метод $strategy не существует в " . __CLASS__);
        }

        $result = $this->{$strategy}($key, $value, $cache_duration, $params);
        Logger::log('set', $key, 'cache');

        return $result;
    }

    /**
     * Установить значение ключа с временем жизни (строковый кеш).
     * Использует команду Redis SETEX для установки значения с TTL.
     *
     * @param string $key Ключ кеша
     * @param mixed $value Значение для сохранения (будет сериализовано и сжато)
     * @param int $cache_duration Время жизни кеша в секундах
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return object Возвращает $this для цепочки вызовов
     */
    protected function setex(string $key, $value, int $cache_duration = 0, array $params = []): object
    {
        Redis::setex($key, $cache_duration, $this->serialize($value));
        Logger::log('setex', $key, 'cache');

        return $this;
    }

    /**
     * Установить значение поля в хэш-таблице.
     * Использует команду Redis HSET для установки значения поля.
     * Делимитер отделяет только ИД (последняя часть ключа), остальное - ключ хэша.
     * Если делимитер не найден, используется стратегия setex.
     *
     * @param string $key Полный ключ в формате "hashKey{delimiter}fieldId"
     * @param mixed $value Значение для сохранения (будет сериализовано и сжато)
     * @param int $cache_duration Время жизни кеша в секундах (устанавливается для всего хэша)
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return object Возвращает $this для цепочки вызовов
     */
    protected function hset(string $key, $value, int $cache_duration = 0, array $params = []): object
    {
        $originalKey = $key;
        $delimiter = Config::getHashDelimiter();
        $delimiterEscaped = preg_quote($delimiter, '#');

        // Делимитер отделяет только ИД (последняя часть), остальное - ключ хеша
        if (preg_match('#' . $delimiterEscaped . '(.*)$#', $key, $matches)) {
            $field = $matches[1];
            $hashKey = substr($key, 0, -strlen($delimiter . $field));

            Redis::hset($hashKey, $field, $this->serialize($value));
            if ($cache_duration > 0) {
                Redis::expire($hashKey, $cache_duration);
            }
        } else {
            $this->setex($key, $value, $cache_duration, $params);
        }

        Logger::log('hset', $originalKey, 'cache');

        return $this;
    }

    /**
     * Удалить ключ или поле из кеша
     *
     * Выбирает стратегию удаления на основе параметра 'strategy'.
     *
     * Упрощенные стратегии (рекомендуемые):
     * - 'string' - Использование строкового кеша (del)
     * - 'hash' или 'table' - Использование хэш-таблицы (hdel)
     *
     * Устаревшие стратегии (для обратной совместимости):
     * - hdel, del
     *
     * @param string $key Ключ для удаления
     * @param array $params Параметры, могут содержать 'strategy' для выбора метода удаления
     * @return object Возвращает $this для цепочки вызовов
     * @throws \RuntimeException Если указанная стратегия не существует
     */
    public function delete(string $key, array $params = []): object
    {
        $strategy = $params['strategy'] ?? null;

        // Упрощенная логика: если strategy = 'string' или 'hash'/'table', выбираем метод
        if ($strategy === 'string') {
            $strategy = 'del';
        } else if ($strategy === 'hash' || $strategy === 'table') {
            $strategy = 'hdel';
        } else {
            // Обратная совместимость: по умолчанию хеш таблица
            $strategy = $strategy ?? 'hdel';
        }

        if (!method_exists($this, $strategy)) {
            throw new \RuntimeException("Метод $strategy не существует в " . __CLASS__);
        }

        $result = $this->{$strategy}($key, $params);
        Logger::log('delete', $key, 'cache');

        return $result;
    }

    /**
     * Удалить ключ из кеша (строковый кеш).
     * Использует команду Redis DEL для удаления ключа.
     *
     * @param string $key Ключ для удаления
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return object Возвращает $this для цепочки вызовов
     */
    protected function del(string $key, array $params = []): object
    {
        Redis::del($key);
        Logger::log('del', $key, 'cache');

        return $this;
    }

    /**
     * Удалить поле из хэш-таблицы.
     * Использует команду Redis HDEL для удаления поля из хэша.
     * Делимитер отделяет только ИД (последняя часть ключа), остальное - ключ хэша.
     * Если поле равно '*', удаляется весь хэш.
     * Если делимитер не найден, используется стратегия del.
     *
     * @param string $key Полный ключ в формате "hashKey{delimiter}fieldId" или "hashKey{delimiter}*" для удаления всего хэша
     * @param array $params Дополнительные параметры (не используются)
     *
     * @return object Возвращает $this для цепочки вызовов
     */
    protected function hdel(string $key, array $params = []): object
    {
        $originalKey = $key;
        $delimiter = Config::getHashDelimiter();
        $delimiterEscaped = preg_quote($delimiter, '#');

        // Делимитер отделяет только ИД (последняя часть), остальное - ключ хеша
        if (preg_match('#' . $delimiterEscaped . '(.*)$#', $key, $matches)) {
            $field = $matches[1];
            $hashKey = substr($key, 0, -strlen($delimiter . $field));

            if ($field == '*') {
                $this->del($hashKey);
            } else {
                Redis::hdel($hashKey, $field);
            }
        } else {
            $this->del($key, $params);
        }

        Logger::log('hdel', $originalKey, 'cache');

        return $this;
    }

    /**
     * Сериализовать и сжать данные для хранения в Redis.
     * Сначала вызывает родительский метод serialize(), затем сжимает данные через gzcompress.
     *
     * @param mixed $data Данные для сериализации
     *
     * @return string Сериализованная и сжатая строка
     */
    protected function serialize($data): string
    {
        $data = parent::serialize($data);
        return gzcompress($data, 7);
    }

    /**
     * Десериализовать и распаковать данные из Redis.
     * Сначала распаковывает данные через gzuncompress, затем вызывает родительский метод unserialize().
     *
     * @param string|null $data Сериализованная и сжатая строка из Redis
     *
     * @return mixed Десериализованные данные
     */
    protected function unserialize(?string $data)
    {
        if (!$data) {
            return null;
        }

        $data = gzuncompress($data);
        return parent::unserialize($data);
    }
}

