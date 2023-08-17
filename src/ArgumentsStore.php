<?php

namespace Abdavid92\LaravelQuasarTable;

use Illuminate\Support\Facades\Session;

/**
 * @author Abel David.
 */
class ArgumentsStore
{
    private string $globalKey;

    /**
     * @param string $globalKey
     */
    public function __construct(string $globalKey)
    {
        $this->globalKey = "{$globalKey}_datatable";
    }

    /**
     * @return array
     */
    public function getArgs(): array
    {
        return Session::get($this->globalKey, []);
    }

    /**
     * @param array $args
     * @return void
     */
    public function setArgs(array $args): void
    {
        Session::flash($this->globalKey, $args);
    }
}