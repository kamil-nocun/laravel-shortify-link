<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function queueRow(string $table, string $jobClass): void
{
    $payload = json_encode(['displayName' => $jobClass, 'data' => ['command' => 'O:'.strlen($jobClass).':"'.$jobClass.'":0:{}']]);

    $table === 'jobs'
        ? DB::table('jobs')->insert(['queue' => 'default', 'payload' => $payload, 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp])
        : DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => $payload, 'exception' => 'x', 'failed_at' => now()]);
}

test('queued and failed click jobs that still carry visitor data are removed and other jobs stay', function () {
    foreach (['jobs', 'failed_jobs'] as $table) {
        queueRow($table, 'App\\Jobs\\RecordLinkClick');
        queueRow($table, 'App\\Jobs\\SomethingElse');
    }

    (require database_path('migrations/2026_10_11_000100_purge_queued_click_jobs_with_visitor_data.php'))->up();

    foreach (['jobs', 'failed_jobs'] as $table) {
        expect(DB::table($table)->count())->toBe(1)
            ->and(DB::table($table)->value('payload'))->toContain('SomethingElse');
    }
});
