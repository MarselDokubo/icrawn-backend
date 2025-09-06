<?php

namespace HiEvents\Support;

use Illuminate\Database\QueryException;
use Throwable;

final class TxnProbe
{
    /**
     * Wrap a step in a labeled try/catch so failures report where/SQL/bindings/dbCode.
     *
     * @template T
     * @param  string   $where
     * @param  callable():T  $fn
     * @return T
     * @throws TxnStepException
     */
    public static function step(string $where, callable $fn)
    {
        try {
            return $fn();
        } catch (QueryException $e) {
            throw TxnStepException::fromQueryException($where, $e);
        } catch (Throwable $e) {
            throw TxnStepException::wrap($where, $e);
        }
    }
}
