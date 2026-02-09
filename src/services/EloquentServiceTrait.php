<?php

namespace pribolshoy\laravelrepository\services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Трейт для работы с Eloquent моделями в сервисах
 *
 * Предоставляет реализацию абстрактных методов из AbstractCachebleService
 * для работы с Laravel Eloquent моделями. Поддерживает работу как с объектами
 * моделей, так и с массивами данных.
 *
 * Используется в сервисах, которые работают с Eloquent репозиториями.
 *
 * @package pribolshoy\laravelrepository\services
 */
trait EloquentServiceTrait
{
    /**
     * Получить первичный ключ элемента
     *
     * Извлекает первичный ключ из элемента. Поддерживает работу с:
     * - Eloquent моделями (через getKey())
     * - Массивами (через ключ 'id')
     * - Объектами с методом getItemPrimaryKey() из родительского класса
     *
     * @param mixed $item Элемент (модель Eloquent или массив)
     * @return mixed|null Первичный ключ или null, если не найден
     */
    public function getItemPrimaryKey($item)
    {
        if ($result = parent::getItemPrimaryKey($item)) {
            return $result;
        }

        if (is_object($item) && $item instanceof Model) {
            return $item->getKey();
        }

        if (is_array($item) && isset($item['id'])) {
            return $item['id'];
        }

        return null;
    }

    /**
     * Проверить наличие атрибута у элемента
     *
     * Проверяет существование атрибута в элементе. Поддерживает работу с:
     * - Eloquent моделями (через offsetExists())
     * - Массивами (через isset())
     *
     * @param mixed $item Элемент (модель Eloquent или массив)
     * @param string $name Имя атрибута
     * @return bool true если атрибут существует, false в противном случае
     */
    public function hasItemAttribute($item, string $name): bool
    {
        if (is_object($item) && $item instanceof Model) {
            return $item->offsetExists($name);
        }

        if (is_array($item)) {
            return isset($item[$name]);
        }

        return false;
    }

    /**
     * Получить значение атрибута элемента
     *
     * Извлекает значение атрибута из элемента. Поддерживает работу с:
     * - Eloquent моделями (через getAttribute())
     * - Массивами (через доступ по ключу)
     *
     * @param mixed $item Элемент (модель Eloquent или массив)
     * @param string $name Имя атрибута
     * @return mixed|null Значение атрибута или null, если не найдено
     */
    public function getItemAttribute($item, string $name)
    {
        if (is_object($item)) {
            return $item->getAttribute($name);
        }

        if (is_array($item)) {
            return $item[$name] ?? null;
        }

        return null;
    }

    /**
     * Сортировка массива элементов
     *
     * Сортирует элементы по ключам и направлениям, указанным в свойстве $sorting.
     * Поддерживает работу с Eloquent моделями и массивами.
     *
     * @param array $items Массив элементов для сортировки
     * @return array Отсортированный массив
     */
    public function sort(array $items): array
    {
        if ($this->sorting && !empty($items)) {
            foreach ($this->sorting as $key => $direction) {
                usort($items, function ($a, $b) use ($key, $direction) {
                    $valueA = is_object($a) && $a instanceof Model
                        ? $a->getAttribute($key)
                        : ($a[$key] ?? null);
                    $valueB = is_object($b) && $b instanceof Model
                        ? $b->getAttribute($key)
                        : ($b[$key] ?? null);

                    if ($valueA == $valueB) {
                        return 0;
                    }

                    $result = $valueA < $valueB ? -1 : 1;
                    return $direction === SORT_ASC ? $result : -$result;
                });
            }
        }

        return $items;
    }

    /**
     * Собрать связи модели из массива данных
     *
     * Рекурсивно создает модели связей и устанавливает их на родительскую модель
     * через метод setRelation(). Поддерживает как одиночные связи, так и коллекции связей.
     * Имя связи формируется из имени класса модели (lcfirst(class_basename($class))).
     *
     * @param Model $parentModel Родительская модель
     * @param array|mixed $relations Данные связей (массив с ключами - классами моделей)
     * @return Model Родительская модель с установленными связями
     */
    protected function collectRelationsFromArray(Model $parentModel, $relations)
    {
        if (is_array($relations) && $relations) {
            foreach ($relations as $class => $relation) {
                if (!$relation) {
                    continue;
                }

                if (is_array(current($relation))) {
                    $relationModels = [];
                    foreach ($relation as $data) {
                        $relationModel = new $class();
                        $relationModel->fill($data);
                        $relationModel = $this->collectRelationsFromArray($relationModel, $data['relations'] ?? []);
                        $relationModels[] = $relationModel;
                    }
                    $relationName = lcfirst(class_basename($class));

                    $parentModel->setRelation($relationName, $relationModels);
                } else {
                    $relationModel = new $class();
                    $relationModel->fill($relation);
                    $relationModel = $this->collectRelationsFromArray($relationModel, $relation['relations'] ?? []);

                    $relationName = lcfirst(class_basename($class));

                    $parentModel->setRelation($relationName, $relationModel);
                }
            }
        }

        return $parentModel;
    }
}
