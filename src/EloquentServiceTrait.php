<?php

namespace pribolshoy\laravelrepository;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Class EloquentServiceTrait
 * This trait is for implementation of abstract methods
 * from AbstractCachebleService.
 * For using by Laravel Eloquent Model objects
 *
 * @package pribolshoy\laravelrepository
 */
trait EloquentServiceTrait
{
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

    public function getItemAttribute($item, string $name)
    {
        if (is_object($item) && $item instanceof Model) {
            return $item->getAttribute($name);
        }

        if (is_array($item)) {
            return $item[$name] ?? null;
        }

        return null;
    }
    
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

