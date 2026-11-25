<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::roots()->with(['children' => fn ($q) => $q->withCount('products')])->withCount('products')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true]), 'parents' => Category::roots()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']);
        if (Category::where('slug', $data['slug'])->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(3));
        }
        Category::create($data);

        return redirect()->route('admin.categorias.index')->with('status', 'Categoría creada.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => Category::roots()->whereKeyNot($category->id)->get()]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request));

        return redirect()->route('admin.categorias.index')->with('status', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        ensure_can_delete();
        if ($category->products()->exists() || $category->children()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar: tiene productos o subcategorías.']);
        }
        $category->delete();

        return back()->with('status', 'Categoría eliminada.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nombre']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
