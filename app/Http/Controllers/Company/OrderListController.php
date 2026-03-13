<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class OrderListController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Filters
        $query = Order::forCompany($companyId)
            ->with(['orderType', 'items', 'creator', 'shipment']);

        // Date filter
        $dateFilter = $request->get('date_filter', 'today');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($dateFilter === 'today') {
            $query->whereDate('process_date', today());
        } elseif ($dateFilter === 'yesterday') {
            $query->whereDate('process_date', today()->subDay());
        } elseif ($dateFilter === 'this_week') {
            $query->whereBetween('process_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'this_month') {
            $query->whereMonth('process_date', now()->month)->whereYear('process_date', now()->year);
        } elseif ($dateFilter === 'custom' && $dateFrom && $dateTo) {
            $query->whereBetween('process_date', [$dateFrom, $dateTo]);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Order type filter
        if ($request->filled('order_type_id')) {
            $query->where('order_type_id', $request->order_type_id);
        }

        // Search (phone or name)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_phone', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%");
            });
        }

        // Created by filter
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        $orders = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        // Stats
        $todayStats = Order::forCompany($companyId)->whereDate('process_date', today());
        $stats = [
            'today_count' => (clone $todayStats)->count(),
            'today_total' => (clone $todayStats)->sum('total_amount'),
            'today_pending' => (clone $todayStats)->where('status', 'pending')->count(),
            'today_confirmed' => (clone $todayStats)->where('status', 'confirmed')->count(),
        ];

        // Filter options
        $orderTypes = OrderType::forCompany($companyId)->active()->orderBy('sort_order')->get();
        $telemarketers = User::where('company_id', $companyId)->where('is_telemarketing_active', true)->orderBy('name')->get();

        return view('orders.index', compact('orders', 'stats', 'orderTypes', 'telemarketers', 'dateFilter'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        if ($order->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
        ]);

        $order->update(['status' => $request->status]);

        return back()->with('success', "Order #{$order->id} status updated to {$request->status}.");
    }

    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Order::forCompany($companyId)
            ->with(['orderType', 'items', 'creator']);

        // Apply same filters as index
        $dateFilter = $request->get('date_filter', 'today');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($dateFilter === 'today') {
            $query->whereDate('process_date', today());
        } elseif ($dateFilter === 'yesterday') {
            $query->whereDate('process_date', today()->subDay());
        } elseif ($dateFilter === 'this_week') {
            $query->whereBetween('process_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'this_month') {
            $query->whereMonth('process_date', now()->month)->whereYear('process_date', now()->year);
        } elseif ($dateFilter === 'custom' && $dateFrom && $dateTo) {
            $query->whereBetween('process_date', [$dateFrom, $dateTo]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('order_type_id')) {
            $query->where('order_type_id', $request->order_type_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_phone', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%");
            });
        }
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        $orders = $query->orderByDesc('created_at')->get();

        // Build CSV
        $csv = "Order ID,Order Type,Customer Name,Customer Phone,Province,City,Barangay,Address Details,Items,Total Amount,Process Date,Status,Created By,Created At\n";

        foreach ($orders as $order) {
            $itemsSummary = $order->items->map(function ($i) {
                return "{$i->item_name} x{$i->quantity} (₱" . number_format($i->unit_price, 2) . ")";
            })->implode(' | ');

            $csv .= implode(',', [
                $order->id,
                '"' . ($order->orderType?->name ?? 'N/A') . '"',
                '"' . str_replace('"', '""', $order->customer_name) . '"',
                '"' . $order->customer_phone . '"',
                '"' . ($order->province ?? '') . '"',
                '"' . ($order->city ?? '') . '"',
                '"' . ($order->barangay ?? '') . '"',
                '"' . str_replace('"', '""', $order->address_details ?? '') . '"',
                '"' . str_replace('"', '""', $itemsSummary) . '"',
                $order->total_amount,
                $order->process_date?->format('Y-m-d'),
                $order->status,
                '"' . ($order->creator?->name ?? 'N/A') . '"',
                $order->created_at->format('Y-m-d H:i'),
            ]) . "\n";
        }

        $filename = 'orders_' . ($dateFilter === 'today' ? 'today' : $dateFilter) . '_' . now()->format('Ymd_His') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $order->load(['orderType', 'items', 'creator', 'shipment']);

        return view('orders.show', compact('order'));
    }
}
