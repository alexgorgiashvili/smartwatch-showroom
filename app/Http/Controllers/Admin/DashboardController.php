<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalProducts = Product::count();
        $totalInquiries = Inquiry::count();
        $totalUsers = User::count();
        $totalAdmins = User::where('is_admin', true)->count();

        $recentInquiries = Inquiry::query()
            ->with('product')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // Stock Metrics
        $lowStockCount = ProductVariant::where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        $outOfStockCount = ProductVariant::where('quantity', '<=', 0)->count();

        $totalInventory = ProductVariant::sum('quantity');

        $recentAdjustments = StockAdjustment::query()
            ->with('variant.product')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // Order Metrics
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalRevenue = Order::whereNotIn('status', ['cancelled'])->sum('total_amount');

        $recentOrders = Order::query()
            ->with('items')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'totalProducts' => $totalProducts,
            'totalInquiries' => $totalInquiries,
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'recentInquiries' => $recentInquiries,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'totalInventory' => $totalInventory,
            'recentAdjustments' => $recentAdjustments,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'totalRevenue' => $totalRevenue,
            'recentOrders' => $recentOrders,
        ]);
    }
}
