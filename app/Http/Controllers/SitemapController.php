<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;

/** /sitemap.xml: le dice a Google qué páginas existen (tiendas, catálogo, productos, novedades). */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $urls = [['loc' => route('landing'), 'priority' => '1.0', 'changefreq' => 'daily']];

        $branches = Branch::active()->get();
        $categories = Category::active()->get();

        foreach ($branches as $b) {
            $urls[] = ['loc' => route('home', $b), 'priority' => '0.9', 'changefreq' => 'daily'];
            $urls[] = ['loc' => route('catalog.index', $b), 'priority' => '0.8', 'changefreq' => 'daily'];
            $urls[] = ['loc' => route('feed.index', $b), 'priority' => '0.7', 'changefreq' => 'daily'];
            foreach ($categories as $c) {
                $urls[] = ['loc' => route('catalog.category', ['branch' => $b, 'category' => $c]), 'priority' => '0.6', 'changefreq' => 'weekly'];
            }
            foreach (Product::published()->inBranch($b->id)->get(['id', 'slug', 'updated_at']) as $p) {
                $urls[] = ['loc' => route('products.show', ['branch' => $b, 'product' => $p]), 'lastmod' => $p->updated_at?->toAtomString(), 'priority' => '0.8', 'changefreq' => 'weekly'];
            }
            foreach (Post::published()->where('branch_id', $b->id)->get(['id', 'updated_at']) as $post) {
                $urls[] = ['loc' => route('feed.show', ['branch' => $b, 'post' => $post]), 'lastmod' => $post->updated_at?->toAtomString(), 'priority' => '0.5', 'changefreq' => 'monthly'];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>'
                .(! empty($u['lastmod']) ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '')
                .'<changefreq>'.$u['changefreq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
