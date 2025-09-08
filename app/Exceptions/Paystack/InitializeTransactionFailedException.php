<?php

namespace HiEvents\Exceptions\Paystack;

class InitializeTransactionFailedException extends \RuntimeException {}



// Had this previously

// namespace HiEvents\Exceptions\Paystack;

// use Exception;

// class InitializeTransactionFailedException extends Exception
// {
//     public static function fromApi(string $message, int $code = 0): self
//     {
//         return new self($message, $code);
//     }

//     public static function fromThrowable(\Throwable $e, string $prefix = 'Paystack initialize failed'): self
//     {
//         return new self("{$prefix}: {$e->getMessage()}", (int)$e->getCode(), $e);
//     }
// }