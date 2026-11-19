<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $scoped = $request->user()->scopedBranchId();
        $branches = Branch::active()->orderByDesc('is_main')->get();
        $branchId = $scoped ?? ($request->filled('sucursal') ? (int) $request->sucursal : null);

        // Se recalcula como mucho cada 30 minutos (suma miles de ventas)
        $data = Cache::remember('pronostico:'.($branchId ?? 'todas').':'.now()->format('Y-m-d-H').':'.intdiv(now()->minute, 30), 1800,
            fn () => ForecastService::build($branchId));

        return view('admin.forecast.index', $data + [
            'branches' => $branches, 'branchId' => $branchId, 'scoped' => $scoped,
            'branchName' => $branchId ? $branches->firstWhere('id', $branchId)?->name : 'todas las tiendas',
        ]);
    }
}
