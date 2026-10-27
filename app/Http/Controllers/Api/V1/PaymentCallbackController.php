<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives provider callbacks. Responses intentionally reveal nothing beyond an outcome code.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        $result = $payments->handleCallback($provider, $request);

        return response()->json(['message' => $result['message']], $result['status']);
    }
}
