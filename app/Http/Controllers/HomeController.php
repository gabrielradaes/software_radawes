<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use App\Support\CurrentBranch;

class HomeController extends Controller
{
    public function __invoke(CurrentBranch $current)
    {
        $branch = $current->get();

        $featured = Product::published()->inBranch($branch->id)->featured()
            ->with(['primaryImage', 'category'])
            ->withCount('likes')
            ->latest('published_at')
            ->take(8)
            ->get();

        $newest = Product::published()->inBranch($branch->id)
            ->with(['primaryImage', 'category'])
            ->withCount('likes')
            ->latest('published_at')
            ->take(4)
            ->get();

        $posts = Post::published()->forBranch($branch->id)
            ->with(['media', 'product.primaryImage', 'author'])
            ->withCount(['likes', 'comments'])
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('home', compact('branch', 'featured', 'newest', 'posts'));
    }
}
