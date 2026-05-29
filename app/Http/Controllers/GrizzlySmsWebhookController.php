<?php

namespace App\Http\Controllers;

use App\Models\SmsActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GrizzlySmsWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $data = $request->all();

        Log::info('GrizzlySMS webhook received', $data);

        $activationId = $data['activationId'] ?? null;

        if (blank($activationId)) {
            Log::warning('GrizzlySMS webhook: missing activationId', $data);

            return response()->json(['status' => 'ok']);
        }

        $activation = SmsActivation::where('activation_id', (string) $activationId)->first();

        if (! $activation) {
            Log::warning('GrizzlySMS webhook: activation not found', ['activationId' => $activationId]);

            return response()->json(['status' => 'ok']);
        }

        $activation->update([
            'status' => 'code_received',
            'sms_code' => $data['code'] ?? null,
            'sms_text' => $data['text'] ?? null,
            'sms_received_at' => isset($data['receivedAt'])
                ? \Carbon\Carbon::parse($data['receivedAt'])
                : now(),
        ]);

        Log::info('GrizzlySMS webhook: activation updated', [
            'activationId' => $activationId,
            'code' => $data['code'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
