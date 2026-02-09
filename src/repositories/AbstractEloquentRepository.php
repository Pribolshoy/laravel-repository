<?php

namespace pribolshoy\laravelrepository\repositories;

use pribolshoy\repository\AbstractCachebleRepository;
use pribolshoy\laravelrepository\helpers\EloquentHelper;
use pribolshoy\repository\traits\CatalogTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;

/**
 * Абстрактный класс репозитория для работы с Eloquent моделями
 *
 * Предоставляет базовую функциональность для работы с Eloquent моделями,
 * включая построение запросов, пагинацию, фильтрацию и кеширование.
 *
 * Все конкретные реализации репозиториев должны наследоваться от этого класса
 * и реализовать метод addQueries().
 * Метод defaultFilter() имеет базовую реализацию и может быть переопределён при необходимости.
 *
 * @package pribolshoy\laravelrepository\repositories
 */
abstract class AbstractEloquentRepository extends AbstractCachebleRepository
{
    use EloquentHelper;
    use CatalogTrait;

    /**
     * Путь к драйверам репозитория для работы с Eloquent
     *
     * Используется для автоматического определения класса драйвера кеша
     * на основе имени драйвера.
     *
     * @var string|null
     */
    protected ?string $driver_path = "\\pribolshoy\\laravelrepository\\drivers\\";

    /**
     * Создаёт построитель запросов на основе модели Eloquent.
     *
     * Инициализирует свойство $queryBuilder как Builder через метод query() модели.
     *
     * @return $this
     * @throws \pribolshoy\repository\exceptions\RepositoryException Если модель не задана
     */
    protected function makeQueryBuilder()
    {
        $this->queryBuilder = ($this->getQueryBuilderInstance())::query();
        return $this;
    }


    /**
     * Дополняет метод родительского класса.
     *
     * Устанавливает режим выборки данных (массив или объекты) перед выполнением запроса.
     *
     * @return $this
     */
    protected function beforeFetch()
    {
        if ($this->getIsArray()) {
            $this->getQueryBuilder()->toBase();
        }
        return parent::beforeFetch();
    }

    /**
     * Выполняет выборку данных из БД через подготовленную модель.
     *
     * В методе происходит:
     * - Подсчёт общего количества элементов
     * - Установка лимита и смещения для выборки
     * - Выполнение запроса (один элемент или список)
     *
     * Примечание: Если установлен фильтр 'single' => true, метод возвращает один элемент
     * (может быть null), а не массив. Иначе возвращается массив элементов.
     *
     * @return object
     */
    protected function fetch(): object
    {
        $this->getTotal();
        // после получения полного списка элементов
        $this->addLimitAndOffset();

        $this->items = $this->getQueryBuilder()->get();

        if ($this->getIsArray() ) {
            $this->items = $this->getQueryBuilder()->get()->toArray();
        } else {
            $this->items = $this->getQueryBuilder()->get()->all();
        }

        return $this;
    }

    /**
     * Получить значение лимита с учетом приоритета perPage > limit > значение по умолчанию
     *
     * @param int $default Значение по умолчанию
     * @return int
     */
    protected function getLimit(int $default = 25): int
    {
        // Приоритет: perPage > limit > значение по умолчанию
        if ($this->existsFilter('perPage')) {
            return (int)$this->getFilter('perPage');
        }

        if ($this->existsFilter('limit')) {
            return (int)$this->getFilter('limit');
        }

        return $default;
    }

    /**
     * Устанавливает limit и offset запроса перед выборкой.
     *
     * Также применяет сортировку, если она задана в фильтре.
     *
     * @return object
     */
    protected function addLimitAndOffset(): object
    {
        if ($orderBy = $this->getOrderbyByFilter()) {
            foreach ($orderBy as $column => $direction) {
                $this->getQueryBuilder()->orderBy($column, $direction === SORT_ASC ? 'asc' : 'desc');
            }
        }

        $limit = $this->getLimit();
        if ($limit > 0) {
            $this->getQueryBuilder()->limit($limit);
        }

        $this->getQueryBuilder()->offset($this->filter['offset'] ?? 0);

        return $this;
    }

    /**
     * Вычисляет общее количество элементов выборки и инициализирует пагинацию.
     *
     * Если требуется подсчёт общего количества (getNeedTotal() возвращает true),
     * выполняет count() запрос и настраивает пагинацию с учётом текущей страницы.
     *
     * @return $this
     */
    protected function getTotal()
    {
        if ($this->getNeedTotal()) {
            $query = $this->getQueryBuilder();
            $baseQuery = $query->getQuery();
            
            // Если есть GROUP BY, используем подзапрос для правильного подсчета
            if (!empty($baseQuery->groups)) {
                $model = $query->getModel();
                
                // Создаем подзапрос из исходного запроса
                $subQuery = $query->toBase();
                
                // Создаем новый запрос для подсчета через fromSub
                $countQuery = $model->getConnection()->table(DB::raw('(' . $subQuery->toSql() . ') as subquery'))
                    ->mergeBindings($subQuery)
                    ->selectRaw('COUNT(*) as aggregate');
                
                $this->setTotalCount($countQuery->value('aggregate'));
            } else {
                $this->setTotalCount($query->count());
            }
            
            $limit = $this->getLimit();
            $this->initPages($this->getTotalCount(), $limit);
        }
        return $this;
    }

    /**
     * Инициализирует пагинацию для выборки.
     *
     * Создаёт объект пагинации с заданным общим количеством элементов и размером страницы.
     *
     * @param int $totalCount Общее количество элементов выборки
     * @param int $pageSize Количество элементов на странице
     * @param bool $pageSizeParam Параметр для изменения размера страницы через URL
     *
     * @return static
     * @throws \RuntimeException Если класс пагинации не установлен
     */
    public function initPages(int $totalCount, int $pageSize, bool $pageSizeParam = false)
    {
        $pagination_class = $this->getPaginatorClass(LengthAwarePaginator::class);

        if (!class_exists($pagination_class)) {
            throw new \RuntimeException("Класс пагинации '{$pagination_class}' не найден");
        }

        $currentPage = $this->getFilters()['page'] ?? Paginator::resolveCurrentPage();
        $offset = ($currentPage - 1) * $pageSize;

        // Создаём объект пагинации Laravel
        $path = Request::has('url') ? Request::get('url') : (function_exists('request') ? request()->url() : '/');
        $pages = new $pagination_class(
            [],
            $totalCount,
            $pageSize,
            $currentPage,
            [
                'path' => $path,
                'pageName' => 'page',
            ]
        );

        $this->setPages($pages);
        $this->filter['offset'] = $offset;

        return $this;
    }

    /**
     * Возвращает имя таблицы, связанной с моделью.
     *
     * @return string Имя таблицы в БД
     */
    public function getTableName(): string
    {
        return $this->getQueryBuilderInstance()->getTable();
    }

    /**
     * Добавление условий запроса на основе фильтров из defaultFilter()
     *
     * Применяет стандартные фильтры к запросу:
     * - id: фильтр по одиночному ID
     * - ids: фильтр по массиву ID
     * - code: фильтр по коду
     * - exclude_ids: исключение по массиву ID
     * - status: фильтр по статусу
     * - status_id: фильтр по ID статуса
     *
     * Может быть переопределён в дочерних классах для добавления специфичной логики.
     *
     * @return $this
     */
    protected function addQueries()
    {
        $tableName = $this->getTableName();

        // Фильтр по одиночному ID
        if ($this->existsFilter('id')) {
            $id = $this->getFilter('id');
            if ($id !== null) {
                $this->getQueryBuilder()->where($tableName . '.id', $id);
            }
        }

        // Фильтр по массиву ID
        if ($this->existsFilter('ids')) {
            $ids = $this->getFilter('ids');
            if (is_array($ids) && !empty($ids)) {
                $this->getQueryBuilder()->whereIn($tableName . '.id', $ids);
            }
        }

        // Фильтр по коду
        if ($this->existsFilter('code')) {
            $code = $this->getFilter('code');
            if ($code !== null) {
                $this->getQueryBuilder()->where($tableName . '.code', $code);
            }
        }

        // Исключение по массиву ID
        if ($this->existsFilter('exclude_ids')) {
            $excludeIds = $this->getFilter('exclude_ids');
            if (is_array($excludeIds) && !empty($excludeIds)) {
                $this->getQueryBuilder()->whereNotIn($tableName . '.id', $excludeIds);
            }
        }

        // Фильтр по статусу
        if ($this->existsFilter('status')) {
            $status = $this->getFilter('status');
            if ($status !== null) {
                $this->getQueryBuilder()->where($tableName . '.status', $status);
            }
        }

        // Фильтр по ID статуса
        if ($this->existsFilter('status_id')) {
            $statusId = $this->getFilter('status_id');
            if ($statusId !== null) {
                $this->getQueryBuilder()->where($tableName . '.status_id', $statusId);
            }
        }

        return $this;
    }

    /**
     * Метод в котором происходит стандартная специфическая
     * фильтрация из параметров запроса.
     *
     * Устанавливает значения по умолчанию и собирает фильтры из параметров.
     * Может быть переопределён в дочерних классах для добавления специфичной логики.
     *
     * @return void
     */
    protected function defaultFilter(): void
    {
        // Устанавливаем значения по умолчанию
        $this->addFilterValueByParams('page', 0, false);
        $this->addFilterValueByParams('offset', 0, false);

        // Поддержка perPage и limit (приоритет у perPage)
        // Оба параметра собираются из запроса, но внутренне используется limit
        $this->addFilterValueByParams('perPage');
        $this->addFilterValueByParams('limit');

        // Нормализуем limit: если есть perPage, используем его значение для limit
        // Приоритет: perPage > limit > значение по умолчанию
        if ($this->existsFilter('perPage')) {
            $this->addFilterValue('limit', $this->getFilter('perPage'), false);
        } elseif (!$this->existsFilter('limit')) {
            // Значение по умолчанию устанавливаем в limit
            $this->addFilterValue('limit', 25, false);
        }

        $this->addFilterValueByParams('id');
        $this->addFilterValueByParams('ids');
        $this->addFilterValueByParams('code');
        $this->addFilterValueByParams('exclude_ids');
        $this->addFilterValueByParams('search');
        $this->addFilterValueByParams('status');
        $this->addFilterValueByParams('status_id');
        $this->addFilterValueByParams('custom_filters');

        // Сбор параметров сортировки
        $this->collectSortingByParam();
    }
}
