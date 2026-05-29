<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentLogController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentLog::with('order:id,order_number,customer_name,total_amount,currency,payment_status');

        // Filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($chveniStatusi = $request->query('chveni_statusi')) {
            $query->where('chveni_statusi', $chveniStatusi);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bog_order_id', 'like', "%{$search}%")
                    ->orWhere('external_order_id', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        $paymentLogs = $query->orderBy('created_at', 'desc')
            ->paginate(25);

        $filters = [
            'status' => $status,
            'chveni_statusi' => $chveniStatusi,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => $search,
        ];

        $view = view('admin.payments.index', [
            'paymentLogs' => $paymentLogs,
            'filters' => $filters,
            'totalCount' => PaymentLog::count(),
            'todayCount' => PaymentLog::whereDate('created_at', today())->count(),
        ]);

        return $this->renderPjaxView($request, $view);
    }

    public function show(Request $request, PaymentLog $paymentLog)
    {
        $paymentLog->load('order:id,order_number,customer_name,customer_phone,total_amount,currency,payment_status,payment_type,order_source,created_at');

        $view = view('admin.payments.show', [
            'paymentLog' => $paymentLog,
        ]);

        return $this->renderPjaxView($request, $view);
    }
}
