<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['users', 'stocks', 'whatsappLeads'])->orderByDesc('is_main')->get();

        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('admin.branches.form', ['branch' => new Branch(['is_active' => true, 'country' => 'Bolivia']), 'links' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['city'].'-'.Str::limit($data['name'], 20, ''));
        if (Branch::where('slug', $data['slug'])->exists()) {
            $data['slug'] .= '-'.Str::lower(Str::random(3));
        }

        DB::transaction(function () use ($data, $request) {
            $branch = Branch::create($data);
            $this->syncLinks($request, $branch);
            if ($branch->is_main) {
                Branch::whereKeyNot($branch->id)->update(['is_main' => false]);
            }
        });

        return redirect()->route('admin.sucursales.index')->with('status', 'Sucursal creada.');
    }

    public function edit(Branch $branch)
    {
        return view('admin.branches.form', ['branch' => $branch, 'links' => $branch->socialLinks->toArray()]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validated($request, $branch);

        DB::transaction(function () use ($data, $request, $branch) {
            $branch->update($data);
            $this->syncLinks($request, $branch);
            if ($branch->is_main) {
                Branch::whereKeyNot($branch->id)->update(['is_main' => false]);
            }
        });

        return redirect()->route('admin.sucursales.index')->with('status', 'Sucursal actualizada.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->is_main || $branch->sales()->exists() || $branch->users()->exists()) {
            $branch->update(['is_active' => false]);

            return back()->with('status', 'La sucursal tiene historial o es la principal: se desactivó.');
        }
        $branch->delete();

        return back()->with('status', 'Sucursal eliminada.');
    }

    protected function validated(Request $request, ?Branch $branch = null): array
    {
        // Aceptar el dominio pegado como enlace (https://www.ejemplo.com/) y limpiarlo antes de validar
        if ($request->filled('domain')) {
            $request->merge(['domain' => strtolower(preg_replace('~^https?://|^www\.|/.*$~i', '', trim($request->input('domain'))))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9.-]+$/', Rule::unique('branches')->ignore($branch)],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'web_stock_buffer' => ['nullable', 'integer', 'min:0', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
            'links' => ['nullable', 'array'],
            'links.*.platform' => ['nullable', 'in:facebook,instagram,tiktok,youtube,web'],
            'links.*.handle' => ['nullable', 'string', 'max:120'],
            'links.*.url' => ['nullable', 'url', 'max:255'],
        ], [], ['name' => 'nombre', 'city' => 'ciudad', 'whatsapp' => 'WhatsApp', 'domain' => 'dominio']);

        $data['whatsapp'] = preg_replace('/\D+/', '', $data['whatsapp']);
        $data['domain'] = isset($data['domain']) ? strtolower(preg_replace('~^https?://|^www\.|/.*$~', '', $data['domain'])) ?: null : null;
        $data['is_main'] = $request->boolean('is_main');
        $data['web_stock_buffer'] = (int) ($data['web_stock_buffer'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        unset($data['links']);

        return $data;
    }

    protected function syncLinks(Request $request, Branch $branch): void
    {
        $branch->socialLinks()->delete();
        foreach ($request->input('links', []) as $i => $l) {
            if (empty($l['url']) || empty($l['platform'])) {
                continue;
            }
            $branch->socialLinks()->create([
                'platform' => $l['platform'],
                'handle' => $l['handle'] ?? null,
                'url' => $l['url'],
                'sort_order' => $i,
            ]);
        }
    }
}
