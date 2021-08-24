<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Http\Controllers\AuthController;
use JWTAuth;

class ProductController extends Controller
{
    //
    public function order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|digits_between:1,4',
            'quantity' => 'required|digits_between:1,4',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $productId = $request->get('product_id');
        $quantity = $request->get('quantity');
        $user = JWTAuth::parseToken()->authenticate();

        $products = Product::find($productId);

        if ($products == null) {
            return response()->json(['message' => 'Sorry, the product does not exist'], 404);
        }

        if ($quantity > $products->stock) {
            return response()->json(['message' => 'Failed to order this product due to unavailability of the stock'], 400);
        }

        Order::create(array_merge($request->all(), ['user_id' => $user->id]));
        Product::where('id', $productId)->update(['stock' => ($products->stock - $quantity)]);
        return response()->json(['message' => 'You have successfully ordered this product'], 201);
    }
}
