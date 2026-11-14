<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Post;

/** Página raíz: elige tu tienda. */
class LandingController extends Controller
{
    public function __invoke()
    {
        $branches = Branch::active()->with('socialLinks')->withCount('products')->orderByDesc('is_main')->orderBy('name')->get();

        if ($branches->count() === 1) {
            return redirect()->route('home', $branches->first());
        }

        $latest = Post::published()->whereNotNull('branch_id')->with(['branch', 'media'])->withCount('likes')
            ->latest('published_at')->take(6)->get();

        return view('landing', compact('branches', 'latest'));
    }
}
