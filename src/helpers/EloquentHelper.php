<?php

namespace pribolshoy\laravelrepository\helpers;

use Illuminate\Database\Eloquent\Model;
use pribolshoy\repository\interfaces\RepositoryInterface;

/**
 * Трейт со вспомогательными свойствами и методами для Eloquent репозиториев
 *
 * Предоставляет функциональность для работы с сортировкой, фильтрацией
 * и кешированием в репозиториях, работающих с Eloquent моделями.
 *
 * Может использоваться только в классах, реализующих RepositoryInterface.
 *
 * @package pribolshoy\laravelrepository\helpers
 */
trait EloquentHelper
{
    /**
     * Значение для обозначения несуществующих элементов
     *
     * @var int
     */
    public int $not_exists_value = 999999999;

    /**
     * Получить массив доступных значений для сортировки (orderBy)
     *
     * Возвращает список полей, по которым можно сортировать данные.
     * Сначала пытается получить список через метод getOrdersBy() модели,
     * если метод отсутствует - использует fillable атрибуты модели.
     * Для каждого поля добавляется вариант с префиксом имени таблицы.
     *
     * Может быть переопределен в дочерних классах для возврата статичных данных.
     *
     * @return array Массив доступных полей для сортировки
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    protected function getAvailableOrdersBy()
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        $result = [];

        $entity = $this->getQueryBuilderInstance();

        if ($entity) {
            if (method_exists($entity, 'getOrdersBy')) {
                if ($orders = $entity->getOrdersBy()) {
                    $result = $orders;

                    foreach ($orders as $order) {
                        $result[] = $this->getTableName() . '.' . $order;
                    }
                }
            } else {
                // Получаем fillable атрибуты модели
                $fillable = $entity->getFillable();
                foreach ($fillable as $attribute) {
                    $result[] = $attribute;
                    $result[] = $this->getTableName() . '.' . $attribute;
                }
            }

            return $result;
        }

        return [];
    }


    /**
     * Сбор фильтров сортировок из параметров запроса
     *
     * Обрабатывает параметр 'sort' из запроса и преобразует его в фильтры сортировки.
     * Поддерживает:
     * - Массивы сортировок
     * - Строки с делимитером (например, "name-asc")
     * - Одиночные значения (по умолчанию SORT_DESC)
     *
     * @return $this
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function collectSortingByParam()
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        if (!$this->existsParam('sort')) {
            $this->addFilterValueByParams('sort', SORT_DESC, false);
            return $this;
        }

        $sort = $this->getParam('sort');

        if (is_array($sort)) {
            $i = 0;
            // обнуляем фильтр sort
            $this->addFilterValue('sort', null, false);

            foreach ($sort as $sort_part) {
                if ($this->isSortingWithDelimiter($sort_part)) {
                    $this->collectSortingWithDelimiter($sort_part, '-', $i ? true : false);
                } else {
                    $this->addFilterValue('sort', $sort_part);
                }
                $i++;
            }
        } else {
            if ($this->isSortingWithDelimiter($sort)) {
                $this->collectSortingWithDelimiter($sort, '-', false);
            } else {
                $this->addFilterValue('sort', $sort, false);
            }
        }
        return $this;
    }

    /**
     * Сбор фильтров сортировок из переданной строки с делимитером
     *
     * Парсит строку вида "field-direction" (например, "name-asc" или "created_at-desc")
     * и добавляет соответствующие фильтры сортировки. Проверяет, что поле доступно
     * для сортировки через getAvailableOrdersBy().
     *
     * @param string $sorting Строка сортировки в формате "field-direction"
     * @param string $delimiter Разделитель между полем и направлением (по умолчанию '-')
     * @param bool $append Добавлять к существующим фильтрам или заменять их
     * @return $this
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function collectSortingWithDelimiter(string $sorting, string $delimiter = '-', bool $append = true)
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        $sort_parts = explode($delimiter, $sorting);

        if (count($sort_parts) === 2) {
            // если это допустимая сортировка
            if (!$this->getAvailableOrdersBy()
                || in_array($sort_parts[0], $this->getAvailableOrdersBy())
            ) {
                $orderBy = $this->getTableName() . '.' . $sort_parts[0];
                $sort = ($sort_parts[1] === 'asc') ? SORT_ASC : SORT_DESC;

                $this->addFilterValue('orderBy', $orderBy, $append);
                $this->addFilterValue('sort', $sort, $append);
            }
        }

        return $this;
    }

    /**
     * Получить массив сортировок из фильтра
     *
     * Извлекает параметры сортировки из фильтра и возвращает их в виде массива
     * [column => direction]. Поддерживает множественные сортировки.
     * Проверяет доступность полей для сортировки через getAvailableOrdersBy().
     *
     * @param bool $clear_columns Убрать префикс таблицы из имен колонок
     * @return array|null Массив сортировок [column => direction] или null, если сортировка не задана
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function getOrderbyByFilter(bool $clear_columns = false)
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        if (!$this->existsFilter('orderBy')) {
            return null;
        }

        $result = [];

        $orderBy = $this->getFilter('orderBy');
        $sort = $this->getFilter('sort') ?? SORT_DESC;

        // если массив сортировок
        if (is_array($orderBy)) {
            $result = [];

            foreach ($orderBy as $key => $order) {
                // если не существует доступных сортировок сущности - пропускаем
                if ($this->getAvailableOrdersBy()
                    && !in_array($order, $this->getAvailableOrdersBy())
                ) {
                    continue;
                }

                // сортировка тоже массив, то берем направления с тем же ключем
                if (is_array($sort)) {
                    if (isset($sort[$key])) {
                        $sorting = $sort[$key];
                    }
                } else {
                    $sorting = $sort ?? SORT_DESC;
                }

                $result = array_merge($result, [$order => $sorting]);
            }

        } elseif (is_string($orderBy) && in_array($orderBy, $this->getAvailableOrdersBy())) {
            // если направление в виде массива, то берем первый
            $sort = (is_array($sort)) ? current($sort) : $sort;
            $result = [$orderBy => $sort];
        }

        if ($clear_columns && $result) {
            $new_result = [];
            foreach ($result as $column => $value) {
                $columnsParts = explode('.', $column);
                if (count($columnsParts) > 1) {
                    $new_result[$columnsParts[1]] = $value;
                } else {
                    $new_result[$column] = $value;
                }
            }
            $result = $new_result;
        }

        return $result ?? null;
    }

    /**
     * Проверить, является ли строка сортировкой с делимитером
     *
     * Проверяет, содержит ли строка делимитер и состоит ли она из двух частей
     * (поле и направление сортировки).
     *
     * @param string $sorting Строка для проверки
     * @param string $delimiter Разделитель (по умолчанию '-')
     * @return bool true если строка является сортировкой с делимитером
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function isSortingWithDelimiter(string $sorting, string $delimiter = '-')
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        return (bool)(stripos($sorting, $delimiter)
            && count(explode($delimiter, $sorting)) === 2);
    }

    /**
     * Пересечение массивов
     *
     * Возвращает массив, содержащий только значения, присутствующие в обоих массивах.
     * Если пересечение пустое, возвращает массив с одним элементом not_exists_value.
     * Если один из массивов пуст, возвращает другой массив.
     *
     * @param array|mixed $array_1 Первый массив
     * @param array|mixed $array_2 Второй массив
     * @return array Массив пересекающихся значений, один из исходных массивов или [not_exists_value]
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function mergeIntersect($array_1, $array_2)
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        if ($array_1 && $array_2) {
            $result = array_intersect($array_1, $array_2);
            if (empty($result)) {
                $result = [$this->not_exists_value];
            }
        } else if ($array_1) {
            $result = $array_1;
        } else {
            $result = $array_2;
        }

        return $result;
    }

    /**
     * Получить наименование кеша для total по текущим параметрам
     *
     * Генерирует имя ключа кеша для хранения общего количества элементов.
     * Из параметров исключаются атрибуты page, cache и offset.
     *
     * @deprecated TODO: удалить за ненадобностью. Функционал перенести в сервис
     *
     * @return string Имя ключа кеша для total или пустая строка
     * @throws \RuntimeException Если трейт используется не в классе, реализующем RepositoryInterface
     */
    public function getTotalHashName()
    {
        if (!($this instanceof RepositoryInterface)) {
            throw new \RuntimeException('Trait EloquentHelper can only be used in classes implementing RepositoryInterface');
        }
        $hash_name = $this->getTotalHashPrefix();

        if ($this->filter) {
            // таблица
            $hash_name = $hash_name . ':';
            foreach ($this->filter as $key => $value) {
                if (!$value) {
                    continue;
                }
                if ($key == 'cache') {
                    continue;
                }
                //                if ($key == 'page') continue;
                if ($key == 'offset') {
                    continue;
                }

                if (is_array($value)) {
                    $hash_name .= $key . '=' . implode(',', $value) . '&';
                } else {
                    $hash_name .= $key . '=' . $value . '&';
                }
            }
            $hash_name = trim($hash_name, '&');
        }

        return $hash_name ?? '';
    }
}

