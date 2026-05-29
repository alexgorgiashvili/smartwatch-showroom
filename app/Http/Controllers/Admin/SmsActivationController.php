<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsActivation;
use App\Services\GrizzlySmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsActivationController extends Controller
{
    public function __construct(
        private GrizzlySmsService $grizzlySms
    ) {}

    public function index(Request $request)
    {
        // Simple listing - latest first, pending at top
        $activations = SmsActivation::latest()
            ->orderByRaw("CASE WHEN status IN ('pending', 'ready') THEN 0 ELSE 1 END")
            ->paginate(30);

        // Stats
        $stats = [
            'total' => SmsActivation::count(),
            'pending' => SmsActivation::whereIn('status', ['pending', 'ready'])->count(),
            'completed' => SmsActivation::where('status', 'completed')->count(),
            'cancelled' => SmsActivation::where('status', 'cancelled')->count(),
            'total_cost' => SmsActivation::whereNotNull('cost')->sum('cost'),
        ];

        $balance = $this->grizzlySms->isConfigured()
            ? $this->grizzlySms->getBalance()
            : ['success' => false, 'balance' => 0];

        $countries = $this->grizzlySms->isConfigured()
            ? $this->grizzlySms->getCountries()
            : [];

        $view = view('admin.sms-activation.index', [
            'activations' => $activations,
            'balance' => $balance['balance'] ?? 0,
            'countries' => $countries,
            'configured' => $this->grizzlySms->isConfigured(),
            'stats' => $stats,
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function getServices(Request $request): JsonResponse
    {
        $country = $request->input('country');

        if (!$country) {
            return response()->json(['success' => false, 'error' => 'Country required']);
        }

        $services = $this->grizzlySms->getServicesForCountry($country);

        return response()->json(['success' => true, 'services' => $services]);
    }

    public function getNumber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => 'required|string',
            'service' => 'required|string',
        ]);

        $result = $this->grizzlySms->buyNumber($data['service'], $data['country']);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'activation' => $result['activation'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to get number',
        ]);
    }

    public function setStatus(Request $request, SmsActivation $activation): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:1,3,6,8',
        ]);

        $result = $this->grizzlySms->setStatus($activation->activation_id, (int) $data['status']);

        if ($result['success']) {
            $activation->refresh();
            return response()->json(['success' => true, 'activation' => $activation]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update status',
        ]);
    }

    public function checkStatus(SmsActivation $activation): JsonResponse
    {
        $result = $this->grizzlySms->checkStatus($activation->activation_id);

        if ($result['success']) {
            $activation->refresh();
            return response()->json(['success' => true, 'activation' => $activation]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to check status',
        ]);
    }
}
