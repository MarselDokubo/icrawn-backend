<?php

namespace HiEvents\Http\Actions\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaystackCallbackAction
{
    public function __invoke(Request $request, $eventId, $orderShortId)
    {
        $reference = $request->query('reference');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
        ])->get(env('PAYSTACK_PAYMENT_URL') . '/transaction/verify/' . $reference);

        if ($response->successful() && $response['data']['status'] === 'success') {
            // TODO: Update order status, send confirmation, etc.
            return response()->json(['message' => 'Payment successful via callback']);
        }
        return response()->json(['error' => 'Payment verification failed via callback'], 400);
    }
}
