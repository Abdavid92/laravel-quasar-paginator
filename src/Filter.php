<?php

namespace Abdavid92\LaravelQuasarTable;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * @author Abel David.
 */
class Filter
{
    /**
     * Custom filters.
     *
     * @var array<string, Closure>
     */
    private array $customFilters = [];

    /**
     * @param Builder $builder
     * @param mixed $columns
     * @param string $table
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly mixed   $columns,
        private readonly string  $table
    )
    {
        //
    }

    /**
     * @param string|null $filter
     * @return Builder
     */
    public function __invoke(?string $filter): Builder
    {
        if ($filter) {

            foreach ($this->getColumns() as $column) {

                if (array_key_exists($column, $this->customFilters)) {

                    $this->customFilters[$column]($this->builder, $filter);
                } else {

                    $this->builder->orWhere($this->table.'.'.$column, 'LIKE', "%$filter%");
                }
            }
        }

        return $this->builder;
    }

    /**
     * @param array<string, Closure> $customFilters
     * @return $this
     */
    public function setCustomFilters(array $customFilters): self
    {
        $this->customFilters = $customFilters;
        return $this;
    }

    /**
     * @return array
     */
    private function getColumns(): array
    {
        $columns = [];
        $finalColumns = [];

        if (is_string($this->columns)) {

            $columns = json_decode($this->columns, true);
        } else if (is_array($this->columns)) {

            $columns = $this->columns;
        }

        foreach ($columns as $column) {

            if (isset($column['name']) && isset($column['filterable']) && $column['filterable']) {

                $finalColumns[] = $column['name'];
            }
        }

        return $finalColumns;
    }
}