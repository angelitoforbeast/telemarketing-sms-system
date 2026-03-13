<?php

namespace App\Http\Controllers\Telemarketing;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Store a new order from the call page (AJAX).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'shipment_id' => 'required|integer|exists:shipments,id',
            'telemarketing_log_id' => 'nullable|integer',
            'order_type_id' => 'required|integer|exists:order_types,id',
            'customer_phone' => 'required|string|max:20',
            'customer_name' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'address_details' => 'nullable|string|max:1000',
            'process_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $order = DB::transaction(function () use ($request) {
                // Calculate total
                $totalAmount = 0;
                foreach ($request->items as $item) {
                    $totalAmount += $item['quantity'] * $item['unit_price'];
                }

                // Create order
                $order = Order::create([
                    'company_id' => $request->user()->company_id,
                    'shipment_id' => $request->shipment_id,
                    'telemarketing_log_id' => $request->telemarketing_log_id,
                    'order_type_id' => $request->order_type_id,
                    'created_by' => $request->user()->id,
                    'customer_phone' => $request->customer_phone,
                    'customer_name' => $request->customer_name,
                    'province' => $request->province,
                    'city' => $request->city,
                    'barangay' => $request->barangay,
                    'address_details' => $request->address_details,
                    'total_amount' => $totalAmount,
                    'process_date' => $request->process_date,
                    'status' => 'pending',
                    'notes' => $request->notes,
                ]);

                // Create order items
                foreach ($request->items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_name' => $item['item_name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['quantity'] * $item['unit_price'],
                    ]);
                }

                return $order;
            });

            Log::info('New order created from call page', [
                'order_id' => $order->id,
                'shipment_id' => $request->shipment_id,
                'customer_phone' => $request->customer_phone,
                'total' => $order->total_amount,
                'items_count' => count($request->items),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'message' => 'Order #' . $order->id . ' created successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create order', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer order history by phone number (AJAX).
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $companyId = $request->user()->company_id;
        $orders = Order::forCompany($companyId)
            ->forCustomerPhone($request->phone)
            ->with(['orderType', 'items', 'creator'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => $order->orderType?->name ?? 'N/A',
                    'type_color' => $order->orderType?->color ?? 'gray',
                    'total' => number_format($order->total_amount, 2),
                    'status' => $order->status,
                    'process_date' => $order->process_date?->format('M d, Y'),
                    'items_count' => $order->items->count(),
                    'items' => $order->items->map(fn($i) => [
                        'name' => $i->item_name,
                        'qty' => $i->quantity,
                        'price' => number_format($i->unit_price, 2),
                        'subtotal' => number_format($i->subtotal, 2),
                    ]),
                    'created_by' => $order->creator?->name ?? 'N/A',
                    'created_at' => $order->created_at->format('M d, Y H:i'),
                    'address' => $order->full_address,
                ];
            }),
            'total_orders' => $orders->count(),
        ]);
    }
}
