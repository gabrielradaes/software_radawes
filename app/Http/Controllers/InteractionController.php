<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class InteractionController extends Controller
{
    protected function resolve(string $type, int $id)
    {
        return match ($type) {
            'post' => Post::published()->findOrFail($id),
            'product' => Product::published()->findOrFail($id),
        };
    }

    public function toggleLike(Request $request, string $type, int $id)
    {
        $model = $this->resolve($type, $id);

        $liked = $model->toggleLike(
            $request->attributes->get('visitor_token'),
            $request->user()?->id,
            $request->user('customer')?->id,
        );

        return response()->json([
            'liked' => $liked,
            'count' => $model->likes()->count(),
        ]);
    }

    /**
     * Comentar requiere cuenta de cliente (o ser del equipo): así sabemos quién pregunta
     * y podemos responderle por WhatsApp.
     */
    public function comment(Request $request, string $type, int $id)
    {
        $model = $this->resolve($type, $id);
        $customer = $request->user('customer');
        $staff = $request->user();

        if (! $customer && ! $staff) {
            session(['url.intended' => url()->previous()]);

            return redirect()->route('customer.login')->with('status', 'Crea tu cuenta o inicia sesión para comentar.');
        }

        $key = 'comment:'.($customer?->id ?? 'staff-'.$staff->id);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['body' => 'Espera un momento antes de comentar de nuevo.'])->withInput();
        }
        RateLimiter::hit($key, 60);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ], [], ['body' => 'comentario']);

        $comment = $model->comments()->create([
            ...$data,
            'customer_id' => $customer?->id,
            'user_id' => $customer ? null : $staff->id,
            'visitor_token' => $request->attributes->get('visitor_token'),
            'author_name' => $customer?->name ?? ($staff->branch?->name ?? 'RyE'),
            'author_phone' => $customer?->phone,
            'is_approved' => true,
        ]);

        return back()->with('status', '¡Gracias por tu comentario!')->withFragment('comentario-'.$comment->id);
    }
}
