<?php

namespace HiEvents\Http\Actions\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifyPaystackAction
{
    public function __invoke(Request $request)
    {
        $reference = $request->query('reference');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
        ])->get(env('PAYSTACK_PAYMENT_URL') . '/transaction/verify/' . $reference);

        if ($response->successful() && $response['data']['status'] === 'success') {
            // Handle successful payment (e.g., update order, send email)
            return response()->json(['message' => 'Payment successful']);
        }
        return response()->json(['error' => 'Payment verification failed'], 400);
    }
}
