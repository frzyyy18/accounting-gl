<?php

namespace App\Support;

class SimplePdf
{
    // ─── Page dimensions (A4 landscape) ─────────────────────────────────────
    private const PAGE_W = 842;
    private const PAGE_H = 595;
    private const MARGIN  = 36;   // left / right margin

    // ─── Fonts (Type1 built-in) ──────────────────────────────────────────────
    private const FONT_REGULAR    = '/Helvetica';
    private const FONT_BOLD       = '/Helvetica-Bold';
    private const FONT_MONO       = '/Courier';
    private const FONT_MONO_BOLD  = '/Courier-Bold';

    // =========================================================================
    //  PUBLIC API
    // =========================================================================

    /**
     * Simple list-style PDF (trial balance, profit-loss, cash-flow, etc.)
     */
    public static function table(string $title, array $rows): string
    {
        // Header block: title (2 lines) + blank + data rows
        $headerLines = [$title, str_repeat('─', 96), ''];
        $allLines    = array_merge($headerLines, array_values(array_filter($rows, fn ($l) => $l !== null)));

        $linesPerPage = 38;   // rows that fit below the running page header
        $chunks       = array_chunk($allLines, $linesPerPage);
        $totalPages   = count($chunks);

        $objects    = [];
        $pageIds    = [];
        $contentIds = [];

        foreach ($chunks as $idx => $chunk) {
            $pageIds[]    = 3 + ($idx * 2);
            $contentIds[] = 4 + ($idx * 2);
            $objects[$contentIds[$idx]] = self::simpleContent($chunk, $idx + 1, $totalPages, $title);
        }

        return self::buildPdf($objects, $pageIds, $contentIds, self::FONT_REGULAR, self::FONT_BOLD);
    }

    /**
     * Fixed-width / monospaced report (legacy, kept for compat)
     */
    public static function fixedWidth(array $lines): string
    {
        $lines        = array_values($lines);
        $linesPerPage = 38;
        $chunks       = array_chunk($lines, $linesPerPage);
        $totalPages   = count($chunks);

        $objects    = [];
        $pageIds    = [];
        $contentIds = [];

        foreach ($chunks as $idx => $chunk) {
            $pageIds[]    = 3 + ($idx * 2);
            $contentIds[] = 4 + ($idx * 2);
            $objects[$contentIds[$idx]] = self::fixedWidthContent($chunk, $idx + 1, $totalPages);
        }

        return self::buildPdf($objects, $pageIds, $contentIds, self::FONT_MONO, self::FONT_MONO_BOLD);
    }

    /**
     * General Ledger – structured multi-section PDF
     */
    public static function generalLedger(string $company, string $period, array $sections): string
    {
        $rows = [];

        foreach ($sections as $section) {
            $rows[] = ['kind' => 'spacer'];
            $rows[] = ['kind' => 'account', 'description' => strtoupper((string) $section['account'])];
            $rows[] = [
                'kind'        => 'transaction',
                'type'        => '',
                'date'        => '',
                'description' => 'Opening Balance:',
                'department'  => '',
                'voucher'     => '',
                'debit'       => '',
                'credit'      => '',
                'balance'     => $section['opening_balance'],
                'bold'        => false,
            ];

            foreach ($section['rows'] as $r) {
                $rows[] = ['kind' => 'transaction'] + $r;
            }

            $rows[] = ['kind' => 'rule'];
            $rows[] = [
                'kind'        => 'transaction',
                'type'        => '',
                'date'        => '',
                'description' => 'Period Totals / Closing Balance:',
                'department'  => '',
                'voucher'     => '',
                'debit'       => $section['period_debit'],
                'credit'      => $section['period_credit'],
                'balance'     => $section['closing_balance'],
                'bold'        => true,
            ];
            $rows[] = ['kind' => 'rule'];
        }

        if ($sections === []) {
            $rows[] = ['kind' => 'account', 'description' => 'Tidak ada transaksi pada filter ini.'];
        }

        return self::ledgerPdf($company, $period, $rows);
    }

    public static function profitLossReport(string $company, string $period, array $rows, string $income, string $expense, string $netIncome, bool $hasComparison = false): string
    {
        $tableRows = [
            ['kind' => 'section', 'label' => 'LABA RUGI / PROFIT & LOSS'],
            ['kind' => 'header', 'columns' => $hasComparison
                ? ['Kode Akun', 'Nama Akun', 'Tipe', 'Nilai', 'Pembanding', 'Selisih']
                : ['Kode Akun', 'Nama Akun', 'Tipe', 'Nilai']],
        ];

        foreach ($rows as $row) {
            $tableRows[] = ['kind' => 'row', 'columns' => $row];
        }

        $tableRows[] = ['kind' => 'rule'];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Total Pendapatan', 'value' => $income];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Total Beban', 'value' => $expense];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Laba/Rugi Bersih', 'value' => $netIncome, 'bold' => true];

        return self::statementPdf($company, 'Laba Rugi / Profit & Loss', $period, $tableRows, $hasComparison ? 'wide' : 'normal');
    }

    public static function balanceSheetReport(string $company, string $period, array $sections, array $summary, bool $hasComparison = false): string
    {
        return self::balanceSheetPdf($company, $period, $sections, $summary, $hasComparison);
    }

    public static function trialBalanceReport(string $company, string $period, array $rows, string $totalDebit, string $totalCredit, bool $hasComparison = false): string
    {
        $tableRows = [
            ['kind' => 'section', 'label' => 'NERACA SALDO / TRIAL BALANCE'],
            ['kind' => 'header', 'columns' => $hasComparison
                ? ['Kode', 'Nama Akun', 'Debit', 'Kredit', 'Saldo Debit', 'Saldo Kredit', 'Pembanding']
                : ['Kode', 'Nama Akun', 'Debit', 'Kredit', 'Saldo Debit', 'Saldo Kredit']],
        ];

        foreach ($rows as $row) {
            $tableRows[] = ['columns' => $row];
        }

        $tableRows[] = ['kind' => 'rule'];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Total Debit', 'value' => $totalDebit, 'bold' => true];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Total Kredit', 'value' => $totalCredit, 'bold' => true];

        return self::statementPdf($company, 'NERACA SALDO / TRIAL BALANCE', $period, $tableRows, $hasComparison ? 'trial_balance_compare' : 'trial_balance');
    }

    public static function cashFlowReport(string $company, string $period, array $summary, array $transactions): string
    {
        $tableRows = [['kind' => 'section', 'label' => 'RINGKASAN ARUS KAS']];

        foreach ($summary as $label => $value) {
            $tableRows[] = ['kind' => 'summary', 'label' => (string) $label, 'value' => (string) $value, 'bold' => true];
        }

        $tableRows[] = ['kind' => 'spacer'];
        $tableRows[] = ['kind' => 'section', 'label' => 'MUTASI KAS / BANK'];
        $tableRows[] = ['kind' => 'header', 'columns' => ['Tanggal', 'Cabang', 'Tipe', 'Referensi', 'Keterangan', 'Masuk', 'Keluar']];

        foreach ($transactions as $row) {
            $tableRows[] = ['columns' => $row];
        }

        return self::statementPdf($company, 'ARUS KAS / CASH FLOW', $period, $tableRows, 'cash_flow');
    }

    public static function cashFlowIndirectReport(string $company, string $period, string $netIncome, array $adjustments, string $operatingCashFlow): string
    {
        $tableRows = [
            ['kind' => 'section', 'label' => 'ARUS KAS METODE TIDAK LANGSUNG'],
            ['kind' => 'summary', 'label' => 'Laba/Rugi Bersih', 'value' => $netIncome, 'bold' => true],
            ['kind' => 'spacer'],
            ['kind' => 'section', 'label' => 'PENYESUAIAN PERUBAHAN MODAL KERJA'],
            ['kind' => 'header', 'columns' => ['Kode', 'Nama Akun', 'Tipe', 'Nilai']],
        ];

        foreach ($adjustments as $row) {
            $tableRows[] = ['columns' => $row];
        }

        $tableRows[] = ['kind' => 'rule'];
        $tableRows[] = ['kind' => 'summary', 'label' => 'Arus Kas Bersih dari Aktivitas Operasi', 'value' => $operatingCashFlow, 'bold' => true];

        return self::statementPdf($company, 'ARUS KAS TIDAK LANGSUNG', $period, $tableRows, 'cash_flow_indirect');
    }

    // =========================================================================
    //  CONTENT BUILDERS
    // =========================================================================

    /**
     * Simple table content stream (one page worth of lines)
     */
    private static function simpleContent(array $lines, int $page, int $pages, string $title): string
    {
        $s = '';
                // ── Page border ──────────────────────────────────────────────────────
        $s .= self::rect(self::MARGIN, self::MARGIN, self::PAGE_W - self::MARGIN * 2, self::PAGE_H - self::MARGIN * 2);

        // ── Header bar ───────────────────────────────────────────────────────
        $headerY = self::PAGE_H - self::MARGIN - 20;
        $s .= self::filledRect(self::MARGIN, $headerY, self::PAGE_W - self::MARGIN * 2, 22, 0.93);
        $s .= self::text(self::PAGE_W / 2, $headerY + 7, self::FONT_BOLD, 11, $title, 'center');
        $s .= self::text(self::PAGE_W - self::MARGIN - 6, $headerY + 7, self::FONT_REGULAR, 8, "Page {$page}/{$pages}", 'right');

        // ── Divider ──────────────────────────────────────────────────────────
        $divY = $headerY - 6;
        $s .= self::hLine(self::MARGIN, $divY, self::PAGE_W - self::MARGIN);

        // ── Data rows ────────────────────────────────────────────────────────
        $x   = self::MARGIN + 8;
        $y   = $divY - 13;
        $lh  = 11;  // line height

        foreach ($lines as $idx => $line) {
            if ($idx === 0 && $page === 1) {
                // First line is the title – already in header; skip
                continue;
            }
            if ((string)$line === str_repeat('─', 96) || (string)$line === str_repeat('-', 96)) {
                $s .= self::hLine(self::MARGIN + 4, $y + 4, self::PAGE_W - self::MARGIN - 4);
                $y -= 6;
                continue;
            }

            $isBold = (str_ends_with(trim((string)$line), ':') && !str_contains((string)$line, '|'))
                      || str_starts_with(trim((string)$line), '===');

            $s .= self::text($x, $y, $isBold ? self::FONT_BOLD : self::FONT_REGULAR, 8.5, (string)$line);
            $y -= $lh;
        }

        // ── Footer ───────────────────────────────────────────────────────────
        $s .= self::hLine(self::MARGIN, self::MARGIN + 12, self::PAGE_W - self::MARGIN);
        $s .= self::text(self::PAGE_W / 2, self::MARGIN + 3, self::FONT_REGULAR, 8, "Page {$page} of {$pages}", 'center');

        return self::wrapStream($s);
    }

    /**
     * Fixed-width monospaced content stream
     */
    private static function fixedWidthContent(array $lines, int $page, int $pages): string
    {
        $s = '';
        $s .= self::rect(self::MARGIN, self::MARGIN, self::PAGE_W - self::MARGIN * 2, self::PAGE_H - self::MARGIN * 2);

        // ── Header ───────────────────────────────────────────────────────────
        $headerY = self::PAGE_H - self::MARGIN - 20;
        $s .= self::filledRect(self::MARGIN, $headerY, self::PAGE_W - self::MARGIN * 2, 22, 0.93);
        $s .= self::text(self::PAGE_W / 2, $headerY + 7, self::FONT_MONO_BOLD, 10, 'GENERAL LEDGER', 'center');
        $s .= self::text(self::PAGE_W - self::MARGIN - 6, $headerY + 7, self::FONT_MONO, 8, "Page {$page}/{$pages}", 'right');
        $s .= self::hLine(self::MARGIN, $headerY - 6, self::PAGE_W - self::MARGIN, 0.35);

        $y = $headerY - 19;

        foreach ($lines as $line) {
            $isBold = str_starts_with(trim((string)$line), 'REPORT ')
                || trim((string)$line) === 'GENERAL LEDGER';
            $font = $isBold ? self::FONT_MONO_BOLD : self::FONT_MONO;
            $s .= self::text(self::MARGIN + 8, $y, $font, 8, (string)$line);
            $y -= 11;
        }

        // ── Footer ───────────────────────────────────────────────────────────
        $s .= self::hLine(self::MARGIN, self::MARGIN + 12, self::PAGE_W - self::MARGIN);
        $s .= self::text(self::PAGE_W / 2, self::MARGIN + 3, self::FONT_MONO, 8, "Page {$page} of {$pages}", 'center');

        return self::wrapStream($s);
    }

    // =========================================================================
    //  GENERAL LEDGER DETAIL
    // =========================================================================

    private static function ledgerPdf(string $company, string $period, array $rows): string
    {
        $objects    = [];
        $pageChunks = array_chunk($rows, 28);
        $totalPages = count($pageChunks);
        $pageIds    = [];
        $contentIds = [];

        foreach ($pageChunks as $idx => $pageRows) {
            $pageIds[]    = 3 + ($idx * 2);
            $contentIds[] = 4 + ($idx * 2);
            $objects[$contentIds[$idx]] = self::ledgerContent($company, $period, $pageRows, $idx + 1, $totalPages);
        }

        return self::buildPdf($objects, $pageIds, $contentIds, self::FONT_REGULAR, self::FONT_BOLD, true);
    }

    private static function ledgerContent(string $company, string $period, array $rows, int $page, int $pages): string
    {
        $s = '';

        // ── Page border ──────────────────────────────────────────────────────
        $s .= self::rect(self::MARGIN, self::MARGIN, self::PAGE_W - self::MARGIN * 2, self::PAGE_H - self::MARGIN * 2);

        // ── Header area ──────────────────────────────────────────────────────
        $topY = self::PAGE_H - self::MARGIN - 4;

        $s .= self::filledRect(self::MARGIN, $topY - 50, self::PAGE_W - self::MARGIN * 2, 52, 0.93);

        $s .= self::text(self::PAGE_W / 2, $topY - 10, self::FONT_BOLD, 12, $company, 'center');
        $s .= self::text(self::PAGE_W / 2, $topY - 24, self::FONT_BOLD, 10, 'GENERAL LEDGER', 'center');
        $s .= self::text(self::PAGE_W / 2, $topY - 37, self::FONT_REGULAR, 8.5, $period, 'center');
        $s .= self::text(self::PAGE_W - self::MARGIN - 6, $topY - 10, self::FONT_REGULAR, 8, "Page {$page}/{$pages}", 'right');
        $s .= self::hLine(self::MARGIN, $topY - 52, self::PAGE_W - self::MARGIN, 0.35);

        // ── Column headers ───────────────────────────────────────────────────
        $columns = self::ledgerColumns();
        $colHeaderY = $topY - 62;

        $s .= self::filledRect(self::MARGIN, $colHeaderY - 2, self::PAGE_W - self::MARGIN * 2, 15, 0.38);
        foreach ($columns as $col) {
            $s .= self::text($col['x'], $colHeaderY + 3, self::FONT_BOLD, 8, $col['label'], $col['align'], 1);
        }
        $s .= self::hLine(self::MARGIN, $colHeaderY - 4, self::PAGE_W - self::MARGIN, 0.2);

        // ── Data rows ────────────────────────────────────────────────────────
        $y  = $colHeaderY - 18;
        $lh = 13;

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? '';

            if ($kind === 'spacer') {
                $y -= 5;
                continue;
            }

            if ($kind === 'rule') {
                $s .= self::hLine(self::MARGIN + 2, $y + 5, self::PAGE_W - self::MARGIN - 2);
                $y -= 8;
                continue;
            }

            if ($kind === 'account') {
                $s .= self::filledRect(self::MARGIN, $y - 2, self::PAGE_W - self::MARGIN * 2, $lh, 0.9);
                $s .= self::text(self::MARGIN + 6, $y + 2, self::FONT_BOLD, 8, (string)$row['description']);
                $y -= $lh + 1;
                continue;
            }

            // transaction row
            $font = ($row['bold'] ?? false) ? self::FONT_BOLD : self::FONT_REGULAR;
            foreach ($columns as $col) {
                $val = self::truncate((string)($row[$col['key']] ?? ''), $col['w'], 8);
                $s .= self::text($col['x'], $y + 1, $font, 8, $val, $col['align']);
            }

            // subtle alternating row tint every other data row
            $y -= $lh;
        }

        // ── Footer ───────────────────────────────────────────────────────────
        $s .= self::hLine(self::MARGIN, self::MARGIN + 12, self::PAGE_W - self::MARGIN);
        $s .= self::text(self::PAGE_W / 2, self::MARGIN + 3, self::FONT_REGULAR, 8, "Page {$page} of {$pages}", 'center');

        return self::wrapStream($s);
    }

    private static function ledgerColumns(): array
    {
        return [
            ['key' => 'type',        'label' => 'Type',        'x' => 40,  'w' => 36,  'align' => 'left'],
            ['key' => 'date',        'label' => 'Date',        'x' => 84,  'w' => 70,  'align' => 'left'],
            ['key' => 'description', 'label' => 'Description', 'x' => 165, 'w' => 220, 'align' => 'left'],
            ['key' => 'department',  'label' => 'Department',  'x' => 298, 'w' => 48,  'align' => 'left'],
            ['key' => 'voucher',     'label' => 'Voucher No',  'x' => 398, 'w' => 100, 'align' => 'left'],
            ['key' => 'debit',       'label' => 'Debit',       'x' => 618, 'w' => 72,  'align' => 'right'],
            ['key' => 'credit',      'label' => 'Credit',      'x' => 704, 'w' => 72,  'align' => 'right'],
            ['key' => 'balance',     'label' => 'Balance',     'x' => 800, 'w' => 72,  'align' => 'right'],
        ];
    }

    // =========================================================================
    //  FINANCIAL STATEMENT TABLES
    // =========================================================================

    private static function statementPdf(string $company, string $title, string $period, array $rows, string $layout): string
    {
        $pageChunks = array_chunk($rows, 29);
        $totalPages = max(1, count($pageChunks));
        $objects = [];
        $pageIds = [];
        $contentIds = [];

        foreach ($pageChunks ?: [[]] as $idx => $pageRows) {
            $pageIds[] = 3 + ($idx * 2);
            $contentIds[] = 4 + ($idx * 2);
            $objects[$contentIds[$idx]] = self::statementContent($company, $title, $period, $pageRows, $idx + 1, $totalPages, $layout);
        }

        return self::buildPdf($objects, $pageIds, $contentIds, self::FONT_REGULAR, self::FONT_BOLD);
    }

    private static function statementContent(string $company, string $title, string $period, array $rows, int $page, int $pages, string $layout): string
    {
        $s = '';
        $s .= self::rect(self::MARGIN, self::MARGIN, self::PAGE_W - self::MARGIN * 2, self::PAGE_H - self::MARGIN * 2);

        $topY = self::PAGE_H - self::MARGIN - 4;
        $s .= self::filledRect(self::MARGIN, $topY - 50, self::PAGE_W - self::MARGIN * 2, 52, 0.93);
        $s .= self::text(self::PAGE_W / 2, $topY - 10, self::FONT_BOLD, 12, $company, 'center');
        $s .= self::text(self::PAGE_W / 2, $topY - 24, self::FONT_BOLD, 10, $title, 'center');
        $s .= self::text(self::PAGE_W / 2, $topY - 37, self::FONT_REGULAR, 8.5, $period, 'center');
        $s .= self::text(self::PAGE_W - self::MARGIN - 6, $topY - 10, self::FONT_REGULAR, 8, "Page {$page}/{$pages}", 'right');
        $s .= self::hLine(self::MARGIN, $topY - 52, self::PAGE_W - self::MARGIN, 0.35);

        $columns = self::statementColumns($layout);
        $y = $topY - 70;
        $lh = 14;

        foreach ($rows as $row) {
            $kind = $row['kind'] ?? 'row';

            if ($kind === 'spacer') {
                $y -= 5;
                continue;
            }

            if ($kind === 'rule') {
                $s .= self::hLine(self::MARGIN + 4, $y + 5, self::PAGE_W - self::MARGIN - 4, 0.45);
                $y -= 7;
                continue;
            }

            if ($kind === 'section') {
                $s .= self::filledRect(self::MARGIN + 2, $y - 3, self::PAGE_W - self::MARGIN * 2 - 4, $lh + 1, 0.88);
                $s .= self::text(self::MARGIN + 8, $y + 1, self::FONT_BOLD, 8.5, (string) $row['label']);
                $y -= $lh + 2;
                continue;
            }

            if ($kind === 'header') {
                $s .= self::filledRect(self::MARGIN + 2, $y - 3, self::PAGE_W - self::MARGIN * 2 - 4, $lh + 1, 0.35);
                foreach ($columns as $idx => $col) {
                    $label = (string) ($row['columns'][$idx] ?? $col['label']);
                    $s .= self::text($col['x'], $y + 1, self::FONT_BOLD, 8, $label, $col['align'], 1);
                }
                $y -= $lh + 1;
                continue;
            }

            if ($kind === 'summary') {
                $font = ($row['bold'] ?? false) ? self::FONT_BOLD : self::FONT_REGULAR;
                $s .= self::hLine(self::MARGIN + 4, $y + 8, self::PAGE_W - self::MARGIN - 4, 0.75);
                $s .= self::text(self::MARGIN + 310, $y + 1, $font, 8.5, (string) $row['label']);
                $s .= self::text(self::PAGE_W - self::MARGIN - 12, $y + 1, $font, 8.5, (string) $row['value'], 'right');
                $y -= $lh;
                continue;
            }

            foreach ($columns as $idx => $col) {
                $value = (string) ($row['columns'][$idx] ?? '');
                $s .= self::text($col['x'], $y + 1, self::FONT_REGULAR, 8, self::truncate($value, $col['w'], 8), $col['align']);
            }
            $y -= $lh;
        }

        $s .= self::hLine(self::MARGIN, self::MARGIN + 12, self::PAGE_W - self::MARGIN);
        $s .= self::text(self::PAGE_W / 2, self::MARGIN + 3, self::FONT_REGULAR, 8, "Page {$page} of {$pages}", 'center');

        return self::wrapStream($s);
    }

    private static function statementColumns(string $layout): array
    {
        return match ($layout) {
            'wide' => [
                ['label' => 'Kode Akun', 'x' => 44, 'w' => 70, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 126, 'w' => 245, 'align' => 'left'],
                ['label' => 'Tipe', 'x' => 374, 'w' => 110, 'align' => 'left'],
                ['label' => 'Nilai', 'x' => 570, 'w' => 86, 'align' => 'right'],
                ['label' => 'Pembanding', 'x' => 680, 'w' => 86, 'align' => 'right'],
                ['label' => 'Selisih', 'x' => 800, 'w' => 86, 'align' => 'right'],
            ],
            'balance_compare' => [
                ['label' => 'Kode', 'x' => 44, 'w' => 70, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 126, 'w' => 360, 'align' => 'left'],
                ['label' => 'Saldo', 'x' => 650, 'w' => 95, 'align' => 'right'],
                ['label' => 'Pembanding', 'x' => 800, 'w' => 95, 'align' => 'right'],
            ],
            'balance' => [
                ['label' => 'Kode', 'x' => 44, 'w' => 70, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 126, 'w' => 430, 'align' => 'left'],
                ['label' => 'Saldo', 'x' => 800, 'w' => 110, 'align' => 'right'],
            ],
            'trial_balance_compare' => [
                ['label' => 'Kode', 'x' => 44, 'w' => 58, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 108, 'w' => 230, 'align' => 'left'],
                ['label' => 'Debit', 'x' => 412, 'w' => 82, 'align' => 'right'],
                ['label' => 'Kredit', 'x' => 510, 'w' => 82, 'align' => 'right'],
                ['label' => 'Saldo Debit', 'x' => 610, 'w' => 82, 'align' => 'right'],
                ['label' => 'Saldo Kredit', 'x' => 708, 'w' => 82, 'align' => 'right'],
                ['label' => 'Pembanding', 'x' => 804, 'w' => 82, 'align' => 'right'],
            ],
            'trial_balance' => [
                ['label' => 'Kode', 'x' => 44, 'w' => 70, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 126, 'w' => 270, 'align' => 'left'],
                ['label' => 'Debit', 'x' => 500, 'w' => 92, 'align' => 'right'],
                ['label' => 'Kredit', 'x' => 608, 'w' => 92, 'align' => 'right'],
                ['label' => 'Saldo Debit', 'x' => 714, 'w' => 92, 'align' => 'right'],
                ['label' => 'Saldo Kredit', 'x' => 806, 'w' => 92, 'align' => 'right'],
            ],
            'cash_flow' => [
                ['label' => 'Tanggal', 'x' => 44, 'w' => 58, 'align' => 'left'],
                ['label' => 'Cabang', 'x' => 108, 'w' => 42, 'align' => 'left'],
                ['label' => 'Tipe', 'x' => 156, 'w' => 105, 'align' => 'left'],
                ['label' => 'Referensi', 'x' => 268, 'w' => 82, 'align' => 'left'],
                ['label' => 'Keterangan', 'x' => 358, 'w' => 245, 'align' => 'left'],
                ['label' => 'Masuk', 'x' => 704, 'w' => 90, 'align' => 'right'],
                ['label' => 'Keluar', 'x' => 804, 'w' => 90, 'align' => 'right'],
            ],
            'cash_flow_indirect' => [
                ['label' => 'Kode', 'x' => 44, 'w' => 70, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 126, 'w' => 430, 'align' => 'left'],
                ['label' => 'Tipe', 'x' => 566, 'w' => 110, 'align' => 'left'],
                ['label' => 'Nilai', 'x' => 800, 'w' => 110, 'align' => 'right'],
            ],
            default => [
                ['label' => 'Kode Akun', 'x' => 44, 'w' => 80, 'align' => 'left'],
                ['label' => 'Nama Akun', 'x' => 140, 'w' => 330, 'align' => 'left'],
                ['label' => 'Tipe', 'x' => 478, 'w' => 140, 'align' => 'left'],
                ['label' => 'Nilai', 'x' => 800, 'w' => 110, 'align' => 'right'],
            ],
        };
    }

    private static function balanceSheetPdf(string $company, string $period, array $sections, array $summary, bool $hasComparison): string
    {
        $rows = [];
        $header = $hasComparison
            ? ['Kode', 'Nama Akun', 'Saldo', 'Pembanding']
            : ['Kode', 'Nama Akun', 'Saldo'];

        foreach ($sections as $section) {
            $rows[] = ['kind' => 'section', 'label' => (string) $section['title']];
            $rows[] = ['kind' => 'header', 'columns' => $header];

            foreach (($section['rows'] ?? []) as $row) {
                $rows[] = ['columns' => $row];
            }

            $rows[] = [
                'kind' => 'summary',
                'label' => (string) $section['total_label'],
                'value' => (string) $section['total'],
                'bold' => true,
            ];
            $rows[] = ['kind' => 'spacer'];
        }

        $rows[] = ['kind' => 'rule'];
        foreach ($summary as $label => $value) {
            $rows[] = [
                'kind' => 'summary',
                'label' => (string) $label,
                'value' => (string) $value,
                'bold' => true,
            ];
        }

        return self::statementPdf(
            $company,
            'NERACA / BALANCE SHEET',
            $period,
            $rows,
            $hasComparison ? 'balance_compare' : 'balance'
        );
    }

    // =========================================================================
    //  LOW-LEVEL PDF HELPERS
    // =========================================================================

    /** Build a complete PDF binary from pre-built objects */
    private static function buildPdf(
        array $objects,
        array $pageIds,
        array $contentIds,
        string $f1,
        string $f2,
        bool $includeCourier = false
    ): string {
        $fontDict = '/F1 << /Type /Font /Subtype /Type1 /BaseFont '.$f1.' >>'
            .' /F2 << /Type /Font /Subtype /Type1 /BaseFont '.$f2.' >>';

        if ($includeCourier) {
            $fontDict .= ' /F3 << /Type /Font /Subtype /Type1 /BaseFont /Courier >>'
                .' /F4 << /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['
            . implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageIds))
            . '] /Count ' . count($pageIds) . ' >>';

        foreach ($pageIds as $idx => $pageId) {
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . self::PAGE_W . ' ' . self::PAGE_H . ']'
                . ' /Resources << /Font << ' . $fontDict . ' >> >>'
                . ' /Contents ' . $contentIds[$idx] . ' 0 R >>';
        }

        ksort($objects);

        $pdf     = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $obj) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$obj}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** Wrap a content stream with proper PDF stream envelope */
    private static function wrapStream(string $stream): string
    {
        return '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
    }

    // ─── Graphics primitives ────────────────────────────────────────────────

    /** Horizontal line */
    private static function hLine(float $x1, float $y, float $x2, float $gray = 0.6): string
    {
        return round($gray, 2) . " G\n"
            . round($x1, 2) . ' ' . round($y, 2) . " m "
            . round($x2, 2) . ' ' . round($y, 2) . " l S\n0 G\n";
    }

    /** Rectangle (stroke only) */
    private static function rect(float $x, float $y, float $w, float $h, float $gray = 0.75): string
    {
        return round($gray, 2) . " G\n"
            . round($x, 2) . ' ' . round($y, 2) . ' ' . round($w, 2) . ' ' . round($h, 2)
            . " re S\n0 G\n";
    }

    /** Filled rectangle */
    private static function filledRect(float $x, float $y, float $w, float $h, float $gray = 0.9): string
    {
        return round($gray, 2) . " g\n"
            . round($x, 2) . ' ' . round($y, 2) . ' ' . round($w, 2) . ' ' . round($h, 2)
            . " re f\n0 g\n";
    }

    /** Place a text string */
    private static function text(float $x, float $y, string $font, float $size, string $text, string $align = 'left', float $gray = 0): string
    {
        $text = self::ascii($text);
        $approxW = strlen($text) * $size * 0.50;

        if ($align === 'center') {
            $x -= $approxW / 2;
        } elseif ($align === 'right') {
            $x -= $approxW;
        }

        return round($gray, 2) . " g\nBT\n{$font} {$size} Tf\n"
            . round($x, 2) . ' ' . round($y, 2) . " Td\n"
            . '(' . self::escape($text) . ") Tj\nET\n0 g\n";
    }

    /** Truncate text to fit column width */
    private static function truncate(string $text, float $colWidth, float $fontSize): string
    {
        $max = (int) floor($colWidth / ($fontSize * 0.50));
        if (strlen($text) > $max) {
            return substr($text, 0, max(0, $max - 1)) . '~';
        }
        return $text;
    }

    /** Strip non-ASCII characters */
    private static function ascii(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    /** Escape special PDF characters */
    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
 
