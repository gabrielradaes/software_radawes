<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WhatsappController;
use App\Http\Middleware\SetBranchFromRoute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel de administración (se registra antes que las tiendas para que
| /admin no sea tomado como una tienda)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'show'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('recuperar', [Admin\AuthController::class, 'showForgot'])->name('password.forgot');
        Route::post('recuperar', [Admin\AuthController::class, 'sendReset'])->middleware('throttle:10,1');
        Route::get('recuperar/codigo', [Admin\AuthController::class, 'showReset'])->name('password.reset');
        Route::post('recuperar/codigo', [Admin\AuthController::class, 'reset'])->middleware('throttle:20,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Catálogo (admin y supervisor)
        Route::middleware('can:products.manage')->group(function () {
            Route::resource('productos', Admin\ProductController::class)->except('show')->parameters(['productos' => 'product'])->scoped(['product' => 'id']);
            Route::post('productos/{product:id}/imagenes', [Admin\ProductImageController::class, 'store'])->name('productos.imagenes.store');
            Route::post('imagenes/{image}/principal', [Admin\ProductImageController::class, 'primary'])->name('imagenes.principal');
            Route::delete('imagenes/{image}', [Admin\ProductImageController::class, 'destroy'])->name('imagenes.destroy');
            Route::patch('variantes/{variant}', [Admin\VariantController::class, 'update'])->name('variantes.update');
            Route::delete('variantes/{variant}', [Admin\VariantController::class, 'destroy'])->name('variantes.destroy');
            Route::resource('categorias', Admin\CategoryController::class)->except('show')->parameters(['categorias' => 'category'])->scoped(['category' => 'id']);
        });

        // Colores de la tienda (admin: todas; supervisor: la suya)
        Route::middleware('can:products.manage')->group(function () {
            Route::get('colores', [Admin\ColorController::class, 'index'])->name('colores.index');
            Route::post('colores', [Admin\ColorController::class, 'store'])->name('colores.store');
        });

        // Contenido (admin y supervisor; el supervisor solo su tienda)
        Route::middleware('can:posts.manage')->group(function () {
            Route::resource('publicaciones', Admin\PostController::class)->except('show')->parameters(['publicaciones' => 'post']);
            Route::delete('media/{media}', [Admin\PostController::class, 'destroyMedia'])->name('media.destroy');
        });
        Route::middleware('can:comments.moderate')->group(function () {
            Route::get('comentarios', [Admin\CommentController::class, 'index'])->name('comentarios.index');
            Route::patch('comentarios/{comment}/aprobar', [Admin\CommentController::class, 'toggle'])->name('comentarios.toggle');
            Route::post('comentarios/{comment}/responder', [Admin\CommentController::class, 'reply'])->name('comentarios.reply');
            Route::delete('comentarios/{comment}', [Admin\CommentController::class, 'destroy'])->name('comentarios.destroy');
        });

        // Clientes
        Route::middleware('can:customers.view')->group(function () {
            Route::get('clientes', [Admin\CustomerController::class, 'index'])->name('clientes.index');
            Route::get('clientes/nuevo', [Admin\CustomerController::class, 'create'])->name('clientes.create');
            Route::post('clientes', [Admin\CustomerController::class, 'store'])->name('clientes.store');
            Route::get('clientes/{customer}', [Admin\CustomerController::class, 'show'])->name('clientes.show');
        });
        Route::patch('clientes/{customer}', [Admin\CustomerController::class, 'update'])->middleware('can:customers.manage')->name('clientes.update');

        // Leads
        Route::middleware('can:leads.manage')->group(function () {
            Route::get('leads', [Admin\LeadController::class, 'index'])->name('leads.index');
            Route::patch('leads/{lead}', [Admin\LeadController::class, 'update'])->name('leads.update');
        });

        // Inventario
        Route::middleware('can:stock.view')->group(function () {
            Route::get('stock', [Admin\StockController::class, 'index'])->name('stock.index');
            Route::get('stock/global', [Admin\StockController::class, 'global'])->name('stock.global');
            Route::get('stock/{stock}/kardex', [Admin\StockController::class, 'kardex'])->name('stock.kardex');
        });
        Route::middleware('can:stock.adjust')->group(function () {
            Route::post('stock/{stock}/movimiento', [Admin\StockController::class, 'move'])->name('stock.move');
            Route::get('productos/{product:id}/variantes', [Admin\VariantController::class, 'index'])->name('productos.variantes');
            Route::post('productos/{product:id}/variantes/generar', [Admin\VariantController::class, 'generate'])->name('productos.variantes.generar');
            Route::post('productos/{product:id}/variantes/stock', [Admin\VariantController::class, 'saveStock'])->name('productos.variantes.stock');
        });

        // Administración (solo admin)
        Route::resource('usuarios', Admin\UserController::class)->except('show')->parameters(['usuarios' => 'user'])->middleware('can:users.manage');
        Route::resource('sucursales', Admin\BranchController::class)->except('show')->parameters(['sucursales' => 'branch'])->scoped(['branch' => 'id'])->middleware('can:branches.manage');
    });
});

/*
|--------------------------------------------------------------------------
| Sitio público: raíz = elegir tienda; cada tienda bajo /{branch}
|--------------------------------------------------------------------------
*/
Route::get('/', LandingController::class)->name('landing');

// Para Google: mapa del sitio
Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');

// Llamado por GitHub Actions tras subir una versión nueva (ver .github/workflows/deploy.yml)
Route::post('/_deploy', \App\Http\Controllers\DeployController::class)->name('deploy');
Route::post('/_deploy/estado', [\App\Http\Controllers\DeployController::class, 'status'])->name('deploy.status');
Route::post('/_deploy/vendor', [\App\Http\Controllers\DeployController::class, 'vendor'])->name('deploy.vendor');
Route::get('/sucursales', fn () => redirect()->route('landing'));

Route::prefix('{branch}')->middleware(SetBranchFromRoute::class)->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalogo/{category}', [CatalogController::class, 'category'])->name('catalog.category');
    Route::get('/producto/{product}', [ProductController::class, 'show'])->name('products.show');

    Route::get('/novedades', [FeedController::class, 'index'])->name('feed.index');
    Route::get('/novedades/{post}', [FeedController::class, 'show'])->name('feed.show');

    Route::post('/me-gusta/{type}/{id}', [InteractionController::class, 'toggleLike'])
        ->whereIn('type', ['post', 'product'])->name('like.toggle');
    Route::post('/comentar/{type}/{id}', [InteractionController::class, 'comment'])
        ->whereIn('type', ['post', 'product'])->name('comment.store');

    Route::get('/whatsapp', WhatsappController::class)->name('whatsapp');

    // Paleta de colores de la tienda (equipo logueado)
    Route::post('/paleta', [\App\Http\Controllers\ThemeController::class, 'save'])->middleware('auth')->name('theme.save');

    // Cuenta de cliente (compartida entre tiendas, pero con el tema de la tienda actual)
    Route::middleware('guest:customer')->group(function () {
        Route::get('/registro', [Customer\AuthController::class, 'showRegister'])->name('customer.register');
        Route::post('/registro', [Customer\AuthController::class, 'register'])->middleware('throttle:30,1');
        Route::get('/ingresar', [Customer\AuthController::class, 'showLogin'])->name('customer.login');
        Route::post('/ingresar', [Customer\AuthController::class, 'login'])->middleware('throttle:30,1');

        // Verificación del Gmail con código de 6 dígitos
        Route::get('/verificar', [Customer\AuthController::class, 'showVerify'])->name('customer.verify');
        Route::post('/verificar', [Customer\AuthController::class, 'verify'])->middleware('throttle:30,1');
        Route::post('/verificar/reenviar', [Customer\AuthController::class, 'resendVerification'])->middleware('throttle:30,1')->name('customer.verify.resend');

        // Recuperar contraseña con código al Gmail
        Route::get('/recuperar', [Customer\AuthController::class, 'showForgot'])->name('customer.password.forgot');
        Route::post('/recuperar', [Customer\AuthController::class, 'sendReset'])->middleware('throttle:30,1');
        Route::get('/recuperar/codigo', [Customer\AuthController::class, 'showReset'])->name('customer.password.reset');
        Route::post('/recuperar/codigo', [Customer\AuthController::class, 'reset'])->middleware('throttle:30,1');
    });
    Route::middleware('auth:customer')->group(function () {
        Route::post('/salir', [Customer\AuthController::class, 'logout'])->name('customer.logout');
        Route::get('/mi-cuenta', [Customer\AccountController::class, 'index'])->name('customer.account');
        Route::put('/mi-cuenta', [Customer\AccountController::class, 'update'])->name('customer.account.update');
        Route::put('/mi-cuenta/clave', [Customer\AccountController::class, 'password'])->name('customer.password');
    });
});
