{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
    <title>{{ $store?->brandName() ?? config('app.name') }}</title>
    <link>{{ url('/') }}</link>
    <description>Calzado de cuero hecho en Brasil</description>
@foreach($items as $i)
    <item>
        <g:id>{{ $i['id'] }}</g:id>
        <g:item_group_id>{{ $i['group'] }}</g:item_group_id>
        <g:title>{{ $i['title'] }}</g:title>
        <g:description>{{ $i['description'] }}</g:description>
        <g:link>{{ $i['link'] }}</g:link>
@if($i['image'])        <g:image_link>{{ $i['image'] }}</g:image_link>
@endif
@foreach($i['extra_images'] as $img)        <g:additional_image_link>{{ $img }}</g:additional_image_link>
@endforeach
        <g:availability>{{ $i['availability'] }}</g:availability>
        <g:condition>new</g:condition>
        <g:price>{{ $i['price'] }}</g:price>
@if($i['sale_price'])        <g:sale_price>{{ $i['sale_price'] }}</g:sale_price>
@endif
        <g:brand>{{ $i['brand'] }}</g:brand>
        <g:identifier_exists>no</g:identifier_exists>
@if($i['size'])        <g:size>{{ $i['size'] }}</g:size>
        <g:size_system>BR</g:size_system>
@endif
@if($i['color'])        <g:color>{{ $i['color'] }}</g:color>
@endif
@if($i['material'])        <g:material>{{ $i['material'] }}</g:material>
@endif
@if($i['category'])        <g:google_product_category>{{ $i['category'] }}</g:google_product_category>
@endif
    </item>
@endforeach
</channel>
</rss>
