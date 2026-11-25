<?php

namespace App\Http\Controllers\Admin;

use App\Assistant\Assistant;
use App\Assistant\Lexicon;
use App\Assistant\Parser;
use App\Http\Controllers\Controller;
use App\Models\AssistantExample;
use App\Models\AssistantMessage;
use App\Models\AssistantWord;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * «Enseñar al asistente»: el equipo le agrega palabras propias del negocio y frases de ejemplo,
 * revisa los mensajes que no entendió y prueba cómo interpreta una frase. Todo gratis, en el propio sistema.
 */
class AssistantTeachController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $teamIds = User::when(! $viewer->isAdmin(), fn ($q) => $q->where('branch_id', $viewer->branch_id)
            ->whereDoesntHave('role', fn ($r) => $r->where('name', Role::ADMIN)))->pluck('id');

        // Mensajes que no entendió (la respuesta siguiente fue «No te entendí»), sin los que ya se enseñaron
        $taught = AssistantExample::pluck('folded')->flip();
        $failed = AssistantMessage::where('role', 'assistant')->where('text', 'like', 'No te entendí%')
            ->whereIn('user_id', $teamIds)->where('created_at', '>=', now()->subDays(60))->latest('id')->limit(80)->get();
        $unknown = $failed->map(function ($reply) {
            $asked = AssistantMessage::where('user_id', $reply->user_id)->where('role', 'user')->where('id', '<', $reply->id)->latest('id')->first();

            return $asked ? ['id' => $asked->id, 'text' => $asked->text, 'user' => $asked->user?->name, 'when' => $asked->created_at] : null;
        })->filter()->reject(fn ($m) => isset($taught[mb_substr(Parser::fold(trim($m['text'])), 0, 300)]) || mb_strlen($m['text']) < 3)
            ->unique(fn ($m) => Parser::fold($m['text']))->take(25)->values();

        return view('admin.assistant.teach', [
            'words' => AssistantWord::with('category', 'user')->orderBy('kind')->orderBy('phrase')->get(),
            'examples' => AssistantExample::with('user')->latest('id')->get()->groupBy('intent'),
            'unknown' => $unknown,
            'intents' => Assistant::TEACHABLE,
            'kinds' => AssistantWord::KINDS,
            'classes' => Category::where('is_active', true)->orderBy('parent_id')->orderBy('sort_order')->get(),
            'probe' => $request->filled('probar') ? $this->probe($viewer, (string) $request->query('probar')) : null,
        ]);
    }

    public function storeWord(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AssistantWord::KINDS))],
            'phrase' => ['required', 'string', 'max:80'],
            'meaning' => ['nullable', 'required_unless:kind,clase', 'string', 'max:120'],
            'category_id' => ['nullable', 'required_if:kind,clase', 'exists:categories,id'],
        ], [
            'meaning.required_unless' => 'Escribe qué significa.',
            'category_id.required_if' => 'Elige la clase a la que corresponde.',
        ], ['phrase' => 'palabra', 'meaning' => 'significado']);

        $phrase = trim(preg_replace('/\s+/', ' ', Parser::fold($data['phrase'])), ' .,');
        abort_if($phrase === '', 422);
        $meaning = isset($data['meaning']) ? trim($data['meaning']) : null;
        if ($data['kind'] === 'color' || $data['kind'] === 'marca') {
            $meaning = Str::ucfirst($meaning);
        }
        AssistantWord::updateOrCreate(['kind' => $data['kind'], 'phrase' => $phrase], [
            'meaning' => $data['kind'] === 'clase' ? Category::find($data['category_id'])?->name : $meaning,
            'category_id' => $data['kind'] === 'clase' ? $data['category_id'] : null,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', "Listo: ahora el asistente entiende «{$phrase}».");
    }

    public function storeExample(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'min:3', 'max:300'],
            'intent' => ['required', Rule::in(array_keys(Assistant::TEACHABLE))],
        ], [], ['text' => 'frase', 'intent' => 'acción']);

        AssistantExample::updateOrCreate(['folded' => mb_substr(Parser::fold(trim($data['text'])), 0, 300), 'intent' => $data['intent']],
            ['text' => trim($data['text']), 'source' => 'admin', 'user_id' => $request->user()->id]);

        return back()->with('status', 'Frase aprendida: «'.Str::limit(trim($data['text']), 60).'» = '.Assistant::TEACHABLE[$data['intent']].'.');
    }

    public function destroyWord(Request $request, AssistantWord $word)
    {
        $this->ensureCanRemove($request, $word->user_id);
        $word->delete();

        return back()->with('status', 'Palabra quitada.');
    }

    public function destroyExample(Request $request, AssistantExample $example)
    {
        $this->ensureCanRemove($request, $example->user_id);
        $example->delete();

        return back()->with('status', 'Frase quitada.');
    }

    /** Quitar es del admin; cada uno puede quitar lo que él mismo enseñó (para corregir un error). */
    private function ensureCanRemove(Request $request, ?int $ownerId): void
    {
        abort_unless($request->user()->can('records.delete') || $ownerId === $request->user()->id, 403, 'Solo el administrador puede quitar lo que enseñó otra persona.');
    }

    /** Cómo entendería el asistente una frase, sin hacer nada. */
    private function probe(User $user, string $text): array
    {
        $text = mb_substr(trim($text), 0, 500);
        $rewritten = Lexicon::rewrite($text);
        $p = new Parser($rewritten);
        $rule = $p->intent();
        $learned = Lexicon::classify($p->a);
        $intent = (new Assistant($user))->learnedIntent($p, $rule, false);
        $category = $p->category();

        return [
            'text' => $text,
            'rewritten' => $rewritten !== $text ? $rewritten : null,
            'intent' => $intent,
            'label' => $intent ? (Assistant::TEACHABLE[$intent] ?? Str::ucfirst(str_replace('_', ' ', $intent))) : null,
            'how' => ! $intent ? null : ($intent === $rule ? 'por sus reglas' : 'por lo que le enseñaron ('.round(($learned['score'] ?? 0) * 100).'% parecido)'),
            'data' => array_filter([
                'Código' => $p->code(),
                'Varios códigos' => count($p->numericCodes()) > 1 ? implode(', ', array_column($p->numericCodes(), 'code')) : null,
                'Colores' => implode(', ', $p->colors()),
                'Tallas' => implode(', ', $p->tallas()),
                'Marca' => $p->brand(),
                'Clase' => $category?->name,
                'Precio' => $p->price() !== null ? money($p->price()) : null,
                'Cantidad' => $p->quantity() ?? $p->quantityEach(),
                'Celular' => $p->phone(),
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }
}
