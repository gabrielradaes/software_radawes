<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('slug', 'comercio')->firstOrFail();
        $admin = User::where('email', 'admin@rye.test')->firstOrFail();
        $vendedor = User::where('email', 'vendedor@rye.test')->firstOrFail();

        Post::query()->forceDelete();

        $posts = [
            [
                'type' => 'foto', 'product' => 'botin-chelsea', 'pinned' => true, 'days' => 1,
                'title' => '¡Llegaron los Chelsea! 🔥',
                'body' => "Nueva colección de botines Chelsea en cuero legítimo brasileño. Elástico lateral, tirador posterior y una horma que abraza el pie.\n\nTallas 38 a 44. Pasa por la tienda o pídelo por WhatsApp 👇",
                'comments' => [
                    ['Carlos M.', '¿Tienen en 43 café?', 'Sí Carlos, nos quedan 2 pares en 43 café. ¡Te lo guardamos!'],
                    ['Luis Q.', 'Compré el negro la semana pasada, calidad 10/10 👌', null],
                ],
            ],
            [
                'type' => 'oferta', 'product' => 'zapato-derby-brogue', 'pinned' => false, 'days' => 3,
                'title' => 'Oferta de la semana: Derby Brogue',
                'body' => "De Bs 520 a **Bs 450** solo hasta el domingo. Perforado brogue clásico en color miel y café.\n\nStock limitado, ¡no te quedes sin el tuyo!",
                'comments' => [
                    ['Jorge A.', '¿Hacen envíos a El Alto?', 'Claro Jorge, hacemos envíos a El Alto y toda La Paz. Escríbenos por WhatsApp para coordinar.'],
                ],
            ],
            [
                'type' => 'texto', 'product' => null, 'pinned' => false, 'days' => 5,
                'title' => '¿Por qué calzado brasileño?',
                'body' => "Brasil es el tercer productor mundial de calzado y el primero en cuero bovino. Sus fábricas combinan tradición artesanal con control de calidad industrial.\n\nEn RadaWes elegimos cada modelo pensando en durabilidad, comodidad y precio justo. 🇧🇷🇧🇴",
                'comments' => [],
            ],
            [
                'type' => 'foto', 'product' => 'mocasin-penny', 'pinned' => false, 'days' => 8,
                'title' => 'Mocasín Penny: el comodín de tu clóset',
                'body' => "Con traje, con jeans, con chinos... el Penny va con todo. Disponible en café, negro y miel.",
                'comments' => [
                    ['李永辉', 'He comprado muchos zapatos ahí, qué chulo la calidad', '¡Gracias por la confianza! 🙌'],
                ],
            ],
            [
                'type' => 'foto', 'product' => 'scooter-150cc', 'pinned' => false, 'days' => 2,
                'title' => 'Nueva línea: Motos 🛵',
                'body' => "Ampliamos la familia RyE. Scooter 150cc automática, económica y lista para la ciudad. Consulta financiamiento por WhatsApp.",
                'comments' => [
                    ['Marco V.', '¿Precio?', 'Hola Marco, Bs 14.500 con casco de regalo. Tenemos planes de financiamiento, escríbenos 😉'],
                ],
            ],
            [
                'type' => 'texto', 'product' => null, 'pinned' => false, 'days' => 12,
                'title' => 'Horario y ubicación 📍',
                'body' => "Estamos en la Calle Comercio Nº 920, entre Genaro Sanjinez y Yanacocha, La Paz.\nLunes a sábado de 9:00 a 19:30. ¡Te esperamos!",
                'comments' => [],
            ],
        ];

        foreach ($posts as $p) {
            $product = $p['product'] ? Product::where('slug', $p['product'])->first() : null;
            $post = Post::create([
                'user_id' => $admin->id,
                'branch_id' => $branch->id,
                'product_id' => $product?->id,
                'type' => $p['type'],
                'title' => $p['title'],
                'body' => $p['body'],
                'is_pinned' => $p['pinned'],
                'published_at' => now()->subDays($p['days'])->subHours(rand(1, 9)),
            ]);

            if ($product) {
                foreach ($product->images as $i => $img) {
                    $post->media()->create(['type' => 'imagen', 'path' => $img->path, 'sort_order' => $i]);
                }
            }

            // Likes de visitantes anónimos
            foreach (range(1, rand(4, 23)) as $_) {
                $post->likes()->create(['visitor_token' => (string) Str::uuid()]);
            }

            foreach ($p['comments'] as [$author, $body, $reply]) {
                $c = $post->comments()->create([
                    'visitor_token' => (string) Str::uuid(),
                    'author_name' => $author,
                    'body' => $body,
                    'created_at' => $post->published_at->addHours(rand(1, 20)),
                ]);
                if ($reply) {
                    $post->comments()->create([
                        'parent_id' => $c->id,
                        'user_id' => $vendedor->id,
                        'author_name' => 'RadaWes',
                        'body' => $reply,
                        'created_at' => $c->created_at->addHours(2),
                    ]);
                }
            }
        }

        // Algunos likes en productos destacados
        foreach (Product::featured()->get() as $prod) {
            foreach (range(1, rand(3, 15)) as $_) {
                $prod->likes()->create(['visitor_token' => (string) Str::uuid()]);
            }
        }
    }
}
