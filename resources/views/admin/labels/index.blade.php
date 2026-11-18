<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e5e7eb; color: #111; }
        .bar { position: sticky; top: 0; background: #1f2937; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; z-index: 2; }
        .bar button, .bar select { font: inherit; padding: 6px 12px; border-radius: 6px; border: 0; }
        .bar button { background: #059669; color: #fff; font-weight: bold; cursor: pointer; }
        .sheet { width: 210mm; margin: 12px auto; background: #fff; padding: 13mm 0 0 0; display: grid; grid-template-columns: repeat(3, 70mm); grid-auto-rows: 37mm; }
        .sheet.small { grid-template-columns: repeat(5, 38mm); grid-auto-rows: 21.2mm; padding: 10.7mm 4.5mm 0; }
        .label { padding: 2.2mm 3mm; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; border: 1px dashed #d1d5db; }
        .brand { font-size: 7pt; text-transform: uppercase; letter-spacing: .5px; color: #555; display: flex; justify-content: space-between; }
        .name { font-size: 9pt; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta { font-size: 8pt; display: flex; justify-content: space-between; align-items: baseline; }
        .price { font-size: 12pt; font-weight: bold; }
        .code svg { width: 100%; height: 11mm; display: block; }
        .sku { font-size: 6.5pt; text-align: center; font-family: monospace; }
        .small .label { padding: 1mm 1.5mm; }
        .small .brand { display: none; }
        .small .name { font-size: 6.5pt; }
        .small .meta { font-size: 6.5pt; }
        .small .price { font-size: 8pt; }
        .small .code svg { height: 8mm; }
        .small .sku { font-size: 5.5pt; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { margin: 0; page-break-after: always; }
            .label { border-color: transparent; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
<div class="bar">
    <strong>{{ $title }}</strong>
    <span>{{ $labels->count() }} etiquetas</span>
    <label>Hoja:
        <select onchange="document.getElementById('normal').hidden = this.value === 'small'; document.getElementById('small').hidden = this.value !== 'small'">
            <option value="normal">A4 · 24 etiquetas (70 × 37 mm)</option>
            <option value="small">A4 · 65 etiquetas (38 × 21 mm)</option>
        </select>
    </label>
    <button onclick="window.print()">🖨️ Imprimir</button>
    <span style="opacity:.75;font-size:13px">En la ventana de impresión elige «Tamaño real / 100%» y sin márgenes.</span>
</div>

@if($labels->isEmpty())
    <p style="text-align:center;padding:40px">No hay etiquetas para imprimir (sin pares en stock).</p>
@endif

@foreach(['normal' => 24, 'small' => 65] as $layout => $per)
<div id="{{ $layout }}" @if($layout === 'small') hidden @endif>
@foreach($labels->chunk($per) as $page)
    <div class="sheet {{ $layout === 'small' ? 'small' : '' }}">
        @foreach($page as $v)
            <div class="label">
                <div class="brand"><span>{{ $brand }}</span><span>{{ $v->product->code }}</span></div>
                <div class="name">{{ $v->product->name }}</div>
                <div class="meta">
                    <span class="size">@if($v->valueOf('talla'))T. <strong>{{ $v->valueOf('talla') }}</strong>@endif {{ $v->valueOf('color') }}</span>
                    <span class="price">{{ money($v->effectivePrice()) }}</span>
                </div>
                <div class="code">{!! \App\Support\Barcode::svg($v->sku, 40, 1.2) !!}</div>
                <div class="sku">{{ $v->sku }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</div>
@endforeach
</body>
</html>
