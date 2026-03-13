<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceTier;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $products = Product::forCompany($companyId)
            ->with('priceTiers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('settings.products', compact('products'));
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

        $product->update([
            'name' => $request->name,
            'default_price' => $request->default_price,
            'is_active' => $request->boolean('is_active', true),
        ]);

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

        $product->delete();
        return back()->with('success', 'Product deleted successfully.');
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

        foreach ($request->tiers as $tierData) {
            $product->priceTiers()->create([
                'min_qty' => $tierData['min_qty'],
                'max_qty' => $tierData['max_qty'] ?? null,
                'price' => $tierData['price'],
            ]);
        }

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
