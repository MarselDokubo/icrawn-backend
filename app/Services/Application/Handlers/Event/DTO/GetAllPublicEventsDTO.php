<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetAllPublicEventsDTO extends BaseDTO
{
    public function __construct(
        public QueryParamsDTO $queryParams,
    )
    {
    }
}
