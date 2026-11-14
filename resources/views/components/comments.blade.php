@props(['model', 'type', 'compact' => false])
@php
    $comments = $model->relationLoaded('approvedComments') ? $model->approvedComments : $model->approvedComments()->with('replies')->get();
    $formId = 'form-'.$type.'-'.$model->id;
    $customer = auth('customer')->user();
    $staff = auth()->user();
@endphp
<section id="comentarios-{{ $type }}-{{ $model->id }}" class="{{ $compact ? '' : 'mt-8' }}" x-data="{ replyTo: null, replyName: '' }">
    @unless($compact)
        <h3 class="font-display text-xl font-bold mb-4">Comentarios <span class="text-brand-500 text-base font-sans font-normal">({{ $model->comments_count ?? $comments->count() }})</span></h3>
    @endunless

    <div class="space-y-4">
        @forelse($comments as $c)
            <div id="comentario-{{ $c->id }}" class="flex gap-3">
                <div class="w-9 h-9 rounded-full shrink-0 flex items-center justify-center font-bold text-sm {{ $c->isStaff() ? 'bg-brand-900 text-gold-400' : 'bg-brand-100 text-brand-700' }}">
                    {{ mb_strtoupper(mb_substr($c->author_name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="bg-brand-50 rounded-2xl rounded-tl-sm px-4 py-2.5">
                        <p class="text-sm font-semibold flex items-center gap-2">
                            {{ $c->author_name }}
                            @if($c->isStaff()) <span class="text-[10px] uppercase tracking-wider bg-gold-500 text-brand-950 px-1.5 py-0.5 rounded">Equipo</span> @endif
                        </p>
                        <p class="text-sm text-brand-800 mt-0.5 break-words">{{ $c->body }}</p>
                    </div>
                    <div class="flex gap-3 mt-1 ml-2 text-xs text-brand-500">
                        <span>{{ $c->created_at->diffForHumans() }}</span>
                        @if($customer || $staff)
                            <button type="button" class="font-semibold hover:text-brand-900" @click="replyTo = {{ $c->id }}; replyName = @js($c->author_name); $nextTick(() => document.getElementById(@js($formId)).scrollIntoView({behavior:'smooth', block:'center'}))">Responder</button>
                        @endif
                    </div>

                    @foreach($c->replies as $r)
                        <div id="comentario-{{ $r->id }}" class="flex gap-2.5 mt-3">
                            <div class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center font-bold text-xs {{ $r->isStaff() ? 'bg-brand-900 text-gold-400' : 'bg-brand-100 text-brand-700' }}">
                                {{ mb_strtoupper(mb_substr($r->author_name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="bg-white border border-brand-100 rounded-2xl rounded-tl-sm px-3.5 py-2">
                                    <p class="text-xs font-semibold flex items-center gap-2">
                                        {{ $r->author_name }}
                                        @if($r->isStaff()) <span class="text-[10px] uppercase tracking-wider bg-gold-500 text-brand-950 px-1.5 py-0.5 rounded">Equipo</span> @endif
                                    </p>
                                    <p class="text-sm text-brand-800 mt-0.5 break-words">{{ $r->body }}</p>
                                </div>
                                <p class="text-xs text-brand-500 mt-1 ml-2">{{ $r->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-brand-500">Sé el primero en comentar.</p>
        @endforelse
    </div>

    @if($customer || $staff)
        <form id="{{ $formId }}" method="POST" action="{{ route('comment.store', [$type, $model->id]) }}" class="mt-5 bg-white border border-brand-100 rounded-2xl p-4">
            @csrf
            <input type="hidden" name="parent_id" :value="replyTo">
            <div x-show="replyTo" x-cloak class="flex items-center justify-between text-xs bg-brand-50 rounded-lg px-3 py-1.5 mb-3">
                <span>Respondiendo a <strong x-text="replyName"></strong></span>
                <button type="button" class="text-brand-500 hover:text-brand-900" @click="replyTo = null">✕ cancelar</button>
            </div>
            <div class="flex items-center gap-2 text-xs text-brand-500 mb-2">
                <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold {{ $customer ? 'bg-brand-100 text-brand-700' : 'bg-brand-900 text-gold-400' }}">{{ $customer ? $customer->initials() : $staff->initials() }}</span>
                Comentando como <strong class="text-brand-900">{{ $customer?->name ?? ($staff->branch?->name ?? 'RyE') }}</strong>
                @unless($customer)<span class="text-[10px] uppercase tracking-wider bg-gold-500 text-brand-950 px-1.5 py-0.5 rounded">Equipo</span>@endunless
            </div>
            @error('body')<p class="text-xs text-rose-600 mb-2">{{ $message }}</p>@enderror
            <textarea name="body" required maxlength="1000" rows="2" placeholder="Escribe un comentario o pregunta..." class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">{{ old('body') }}</textarea>
            <div class="mt-3 flex justify-end">
                <button class="bg-brand-900 hover:bg-brand-800 text-white text-sm font-semibold px-5 py-2 rounded-full transition">Publicar</button>
            </div>
        </form>
    @else
        <div id="{{ $formId }}" class="mt-5 bg-brand-900 text-white rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="font-display font-bold text-lg">¿Tienes una pregunta?</p>
                <p class="text-sm text-brand-200">Inicia sesión o crea tu cuenta gratis para comentar y que te respondamos.</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <a href="{{ route('customer.login') }}" class="bg-white text-brand-900 text-sm font-semibold px-4 py-2 rounded-full">Iniciar sesión</a>
                <a href="{{ route('customer.register') }}" class="bg-gold-500 text-brand-950 text-sm font-semibold px-4 py-2 rounded-full">Registrarme</a>
            </div>
        </div>
    @endif
</section>
