<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductActivityLog;
use App\Models\ProductPriceTier;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $products = Product::forCompany($companyId)
            ->with(['priceTiers', 'createdBy', 'updatedBy'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activityLogs = ProductActivityLog::where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('settings.products', compact('products', 'activityLogs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'default_price' => 'required|numeric|min:0',
        ]);

        $product = Product::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->name,
            'default_price' => $request->default_price,
            'is_active' => true,
            'sort_order' => Product::forCompany($request->user()->company_id)->max('sort_order') + 1,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        ProductActivityLog::log($product, $request->user(), 'created', [
            'name' => $product->name,
            'default_price' => '₱' . number_format($product->default_price, 2),
        ]);

        return back()->with('success', 'Product "' . $product->name . '" created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        if ($product->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'default_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $oldValues = [
            'name' => $product->name,
            'default_price' => (float) $product->default_price,
            'is_active' => $product->is_active,
        ];

        $newActive = $request->boolean('is_active', true);

        $product->update([
            'name' => $request->name,
            'default_price' => $request->default_price,
            'is_active' => $newActive,
            'updated_by' => $request->user()->id,
        ]);

        // Log status change separately
        if ($oldValues['is_active'] !== $newActive) {
            ProductActivityLog::log($product, $request->user(), $newActive ? 'activated' : 'deactivated');
        }

        // Log general update
        $changes = [];
        if ($oldValues['name'] !== $request->name) {
            $changes['name'] = $oldValues['name'] . ' → ' . $request->name;
        }
        if ($oldValues['default_price'] != (float) $request->default_price) {
            $changes['default_price'] = '₱' . number_format($oldValues['default_price'], 2) . ' → ₱' . number_format($request->default_price, 2);
        }

        if (!empty($changes)) {
            ProductActivityLog::log($product, $request->user(), 'updated', $changes);
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product)
    {
        if ($product->company_id !== $request->user()->company_id) {
            abort(403);
        }

        // Check if product is used in any order items
        if ($product->id && \DB::table('order_items')->where('product_id', $product->id)->exists()) {
            return back()->with('error', 'Cannot delete: this product is used in existing orders. Deactivate it instead.');
        }

        $productName = $product->name;

        ProductActivityLog::log($product, $request->user(), 'deleted', [
            'name' => $productName,
            'default_price' => '₱' . number_format($product->default_price, 2),
        ]);

        $product->delete();

        return back()->with('success', 'Product "' . $productName . '" deleted successfully.');
    }

    /**
     * Save price tiers for a product (AJAX)
     */
    public function saveTiers(Request $request, Product $product)
    {
        if ($product->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'tiers' => 'present|array',
            'tiers.*.min_qty' => 'required|integer|min:1',
            'tiers.*.max_qty' => 'nullable|integer|min:1',
            'tiers.*.price' => 'required|numeric|min:0',
        ]);

        // Delete existing tiers and recreate
        $product->priceTiers()->delete();

        $tierDetails = [];
        foreach ($request->tiers as $tierData) {
            $product->priceTiers()->create([
                'min_qty' => $tierData['min_qty'],
                'max_qty' => $tierData['max_qty'] ?? null,
                'price' => $tierData['price'],
            ]);
            $tierDetails[] = 'Qty ' . $tierData['min_qty']
                . ($tierData['max_qty'] ? '-' . $tierData['max_qty'] : '+')
                . ' = ₱' . number_format($tierData['price'], 2);
        }

        ProductActivityLog::log($product, $request->user(), 'tiers_updated', [
            'tier_count' => count($request->tiers),
            'tiers' => implode(', ', $tierDetails),
        ]);

        $product->update(['updated_by' => $request->user()->id]);

        return response()->json([
            'success' => true,
            'message' => 'Price tiers saved for ' . $product->name,
        ]);
    }

    /**
     * API: Get products with tiers for the call page dropdown
     */
    public function apiList(Request $request)
    {
        $companyId = $request->user()->company_id;

        $products = Product::forCompany($companyId)
            ->active()
            ->with('priceTiers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'default_price' => (float) $product->default_price,
                    'tiers' => $product->priceTiers->map(function ($tier) {
                        return [
                            'min_qty' => $tier->min_qty,
                            'max_qty' => $tier->max_qty,
                            'price' => (float) $tier->price,
                        ];
                    }),
                ];
            });

        return response()->json($products);
    }
}
