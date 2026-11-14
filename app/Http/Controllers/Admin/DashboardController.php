<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Product;
use App\Models\Stock;
use App\Models\WhatsappLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $branchId = $user->scopedBranchId();
        $since = now()->subDays(7);

        $stats = [
            'productos' => Product::published()->count(),
            'stock_bajo' => Stock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereColumn('quantity', '<=', 'min_quantity')->count(),
            'leads_nuevos' => WhatsappLead::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'nuevo')->count(),
            'leads_semana' => WhatsappLead::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('created_at', '>=', $since)->count(),
            'likes_semana' => Like::where('created_at', '>=', $since)->count(),
            'comentarios_semana' => Comment::where('created_at', '>=', $since)->count(),
            'comentarios_pendientes' => Comment::where('is_approved', false)->count(),
            'posts' => Post::published()->count(),
        ];

        $leads = WhatsappLead::with(['product', 'variant.attributeValues.attribute', 'post', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()->take(8)->get();

        $comments = Comment::with('commentable')->whereNull('parent_id')->latest()->take(6)->get();

        $topConsultados = WhatsappLead::select('product_id', DB::raw('count(*) as total'))
            ->whereNotNull('product_id')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('product_id')->orderByDesc('total')->take(5)
            ->with('product.primaryImage')->get();

        $topGustados = Product::published()->withCount('likes')->with('primaryImage')
            ->orderByDesc('likes_count')->take(5)->get();

        // Leads por día (últimos 14 días) para el mini gráfico
        $serie = WhatsappLead::select(DB::raw('date(created_at) as d'), DB::raw('count(*) as n'))
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('d')->pluck('n', 'd');
        $dias = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $chart = $dias->map(fn ($d) => ['d' => $d, 'n' => (int) ($serie[$d] ?? 0)])->values();

        // Rendimiento por tienda (últimos 30 días): qué página mueve más
        $byBranch = collect();
        if (! $branchId) {
            $d30 = now()->subDays(30);
            $byBranch = \App\Models\Branch::active()->orderByDesc('is_main')->get()->map(function ($b) use ($d30) {
                $postIds = Post::where('branch_id', $b->id)->pluck('id');

                return [
                    'branch' => $b,
                    'productos' => $b->products()->count(),
                    'stock' => (int) Stock::where('branch_id', $b->id)->sum('quantity'),
                    'posts' => Post::where('branch_id', $b->id)->where('published_at', '>=', $d30)->count(),
                    'likes' => Like::where('likeable_type', Post::class)->whereIn('likeable_id', $postIds)->where('created_at', '>=', $d30)->count(),
                    'comentarios' => Comment::where('commentable_type', Post::class)->whereIn('commentable_id', $postIds)->where('created_at', '>=', $d30)->count(),
                    'leads' => WhatsappLead::where('branch_id', $b->id)->where('created_at', '>=', $d30)->count(),
                    'vendidos' => WhatsappLead::where('branch_id', $b->id)->where('status', 'vendido')->where('created_at', '>=', $d30)->count(),
                ];
            });
        }

        return view('admin.dashboard', compact('stats', 'leads', 'comments', 'topConsultados', 'topGustados', 'chart', 'byBranch'));
    }
}
