<?php

use App\Models\AuditLog;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-11-10 02:30:00');
});

it('writes a compressed database backup and removes expired ones', function () {
    Storage::fake('local');
    config(['hms.backups.disk' => 'local', 'hms.backups.keep_days' => 14]);
    Storage::disk('local')->put('backups/hms-2026-10-01-023000.sql.gz', 'old');
    Storage::disk('local')->put('backups/hms-2026-11-05-023000.sql.gz', 'recent');
    Storage::disk('local')->put('backups/keep-me.txt', 'unrelated');

    Process::fake(function (PendingProcess $process) {
        $resultFile = collect($process->command)->first(fn (string $argument) => str_starts_with($argument, '--result-file='));
        file_put_contents(substr((string) $resultFile, strlen('--result-file=')), "CREATE TABLE guests (id int);\n");

        return Process::result();
    });

    $this->artisan('hms:backup-database')->assertSuccessful();

    $backups = Storage::disk('local')->files('backups');
    expect($backups)->toContain('backups/hms-2026-11-10-023000.sql.gz', 'backups/hms-2026-11-05-023000.sql.gz', 'backups/keep-me.txt')
        ->not->toContain('backups/hms-2026-10-01-023000.sql.gz')
        ->and(gzdecode(Storage::disk('local')->get('backups/hms-2026-11-10-023000.sql.gz')))->toContain('CREATE TABLE guests');

    Process::assertRan(fn (PendingProcess $process) => ! in_array('--password', $process->command, true) && ($process->environment['MYSQL_PWD'] ?? null) !== null);
});

it('reports a failed backup instead of keeping a partial file', function () {
    Storage::fake('local');
    config(['hms.backups.disk' => 'local']);
    Process::fake(fn () => Process::result(errorOutput: 'Access denied for user', exitCode: 2));

    $this->artisan('hms:backup-database')->assertFailed();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('prunes audit entries older than the retention period', function () {
    config(['hms.audit.retention_days' => 365]);
    $old = AuditLog::create(['action' => 'test.old']);
    $old->forceFill(['created_at' => now()->subDays(400)])->save();
    AuditLog::create(['action' => 'test.recent']);

    $this->artisan('model:prune', ['--model' => [AuditLog::class]])->assertSuccessful();

    expect(AuditLog::query()->pluck('action')->all())->toBe(['test.recent']);
});

it('sends security headers on every response', function () {
    $this->get(route('home'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('keeps the generated permission and route docs in sync with the code', function () {
    $this->artisan('hms:generate-docs', ['--check' => true])->assertSuccessful();
});
