<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filtro', 'todos');

        $comments = Comment::with(['commentable', 'user', 'replies'])
            ->whereNull('parent_id')
            ->when($filter === 'pendientes', fn ($q) => $q->where('is_approved', false))
            ->when($filter === 'sin-respuesta', fn ($q) => $q->whereDoesntHave('replies', fn ($r) => $r->whereNotNull('user_id')))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.comments.index', compact('comments', 'filter'));
    }

    public function toggle(Comment $comment)
    {
        $comment->update(['is_approved' => ! $comment->is_approved]);

        return back()->with('status', $comment->is_approved ? 'Comentario aprobado.' : 'Comentario ocultado.');
    }

    public function reply(Request $request, Comment $comment)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']], [], ['body' => 'respuesta']);

        $comment->commentable->comments()->create([
            'parent_id' => $comment->id,
            'user_id' => $request->user()->id,
            'author_name' => $request->user()->branch?->name ?? 'RyE',
            'body' => $data['body'],
            'is_approved' => true,
        ]);

        return back()->with('status', 'Respuesta publicada.');
    }

    public function destroy(Comment $comment)
    {
        ensure_can_delete();
        $comment->delete();

        return back()->with('status', 'Comentario eliminado.');
    }
}
