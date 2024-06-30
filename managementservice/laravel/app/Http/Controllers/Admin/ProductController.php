<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $name = $request->input('name');
        $sku = $request->input('sku');
        // $vehicle_type = $request->input('vehicle_type');
        $orderBy = $request->input('order_by', 'id');
		$sortBy = $request->input('sort_by', 'asc');

        $product = Product::orderBy($orderBy, $sortBy);

        if ($name) {
            $product = $product->where('name', 'like', '%' . $name . '%');
        }

        if ($sku) {
            $product = $product->where('sku', 'like', '%' . $sku . '%');
        }

        // if ($vehicle_type) {
        //     $product = $product->where('vehicle_type', 'like', '%' . $vehicle_type . '%');
        // }

        $product=$product->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of all products',
            'data' => $product
        ]);
    }

    public function getAll()

    {
        $product = Product::where('status','Active')->get(['id', 'name', 'sku', 'price', 'quantity']);
        return response()->json([
            'success' => true,
            'message' => 'List of all products',
            'data' => $product
        ]);
    }
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $product = new Product();
        $product->name = $validatedData['name'];
        $product->sku = $product->generateSku($product->name);
        $product->description = $validatedData['description'];
        $product->price = $validatedData['price'];
        $product->cost = $validatedData['cost'];
        $product->quantity = $validatedData['quantity'];

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/products'), $imageName);
            $product->image = $imageName;
        }

        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ]);
    }
    public function show($id)
    {
        $product = Product::find($id);
        if ($product) {
            return response()->json([
                'success' => true,
                'message' => 'Product found',
                'data' => $product
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'data' => null
            ]);
        }
    }
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if ($product) {
            $nameChanged = false;
            if ($request->filled('name') && $request->input('name') !== $product->name) {
                $product->name = $request->input('name');
                $nameChanged = true;
            }

            if ($nameChanged) {
                $product->sku = $product->generateSku($product->name);
            }

            $product->description = $request->filled('description') ? $request->input('description') : $product->description;
            $product->price = $request->filled('price') ? $request->input('price') : $product->price;
            $product->cost = $request->filled('cost') ? $request->input('cost') : $product->cost;
            $product->quantity = $request->filled('quantity') ? $request->input('quantity') : $product->quantity;
            $product->status = $request->filled('status') ? $request->input('status') : $product->status;

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('images/products'), $imageName);
                $product->image = $imageName;
            }

            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'data' => null
            ]);
        }
    }
    public function destroy($id)
    {
        $product = Product::find($id);
        if ($product) {
            $product->delete();
            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
                'data' => $product
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'data' => null
            ]);
        }
    }
}
