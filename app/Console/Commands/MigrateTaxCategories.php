<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class MigrateTaxCategories extends Command
{
    protected $signature = 'tax:migrate-categories {--dry-run} {--force : Update akun yang sudah punya tax_category jika hasil deteksi berbeda}';

    protected $description = 'Isi tax_category akun pajak existing berdasarkan pola nama/kode lama.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $counts = [
            'ppn' => 0,
            'pph21' => 0,
            'pph23' => 0,
            'pph_final' => 0,
        ];

        Account::query()
            ->when(! $force, fn ($q) => $q->whereNull('tax_category'))
            ->orderBy('id')
            ->get()
            ->each(function (Account $account) use (&$counts, $dryRun) {
                $category = $this->detectCategory($account);
                if (! $category || $account->tax_category === $category) {
                    return;
                }

                $counts[$category]++;
                $this->line(($dryRun ? '[dry-run] ' : '').$account->code.' - '.$account->name.' => '.($account->tax_category ?: 'null').' -> '.$category);

                if (! $dryRun) {
                    $account->update(['tax_category' => $category]);
                }
            });

        $this->newLine();
        foreach ($counts as $category => $count) {
            $this->info($category.': '.$count.' akun '.($dryRun ? 'terdeteksi' : 'diupdate'));
        }

        return self::SUCCESS;
    }

    private function detectCategory(Account $account): ?string
    {
        $text = strtoupper($account->code.' '.$account->name);
        $normalized = preg_replace('/[^A-Z0-9]+/', ' ', $text) ?: '';

        if (preg_match('/\bPPH\s*(FINAL|4)\b/', $normalized)) {
            return 'pph_final';
        }

        if (preg_match('/\bPPH\s*(PS|PASAL)?\s*23\b/', $normalized) || preg_match('/\bPPH23\b/', $normalized)) {
            return 'pph23';
        }

        if (preg_match('/\bPPH\s*(PS|PASAL)?\s*21\b/', $normalized) || preg_match('/\bPPH21\b/', $normalized) || str_contains($normalized, 'PAJAK 21')) {
            return 'pph21';
        }

        if (str_contains($normalized, 'PPN') || str_contains($normalized, 'PAJAK PERTAMBAHAN NILAI')) {
            return 'ppn';
        }

        return null;
    }
}
