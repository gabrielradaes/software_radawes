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
        \App\Models\AuditLog::record('respaldo', null, 'Copia de seguridad creada: '.basename($path));

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

    public function audit(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->usuario))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->q.'%'))
            ->when($request->filled('accion'), fn ($q) => $q->where('action', $request->accion))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.system.audit', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
