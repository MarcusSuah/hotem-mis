<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

/**
 * Issues sequential, gap-free document numbers per series and year, e.g.
 * "INV-2026-000042". The sequence row is locked for the duration of the
 * caller's transaction, so concurrent requests never share a number.
 */
class DocumentNumberGenerator
{
    public function next(string $series, string $prefix): string
    {
        return DB::transaction(function () use ($series, $prefix): string {
            $year = (int) now()->format('Y');

            DB::table('document_sequences')->insertOrIgnore([
                'name' => $series,
                'year' => $year,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('name', $series)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('document_sequences')
                ->where('id', $sequence->id)
                ->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return sprintf('%s-%d-%06d', strtoupper($prefix), $year, $number);
        });
    }
}
