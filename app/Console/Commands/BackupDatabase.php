<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('hms:backup-database {--keep= : Days of backups to keep (defaults to config)}')]
#[Description('Dump the MySQL database to a compressed file on the backup disk and remove old backups')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}");

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->components->error("Backups support MySQL and MariaDB; the [{$connection}] connection uses [".($config['driver'] ?? 'unknown').'].');

            return self::FAILURE;
        }

        $temporary = storage_path('app/tmp-backup-'.Str::random(16).'.sql');

        // The password is passed through the environment, never on the command line.
        $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->timeout(3600)
            ->run([
                (string) config('hms.backups.mysqldump', 'mysqldump'),
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? ''),
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file='.$temporary,
                (string) ($config['database'] ?? ''),
            ]);

        if ($result->failed() || ! is_file($temporary)) {
            @unlink($temporary);
            Log::error('Database backup failed.', ['error' => Str::limit($result->errorOutput(), 500)]);
            $this->components->error('The database dump failed: '.Str::limit(trim($result->errorOutput()) ?: 'mysqldump produced no file.', 300));

            return self::FAILURE;
        }

        $disk = Storage::disk((string) config('hms.backups.disk', 'local'));
        $directory = trim((string) config('hms.backups.path', 'backups'), '/');
        $name = $directory.'/hms-'.now()->format('Y-m-d-His').'.sql.gz';

        $stream = fopen('php://temp', 'w+b');
        $source = fopen($temporary, 'rb');

        if ($stream === false || $source === false) {
            @unlink($temporary);
            $this->components->error('Could not open the dump for compression.');

            return self::FAILURE;
        }

        $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);

        if ($deflate === false) {
            fclose($source);
            @unlink($temporary);
            $this->components->error('The zlib extension could not start compression.');

            return self::FAILURE;
        }

        while (! feof($source)) {
            fwrite($stream, (string) deflate_add($deflate, (string) fread($source, 1024 * 1024), ZLIB_NO_FLUSH));
        }

        fwrite($stream, (string) deflate_add($deflate, '', ZLIB_FINISH));
        fclose($source);
        @unlink($temporary);
        rewind($stream);

        $disk->writeStream($name, $stream);
        fclose($stream);

        $size = $disk->size($name);
        $removed = $this->prune($directory);

        Log::info('Database backup created.', ['file' => $name, 'size' => $size, 'removed' => $removed]);
        $this->components->info("Backup written to {$name}; removed {$removed} old backup(s).");

        return self::SUCCESS;
    }

    /**
     * Remove backups older than the retention period. Age comes from the
     * timestamp in the file name, which survives copying between disks.
     */
    private function prune(string $directory): int
    {
        $keepDays = max(1, (int) ($this->option('keep') ?? config('hms.backups.keep_days', 14)));
        $cutoff = now()->subDays($keepDays);
        $disk = Storage::disk((string) config('hms.backups.disk', 'local'));
        $removed = 0;

        foreach ($disk->files($directory) as $file) {
            if (! preg_match('/^hms-(\d{4}-\d{2}-\d{2}-\d{6})\.sql\.gz$/', basename($file), $match)) {
                continue;
            }

            $created = CarbonImmutable::createFromFormat('Y-m-d-His', $match[1]);

            if ($created !== null && $created->lt($cutoff)) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
