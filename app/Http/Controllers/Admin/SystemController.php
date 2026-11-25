<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DatabaseBackup;
use Illuminate\Http\Request;
use Throwable;

/** Solo administradores: copias de seguridad y auditoría. */
class SystemController extends Controller
{
    public function backups()
    {
        return view('admin.system.backups', ['backups' => DatabaseBackup::list()]);
    }

    public function createBackup()
    {
        try {
            $path = DatabaseBackup::run('manual');
        } catch (Throwable $e) {
            return back()->withErrors(['backup' => 'No se pudo crear la copia: '.$e->getMessage()]);
        }
        return back()->with('status', 'Copia creada: '.basename($path));
    }

    public function downloadBackup(string $file)
    {
        abort_unless(preg_match('/^rye-\d{8}-\d{6}\.sql(\.gz)?$/', $file), 404);
        $path = DatabaseBackup::dir().'/'.$file;
        abort_unless(is_file($path), 404);
        AuditLog::record('descarga', null, 'Descargó la copia de seguridad '.$file);

        // Tipo explícito: así no depende de la extensión fileinfo
        return response()->download($path, $file, ['Content-Type' => str_ends_with($file, '.gz') ? 'application/gzip' : 'application/sql']);
    }

}
