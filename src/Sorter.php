<?php

namespace Abdavid92\LaravelQuasarPaginator;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * @author Abel David.
 */
class Sorter
{
    /**
     * @var Closure|null
     */
    private ?Closure $customSorter = null;

    /**
     * @param Builder $builder
     * @param string $table
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly string $table
    )
    {
        //
    }

    /**
     * @param Closure|null $customSorter
     * @return $this
     */
    public function setCustomSorter(?Closure $customSorter): self
    {
        $this->customSorter = $customSorter;
        return $this;
    }

    /**
     * @param string|null $sort
     * @param bool $descending
     * @return Builder
     */
    public function __invoke(?string $sort, bool $descending): Builder
    {
        if ($sort) {

            if ($this->customSorter) {

                ($this->customSorter)($sort, $descending);
            } else {

                $this->builder->orderBy($this->table.'.'.$sort, $descending ? 'desc' : 'asc');
            }
        }

        return $this->builder;
    }
}