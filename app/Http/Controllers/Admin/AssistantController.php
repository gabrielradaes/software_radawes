<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Assistant;
use App\Http\Controllers\Controller;
use App\Models\AssistantExample;
use App\Models\AssistantMessage;
use App\Models\Role;
use App\Models\User;
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
        $messages = AssistantMessage::where('user_id', $request->user()->id)->whereNull('hidden_at')->latest('id')->limit(40)->get()->reverse()->values();

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

    /**
     * «¿Qué querías hacer?»: la persona elige la acción de un mensaje que no se entendió.
     * La frase queda guardada como ejemplo (el asistente aprende) y se atiende con esa acción.
     */
    public function learn(Request $request)
    {
        $assistant = new Assistant($request->user(), $request->session()->get(self::KEY));
        $data = $request->validate(['intent' => ['required', 'string', 'in:'.implode(',', array_column($assistant->choices(), 1))]]);
        $last = AssistantMessage::where('user_id', $request->user()->id)->where('role', 'user')->whereNull('hidden_at')->latest('id')->first();
        abort_unless($last && mb_strlen($last->text) >= 3, 422, 'No hay un mensaje para enseñar.');

        AssistantExample::firstOrCreate(['folded' => mb_substr(\App\Assistant\Parser::fold(trim($last->text)), 0, 300), 'intent' => $data['intent']],
            ['text' => mb_substr($last->text, 0, 300), 'source' => 'boton', 'user_id' => $request->user()->id]);
        $label = Assistant::CHOICES[$data['intent']];

        return $this->run($request, "👉 Quería: {$label}", function (Assistant $a) use ($last, $data) {
            $reply = $a->handle($last->text, [], $data['intent']);
            $reply['text'] = 'Gracias, ya lo aprendí 🎓. '.$reply['text'];

            return $reply;
        });
    }

    public function cancel(Request $request)
    {
        return $this->run($request, 'Cancelar', fn (Assistant $a) => $a->cancel());
    }

    /** Limpia la pantalla y la conversación pendiente (los mensajes quedan guardados para que el admin pueda revisarlos). */
    public function clear(Request $request)
    {
        (new Assistant($request->user(), $request->session()->get(self::KEY)))->cancel();
        $request->session()->forget(self::KEY);
        AssistantMessage::where('user_id', $request->user()->id)->whereNull('hidden_at')->update(['hidden_at' => now()]);

        return redirect()->route('admin.asistente');
    }

    /**
     * Conversaciones del equipo con el asistente (solo lectura): para ver qué le piden y en qué se confunde.
     * El admin ve a todos; el supervisor, a la gente de su tienda (no a los administradores).
     */
    public function team(Request $request)
    {
        $viewer = $request->user();
        $people = User::with('role', 'branch')
            ->whereIn('id', AssistantMessage::select('user_id')->distinct())
            ->when(! $viewer->isAdmin(), fn ($q) => $q->where('branch_id', $viewer->branch_id)
                ->whereDoesntHave('role', fn ($r) => $r->where('name', Role::ADMIN)))
            ->withCount(['assistantMessages as asked' => fn ($q) => $q->where('role', 'user')])
            ->withMax('assistantMessages as last_at', 'created_at')
            ->orderByDesc('last_at')->get();

        $person = $people->firstWhere('id', (int) $request->query('usuario')) ?? $people->first();
        $messages = $person
            ? AssistantMessage::where('user_id', $person->id)->latest('id')->limit(300)->get()->reverse()->values()
            : collect();

        return view('admin.assistant.team', [
            'people' => $people,
            'person' => $person,
            'messages' => $messages->map(fn ($m) => $this->present($m) + ['date' => $m->created_at?->translatedFormat('l j \d\e F'), 'hidden' => (bool) $m->hidden_at, 'intent' => $m->intent]),
        ]);
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
            'choices' => $meta['choices'] ?? [],
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
