<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * ENG-6: prior to this command there was no backup mechanism at all —
 * docs/ops/BACKUP_STRATEGY.md described what *should* happen but nothing
 * automated it. This is the minimum viable implementation that document
 * calls for:
 *
 *  - a full logical `mysqldump` of the database, gzip-compressed;
 *  - an archive of storage/app (documents not yet migrated to S3, FUN-5);
 *  - both written to an off-server disk (config('backup.disk'), default
 *    'backups' locally, expected to be BACKUP_DISK=s3 in production —
 *    see the config file's own docblock for why this matters);
 *  - retention pruning so this doesn't grow unbounded.
 *
 * This intentionally shells out to `mysqldump`/`tar` rather than adding a
 * new Composer dependency (e.g. spatie/laravel-backup) — those binaries
 * are already required wherever MySQL itself is required, so there's
 * nothing extra to install on the production host.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--dry-run : Report what would be backed up without shelling out or writing anything}';

    protected $description = 'ENG-6: nightly database + document backup to an off-server disk';

    public function handle(): int
    {
        $disk = (string) config('backup.disk');
        $timestamp = now()->format('Y-m-d_His');
        $prefix = now()->format('Y/m/d');

        $dbConnection = (string) config('database.default');
        $db = config("database.connections.{$dbConnection}");

        if (! $db || ($db['driver'] ?? null) !== 'mysql') {
            $this->error("backup:run currently only supports the mysql driver (connection '{$dbConnection}' is '".($db['driver'] ?? 'unknown')."').");

            return CommandAlias::FAILURE;
        }

        $dbDumpTarget = "{$prefix}/database-{$timestamp}.sql.gz";
        $filesTarget = "{$prefix}/storage-app-{$timestamp}.tar.gz";

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would dump database '{$db['database']}' to disk '{$disk}' at {$dbDumpTarget}.");
            foreach (config('backup.file_paths', []) as $path) {
                $this->info("[dry-run] Would archive '{$path}' to disk '{$disk}' at {$filesTarget}.");
            }
            $this->info('[dry-run] Would prune backups older than '.config('backup.retention_days').' day(s) on disk '.$disk.'.');

            return CommandAlias::SUCCESS;
        }

        try {
            $this->backupDatabase($db, $disk, $dbDumpTarget);
            $this->info("Database backup written to [{$disk}] {$dbDumpTarget}");

            $this->backupFiles($disk, $filesTarget);
            $this->info("File backup written to [{$disk}] {$filesTarget}");
        } catch (ProcessFailedException $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return CommandAlias::FAILURE;
        }

        $pruned = $this->pruneOldBackups($disk);
        if ($pruned > 0) {
            $this->info("Pruned {$pruned} backup file(s) older than ".config('backup.retention_days').' day(s).');
        }

        return CommandAlias::SUCCESS;
    }

    private function backupDatabase(array $db, string $disk, string $target): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'lawfirm-db-backup-').'.sql.gz';

        // --single-transaction keeps this non-blocking against InnoDB
        // tables (no long-held locks on a production database), and
        // --routines/--events capture anything beyond plain table data.
        $dumpCommand = sprintf(
            'mysqldump --single-transaction --routines --events -h%s -P%s -u%s %s | gzip > %s',
            escapeshellarg($db['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($db['port'] ?? 3306)),
            escapeshellarg($db['username'] ?? ''),
            escapeshellarg($db['database'] ?? ''),
            escapeshellarg($tempFile)
        );

        $process = Process::fromShellCommandline($dumpCommand, null, [
            // mysqldump reads the password from the environment rather
            // than the command line so it never shows up in `ps`/shell
            // history/process-list output.
            'MYSQL_PWD' => $db['password'] ?? '',
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($tempFile);
            throw new ProcessFailedException($process);
        }

        $this->storeAndDelete($tempFile, $disk, $target);
    }

    private function backupFiles(string $disk, string $target): void
    {
        $paths = config('backup.file_paths', []);
        $existingPaths = array_values(array_filter($paths, 'is_dir'));

        if (empty($existingPaths)) {
            $this->warn('No configured backup.file_paths exist on disk — skipping file archive.');

            return;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'lawfirm-files-backup-').'.tar.gz';

        $process = new Process(array_merge(['tar', '-czf', $tempFile], $existingPaths));
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($tempFile);
            throw new ProcessFailedException($process);
        }

        $this->storeAndDelete($tempFile, $disk, $target);
    }

    private function storeAndDelete(string $tempFile, string $disk, string $target): void
    {
        $stream = fopen($tempFile, 'r');
        try {
            Storage::disk($disk)->put($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tempFile);
        }
    }

    private function pruneOldBackups(string $disk): int
    {
        $retentionDays = (int) config('backup.retention_days');
        $cutoff = now()->subDays($retentionDays);
        $pruned = 0;

        foreach (Storage::disk($disk)->allFiles() as $file) {
            $lastModified = Storage::disk($disk)->lastModified($file);
            if ($lastModified !== false && $lastModified < $cutoff->timestamp) {
                Storage::disk($disk)->delete($file);
                $pruned++;
            }
        }

        return $pruned;
    }
}
