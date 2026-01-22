<?php

namespace pribolshoy\laravelrepository\helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait EloquentHelper
 * Трейт со вспомогательными свойствами и реализацией
 * методов для классов наследующих AbstractEloquentRepository.
 *
 * @package pribolshoy\laravelrepository\helpers
 */
trait EloquentHelper
{
    protected $entity;

    public int $not_exists_value = 999999999;

    /**
     * Метод возвращает массив доступных значений orderBy
     * Может переписываться в наследнике чтоб
     * возвращать статичные данные
     */
    protected function getAvailableOrdersBy()
    {
        $result = [];

        if (!isset($this->entity)) {
            $this->entity = $this->getModel();
        }

        if (isset($this->entity)) {
            if (method_exists($this->entity, 'getOrdersBy')) {
                if ($orders = $this->entity->getOrdersBy()) {
                    $result = $orders;

                    foreach ($orders as $order) {
                        $result[] = $this->getTableName() . '.' . $order;
                    }
                }
            } else {
                // Получаем fillable атрибуты модели
                $fillable = $this->entity->getFillable();
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
     * Является ли строка сортировки
     *
     * @param string $sorting
     * @param string $delimiter
     *
     * @return bool
     */
    public function isSortingWithDelimiter(string $sorting, string $delimiter = '-')
    {
        return (bool)(stripos($sorting, $delimiter)
            && count(explode($delimiter, $sorting)) === 2);
    }

    /**
     * Сбор фильтров сортировок из параметров
     *
     * $return $this
     */
    public function collectSortingByParam()
    {
        if (!$this->existsParam('sort')) {
            $this->addFilterValueByParams('sort', SORT_DESC, false);
            return $this;
        }

        $sort = $this->getParams()['sort'];

        if (is_array($sort)) {
            $i = 0;
            // обнуляем фильтр sort
            $this->getFilters()['sort'] = null;

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
     * Сбор фильтров сортировок из переданной строки
     * с делимитером.
     *
     * @param string $sorting
     * @param string $delimiter
     * @param bool $append
     *
     * @return $this
     */
    public function collectSortingWithDelimiter(string $sorting, string $delimiter = '-', bool $append = true)
    {
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

    public function getOrderbyByFilter(bool $clear_columns = false)
    {
        if (!$this->existsFilter('orderBy')) {
            return null;
        }

        $result = [];

        $orderBy = $this->getFilters()['orderBy'];
        $sort = $this->getFilters()['sort'] ?? SORT_DESC;

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

    public function existsParam(string $name)
    {
        if (!isset($this->getParams()[$name])) {
            return false;
        }
        if (!$this->getParams()[$name]) {
            return false;
        }
        return true;
    }

    public function existsFilter(string $name)
    {
        if (!isset($this->getFilters()[$name])) {
            return false;
        }
        if (!$this->getFilters()[$name]) {
            return false;
        }
        return true;
    }

    /**
     * Добавление значения из self::params в self::filter при
     * условии что оно существует
     *
     * @param $value
     * @param string $default_value
     * @param bool $append
     *
     * @return array|void|string
     */
    public function addFilterValueByParams($value, $default_value = '', $append = true)
    {
        // если существует такой параметр
        if (isset($this->params[$value])) {
            if (is_array($this->params[$value])) {

                if (!empty($this->filter[$value]) && $append) {

                    if (is_array($this->filter[$value])) {
                        $this->filter[$value] = array_merge($this->filter[$value], $this->params[$value]);
                    } else {
                        $this->filter[$value] = array_merge([$this->filter[$value]], $this->params[$value]);
                    }

                } else {
                    $this->filter[$value] = $this->params[$value];
                }

            } else {
                $parts = explode(',', $this->params[$value]);

                if (count($parts) > 1) {

                    if (!empty($this->filter[$value]) && $append) {

                        if (is_array($this->filter[$value])) {
                            $this->filter[$value] = array_merge($this->filter[$value], $parts);
                        } else {
                            $this->filter[$value] = array_merge([$this->filter[$value]], $parts);
                        }

                    } else {
                        $this->filter[$value] = $parts;
                    }

                } else {
                    if (!empty($this->filter[$value]) && $append) {

                        if (is_array($this->filter[$value])) {
                            $this->filter[$value] = array_merge($this->filter[$value], is_array($this->params[$value]) ?: [$this->params[$value]]);
                        } else {
                            $this->filter[$value] = array_merge([$this->filter[$value]], is_array($this->params[$value]) ?: [$this->params[$value]]);
                        }

                    } else {
                        $this->filter[$value] = $this->params[$value];
                    }
                }
            }
        } elseif (strlen($default_value)) {
            $this->filter[$value] = $default_value;
        } elseif (is_bool($default_value)) {
            $this->filter[$value] = $default_value;
        }

        return strlen($this->filter[$value] ?? null);
    }

    /**
     * Присоединить значение к фильтру
     *
     * @param $filter_key
     * @param $value
     * @param bool $append
     * @return array|void|string
     */
    public function addFilterValue($filter_key, $value, $append = true)
    {
        if (!empty($this->filter[$filter_key]) && $append) {

            if (is_array($this->filter[$filter_key])) {
                $this->filter[$filter_key] = array_merge($this->filter[$filter_key], [$value]);
            } else {
                $this->filter[$filter_key] = array_merge([$this->filter[$filter_key]], [$value]);
            }

        } else {
            $this->filter[$filter_key] = $value;
        }

        return $this->filter[$filter_key];
    }

    /**
     * Соединение воедино массивов, в итоге остаются
     * только пересекающиеся значения
     *
     * @param $array_1
     * @param $array_2
     * @return array|void|string
     */
    public function mergeIntersect($array_1, $array_2)
    {
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
     * TODO: удалить за ненадобностью. Функционал перенести в сервис
     * Получить наименование кеша для total
     * по текущим параметрам.
     * Из параметров удаляется атрибут page, cache
     *
     * @return string|null
     */
    public function getTotalHashName()
    {
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

