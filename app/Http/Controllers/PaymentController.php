<?php

namespace HiEvents\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // Initialize Paystack transaction
    public function initialize(Request $request)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('PAYSTACK_SECRET_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('PAYSTACK_PAYMENT_URL') . '/transaction/initialize', [
            'email' => $request->email,
            'amount' => $request->amount * 100, // Paystack expects amount in kobo
            'callback_url' => url('/paystack/callback'),
        ]);

        if ($response->successful()) {
            return redirect($response['data']['authorization_url']);
        }
        return response()->json(['error' => 'Unable to initialize payment'], 500);
    }

    // Verify Paystack transaction
    public function verify(Request $request)
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
