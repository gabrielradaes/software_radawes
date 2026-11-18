<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Assistant;
use App\Http\Controllers\Controller;
use App\Models\AssistantMessage;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

/**
 * Asistente del panel: órdenes por voz (dictado gratis del navegador) o texto.
 * La conversación pendiente vive en la sesión; cada mensaje queda guardado (quién ordenó qué).
 */
class AssistantController extends Controller
{
    private const KEY = 'assistant.state';

    public function index(Request $request)
    {
        $messages = AssistantMessage::where('user_id', $request->user()->id)->latest('id')->limit(40)->get()->reverse()->values();

        if ($messages->isEmpty()) {
            $welcome = (new Assistant($request->user()))->handle('ayuda');
            $messages = collect([new AssistantMessage(['role' => 'assistant', 'text' => $welcome['text'], 'meta' => $welcome])]);
        }

        return view('admin.assistant.index', [
            'messages' => $messages->map(fn ($m) => $this->present($m))->values(),
            'pending' => ($request->session()->get(self::KEY)['step'] ?? null) === 'confirm',
        ]);
    }

    public function message(Request $request)
    {
        $request->validate([
            'text' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => [ImageUpload::rule(6144)],
        ], [], ['images.*' => 'foto']);

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = ImageUpload::store($file, 'products');
        }

        $text = trim((string) $request->input('text'));

        return $this->run($request, $text ?: ($paths ? '📷 '.count($paths).' foto(s)' : ''), fn (Assistant $a) => $a->handle($text, $paths));
    }

    public function confirm(Request $request)
    {
        return $this->run($request, 'Sí, confirmo', fn (Assistant $a) => $a->execute());
    }

    public function cancel(Request $request)
    {
        return $this->run($request, 'Cancelar', fn (Assistant $a) => $a->cancel());
    }

    /** Borra el historial visible y la conversación pendiente del usuario. */
    public function clear(Request $request)
    {
        (new Assistant($request->user(), $request->session()->get(self::KEY)))->cancel();
        $request->session()->forget(self::KEY);
        AssistantMessage::where('user_id', $request->user()->id)->delete();

        return redirect()->route('admin.asistente');
    }

    private function run(Request $request, string $userText, \Closure $action)
    {
        $user = $request->user();
        $assistant = new Assistant($user, $request->session()->get(self::KEY));

        if ($userText !== '') {
            AssistantMessage::create(['user_id' => $user->id, 'role' => 'user', 'text' => $userText]);
        }
        $reply = $action($assistant);
        $request->session()->put(self::KEY, $assistant->state);

        $saved = AssistantMessage::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'text' => $reply['text'],
            'intent' => $reply['intent'],
            'meta' => ['table' => $reply['table'], 'links' => $reply['links'], 'confirm' => $reply['confirm']] + $reply['meta'],
        ]);

        return response()->json($this->present($saved));
    }

    private function present(AssistantMessage $m): array
    {
        $meta = $m->meta ?? [];

        return [
            'id' => $m->id,
            'role' => $m->role,
            'text' => $m->text,
            'html' => $this->format($m->text),
            'table' => $meta['table'] ?? null,
            'links' => $meta['links'] ?? [],
            'confirm' => (bool) ($meta['confirm'] ?? false),
            'time' => $m->created_at?->format('H:i'),
        ];
    }

    /** **negrita**, *cursiva* y saltos de línea, con el resto escapado. */
    private function format(string $text): string
    {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $html);

        return nl2br($html);
    }
}
