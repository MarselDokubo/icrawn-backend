<?php

namespace HiEvents\Exceptions;

use Illuminate\Database\QueryException;

class TxnStepException extends \RuntimeException
{
    public string $step;
    public ?string $sql;
    /** @var array<int,mixed> */
    public array $bindings;
    public ?string $sqlState;

    /**
     * @param array<int,mixed> $bindings
     */
    public function __construct(string $step, \Throwable $prev, ?string $sql = null, array $bindings = [])
    {
        parent::__construct($prev->getMessage(), (int)($prev->getCode() ?: 0), $prev);
        $this->step     = $step;
        $this->sql      = $sql;
        $this->bindings = $bindings;
        // For Postgres this is the useful code: 23505, 23503, 23502, etc.
        $this->sqlState = $prev instanceof QueryException
            ? ($prev->getCode() ?: ($prev->errorInfo[0] ?? null))
            : null;
    }
}
