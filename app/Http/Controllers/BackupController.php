<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    public function index()
    {
        $this->authorizeBackup();
        $backupPath = storage_path('app/backups');
        File::ensureDirectoryExists($backupPath);

        $files = collect(File::files($backupPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql') || str_ends_with($file->getFilename(), '.sql.enc'))
            ->sortByDesc(fn ($file) => $file->getMTime());

        return view('backups.index', compact('files'));
    }

    public function download()
    {
        $this->authorizeBackup();
        $connection = config('database.default');
        abort_unless(in_array($connection, ['mysql', 'mariadb'], true), 422, 'Backup otomatis saat ini hanya mendukung MySQL/MariaDB.');

        $backupPath = storage_path('app/backups');
        File::ensureDirectoryExists($backupPath);
        $database = config("database.connections.$connection.database");
        $filename = $database.'_'.now()->format('Ymd_His').'.sql.enc';
        $target = $backupPath.DIRECTORY_SEPARATOR.$filename;

        File::put($target, Crypt::encryptString($this->generateSqlBackup($database)));

        if (! File::exists($target) || File::size($target) === 0) {
            return back()->withErrors('Backup gagal: file SQL kosong.');
        }

        AuditLog::record('backup_database', 'Backup & Restore');

        return response()->download($target)->deleteFileAfterSend(false);
    }

    public function restore(Request $request)
    {
        $this->authorizeBackup();

        $data = $request->validate([
            'backup_file' => ['required', 'file', 'max:51200'],
            'confirmation' => ['required', 'in:RESTORE'],
            'current_password' => ['required', 'current_password'],
        ]);

        $originalName = strtolower($data['backup_file']->getClientOriginalName());
        if (! str_ends_with($originalName, '.sql.enc')) {
            throw ValidationException::withMessages(['backup_file' => 'File restore harus berupa backup terenkripsi .sql.enc dari aplikasi ini.']);
        }

        $connection = config('database.default');
        abort_unless(in_array($connection, ['mysql', 'mariadb'], true), 422, 'Restore otomatis saat ini hanya mendukung MySQL/MariaDB.');

        $uploaded = $data['backup_file']->storeAs('restore-tmp', 'restore_'.now()->format('Ymd_His').'.sql.enc');
        $source = storage_path('app/'.$uploaded);
        $sql = $this->restoreSqlFromFile($source, true);
        $database = config("database.connections.$connection.database");

        $process = new Process([
            $this->mysqlBinary(),
            '--host='.config("database.connections.$connection.host"),
            '--port='.config("database.connections.$connection.port"),
            '--user='.config("database.connections.$connection.username"),
            $database,
        ]);

        if (filled(config("database.connections.$connection.password"))) {
            $process->setEnv(['MYSQL_PWD' => config("database.connections.$connection.password")]);
        }

        $process->setInput($sql);
        $process->setTimeout(300);
        $process->run();
        File::delete($source);

        if (! $process->isSuccessful()) {
            return back()->withErrors('Restore gagal: '.$process->getErrorOutput());
        }

        AuditLog::record('restore_database', 'Backup & Restore');

        return back()->with('success', 'Database berhasil direstore.');
    }

    private function authorizeBackup(): void
    {
        abort_unless(auth()->user()?->canManage('backup.manage'), 403, 'Anda tidak memiliki hak akses backup dan restore.');
    }

    private function generateSqlBackup(string $database): string
    {
        $connection = config('database.default');
        $process = new Process([
            $this->dumpBinary(),
            '--host='.config("database.connections.$connection.host"),
            '--port='.config("database.connections.$connection.port"),
            '--user='.config("database.connections.$connection.username"),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            $database,
        ]);

        if (filled(config("database.connections.$connection.password"))) {
            $process->setEnv(['MYSQL_PWD' => config("database.connections.$connection.password")]);
        }

        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
            return $process->getOutput();
        }

        return $this->generateSqlBackupFromDatabase($database);
    }

    private function generateSqlBackupFromDatabase(string $database): string
    {
        $sql = "-- Accounting GL database backup\n";
        $sql .= "-- Database: {$database}\n";
        $sql .= '-- Generated at: '.now()->toDateTimeString()."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

        $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->values();

        foreach ($tables as $table) {
            $tableName = $this->quoteIdentifier($table);
            $create = DB::select("SHOW CREATE TABLE {$tableName}")[0];
            $createSql = (array) $create;
            $createStatement = $createSql['Create Table'] ?? array_values($createSql)[1];

            $sql .= "DROP TABLE IF EXISTS {$tableName};\n";
            $sql .= $createStatement.";\n\n";

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$sql, $table) {
                foreach ($rows as $row) {
                    $values = array_map(fn ($value) => $this->sqlValue($value), array_values((array) $row));
                    $columns = array_map(fn ($column) => $this->quoteIdentifier($column), array_keys((array) $row));
                    $sql .= 'INSERT INTO '.$this->quoteIdentifier($table).' ('.implode(',', $columns).') VALUES ('.implode(',', $values).");\n";
                }
            });

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::getPdo()->quote((string) $value);
    }

    private function restoreSqlFromFile(string $source, bool $encrypted): string
    {
        $content = file_get_contents($source);

        if ($encrypted) {
            try {
                return Crypt::decryptString($content);
            } catch (\Throwable) {
                File::delete($source);

                throw ValidationException::withMessages(['backup_file' => 'Backup terenkripsi tidak valid atau APP_KEY berbeda.']);
            }
        }

        return $content;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function dumpBinary(): string
    {
        return env('DB_DUMP_BINARY') ?: 'mysqldump';
    }

    private function mysqlBinary(): string
    {
        return env('DB_MYSQL_BINARY') ?: 'mysql';
    }
}
