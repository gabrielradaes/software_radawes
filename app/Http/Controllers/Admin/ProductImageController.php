<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $request->validate(['images' => ['required', 'array'], 'images.*' => ['image', 'max:4096']]);

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $order = (int) $product->images()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $path = $file->store('products', 'uploads');
            $product->images()->create([
                'path' => 'uploads/'.$path,
                'alt' => $product->name,
                'sort_order' => ++$order,
                'is_primary' => ! $hasPrimary,
            ]);
            $hasPrimary = true;
        }

        return back()->with('status', 'Imágenes subidas.');
    }

    public function primary(ProductImage $image)
    {
        $image->product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('status', 'Imagen principal actualizada.');
    }

    public function destroy(ProductImage $image)
    {
        $product = $image->product;
        if (str_starts_with($image->path, 'uploads/')) {
            Storage::disk('uploads')->delete(substr($image->path, 8));
        }
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary && $next = $product->images()->first()) {
            $next->update(['is_primary' => true]);
        }

        return back()->with('status', 'Imagen eliminada.');
    }
}
