@props(['label', 'name', 'help' => null])
<div {{ $attributes->merge(['class' => '']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium mb-1">{{ $label }}</label>
    {{ $slot }}
    @if($help)<p class="text-xs text-slate-400 mt-1">{{ $help }}</p>@endif
    @error($name)<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>
