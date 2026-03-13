<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\OrderType;
use Illuminate\Http\Request;

class OrderTypeController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $orderTypes = OrderType::forCompany($companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.order-types', compact('orderTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'color' => 'required|string|max:20',
        ]);

        OrderType::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(str_replace(' ', '_', $request->name)),
            'color' => $request->color,
            'is_active' => true,
            'sort_order' => OrderType::forCompany($request->user()->company_id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Order type created successfully.');
    }

    public function update(Request $request, OrderType $orderType)
    {
        // Ensure same company
        if ($orderType->company_id && $orderType->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'color' => 'required|string|max:20',
            'is_active' => 'boolean',
        ]);

        $orderType->update([
            'name' => $request->name,
            'code' => $request->code,
            'color' => $request->color,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Order type updated successfully.');
    }

    public function destroy(Request $request, OrderType $orderType)
    {
        if ($orderType->company_id && $orderType->company_id !== $request->user()->company_id) {
            abort(403);
        }

        if ($orderType->is_system) {
            return back()->with('error', 'System order types cannot be deleted.');
        }

        // Check if any orders use this type
        if ($orderType->orders()->exists()) {
            return back()->with('error', 'Cannot delete: this order type is used by existing orders.');
        }

        $orderType->delete();
        return back()->with('success', 'Order type deleted successfully.');
    }
}
