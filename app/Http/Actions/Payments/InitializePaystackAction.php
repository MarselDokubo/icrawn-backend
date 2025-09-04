<?php

namespace HiEvents\Http\Actions\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InitializePaystackAction
{
    public function __invoke(Request $request)
    {
        $eventId = $request->input('event_id');
        $orderShortId = $request->input('order_short_id');
        $callbackUrl = url(
            "/public/events/{$eventId}/order/{$orderShortId}/paystack/callback"
        );
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('PAYSTACK_PAYMENT_URL') . '/transaction/initialize', [
            'email' => $request->email,
            'amount' => $request->amount * 100,
            'callback_url' => $callbackUrl,
        ]);

        if ($response->successful()) {
            return response()->json([
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $response['data']['reference'],
            ]);
        }
        return response()->json(['error' => 'Unable to initialize payment'], 500);
    }
}
// This action initializes a Paystack transaction by sending a POST request to the Paystack API.
// It expects the request to contain an email and an amount, which it sends to Paystack