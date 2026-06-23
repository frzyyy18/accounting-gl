<?php

namespace App\Http\Controllers;

use App\Models\CashBankTransaction;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Storage;

class TransactionAttachmentController extends Controller
{
    public function journal(JournalEntry $journal)
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $journal->company_id, 403);
        abort_unless($journal->attachment_path && Storage::disk('local')->exists($journal->attachment_path), 404);

        return Storage::disk('local')->download($journal->attachment_path, $journal->attachment_name);
    }

    public function cashBank(CashBankTransaction $transaction)
    {
        abort_unless(auth()->user()->hasRole('super_admin') || auth()->user()->company_id === $transaction->company_id, 403);
        abort_unless($transaction->attachment_path && Storage::disk('local')->exists($transaction->attachment_path), 404);

        return Storage::disk('local')->download($transaction->attachment_path, $transaction->attachment_name);
    }
}
