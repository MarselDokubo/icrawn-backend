<?php
namespace HiEvents\Support;

use HiEvents\Exceptions\TxnStepException as StepEx;
use Illuminate\Database\QueryException;

final class TxnProbe
{
    /**
     * Wrap a unit of DB work so we can surface step/sql/bindings on failure.
     *
     * @template T
     * @param  string   $where
     * @param  callable():T  $fn
     * @return T
     * @throws StepEx
     */
    public static function step(string $where, callable $fn)
    {
        try {
            return $fn();
        } catch (StepEx $e) {
            // Preserve an inner TxnStepException (keep its sql + where)
            if (empty($e->step)) { $e->step = $where; }
            throw $e;
        } catch (QueryException $e) {
            throw new \HiEvents\Exceptions\TxnStepException(
                step:     $where,
                prev:     $e,
                sql:      $e->getSql(),
                bindings: $e->getBindings(),
            );
        } catch (\Throwable $e) {
            throw new \HiEvents\Exceptions\TxnStepException(step: $where, prev: $e);
        }
    }
}
