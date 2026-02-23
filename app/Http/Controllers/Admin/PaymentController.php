<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->value();
        $dateFrom = $request->string('date_from')->value();
        $dateTo = $request->string('date_to')->value();
        $search = trim($request->string('search')->value());

        $payments = PaymentLog::query()
            ->with('order')
            ->when(
                filled($status),
                fn ($query) => $query->where('chveni_statusi', $status)
            )
            ->when(
                filled($dateFrom),
                fn ($query) => $query->whereDate('created_at', '>=', $dateFrom)
            )
            ->when(
                filled($dateTo),
                fn ($query) => $query->whereDate('created_at', '<=', $dateTo)
            )
            ->when(
                filled($search),
                function ($query) use ($search) {
                    $query->where(function ($innerQuery) use ($search) {
                        $innerQuery->where('bog_order_id', 'like', "%{$search}%")
                            ->orWhere('external_order_id', 'like', "%{$search}%")
                            ->orWhereHas('order', function ($orderQuery) use ($search) {
                                $orderQuery->where('order_number', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->latest()
            ->paginate(25)
            ->appends($request->query());

        return view('admin.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ]);
    }
}
