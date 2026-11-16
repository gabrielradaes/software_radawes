<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $request->validate(['images' => ['required', 'array'], 'images.*' => [ImageUpload::rule(6144)]]);

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $order = (int) $product->images()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $path = ImageUpload::store($file, 'products');
            $product->images()->create([
                'path' => $path,
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
        // Directo en la BD: si ya era la principal, $image->update() no guardaría nada (sin cambios)
        $image->product->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        ProductImage::whereKey($image->id)->update(['is_primary' => true]);

        return back()->with('status', 'Imagen principal actualizada.');
    }

    public function destroy(ProductImage $image)
    {
        $product = $image->product;
        // Las publicaciones pueden reutilizar fotos del producto: solo se borra el archivo si nadie más lo usa
        if (! \App\Models\PostMedia::where('path', $image->path)->exists()) {
            ImageUpload::delete($image->path);
        }
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary && $next = $product->images()->first()) {
            $next->update(['is_primary' => true]);
        }

        return back()->with('status', 'Imagen eliminada.');
    }
}
