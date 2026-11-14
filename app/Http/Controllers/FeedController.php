<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\CurrentBranch;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request, CurrentBranch $current)
    {
        $branch = $current->get();
        $token = $request->attributes->get('visitor_token');
        $customerId = $request->user('customer')?->id;

        $posts = Post::published()->forBranch($branch->id)
            ->with(['media', 'product.primaryImage', 'author', 'branch', 'approvedComments.replies'])
            ->withCount(['likes', 'comments'])
            ->withExists(['likes as liked' => fn ($q) => $q->where(fn ($w) => $w->where('visitor_token', $token)->when($customerId, fn ($c) => $c->orWhere('customer_id', $customerId)))])
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->paginate(6);

        return view('feed.index', compact('posts', 'branch'));
    }

    public function show(Request $request, Post $post)
    {
        abort_unless($post->published_at && $post->published_at->isPast(), 404);

        $token = $request->attributes->get('visitor_token');

        $post->load(['media', 'product.primaryImage', 'author', 'branch', 'approvedComments.replies'])
            ->loadCount(['likes', 'comments']);
        $post->liked = $post->isLikedBy($token, null, $request->user('customer')?->id);

        return view('feed.show', compact('post'));
    }
}
