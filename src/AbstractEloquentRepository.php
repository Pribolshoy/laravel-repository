<?php

namespace pribolshoy\laravelrepository;

use pribolshoy\repository\AbstractCachebleRepository;
use pribolshoy\laravelrepository\helpers\EloquentHelper;
use pribolshoy\repository\traits\CatalogTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Request;

/**
 * Class AbstractEloquentRepository
 *
 * Абстрактный класс от которого наследуются
 * все конкретные реализации использующие в качестве модели/сущности
 * Eloquent Model
 *
 * @package pribolshoy\laravelrepository
 */
abstract class AbstractEloquentRepository extends AbstractCachebleRepository
{
    use EloquentHelper, CatalogTrait;

    /**
     * Путь к драйверам репозитория для работы с Eloquent.
     *
     * @var string|null
     */
    protected ?string $driver_path = "\\pribolshoy\\laravelrepository\\drivers\\";

    /**
     * Создаёт построитель запросов на основе модели Eloquent.
     *
     * Инициализирует свойство $model как Builder через метод query() модели.
     *
     * @return $this
     * @throws \pribolshoy\exceptions\RepositoryException
     */
    protected function makeQueryBuilder()
    {
        $this->model = ($this->getModel())::query();
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
            $this->model->toBase();
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

        if (isset($this->filter['single']) && $this->filter['single']) {
            $this->items = $this->model->first();
            if ($this->items && $this->getIsArray()) {
                $this->items = $this->items->toArray();
            }
        } else {
            $this->items = $this->model->get()->all();
            if ($this->getIsArray() && $this->items) {
                $this->items = array_map(function($item) {
                    return $item->toArray();
                }, $this->items);
            }
        }

        return $this;
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
                $this->model->orderBy($column, $direction === SORT_ASC ? 'asc' : 'desc');
            }
        }
        
        $limit = $this->filter['limit'] ?? null;
        if ($limit !== null) {
            $this->model->limit($limit);
        }
        
        $this->model->offset($this->filter['offset'] ?? 0);

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
            $this->setTotalCount($this->model->count());
            $limit = $this->filter['limit'] ?? 25; // Значение по умолчанию, если limit не установлен
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
        $pagination_class = isset($this->pagination_class) ? $this->pagination_class : LengthAwarePaginator::class;
        
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
        return $this->getModel()->getTable();
    }
}

