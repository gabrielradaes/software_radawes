<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Permisos nuevos: eliminar registros (solo admin) e historial del equipo (admin y supervisor).
 *   El supervisor hace casi todo menos eliminar; el vendedor también publica.
 * - Historial de actividad para todos: equipo y clientes (audit_logs gana cliente, tienda y "lote" por pedido).
 * - Publicaciones con color de fondo y de letra a elección.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('batch', 36)->nullable()->after('ip');
            $table->index(['user_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
            $table->index('batch');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->string('bg', 40)->nullable()->after('body');          // #rrggbb o clave de degradado (g-…)
            $table->string('text_color', 7)->nullable()->after('bg');     // #rrggbb; vacío = automático
        });

        $now = now();
        foreach ([
            ['records.delete', 'Eliminar registros (productos, publicaciones, usuarios…)', 'admin'],
            ['activity.view', 'Ver el historial de actividad del equipo', 'admin'],
        ] as [$name, $label, $group]) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'group' => $group, 'updated_at' => $now, 'created_at' => $now]);
        }

        $grant = function (string $role, array $perms) {
            $roleId = DB::table('roles')->where('name', $role)->value('id');
            if (! $roleId) {
                return;
            }
            foreach (DB::table('permissions')->whereIn('name', $perms)->pluck('id') as $pid) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $pid]);
            }
        };
        $grant('supervisor', ['customers.manage', 'activity.view']);
        $grant('empleado', ['posts.manage']);
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['bg', 'text_color']));
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropIndex(['branch_id', 'created_at']);
            $table->dropIndex(['batch']);
            $table->dropColumn(['customer_id', 'branch_id', 'batch']);
        });
        DB::table('permissions')->whereIn('name', ['records.delete', 'activity.view'])->delete();
    }
};
