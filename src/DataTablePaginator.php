<?php

namespace Abdavid92\LaravelQuasarPaginator;

use Closure;
use Countable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Iterator;
use JsonSerializable;

/**
 * @author Abel David.
 *
 * A paginator for Laravel and quasar tables.
 */
class DataTablePaginator implements Arrayable, Jsonable, JsonSerializable, Countable, Iterator
{
    /**
     * Paginator name.
     *
     * @var string
     */
    private string $paginatorName;

    /**
     * Internal paginator.
     *
     * @var LengthAwarePaginator
     */
    private LengthAwarePaginator $paginator;

    /**
     * Iterator pointer.
     *
     * @var int
     */
    private int $pointer = 0;

    /**
     * Query filter.
     *
     * @var string|null
     */
    private ?string $filter;

    /**
     * Custom filter.
     *
     * @var Closure|null
     */
    private ?Closure $customFilter = null;

    /**
     * Query sort.
     *
     * @var string|null
     */
    private ?string $sortBy;

    /**
     * Pages to return.
     *
     * @var int
     */
    private int $perPage = 15;

    /**
     * If descending.
     *
     * @var bool
     */
    private bool $descending;

    /**
     * Columns.
     *
     * @var mixed
     */
    private mixed $columns;

    /**
     * Columns that must add or edit to final result.
     *
     * @var array<string, Closure>
     */
    private array $customColumns = [];

    /**
     * Custom filters.
     *
     * @var array<string, Closure>
     */
    private array $customFilters = [];

    /**
     * @var Closure|null
     */
    private ?Closure $customSorter = null;

    /**
     * Main table name.
     *
     * @var string
     */
    private string $mainTable;

    /**
     * Indicate if the paginator was initialized.
     *
     * @var bool
     */
    private bool $wasInitializedPaginator = false;

    //Keys
    private const PAGINATOR_NAME_KEY = 'paginatorName';
    private const PAGINATION_KEY = 'pagination';
    private const FILTER_KEY = 'filter';
    private const SORT_BY_KEY = 'sortBy';
    private const PER_PAGE_KEY = 'perPage';
    private const DESCENDING_KEY = 'descending';
    private const COLUMNS_KEY = 'columns';

    /**
     * @param Builder $builder
     * @param string|null $sortBy
     * @param bool $descending
     * @param int $perPage
     * @param string|null $paginatorName
     */
    public function __construct(
        private readonly Builder $builder,
        ?string $sortBy = null,
        bool $descending = false,
        int $perPage = 15,
        ?string $paginatorName = null
    )
    {
        $this->initializeProperties(
            $sortBy,
            $descending,
            $perPage,
            $paginatorName
        );
    }

    /**
     * Add or edit a column in each model.
     *
     * @param string $column
     * @param Closure $callback
     * @return $this
     */
    public function customColumn(string $column, Closure $callback): self
    {
        $this->customColumns[$column] = $callback;
        return $this;
    }

    /**
     * Add a custom filter by a column.
     *
     * @param string $column
     * @param Closure $callback
     * @return $this
     */
    public function addCustomFilter(string $column, Closure $callback): self
    {
        $this->customFilters[$column] = $callback;
        return $this;
    }

    /**
     * Set a custom sorter.
     *
     * @param Closure $sorter
     * @return $this
     */
    public function setCustomSorter(Closure $sorter): self
    {
        $this->customSorter = $sorter;
        return $this;
    }

    /**
     * Filter the final result.
     *
     * @param Closure $filter
     * @return $this
     */
    public function filter(Closure $filter): self
    {
        $this->customFilter = $filter;
        return $this;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $this->initializePaginator();

        $pagination = $this->transformItems()->toArray();

        if ($this->customFilter) {

            $pagination['data'] = array_filter($pagination['data'], $this->customFilter);
        }

        return [
            self::PAGINATOR_NAME_KEY => $this->paginatorName,
            self::PAGINATION_KEY => $pagination,
            self::FILTER_KEY => $this->filter,
            self::SORT_BY_KEY => $this->sortBy,
            self::PER_PAGE_KEY => $this->perPage,
            self::DESCENDING_KEY => $this->descending
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function count(): int
    {
        return $this->builder->count();
    }

    /**
     * @inheritdoc
     * @return mixed
     */
    public function current(): mixed
    {
        $this->initializePaginator();

        return $this->paginator->items()[$this->pointer];
    }

    /**
     * @inheritdoc
     * @return void
     */
    public function next(): void
    {
        $this->pointer++;
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function key(): int
    {
        return $this->pointer;
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function valid(): bool
    {
        $this->initializePaginator();
        return $this->pointer < count($this->paginator->items());
    }

    /**
     * @inheritdoc
     * @return void
     */
    public function rewind(): void
    {
        $this->pointer = 0;
    }

    /**
     * @return LengthAwarePaginator
     */
    private function transformItems(): LengthAwarePaginator
    {
        return $this->paginator->through(function (Model $item) {

            foreach ($this->customColumns as $key => $callback) {

                $item->$key = $callback($item);
            }

            return $item;
        });
    }

    /**
     * @return void
     */
    private function initializePaginator(): void
    {
        if (! $this->wasInitializedPaginator) {

            (new Filter($this->builder, $this->columns, $this->mainTable))
                ->setCustomFilters($this->customFilters)($this->filter);

            (new Sorter($this->builder, $this->mainTable))
                ->setCustomSorter($this->customSorter)($this->sortBy, $this->descending);

            $columns = $this->mainTable . '.*';
            $this->paginator = $this->builder->paginate($this->perPage, $columns);
            $this->wasInitializedPaginator = true;
        }
    }

    /**
     * @param string|null $sortBy
     * @param bool $descending
     * @param int $perPage
     * @param string|null $paginatorName
     * @return void
     */
    private function initializeProperties(?string $sortBy, bool $descending, int $perPage, ?string $paginatorName): void
    {
        $this->mainTable = $this->builder->getModel()->getTable();
        $this->paginatorName = $paginatorName ?: $this->mainTable;
        $this->perPage = $perPage;
        $request = app(Request::class);
        $store = app(ArgumentsStore::class, ['globalKey' => $this->paginatorName]);

        $properties = [];

        if ($this->paginatorName == $request->input(self::PAGINATOR_NAME_KEY, $this->paginatorName)) {

            $this->filter = $request->input(self::FILTER_KEY);
            $this->sortBy = $request->input(self::SORT_BY_KEY, $sortBy);
            $this->perPage = $request->input(self::PER_PAGE_KEY, $this->perPage);
            $this->descending = $request->boolean(self::DESCENDING_KEY, $descending);
            $this->columns = $request->input(self::COLUMNS_KEY);

            $properties[self::FILTER_KEY] = $this->filter;
            $properties[self::SORT_BY_KEY] = $this->sortBy;
            $properties[self::PER_PAGE_KEY] = $this->perPage;
            $properties[self::DESCENDING_KEY] = $this->descending;
            $properties[self::COLUMNS_KEY] = $this->columns;
        } else {

            $properties = $store->getArgs();

            $this->filter = $properties[self::FILTER_KEY] ?? null;
            $this->sortBy = $properties[self::SORT_BY_KEY] ?? null;
            $this->perPage = $properties[self::PER_PAGE_KEY] ?? $this->perPage;
            $this->descending = $properties[self::DESCENDING_KEY] ?? false;
            $this->columns = $properties[self::COLUMNS_KEY] ?? null;
        }

        $store->setArgs($properties);
    }
}