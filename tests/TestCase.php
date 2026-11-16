<?php

namespace Tests;

use App\Mail\VerificationCodeMail;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\BaseCatalogSeeder;
use Database\Seeders\BranchSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecSeeder;
use Database\Seeders\StoreCatalogSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Base de las pruebas: misma estructura de datos que producción (roles, 3 tiendas,
 * categorías y fichas técnicas) y una carpeta pública temporal para las fotos subidas.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Las fotos se guardan en una carpeta temporal, no en public/ del proyecto
        $this->publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rye-tests-'.uniqid();
        mkdir($this->publicDir.'/uploads', 0777, true);
        $this->app->usePublicPath($this->publicDir);

        $this->seed([RoleSeeder::class, BranchSeeder::class, BaseCatalogSeeder::class, SpecSeeder::class, StoreCatalogSeeder::class]);

        // Igual que AppServiceProvider en producción (allí corre en cada petición web)
        URL::defaults(['branch' => Branch::where('is_main', true)->value('slug') ?? Branch::value('slug')]);

        Mail::fake();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    protected function branch(string $slug = 'calacoto'): Branch
    {
        return Branch::where('slug', $slug)->firstOrFail();
    }

    /** Usuario del panel con un rol (admin global; supervisor/vendedor en la tienda indicada). */
    protected function staff(string $role = Role::ADMIN, ?string $branch = null, array $extra = []): User
    {
        return User::create([
            'name' => ucfirst($role).' Prueba',
            'email' => $role.'.'.uniqid().'@gmail.com',
            'password' => 'clave-segura-123',
            'role_id' => Role::where('name', $role)->value('id'),
            'branch_id' => $branch ? $this->branch($branch)->id : null,
            'is_active' => true,
            ...$extra,
        ]);
    }

    protected function admin(): User
    {
        return $this->staff(Role::ADMIN);
    }

    protected function supervisor(string $branch = 'calacoto'): User
    {
        return $this->staff(Role::SUPERVISOR, $branch);
    }

    protected function vendedor(string $branch = 'calacoto'): User
    {
        return $this->staff(Role::EMPLEADO, $branch);
    }

    /** Foto real (JPG/PNG) generada con GD, como las que sube un celular. */
    protected function foto(string $name = 'foto.jpg', int $w = 900, int $h = 900): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    /** Archivo con extensión de imagen pero que NO es una imagen. */
    protected function fotoFalsa(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('falsa.jpg', 'esto no es una imagen');
    }

    /** Último código de 6 dígitos enviado por correo a $email. */
    protected function ultimoCodigo(string $email): string
    {
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $m) use ($email, &$code) {
            if ($m->hasTo($email)) {
                $code = $m->code;
            }

            return true;
        });
        $this->assertNotNull($code, "No se envió ningún código a $email");

        return $code;
    }

    protected function assertFotoGuardada(string $path): void
    {
        $this->assertStringStartsWith('uploads/', $path);
        $this->assertFileExists(public_path($path), "La foto $path no se guardó en public/uploads");
    }
}
