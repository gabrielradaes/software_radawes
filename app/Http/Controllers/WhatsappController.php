<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WhatsappLead;
use App\Support\CurrentBranch;
use Illuminate\Http\Request;

/**
 * Punto único de salida a WhatsApp: arma el mensaje, registra el lead y redirige a wa.me.
 * El admin podrá ver después qué productos y publicaciones generan más consultas, y quién consulta.
 */
class WhatsappController extends Controller
{
    public function __invoke(Request $request, CurrentBranch $current)
    {
        $branch = $current->get();
        $customer = $request->user('customer');

        $product = $request->filled('producto') ? Product::where('slug', $request->producto)->first() : null;
        $variant = $request->filled('variante') ? ProductVariant::with('attributeValues.attribute')->find($request->variante) : null;
        $post = $request->filled('post') ? Post::find($request->post) : null;
        $source = substr((string) $request->query('origen', 'web'), 0, 40);

        if ($variant && ! $product) {
            $product = $variant->product;
        }

        $message = $this->buildMessage($branch->name, $product, $variant, $post, $request->query('msg'), $customer);

        WhatsappLead::create([
            'branch_id' => $branch->id,
            'product_id' => $product?->id,
            'product_variant_id' => $variant?->id,
            'post_id' => $post?->id,
            'customer_id' => $customer?->id,
            'visitor_token' => $request->attributes->get('visitor_token'),
            'message' => mb_substr($message, 0, 500),
            'source' => $source,
        ]);

        return redirect()->away(whatsapp_link($branch->whatsapp, $message));
    }

    protected function buildMessage(string $branchName, ?Product $product, ?ProductVariant $variant, ?Post $post, ?string $custom, ?Customer $customer): string
    {
        if ($custom) {
            return mb_substr(strip_tags($custom), 0, 300);
        }

        $hola = $customer ? "¡Hola {$branchName}! 👋 Soy {$customer->name}." : "¡Hola {$branchName}! 👋";

        if ($product) {
            $msg = "$hola Me interesa el *{$product->name}*";
            if ($variant) {
                $msg .= ' ('.$variant->label().')';
            }
            $msg .= ' que vi en su página ('.route('products.show', $product).'). ¿Está disponible?';

            return $msg;
        }

        if ($post) {
            return "$hola Vi su publicación \"{$post->title}\" y quisiera más información.";
        }

        return "$hola Quisiera más información sobre sus productos.";
    }
}
