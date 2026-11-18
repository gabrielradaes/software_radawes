<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copia de seguridad de la base de datos en PHP puro (el hosting no tiene terminal ni mysqldump garantizado).
 * Guarda storage/app/backups/rye-AAAAMMDD-HHMMSS.sql.gz y conserva las últimas KEEP copias.
 * Se restaura importando el archivo en phpMyAdmin.
 */
class DatabaseBackup
{
    public const KEEP = 14;

    public static function dir(): string
    {
        return storage_path('app/backups');
    }

    /** @return string ruta del archivo creado */
    public static function run(string $reason = 'manual'): string
    {
        @set_time_limit(300);
        File::ensureDirectoryExists(self::dir());
        $gz = function_exists('gzopen');
        $path = self::dir().'/rye-'.now()->format('Ymd-His').'.sql'.($gz ? '.gz' : '');
        $out = $gz ? gzopen($path, 'wb6') : fopen($path, 'wb');
        $write = fn (string $s) => $gz ? gzwrite($out, $s) : fwrite($out, $s);

        try {
            $pdo = DB::connection()->getPdo();
            $db = DB::connection()->getDatabaseName();
            $write("-- Copia de seguridad RyE · {$db} · ".now()->toDateTimeString()." · {$reason}\n");
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))->map(fn ($r) => array_values((array) $r)[0]);
            foreach ($tables as $table) {
                $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1];
                $write("DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

                $stmt = $pdo->query("SELECT * FROM `{$table}`", \PDO::FETCH_NUM);
                $batch = [];
                while ($row = $stmt->fetch()) {
                    $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)).')';
                    if (count($batch) >= 200) {
                        $write("INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    $write("INSERT INTO `{$table}` VALUES\n".implode(",\n", $batch).";\n");
                }
                $write("\n");
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $e) {
            $gz ? gzclose($out) : fclose($out);
            @unlink($path);
            Log::error('Copia de seguridad falló: '.$e->getMessage());
            throw $e;
        }
        $gz ? gzclose($out) : fclose($out);

        self::prune();
        Log::info('Copia de seguridad creada', ['file' => basename($path), 'reason' => $reason]);

        return $path;
    }

    public static function prune(): void
    {
        collect(self::list())->slice(self::KEEP)->each(fn ($f) => @unlink($f['path']));
    }

    /** @return array<int, array{name: string, path: string, size: int, at: Carbon}> más reciente primero */
    public static function list(): array
    {
        return collect(glob(self::dir().'/rye-*.sql*') ?: [])
            ->map(fn ($p) => ['name' => basename($p), 'path' => $p, 'size' => filesize($p), 'at' => Carbon::createFromTimestamp(filemtime($p))])
            ->sortByDesc('name')->values()->all();
    }

    public static function latestAt(): ?Carbon
    {
        return self::list()[0]['at'] ?? null;
    }

    /** Copia diaria automática (sin cron): la primera visita del día al panel la crea después de responder. */
    public static function dailyIfDue(): void
    {
        $last = self::latestAt();
        if ($last && $last->gt(now()->subHours(20))) {
            return;
        }
        $lock = storage_path('app/backups/.running');
        File::ensureDirectoryExists(self::dir());
        if (is_file($lock) && filemtime($lock) > time() - 600) {
            return;
        }
        touch($lock);
        try {
            self::run('diaria automática');
        } catch (Throwable) {
            // ya quedó en el log
        } finally {
            @unlink($lock);
        }
    }
}
