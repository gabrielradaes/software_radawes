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

        // Asistente por voz/texto (cada orden se valida con los permisos del rol)
        Route::get('asistente', [Admin\AssistantController::class, 'index'])->name('asistente');
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('asistente/mensaje', [Admin\AssistantController::class, 'message'])->name('asistente.mensaje');
            Route::post('asistente/confirmar', [Admin\AssistantController::class, 'confirm'])->name('asistente.confirmar');
            Route::post('asistente/cancelar', [Admin\AssistantController::class, 'cancel'])->name('asistente.cancelar');
            Route::delete('asistente', [Admin\AssistantController::class, 'clear'])->name('asistente.limpiar');
        });

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

            // CRM: seguimientos, notas, etapa y etiquetas
            Route::get('crm', [Admin\CrmController::class, 'index'])->name('crm');
            Route::post('crm/seguimientos', [Admin\CrmController::class, 'storeTask'])->name('crm.tareas.store');
            Route::patch('crm/seguimientos/{task}', [Admin\CrmController::class, 'toggleTask'])->name('crm.tareas.toggle');
            Route::delete('crm/seguimientos/{task}', [Admin\CrmController::class, 'destroyTask'])->name('crm.tareas.destroy');
            Route::post('clientes/{customer}/historial', [Admin\CrmController::class, 'storeInteraction'])->name('clientes.historial');
            Route::patch('clientes/{customer}/crm', [Admin\CrmController::class, 'updateCustomer'])->name('clientes.crm');
        });

        // Ventas
        Route::middleware('can:sales.view')->group(function () {
            Route::get('ventas', [Admin\SaleController::class, 'index'])->name('ventas.index');
        });
        Route::middleware('can:sales.create')->group(function () {
            Route::get('ventas/nueva', [Admin\SaleController::class, 'create'])->name('ventas.create');
            Route::post('ventas', [Admin\SaleController::class, 'store'])->name('ventas.store');
            // Caja diaria
            Route::get('caja', [Admin\CashController::class, 'index'])->name('caja');
            Route::post('caja/abrir', [Admin\CashController::class, 'open'])->name('caja.abrir');
            Route::post('caja/{session}/cerrar', [Admin\CashController::class, 'close'])->name('caja.cerrar');
            Route::post('caja/{session}/movimiento', [Admin\CashController::class, 'movement'])->name('caja.movimiento');
            // Cambios de talla / devoluciones
            Route::post('ventas/{sale}/cambio', [Admin\SaleController::class, 'exchange'])->name('ventas.cambio');
            // Apartados con adelanto
            Route::get('apartados', [Admin\ReservationController::class, 'index'])->name('apartados.index');
            Route::get('apartados/nuevo', [Admin\ReservationController::class, 'create'])->name('apartados.create');
            Route::post('apartados', [Admin\ReservationController::class, 'store'])->name('apartados.store');
            Route::get('apartados/{reservation}', [Admin\ReservationController::class, 'show'])->whereNumber('reservation')->name('apartados.show');
            Route::post('apartados/{reservation}/retirar', [Admin\ReservationController::class, 'pickUp'])->name('apartados.retirar');
            Route::post('apartados/{reservation}/cancelar', [Admin\ReservationController::class, 'cancel'])->name('apartados.cancelar');
        });
        // Traspasos entre tiendas (envía el supervisor; cualquiera de la tienda de destino confirma)
        Route::middleware('can:stock.view')->group(function () {
            Route::get('traspasos', [Admin\TransferController::class, 'index'])->name('traspasos.index');
            Route::get('traspasos/{transfer}', [Admin\TransferController::class, 'show'])->whereNumber('transfer')->name('traspasos.show');
            Route::post('traspasos/{transfer}/recibir', [Admin\TransferController::class, 'receive'])->name('traspasos.recibir');
            // Inventario físico: contar (todos); abrir, cerrar y cancelar (quien ajusta stock)
            Route::get('inventario', [Admin\StockCountController::class, 'index'])->name('inventario.index');
            Route::get('inventario/{count}', [Admin\StockCountController::class, 'show'])->whereNumber('count')->name('inventario.show');
            Route::post('inventario/{count}/escanear', [Admin\StockCountController::class, 'scan'])->middleware('throttle:600,1')->name('inventario.escanear');
        });
        Route::middleware('can:stock.transfer')->group(function () {
            Route::get('traspasos/nuevo', [Admin\TransferController::class, 'create'])->name('traspasos.create');
            Route::post('traspasos', [Admin\TransferController::class, 'store'])->name('traspasos.store');
            Route::post('traspasos/{transfer}/cancelar', [Admin\TransferController::class, 'cancel'])->name('traspasos.cancelar');
        });
        Route::middleware('can:stock.adjust')->group(function () {
            Route::post('inventario', [Admin\StockCountController::class, 'store'])->name('inventario.store');
            Route::post('inventario/{count}/cerrar', [Admin\StockCountController::class, 'close'])->name('inventario.cerrar');
            Route::post('inventario/{count}/cancelar', [Admin\StockCountController::class, 'cancel'])->name('inventario.cancelar');
        });
        // Etiquetas con código de barras
        Route::get('etiquetas', [Admin\LabelController::class, 'index'])->middleware('can:stock.view')->name('etiquetas');
        Route::middleware('can:sales.view')->group(function () {
            Route::get('ventas/{sale}', [Admin\SaleController::class, 'show'])->name('ventas.show');
        });
        Route::post('ventas/{sale}/anular', [Admin\SaleController::class, 'cancel'])->middleware('can:sales.cancel')->name('ventas.anular');
        Route::middleware('can:customers.view')->group(function () {
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

        // Compras al proveedor, proveedores y curvas de tallas (quien carga stock)
        Route::middleware('can:stock.adjust')->group(function () {
            Route::get('compras', [Admin\PurchaseController::class, 'index'])->name('compras.index');
            Route::get('compras/nueva', [Admin\PurchaseController::class, 'create'])->name('compras.create');
            Route::post('compras', [Admin\PurchaseController::class, 'store'])->name('compras.store');
            Route::get('compras/{purchase}', [Admin\PurchaseController::class, 'show'])->whereNumber('purchase')->name('compras.show');
            Route::get('proveedores', [Admin\PurchaseController::class, 'suppliers'])->name('proveedores.index');
            Route::post('proveedores', [Admin\PurchaseController::class, 'storeSupplier'])->name('proveedores.store');
            Route::put('proveedores/{supplier}', [Admin\PurchaseController::class, 'updateSupplier'])->name('proveedores.update');
            Route::get('curvas', [Admin\PurchaseController::class, 'curves'])->name('curvas.index');
            Route::post('curvas', [Admin\PurchaseController::class, 'storeCurve'])->name('curvas.store');
            Route::delete('curvas/{curve}', [Admin\PurchaseController::class, 'destroyCurve'])->name('curvas.destroy');
        });

        // Solo administradores: copias de seguridad y auditoría
        Route::middleware('can:users.manage')->group(function () {
            Route::get('respaldos', [Admin\SystemController::class, 'backups'])->name('respaldos.index');
            Route::post('respaldos', [Admin\SystemController::class, 'createBackup'])->middleware('throttle:6,1')->name('respaldos.store');
            Route::get('respaldos/{file}', [Admin\SystemController::class, 'downloadBackup'])->name('respaldos.download');
            Route::get('auditoria', [Admin\SystemController::class, 'audit'])->name('auditoria');
        });
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
// Catálogo para Facebook/Instagram (Meta) y Google Merchant Center
Route::get('/catalogo.xml', [\App\Http\Controllers\CatalogFeedController::class, 'catalog'])->name('feed.catalog');

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
    Route::post('/producto/{product}/avisame', [\App\Http\Controllers\RestockController::class, 'store'])->middleware('throttle:10,1')->name('products.restock');

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
