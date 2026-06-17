<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Bridge\BridgeAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getDashboardData();
        $view = view('admin.dashboard', $data);

        return $this->renderPjaxView($request, $view);
    }

    private function getDashboardData(): array
    {
        // ── Overview Stats ──
        $totalProducts = Product::count();
        $totalInquiries = Inquiry::count();
        $totalUsers = User::count();
        $totalAdmins = User::where('is_admin', true)->count();

        // ── Commerce Stats ──
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalRevenue = (float) Order::whereNotIn('status', ['cancelled'])->sum('total_amount');
        $completedPayments = Order::where('payment_status', 'completed')->count();
        $pendingPayments = Order::where('payment_status', 'pending')
            ->whereNotNull('payment_type')->count();
        $rejectedPayments = Order::where('payment_status', 'rejected')->count();

        // ── Inventory Stats ──
        $lowStockCount = ProductVariant::where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')->count();
        $outOfStockCount = ProductVariant::where('quantity', '<=', 0)->count();
        $totalInventory = (int) ProductVariant::sum('quantity');
        $recentAdjustments = StockAdjustment::where('created_at', '>=', now()->subDays(7))->count();

        // ── Chatbot Stats ──
        $chatbotStats = $this->getChatbotStats();

        // ── Inbox Stats ──
        $unreadConversations = Conversation::where('unread_count', '>', 0)->count();

        // ── Recent Orders (last 5) ──
        $recentOrders = Order::with('items')
            ->latest()
            ->take(5)
            ->get(['id', 'order_number', 'customer_name', 'customer_phone', 'total_amount', 'currency', 'status', 'payment_status', 'order_source', 'created_at']);

        // ── Recent Inquiries (last 5) ──
        $recentInquiries = Inquiry::with('product:id,name_en,name_ka')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'product_id', 'preferred_contact', 'message', 'created_at']);

        // ── Recent Stock Adjustments (last 5) ──
        $recentStockAdjustments = StockAdjustment::with('variant.product:id,name_en')
            ->latest()
            ->take(5)
            ->get();

        // ── Orders Chart (last 30 days) ──
        $ordersChart = $this->getOrdersChartData();
        $bridgeAlerts = app(BridgeAlertService::class)->alerts();

        return compact(
            'totalProducts', 'totalInquiries', 'totalUsers', 'totalAdmins',
            'totalOrders', 'pendingOrders', 'totalRevenue', 'completedPayments', 'pendingPayments', 'rejectedPayments',
            'lowStockCount', 'outOfStockCount', 'totalInventory', 'recentAdjustments',
            'chatbotStats', 'unreadConversations',
            'recentOrders', 'recentInquiries', 'recentStockAdjustments',
            'ordersChart', 'bridgeAlerts'
        );
    }

    private function getChatbotStats(): array
    {
        try {
            $service = app(\App\Services\Chatbot\ChatbotQualityMetricsService::class);
            $summary = $service->getDailySummary();

            return [
                'responses_today' => (int) ($summary['counts']['response_total'] ?? 0),
                'fallback_rate' => (float) ($summary['rates']['fallback_rate'] ?? 0),
                'non_georgian_rate' => (float) ($summary['rates']['non_georgian_rate'] ?? 0),
                'auto_reply_accept_rate' => (float) ($summary['rates']['auto_reply_accept_rate'] ?? 0),
                'provider_incidents' => (int) ($summary['counts']['provider_incident_total'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'responses_today' => 0,
                'fallback_rate' => 0,
                'non_georgian_rate' => 0,
                'auto_reply_accept_rate' => 0,
                'provider_incidents' => 0,
            ];
        }
    }

    private function getOrdersChartData(): array
    {
        $days = 30;
        $startDate = Carbon::today()->subDays($days - 1);

        $orders = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('COALESCE(SUM(total_amount), 0) as revenue')
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $counts = [];
        $revenues = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('M d');
            $counts[] = (int) ($orders[$key]->count ?? 0);
            $revenues[] = round((float) ($orders[$key]->revenue ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'orders' => $counts,
            'revenue' => $revenues,
        ];
    }
}
