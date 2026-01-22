<?php

namespace pribolshoy\laravelrepository\drivers;

use pribolshoy\repository\Config;
use pribolshoy\repository\Logger;
use Illuminate\Support\Facades\Redis;

/**
 * Class RedisDriver
 *
 * Redis have many different ways to fetch information,
 * then we can use strategies for setting specific way.
 * Also class have some auto-detection of right ways.
 *
 * Strategies can be sets by params property.
 * Params come from service cache_params['get'] for get() method
 * and cache_params['set'] for set() method.
 *
 * Available strategies for get():
 * getValue - Get the value of a key (analog get($key)).
 *
 * getHValue - Get the value of a hash field (analog hget($key, $field)).
 *
 * getHValues - Get the values of all the given hash fields (analog hmget($key, ...$fields)).
 *
 * getAllHash - Get all the values in a hash (analog hvals($key)).
 *
 * Available strategies for set():
 * hset - Set hash field (analog hset($key, $field, $value)).
 * setex - Set key with expiration (analog setex($key, $ttl, $value)).
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
     * Стратегии для хэш-таблицы: getAllHash, getHValue, getHValues, hset, hdel
     * Стратегии для строкового кеша: getValue, setex, del
     *
     * @param array $cacheParamsGet Параметры из cache_params['get']
     * @return string Делимитер для использования в ключе кеша
     */
    public static function getIdPostfixByStrategy(array $cacheParamsGet = []): string
    {
        $strategy = $cacheParamsGet['strategy'] ?? 'getValue';

        // Стратегии для хэш-таблицы
        $hashStrategies = ['getAllHash', 'getHValue', 'getHValues', 'hset', 'hdel'];
        
        if (in_array($strategy, $hashStrategies)) {
            return Config::getHashDelimiter();
        }

        // Стратегии для строкового кеша
        return Config::getStringDelimiter();
    }

    /**
     * Get data from cache.
     * Params should come from service cache_params['get'].
     *
     * @param string $key
     * @param array $params Parameters from cache_params['get']
     * @return array|mixed
     */
    public function get(string $key, array $params = [])
    {
        // default strategy
        $strategy = $params['strategy'] ?? 'getValue';
        $fields = $params['fields'] ?? [];

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
                if ($items = Redis::hmget($key, ...$fieldsChunk)) {
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
     * Set data to cache.
     * Params should come from service cache_params['set'].
     *
     * @param string $key
     * @param mixed $value
     * @param int $cache_duration
     * @param array $params Parameters from cache_params['set']
     * @return object
     */
    public function set(string $key, $value, int $cache_duration = 0, array $params = []): object
    {
        // по умолчанию хеш таблица
        $strategy = $params['strategy'] ?? 'hset';

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
     * Удалить ключ или поле из кеша.
     * Выбирает стратегию удаления на основе параметров (по умолчанию hdel для хэш-таблицы).
     *
     * @param string $key Ключ для удаления
     * @param array $params Параметры, могут содержать 'strategy' для выбора метода удаления
     *
     * @return object Возвращает $this для цепочки вызовов
     * @throws \RuntimeException Если указанная стратегия не существует
     */
    public function delete(string $key, array $params = []): object
    {
        // по умолчанию хеш таблица
        $strategy = $params['strategy'] ?? 'hdel';

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

