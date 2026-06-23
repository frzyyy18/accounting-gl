<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage('journal.view');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry) && $user->canManage('journal.view');
    }

    public function create(User $user): bool
    {
        return $user->canManage('journal.create');
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry)
            && $user->canManage('journal.create');
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry)
            && $user->canManage('journal.delete');
    }

    public function submit(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry)
            && $user->canManage('journal.create');
    }

    public function approve(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry)
            && $user->canManage('journal.approve');
    }

    public function reject(User $user, JournalEntry $journalEntry): bool
    {
        return $this->approve($user, $journalEntry);
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $this->sameCompany($user, $journalEntry)
            && $user->canManage('journal.post');
    }

    private function sameCompany(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $journalEntry->company_id;
    }
}
