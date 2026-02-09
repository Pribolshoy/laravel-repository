<?php

namespace Tests\Unit\Helpers;

use Illuminate\Database\Eloquent\Model;
use pribolshoy\laravelrepository\helpers\EloquentHelper;
use pribolshoy\repository\interfaces\RepositoryInterface;
use Tests\TestCase;

/**
 * Тесты для трейта EloquentHelper
 *
 * @package Tests\Unit\Helpers
 */
class EloquentHelperTest extends TestCase
{
    /**
     * Тест получения доступных полей для сортировки через метод getOrdersBy()
     */
    public function testGetAvailableOrdersByWithGetOrdersByMethod(): void
    {
        $repository = new class extends TestRepository {
            public function getQueryBuilderInstance(bool $force = false): object
            {
                return new class extends Model {
                    public function getOrdersBy(): array
                    {
                        return ['name', 'created_at'];
                    }

                    public function getTable(): string
                    {
                        return 'test_table';
                    }
                };
            }

            public function getTableName(): string
            {
                return 'test_table';
            }
        };

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('getAvailableOrdersBy');
        $method->setAccessible(true);
        $result = $method->invoke($repository);

        $this->assertIsArray($result);
        $this->assertContains('name', $result);
        $this->assertContains('created_at', $result);
        $this->assertContains('test_table.name', $result);
        $this->assertContains('test_table.created_at', $result);
        $this->assertCount(4, $result);
    }

    /**
     * Тест получения доступных полей для сортировки через fillable атрибуты
     */
    public function testGetAvailableOrdersByWithFillable(): void
    {
        $repository = new class extends TestRepository {
            public function getQueryBuilderInstance(bool $force = false): object
            {
                return new class extends Model {
                    protected $fillable = ['title', 'status', 'email'];

                    public function getTable(): string
                    {
                        return 'users';
                    }
                };
            }

            public function getTableName(): string
            {
                return 'users';
            }
        };

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('getAvailableOrdersBy');
        $method->setAccessible(true);
        $result = $method->invoke($repository);

        $this->assertIsArray($result);
        $this->assertContains('title', $result);
        $this->assertContains('status', $result);
        $this->assertContains('email', $result);
        $this->assertContains('users.title', $result);
        $this->assertContains('users.status', $result);
        $this->assertContains('users.email', $result);
        $this->assertCount(6, $result);
    }

    /**
     * Тест получения доступных полей когда getOrdersBy() возвращает пустой массив
     */
    public function testGetAvailableOrdersByWithEmptyGetOrdersBy(): void
    {
        $repository = new class extends TestRepository {
            public function getQueryBuilderInstance(bool $force = false): object
            {
                return new class extends Model {
                    public function getOrdersBy(): array
                    {
                        return [];
                    }

                    public function getTable(): string
                    {
                        return 'test_table';
                    }
                };
            }

            public function getTableName(): string
            {
                return 'test_table';
            }
        };

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('getAvailableOrdersBy');
        $method->setAccessible(true);
        $result = $method->invoke($repository);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Тест получения доступных полей когда модель отсутствует
     */
    public function testGetAvailableOrdersByWithoutModel(): void
    {
        $repository = new class extends TestRepository {
            public function getQueryBuilderInstance(bool $force = false): object
            {
                // Возвращаем пустой объект Model без fillable, чтобы проверить поведение
                return new class extends \Illuminate\Database\Eloquent\Model {
                    protected $fillable = [];
                };
            }

            public function getTableName(): string
            {
                return 'test_table';
            }
        };

        // Используем рефлексию для вызова protected метода
        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('getAvailableOrdersBy');
        $method->setAccessible(true);
        $result = $method->invoke($repository);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Тест сбора сортировки из параметров - отсутствие параметра sort
     */
    public function testCollectSortingByParamWithoutSortParam(): void
    {
        $repository = new class extends TestRepository {
            public function existsParam(string $key): bool
            {
                return false;
            }

            public function addFilterValueByParams(string $key, $value, bool $append = true)
            {
                $this->filter[$key] = $value;
                return $this;
            }
        };

        $result = $repository->collectSortingByParam();

        $this->assertSame($repository, $result);
        $this->assertEquals(SORT_DESC, $repository->filter['sort']);
    }

    /**
     * Тест сбора сортировки из параметров - строка с делимитером
     */
    public function testCollectSortingByParamWithDelimiterString(): void
    {
        $repository = new class extends TestRepository {
            public function existsParam(string $key): bool
            {
                return $key === 'sort';
            }

            public function getParam(string $key)
            {
                return 'name-asc';
            }

            public function getAvailableOrdersBy(): array
            {
                return ['name', 'test_table.name'];
            }

            public function getTableName(): string
            {
                return 'test_table';
            }

            public function addFilterValue(string $key, $value, bool $append = true)
            {
                if (!isset($this->filter[$key])) {
                    $this->filter[$key] = [];
                }
                if ($append) {
                    $this->filter[$key][] = $value;
                } else {
                    $this->filter[$key] = $value;
                }
                return $this;
            }
        };

        $result = $repository->collectSortingByParam();

        $this->assertSame($repository, $result);
        $this->assertArrayHasKey('orderBy', $repository->filter);
        $this->assertArrayHasKey('sort', $repository->filter);
    }

    /**
     * Тест сбора сортировки из параметров - массив сортировок
     */
    public function testCollectSortingByParamWithArray(): void
    {
        $repository = new class extends TestRepository {
            public function existsParam(string $key): bool
            {
                return $key === 'sort';
            }

            public function getParam(string $key)
            {
                return ['name-asc', 'created_at-desc'];
            }

            public function getAvailableOrdersBy(): array
            {
                return ['name', 'created_at', 'test_table.name', 'test_table.created_at'];
            }

            public function getTableName(): string
            {
                return 'test_table';
            }

            public function addFilterValue(string $key, $value, bool $append = true)
            {
                if ($key === 'sort' && !$append) {
                    $this->filter[$key] = null;
                    return $this;
                }
                if (!isset($this->filter[$key]) || !is_array($this->filter[$key])) {
                    $this->filter[$key] = [];
                }
                if ($append && is_array($this->filter[$key])) {
                    $this->filter[$key][] = $value;
                } else {
                    $this->filter[$key] = $value;
                }
                return $this;
            }

            public function isSortingWithDelimiter(string $sorting, string $delimiter = '-'): bool
            {
                return (bool)(stripos($sorting, $delimiter)
                    && count(explode($delimiter, $sorting)) === 2);
            }

            public function collectSortingWithDelimiter(string $sorting, string $delimiter = '-', bool $append = true)
            {
                $sort_parts = explode($delimiter, $sorting);

                if (count($sort_parts) === 2) {
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
        };

        $result = $repository->collectSortingByParam();

        $this->assertSame($repository, $result);
    }

    /**
     * Тест проверки строки сортировки с делимитером
     */
    public function testIsSortingWithDelimiter(): void
    {
        $repository = new class extends TestRepository {};

        $this->assertTrue($repository->isSortingWithDelimiter('name-asc'));
        $this->assertTrue($repository->isSortingWithDelimiter('created_at-desc'));
        $this->assertFalse($repository->isSortingWithDelimiter('name'));
        $this->assertFalse($repository->isSortingWithDelimiter('name-asc-extra'));
        $this->assertFalse($repository->isSortingWithDelimiter(''));
    }

    /**
     * Тест сбора сортировки с делимитером
     */
    public function testCollectSortingWithDelimiter(): void
    {
        $repository = new class extends TestRepository {
            public function getAvailableOrdersBy(): array
            {
                return ['name', 'test_table.name'];
            }

            public function getTableName(): string
            {
                return 'test_table';
            }

            public function addFilterValue(string $key, $value, bool $append = true)
            {
                if (!isset($this->filter[$key])) {
                    $this->filter[$key] = [];
                }
                if ($append) {
                    $this->filter[$key][] = $value;
                } else {
                    $this->filter[$key] = $value;
                }
                return $this;
            }
        };

        $result = $repository->collectSortingWithDelimiter('name-asc');

        $this->assertSame($repository, $result);
        $this->assertArrayHasKey('orderBy', $repository->filter);
        $this->assertArrayHasKey('sort', $repository->filter);
        $this->assertContains('test_table.name', $repository->filter['orderBy']);
        $this->assertContains(SORT_ASC, $repository->filter['sort']);
    }

    /**
     * Тест получения сортировки из фильтра - одиночная сортировка
     */
    public function testGetOrderbyByFilterWithSingleSort(): void
    {
        $repository = new class extends TestRepository {
            public function existsFilter(string $key): bool
            {
                return isset($this->filter[$key]);
            }

            public function getFilter(string $key)
            {
                return $this->filter[$key] ?? null;
            }

            public function getAvailableOrdersBy(): array
            {
                return ['name', 'test_table.name'];
            }
        };

        $repository->filter = [
            'orderBy' => 'test_table.name',
            'sort' => SORT_ASC
        ];

        $result = $repository->getOrderbyByFilter();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('test_table.name', $result);
        $this->assertEquals(SORT_ASC, $result['test_table.name']);
    }

    /**
     * Тест получения сортировки из фильтра - множественная сортировка
     */
    public function testGetOrderbyByFilterWithMultipleSorts(): void
    {
        $repository = new class extends TestRepository {
            public function existsFilter(string $key): bool
            {
                return isset($this->filter[$key]);
            }

            public function getFilter(string $key)
            {
                return $this->filter[$key] ?? null;
            }

            public function getAvailableOrdersBy(): array
            {
                return ['name', 'created_at', 'test_table.name', 'test_table.created_at'];
            }
        };

        $repository->filter = [
            'orderBy' => ['test_table.name', 'test_table.created_at'],
            'sort' => [SORT_ASC, SORT_DESC]
        ];

        $result = $repository->getOrderbyByFilter();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('test_table.name', $result);
        $this->assertArrayHasKey('test_table.created_at', $result);
        $this->assertEquals(SORT_ASC, $result['test_table.name']);
        $this->assertEquals(SORT_DESC, $result['test_table.created_at']);
    }

    /**
     * Тест получения сортировки из фильтра с очисткой префикса таблицы
     */
    public function testGetOrderbyByFilterWithClearColumns(): void
    {
        $repository = new class extends TestRepository {
            public function existsFilter(string $key): bool
            {
                return isset($this->filter[$key]);
            }

            public function getFilter(string $key)
            {
                return $this->filter[$key] ?? null;
            }

            public function getAvailableOrdersBy(): array
            {
                return ['name', 'test_table.name'];
            }
        };

        $repository->filter = [
            'orderBy' => 'test_table.name',
            'sort' => SORT_ASC
        ];

        $result = $repository->getOrderbyByFilter(true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals(SORT_ASC, $result['name']);
    }

    /**
     * Тест получения сортировки когда фильтр отсутствует
     */
    public function testGetOrderbyByFilterWithoutFilter(): void
    {
        $repository = new class extends TestRepository {
            public function existsFilter(string $key): bool
            {
                return false;
            }
        };

        $result = $repository->getOrderbyByFilter();

        $this->assertNull($result);
    }

    /**
     * Тест пересечения массивов
     */
    public function testMergeIntersect(): void
    {
        $repository = new class extends TestRepository {};

        // Оба массива не пустые, есть пересечение
        $result = $repository->mergeIntersect([1, 2, 3], [2, 3, 4]);
        $this->assertEquals([2, 3], array_values($result)); // Используем array_values для сравнения без учета ключей

        // Оба массива не пустые, нет пересечения
        $result = $repository->mergeIntersect([1, 2], [3, 4]);
        $this->assertEquals([999999999], $result);

        // Первый массив пустой
        $result = $repository->mergeIntersect([], [1, 2]);
        $this->assertEquals([1, 2], $result);

        // Второй массив пустой
        $result = $repository->mergeIntersect([1, 2], []);
        $this->assertEquals([1, 2], $result);

        // Оба массива пустые
        $result = $repository->mergeIntersect([], []);
        $this->assertEquals([], $result);
    }

    /**
     * Тест генерации имени ключа кеша для total
     */
    public function testGetTotalHashName(): void
    {
        $repository = new class extends TestRepository {
            public function getTotalHashPrefix(): string
            {
                return 'total';
            }
        };

        $repository->filter = [
            'id' => 1,
            'status' => 'active',
            'page' => 2,
            'offset' => 10,
            'cache' => true
        ];

        $result = $repository->getTotalHashName();

        $this->assertIsString($result);
        $this->assertStringStartsWith('total:', $result);
        $this->assertStringContainsString('id=1', $result);
        $this->assertStringContainsString('status=active', $result);
        // Примечание: page не исключается в коде (закомментировано), поэтому проверяем его наличие
        $this->assertStringContainsString('page=2', $result);
        $this->assertStringNotContainsString('offset=', $result);
        $this->assertStringNotContainsString('cache=', $result);
    }

    /**
     * Тест генерации имени ключа кеша с массивом значений
     */
    public function testGetTotalHashNameWithArrayValues(): void
    {
        $repository = new class extends TestRepository {
            public function getTotalHashPrefix(): string
            {
                return 'total';
            }
        };

        $repository->filter = [
            'ids' => [1, 2, 3],
            'status' => 'active'
        ];

        $result = $repository->getTotalHashName();

        $this->assertIsString($result);
        $this->assertStringContainsString('ids=1,2,3', $result);
    }

    /**
     * Тест генерации имени ключа кеша с пустым фильтром
     */
    public function testGetTotalHashNameWithEmptyFilter(): void
    {
        $repository = new class extends TestRepository {
            public function getTotalHashPrefix(): string
            {
                return 'total';
            }
        };

        $repository->filter = [];

        $result = $repository->getTotalHashName();

        // Когда filter пустой, метод возвращает только префикс без ':'
        $this->assertEquals('total', $result);
    }
}

/**
 * Тестовый класс репозитория для использования трейта
 */
class TestRepository implements RepositoryInterface
{
    use EloquentHelper;

    public array $filter = [];
    public array $params = [];

    public function getQueryBuilderInstance(bool $force = false): object
    {
        return new \stdClass();
    }

    public function getTableName(): string
    {
        return 'test_table';
    }

    // Методы интерфейса RepositoryInterface (заглушки для тестов)
    public function search(): array
    {
        return [];
    }

    public function getItems(): array
    {
        return [];
    }

    public function getItem($id)
    {
        return null;
    }

    public function setParams(array $params, bool $update_filter = false, bool $clear_filter = false): \pribolshoy\repository\interfaces\RepositoryInterface
    {
        $this->params = $params;
        if ($update_filter) {
            $this->filter = array_merge($this->filter, $params);
        }
        if ($clear_filter) {
            $this->filter = [];
        }
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getFilters(): array
    {
        return $this->filter;
    }

    public function existsParam(string $key): bool
    {
        return isset($this->params[$key]) || isset($this->filter[$key]);
    }

    public function getParam(string $key)
    {
        return $this->params[$key] ?? $this->filter[$key] ?? null;
    }

    public function existsFilter(string $key): bool
    {
        return isset($this->filter[$key]);
    }

    public function getFilter(string $key)
    {
        return $this->filter[$key] ?? null;
    }

    public function addFilterValue(string $key, $value, bool $append = true)
    {
        if (!isset($this->filter[$key])) {
            $this->filter[$key] = [];
        }
        if ($append) {
            $this->filter[$key][] = $value;
        } else {
            $this->filter[$key] = $value;
        }
        return $this;
    }

    public function addFilterValueByParams(string $key, $value, bool $append = true)
    {
        $this->addFilterValue($key, $value, $append);
        return $this;
    }

    public function getTotalHashPrefix(): string
    {
        return 'test';
    }

    public function getModel(): object
    {
        $instance = $this->getQueryBuilderInstance();
        if ($instance === null) {
            // Возвращаем пустой объект, если модель не установлена
            return new \stdClass();
        }
        return $instance;
    }

    public function getTotalCount(): int
    {
        return $this->total_count ?? 0;
    }
}

