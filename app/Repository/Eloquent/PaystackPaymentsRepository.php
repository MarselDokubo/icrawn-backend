<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\PaystackPaymentDomainObjectAbstract as Defs;
use HiEvents\DomainObjects\PaystackPaymentDomainObject;
use HiEvents\Repository\Interfaces\PaystackPaymentsRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PaystackPaymentsRepository implements PaystackPaymentsRepositoryInterface
{
    public function create(array $data): PaystackPaymentDomainObject
    {
        $id = DB::table(Defs::TABLE)
            ->insertGetId([
                Defs::ORDER_ID          => $data[Defs::ORDER_ID],
                Defs::REFERENCE         => $data[Defs::REFERENCE],
                Defs::AUTHORIZATION_URL => $data[Defs::AUTHORIZATION_URL],
                Defs::ACCESS_CODE       => $data[Defs::ACCESS_CODE] ?? null,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

        return $this->mapRowToDomain(
            DB::table(Defs::TABLE)->where('id', $id)->first()
        );
    }

    public function findByOrderId(int $orderId): ?PaystackPaymentDomainObject
    {
        $row = DB::table(Defs::TABLE)->where(Defs::ORDER_ID, $orderId)->first();
        return $row ? $this->mapRowToDomain($row) : null;
    }

    private function mapRowToDomain(object $row): PaystackPaymentDomainObject
    {
        return new PaystackPaymentDomainObject(
            id: (int) $row->id,
            orderId: (int) $row->{Defs::ORDER_ID},
            reference: (string) $row->{Defs::REFERENCE},
            authorizationUrl: (string) $row->{Defs::AUTHORIZATION_URL},
            accessCode: $row->{Defs::ACCESS_CODE} ?? null,
        );
    }
}
