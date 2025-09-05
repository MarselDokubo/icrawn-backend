<?php

namespace HiEvents\Support;

use Closure;
use Illuminate\Database\QueryException;
use HiEvents\Exceptions\TxnStepException;

class TxnProbe
{
    /**
     * Wrap a DB step; on failure rethrow with the step name + SQL + bindings.
     */
    public static function step(string $name, Closure $fn)
    {
        try {
            return $fn();
        } catch (QueryException $qe) {
            throw new TxnStepException($name, $qe, $qe->getSql(), $qe->getBindings());
        } catch (\Throwable $e) {
            throw new TxnStepException($name, $e);
        }
    }
}
