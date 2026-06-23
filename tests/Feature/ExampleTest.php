<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CashBank;
use App\Models\CashBankTransaction;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Test', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)
            ->post('/journals', [
                'transaction_date' => '2026-05-15',
                'branch_id' => $branch->id,
                'status' => 'draft',
                'description' => 'Jurnal tidak balance',
                'details' => [
                    ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 900],
                ],
            ])
            ->assertSessionHasErrors('details');
    }

    public function test_journal_fiscal_amount_cannot_exceed_line_amount(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Fiscal Limit', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'fiscal-limit@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'Beban', 'type' => 'expense', 'is_active' => true]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)
            ->post('/journals', [
                'transaction_date' => '2026-05-15',
                'branch_id' => $branch->id,
                'status' => 'draft',
                'description' => 'Jurnal fiskal tidak valid',
                'details' => [
                    ['account_id' => $expense->id, 'debit' => 1000, 'credit' => 0, 'fiscal_amount' => 1001],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => 1000],
                ],
            ])
            ->assertSessionHasErrors('details');
    }

    public function test_core_pages_render_for_authenticated_user(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Test', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'admin2@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $approver = User::create(['name' => 'Approver', 'email' => 'approver-core@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'submitted',
            'description' => 'Penjualan tunai',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::first();
        $this->actingAs($approver)->post("/journals/{$journal->id}/approve")->assertRedirect();
        $this->actingAs($approver)->post("/journals/{$journal->id}/post")->assertRedirect();

        foreach ([
            '/dashboard',
            '/companies',
            '/companies/create',
            '/branches',
            '/branches/create',
            '/fiscal-periods',
            '/fiscal-periods/create',
            '/accounts',
            '/accounts/create',
            '/cash-banks',
            '/cash-banks/create',
            '/cash-bank-transactions',
            '/cash-bank-transactions/cash_in/create',
            '/cash-bank-transactions/cash_out/create',
            '/cash-bank-transactions/transfer/create',
            '/journals',
            '/journals/create',
            '/reports/ledger',
            '/reports/trial-balance',
            '/reports/profit-loss',
            '/reports/balance-sheet',
            '/reports/cash-flow',
            '/reports/cash-flow-indirect',
            '/reports/audit-trail',
            '/bank-reconciliations',
            '/bank-reconciliations/create',
            '/closing-entries/create',
            '/users',
            '/users/create',
            '/roles',
            '/backups',
        ] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_locked_period_blocks_staff_journal_changes(): void
    {
        $role = Role::create(['name' => 'journal_staff', 'label' => 'Journal Staff', 'permissions' => ['journal.view', 'journal.create']]);
        $company = Company::create(['name' => 'PT Locked', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Mei 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'locked']);
        $user = User::create(['name' => 'Staff', 'email' => 'staff@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Jurnal periode locked',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertSessionHasErrors('transaction_date');
    }

    public function test_fiscal_period_with_journals_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Period Delete', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $period = FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Mei 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'locked']);
        $user = User::create(['name' => 'Manager', 'email' => 'period-delete@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-PERIOD', 'description' => 'Transaksi periode', 'status' => 'posted', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->delete("/fiscal-periods/{$period->id}")
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('fiscal_periods', ['id' => $period->id]);
    }

    public function test_fiscal_periods_cannot_overlap_in_same_company(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Period Overlap', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Manager', 'email' => 'period-overlap@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Mei 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'open']);

        $this->actingAs($user)->post('/fiscal-periods', [
            'name' => 'Overlap Mei',
            'start_date' => '2026-05-15',
            'end_date' => '2026-06-15',
            'status' => 'open',
        ])->assertStatus(422);
    }

    public function test_kasir_cabang_cannot_open_journal_module(): void
    {
        $role = Role::create(['name' => 'kasir_cabang', 'label' => 'Kasir Cabang']);
        $company = Company::create(['name' => 'PT Viewer', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Kasir', 'email' => 'kasir@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->get('/journals')->assertForbidden();
        $this->actingAs($user)->get('/reports/trial-balance')->assertOk();
    }

    public function test_trial_balance_renders_balances_and_comparison_period(): void
    {
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $company = Company::create(['name' => 'PT Trial Balance', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Auditor', 'email' => 'trial-balance@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $current = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-05-15',
            'journal_number' => 'JV-20260515-001',
            'description' => 'Penjualan tunai',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        JournalDetail::create(['journal_entry_id' => $current->id, 'account_id' => $cash->id, 'debit' => 1500, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $current->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1500]);

        $comparison = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-04-15',
            'journal_number' => 'JV-20260415-001',
            'description' => 'Penjualan pembanding',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        JournalDetail::create(['journal_entry_id' => $comparison->id, 'account_id' => $cash->id, 'debit' => 900, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $comparison->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 900]);

        $this->actingAs($user)
            ->get('/reports/trial-balance?from=2026-05-01&to=2026-05-31&compare_from=2026-04-01&compare_to=2026-04-30')
            ->assertOk()
            ->assertSee('Saldo Pembanding')
            ->assertSee('Rp 1.500,00')
            ->assertSee('Rp 900,00');

        $this->actingAs($user)
            ->get('/reports/trial-balance?from=2026-05-01&to=2026-05-31&export=excel')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel');

        $this->actingAs($user)
            ->get('/reports/trial-balance?from=2026-05-01&to=2026-05-31&export=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_ledger_running_balance_is_per_account_and_includes_opening_balance(): void
    {
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $company = Company::create(['name' => 'PT Ledger Balance', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Auditor', 'email' => 'ledger-balance@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $opening = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-04-30',
            'journal_number' => 'JV-OPEN',
            'description' => 'Saldo awal',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        JournalDetail::create(['journal_entry_id' => $opening->id, 'account_id' => $cash->id, 'debit' => 500, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $opening->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 500]);

        $current = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-05-15',
            'journal_number' => 'JV-CURRENT',
            'description' => 'Penjualan Mei',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        JournalDetail::create(['journal_entry_id' => $current->id, 'account_id' => $cash->id, 'debit' => 1500, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $current->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1500]);

        $this->actingAs($user)
            ->get('/reports/ledger?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertSeeInOrder(['1010 - Kas', 'Rp 1.500,00', 'Rp 2.000,00'])
            ->assertSeeInOrder(['4000 - Pendapatan', 'Rp 1.500,00', 'Rp 2.000,00']);
    }

    public function test_journal_list_search_nominal_duplicate_and_detail_navigation(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Journal UX', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'journal-ux@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $first = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-10', 'journal_number' => 'JV-UX-OLD', 'reference_number' => 'VCH-UX-OLD', 'description' => 'Jurnal lama', 'status' => 'posted', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $first->id, 'account_id' => $cash->id, 'debit' => 1000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $first->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000]);

        $second = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-11', 'journal_number' => 'JV-UX-NEW', 'reference_number' => 'VCH-UX-NEW', 'description' => 'Jurnal baru dicari', 'status' => 'draft', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $second->id, 'account_id' => $cash->id, 'debit' => 2500, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $second->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 2500]);

        $this->actingAs($user)
            ->get('/journals?q=baru')
            ->assertOk()
            ->assertSee('Nominal')
            ->assertSee('Rp 2.500,00')
            ->assertSee('VCH-UX-NEW')
            ->assertDontSee('VCH-UX-OLD');

        $this->actingAs($user)
            ->get('/journals/'.$first->id)
            ->assertOk()
            ->assertSee('Berikutnya')
            ->assertSee('Akun Terbesar');

        $this->actingAs($user)
            ->get('/journals/'.$second->id.'/duplicate')
            ->assertOk()
            ->assertSee('Salinan dari jurnal VCH-UX-NEW')
            ->assertSee('Jurnal baru dicari')
            ->assertSee('2500.00');
    }

    public function test_admin_pajak_without_company_can_open_journals_with_default_company(): void
    {
        $role = Role::create(['name' => 'admin_pajak', 'label' => 'Admin Pajak']);
        Company::create(['name' => 'PT Default Pajak', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin Pajak', 'email' => 'admin-pajak-null-company@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => null]);

        $this->actingAs($user)->get('/journals')->assertOk();
        $this->actingAs($user)->get('/journals/create')->assertOk();
    }

    public function test_role_permission_page_is_limited_to_authorized_user(): void
    {
        $company = Company::create(['name' => 'PT Permission', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $viewerRole = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $super = User::create(['name' => 'Super', 'email' => 'super-permission@test.local', 'password' => 'password', 'role_id' => $superRole->id, 'company_id' => $company->id]);
        $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer-permission@test.local', 'password' => 'password', 'role_id' => $viewerRole->id, 'company_id' => $company->id]);

        $this->actingAs($super)->get('/roles')->assertOk();
        $this->actingAs($viewer)->get('/roles')->assertForbidden();
    }

    public function test_internal_manager_cannot_manage_other_company_or_super_admin_users(): void
    {
        $adminRole = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Admin', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $otherCompany = Company::create(['name' => 'PT Lain', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-company@test.local', 'password' => 'password', 'role_id' => $adminRole->id, 'company_id' => $company->id]);
        $otherUser = User::create(['name' => 'Other', 'email' => 'other-company@test.local', 'password' => 'password', 'role_id' => $adminRole->id, 'company_id' => $otherCompany->id]);

        $this->actingAs($admin)->get("/companies/{$otherCompany->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get('/companies/create')->assertForbidden();
        $this->actingAs($admin)->get("/users/{$otherUser->id}/edit")->assertForbidden();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Escalated',
            'email' => 'escalated@test.local',
            'role_id' => $superRole->id,
            'company_id' => $otherCompany->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_and_filter_branches_by_company(): void
    {
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $companyA = Company::create(['code' => 'A', 'name' => 'PT A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['code' => 'B', 'name' => 'PT B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $super = User::create(['name' => 'Super', 'email' => 'super-branch@test.local', 'password' => 'password', 'role_id' => $superRole->id, 'company_id' => $companyA->id]);

        $this->actingAs($super)
            ->post('/branches', [
                'company_id' => $companyB->id,
                'code' => 'SBY',
                'name' => 'Surabaya',
                'is_active' => true,
            ])
            ->assertRedirect('/branches');

        $this->assertDatabaseHas('branches', [
            'company_id' => $companyB->id,
            'code' => 'SBY',
            'name' => 'Surabaya',
        ]);

        $this->actingAs($super)
            ->get('/branches?company_id='.$companyB->id)
            ->assertOk()
            ->assertSee('B - PT B')
            ->assertSee('SBY');

        $this->actingAs($super)
            ->get('/branches?company_id='.$companyA->id)
            ->assertOk()
            ->assertDontSee('SBY');
    }

    public function test_account_parent_must_belong_to_same_company(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Account', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $otherCompany = Company::create(['name' => 'PT Parent Lain', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'account-parent@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $otherParent = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Aset Lain', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)->post('/accounts', [
            'code' => '1010',
            'name' => 'Kas',
            'type' => 'asset',
            'parent_id' => $otherParent->id,
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_account_parent_cannot_create_cycle(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Account Cycle', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'account-cycle@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $parent = Account::create(['company_id' => $company->id, 'code' => '1000', 'name' => 'Aset', 'type' => 'asset', 'is_active' => true]);
        $child = Account::create(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)->put("/accounts/{$parent->id}", [
            'code' => '1000',
            'name' => 'Aset',
            'type' => 'asset',
            'parent_id' => $child->id,
            'is_active' => true,
        ])->assertStatus(422);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_bank_reconciliation_ignores_transactions_from_other_bank(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Rekonsiliasi Aman', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'secure-reconcile@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccountA = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank A', 'type' => 'asset', 'is_active' => true]);
        $bankAccountB = Account::create(['company_id' => $company->id, 'code' => '1030', 'name' => 'Bank B', 'type' => 'asset', 'is_active' => true]);
        $bankA = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccountA->id, 'name' => 'Bank A', 'opening_balance' => 1000, 'is_active' => true]);
        $bankB = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccountB->id, 'name' => 'Bank B', 'opening_balance' => 0, 'is_active' => true]);
        $otherBankTransaction = CashBankTransaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'cash_bank_id' => $bankB->id,
            'type' => 'cash_in',
            'transaction_date' => '2026-05-15',
            'amount' => 500,
            'description' => 'Mutasi bank lain',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post('/bank-reconciliations', [
            'cash_bank_id' => $bankA->id,
            'statement_date' => '2026-05-31',
            'bank_statement_balance' => 1000,
            'transaction_ids' => [$otherBankTransaction->id],
            'notes' => 'Harus abaikan transaksi bank lain',
        ])->assertRedirect();

        $this->assertFalse($otherBankTransaction->fresh()->is_reconciled);
        $this->assertDatabaseHas('bank_reconciliations', ['cash_bank_id' => $bankA->id, 'book_balance' => 1000, 'difference' => 0]);
    }

    public function test_role_permissions_can_be_updated(): void
    {
        $company = Company::create(['name' => 'PT Role Update', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $staffRole = Role::create(['name' => 'custom_staff', 'label' => 'Custom Staff']);
        $super = User::create(['name' => 'Super', 'email' => 'super-role@test.local', 'password' => 'password', 'role_id' => $superRole->id, 'company_id' => $company->id]);
        $permission = Permission::create(['code' => 'report.view', 'module' => 'Laporan', 'label' => 'Lihat Laporan Keuangan']);

        $this->actingAs($super)->put("/roles/{$staffRole->id}", [
            'permissions' => ['report.view'],
        ])->assertRedirect('/roles');

        $this->assertTrue($staffRole->fresh()->hasPermission('report.view'));
        $this->assertDatabaseHas('permission_role', ['role_id' => $staffRole->id, 'permission_id' => $permission->id]);
    }

    public function test_super_admin_role_cannot_be_modified_through_permissions_page(): void
    {
        $company = Company::create(['name' => 'PT Role Guard', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $super = User::create(['name' => 'Super', 'email' => 'super-role-guard@test.local', 'password' => 'password', 'role_id' => $superRole->id, 'company_id' => $company->id]);
        Permission::create(['code' => 'report.view', 'module' => 'Laporan', 'label' => 'Lihat Laporan Keuangan']);

        $this->actingAs($super)->put("/roles/{$superRole->id}", [
            'permissions' => ['report.view'],
        ])->assertStatus(422);
    }

    public function test_transaction_objects_are_forbidden_across_companies(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Akses', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $otherCompany = Company::create(['name' => 'PT Rahasia', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'idor@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'code' => 'OTH', 'name' => 'Other']);
        $otherAccount = Account::create(['company_id' => $otherCompany->id, 'code' => '1010', 'name' => 'Kas Rahasia', 'type' => 'asset', 'is_active' => true]);
        $otherCashBank = CashBank::create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $otherAccount->id, 'name' => 'Bank Rahasia', 'opening_balance' => 0, 'is_active' => true]);
        $otherJournal = JournalEntry::create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'transaction_date' => '2026-05-15',
            'journal_number' => 'JV-OTHER-001',
            'description' => 'Jurnal company lain',
            'status' => 'submitted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get("/branches/{$otherBranch->id}/edit")->assertForbidden();
        $this->actingAs($user)->get("/accounts/{$otherAccount->id}/edit")->assertForbidden();
        $this->actingAs($user)->get("/cash-banks/{$otherCashBank->id}")->assertForbidden();
        $this->actingAs($user)->get("/journals/{$otherJournal->id}")->assertForbidden();
        $this->actingAs($user)->post("/journals/{$otherJournal->id}/approve")->assertForbidden();
    }

    public function test_journal_workflow_rejects_skipped_steps_and_posted_edits(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Workflow', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'workflow@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $approver = User::create(['name' => 'Approver', 'email' => 'workflow-approver@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Workflow check',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::where('description', 'Workflow check')->first();
        $this->actingAs($user)->post("/journals/{$journal->id}/approve")->assertStatus(422);
        $this->actingAs($user)->post("/journals/{$journal->id}/post")->assertStatus(422);

        $this->actingAs($user)->post("/journals/{$journal->id}/submit")->assertRedirect();
        $this->assertSame($user->id, $journal->fresh()->submitted_by);
        $this->actingAs($user)
            ->post("/journals/{$journal->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda tidak dapat menyetujui jurnal yang Anda ajukan sendiri.');
        $this->actingAs($user)->post("/journals/{$journal->id}/post")->assertStatus(422);
        $this->actingAs($approver)->post("/journals/{$journal->id}/approve")->assertRedirect();
        $this->actingAs($approver)->post("/journals/{$journal->id}/post")->assertRedirect();

        $journal = $journal->fresh();
        $this->actingAs($user)->get("/journals/{$journal->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/journals/{$journal->id}", [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Attempt edit posted',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertForbidden();
        $this->actingAs($user)->delete("/journals/{$journal->id}")->assertForbidden();
    }

    public function test_journal_can_be_cancelled_by_creator_with_reason(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Cancel', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $creator = User::create(['name' => 'Creator', 'email' => 'cancel-creator@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $otherUser = User::create(['name' => 'Other', 'email' => 'cancel-other@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($creator)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Jurnal batal',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::where('description', 'Jurnal batal')->firstOrFail();

        $this->actingAs($otherUser)
            ->post("/journals/{$journal->id}/cancel", ['cancellation_reason' => 'Alasan cukup panjang'])
            ->assertForbidden();

        $this->actingAs($creator)
            ->post("/journals/{$journal->id}/cancel", ['cancellation_reason' => 'pendek'])
            ->assertSessionHasErrors('cancellation_reason');

        $this->actingAs($creator)
            ->post("/journals/{$journal->id}/cancel", ['cancellation_reason' => 'Dokumen sumber transaksi salah input'])
            ->assertRedirect('/journals');

        $journal = $journal->fresh();
        $this->assertSame('cancelled', $journal->status);
        $this->assertSame($creator->id, $journal->cancelled_by);
        $this->assertSame('Dokumen sumber transaksi salah input', $journal->cancellation_reason);
        $this->assertNotNull($journal->cancelled_at);

        $this->actingAs($creator)
            ->get("/journals/{$journal->id}")
            ->assertOk()
            ->assertSee('Jurnal dibatalkan')
            ->assertSee('Dokumen sumber transaksi salah input');
    }

    public function test_journal_uploads_reject_unsafe_files_and_downloads_are_company_scoped(): void
    {
        Storage::fake('local');

        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Upload', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $otherCompany = Company::create(['name' => 'PT Upload Lain', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'code' => 'OTH', 'name' => 'Other']);
        $user = User::create(['name' => 'Admin', 'email' => 'upload@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $otherUser = User::create(['name' => 'Other', 'email' => 'upload-other@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $otherCompany->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $otherCash = Account::create(['company_id' => $otherCompany->id, 'code' => '1010', 'name' => 'Kas Other', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Unsafe upload',
            'attachment' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertSessionHasErrors('attachment');

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Safe upload',
            'attachment' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::where('description', 'Safe upload')->first();
        Storage::disk('local')->assertExists($journal->attachment_path);
        $this->actingAs($user)->get("/journals/{$journal->id}/attachment")->assertOk();

        $otherJournal = JournalEntry::create([
            'company_id' => $otherCompany->id,
            'branch_id' => $otherBranch->id,
            'transaction_date' => '2026-05-15',
            'journal_number' => 'JV-UPLOAD-OTHER',
            'description' => 'Other upload',
            'attachment_path' => 'transaction-attachments/journals/other.pdf',
            'attachment_name' => 'other.pdf',
            'status' => 'draft',
            'created_by' => $otherUser->id,
        ]);
        JournalDetail::create(['journal_entry_id' => $otherJournal->id, 'account_id' => $otherCash->id, 'debit' => 1, 'credit' => 0]);
        Storage::disk('local')->put('transaction-attachments/journals/other.pdf', 'private');

        $this->actingAs($user)->get("/journals/{$otherJournal->id}/attachment")->assertForbidden();
    }

    public function test_cash_bank_transactions_validate_branch_scope_and_locked_periods(): void
    {
        $role = Role::create(['name' => 'kasir_cabang', 'label' => 'Kasir Cabang']);
        $company = Company::create(['name' => 'PT Cash Scope', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branchA = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $branchB = Branch::create(['company_id' => $company->id, 'code' => 'BDG', 'name' => 'Bandung']);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Mei 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'locked']);
        $user = User::create(['name' => 'Finance', 'email' => 'cash-scope@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cashAccount = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'Beban', 'type' => 'expense', 'is_active' => true]);
        $companyCash = CashBank::create(['company_id' => $company->id, 'account_id' => $cashAccount->id, 'scope' => 'company', 'kind' => 'cash', 'name' => 'Kas Utama', 'opening_balance' => 0, 'is_active' => true]);
        $branchBank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branchA->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank JKT', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/cash_out', [
            'transaction_date' => '2026-06-15',
            'branch_id' => $branchB->id,
            'cash_bank_id' => $branchBank->id,
            'counter_account_id' => $expense->id,
            'amount' => 100,
            'description' => 'Wrong branch bank',
        ])->assertSessionHasErrors('cash_bank_id');

        $this->actingAs($user)->post('/cash-bank-transactions/cash_out', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branchA->id,
            'cash_bank_id' => $companyCash->id,
            'counter_account_id' => $expense->id,
            'amount' => 100,
            'description' => 'Locked period cash out',
        ])->assertSessionHasErrors('transaction_date');
    }

    public function test_reports_do_not_leak_other_company_data(): void
    {
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $company = Company::create(['name' => 'PT Report A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $otherCompany = Company::create(['name' => 'PT Report B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'code' => 'OTH', 'name' => 'Other']);
        $user = User::create(['name' => 'Auditor', 'email' => 'report-scope@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas Internal', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan Internal', 'type' => 'revenue', 'is_active' => true]);
        $otherCash = Account::create(['company_id' => $otherCompany->id, 'code' => '1010', 'name' => 'Kas Rahasia', 'type' => 'asset', 'is_active' => true]);
        $otherRevenue = Account::create(['company_id' => $otherCompany->id, 'code' => '4000', 'name' => 'Pendapatan Rahasia', 'type' => 'revenue', 'is_active' => true]);

        $journal = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-REPORT-A', 'description' => 'Visible', 'status' => 'posted', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $cash->id, 'debit' => 1000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000]);

        $otherJournal = JournalEntry::create(['company_id' => $otherCompany->id, 'branch_id' => $otherBranch->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-REPORT-B', 'description' => 'Secret', 'status' => 'posted', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $otherJournal->id, 'account_id' => $otherCash->id, 'debit' => 9999, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $otherJournal->id, 'account_id' => $otherRevenue->id, 'debit' => 0, 'credit' => 9999]);

        $this->actingAs($user)
            ->get('/reports/ledger?status=posted')
            ->assertOk()
            ->assertSee('Kas Internal')
            ->assertDontSee('Kas Rahasia')
            ->assertDontSee('Rp 9.999,00');
    }

    public function test_backup_restore_is_permission_guarded(): void
    {
        $company = Company::create(['name' => 'PT Backup Guard', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $viewerRole = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $superRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $viewer = User::create(['name' => 'Viewer', 'email' => 'backup-viewer@test.local', 'password' => 'password', 'role_id' => $viewerRole->id, 'company_id' => $company->id]);
        $super = User::create(['name' => 'Super', 'email' => 'backup-super@test.local', 'password' => 'password', 'role_id' => $superRole->id, 'company_id' => $company->id]);

        $this->actingAs($viewer)->get('/backups')->assertForbidden();
        $this->actingAs($viewer)->post('/backups/download')->assertForbidden();
        $this->actingAs($viewer)->post('/backups/restore', [
            'backup_file' => UploadedFile::fake()->create('backup.sql', 1, 'text/plain'),
            'confirmation' => 'RESTORE',
            'current_password' => 'password',
        ])->assertForbidden();

        $this->actingAs($super)
            ->get('/backups')
            ->assertOk()
            ->assertSee('File Backup Terenkripsi (.sql.enc)')
            ->assertSee('accept=".sql.enc"', false);
        $this->actingAs($super)->post('/backups/download')->assertStatus(422);
    }

    public function test_restore_requires_current_password(): void
    {
        $company = Company::create(['name' => 'PT Restore Password', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $user = User::create(['name' => 'Super', 'email' => 'restore-password@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->post('/backups/restore', [
            'backup_file' => UploadedFile::fake()->create('backup.sql.enc', 1, 'text/plain'),
            'confirmation' => 'RESTORE',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_restore_rejects_plain_sql_backup_files(): void
    {
        $company = Company::create(['name' => 'PT Restore Plain SQL', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $user = User::create(['name' => 'Super', 'email' => 'restore-plain@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->post('/backups/restore', [
            'backup_file' => UploadedFile::fake()->create('backup.sql', 1, 'text/plain'),
            'confirmation' => 'RESTORE',
            'current_password' => 'password',
        ])->assertSessionHasErrors('backup_file');
    }

    public function test_cash_bank_view_permission_cannot_create_cash_bank(): void
    {
        $role = Role::create(['name' => 'cash_bank_viewer', 'label' => 'Cash Bank Viewer', 'permissions' => ['cash_bank.view']]);
        $company = Company::create(['name' => 'PT Cash Bank Guard', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Viewer', 'email' => 'cash-bank-view@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $account = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)->post('/cash-banks', [
            'account_id' => $account->id,
            'scope' => 'branch',
            'kind' => 'cash',
            'branch_id' => $branch->id,
            'name' => 'Kas View Only',
            'opening_balance' => 1000,
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('cash_banks', ['name' => 'Kas View Only']);
    }

    public function test_cash_in_creates_posted_journal_and_updates_balance(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Cash', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'cash@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cashAccount = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $cashBank = CashBank::create(['company_id' => $company->id, 'account_id' => $cashAccount->id, 'name' => 'Kas Utama', 'opening_balance' => 500, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/cash_in', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'cash_bank_id' => $cashBank->id,
            'counter_account_id' => $revenue->id,
            'amount' => 1000,
            'description' => 'Penerimaan kas',
        ])->assertRedirect('/cash-bank-transactions');

        $this->assertDatabaseHas('journal_entries', ['company_id' => $company->id, 'status' => 'posted', 'description' => 'Penerimaan kas']);
        $this->assertSame(1500.0, $cashBank->fresh()->currentBalance());
    }

    public function test_store_receivable_posts_piutang_and_penjualan(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Tagihan', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'tagihan@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $sales = Account::create(['company_id' => $company->id, 'code' => '400.01', 'name' => 'Penjualan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)->post('/store-receivables', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'receivable_account_id' => $receivable->id,
            'sales_account_id' => $sales->id,
            'store_name' => 'Toko ABC',
            'reference_number' => 'INV-001',
            'amount' => 100000000,
        ])->assertRedirect();

        $journal = JournalEntry::where('reference_number', 'INV-001')->firstOrFail();
        $this->assertSame('posted', $journal->status);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'debit' => 100000000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $sales->id, 'debit' => 0, 'credit' => 100000000]);
    }

    public function test_receivable_receipt_posts_voucher_to_kas_tagihan_and_piutang(): void
    {
        $role = Role::create(['name' => 'admin_pajak', 'label' => 'Admin Pajak']);
        $company = Company::create(['name' => 'PT Voucher Kasir', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin Pajak', 'email' => 'voucher-kasir@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $kasTagihanAccount = Account::create(['company_id' => $company->id, 'code' => '100.04', 'name' => 'Kas Tagihan', 'type' => 'asset', 'is_active' => true]);
        $piutang = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        CashBank::create(['company_id' => $company->id, 'account_id' => $kasTagihanAccount->id, 'scope' => 'company', 'kind' => 'cash', 'name' => 'Kas Tagihan', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/receivable-receipts', [
            'transaction_date' => '2026-05-19',
            'branch_id' => $branch->id,
            'voucher_code' => 'VK-001',
            'receivable_account_id' => $piutang->id,
            'details' => [
                ['account_id' => $kasTagihanAccount->id, 'amount' => 100000000],
            ],
        ])->assertRedirect('/cash-bank-transactions');

        $transaction = CashBankTransaction::where('reference_number', 'VK-001')->firstOrFail();
        $journal = $transaction->journalEntry;
        $this->assertSame('cash_in', $transaction->type);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $kasTagihanAccount->id, 'debit' => 100000000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $piutang->id, 'debit' => 0, 'credit' => 100000000]);
    }

    public function test_receivable_receipt_rejects_non_cash_bank_debit_lines(): void
    {
        $role = Role::create(['name' => 'admin_pajak', 'label' => 'Admin Pajak']);
        $company = Company::create(['name' => 'PT Voucher Multi', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin Pajak', 'email' => 'voucher-multi@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $kasTagihanAccount = Account::create(['company_id' => $company->id, 'code' => '100.04', 'name' => 'Kas Tagihan', 'type' => 'asset', 'is_active' => true]);
        $kasLain = Account::create(['company_id' => $company->id, 'code' => '100.03', 'name' => 'Koin', 'type' => 'asset', 'is_active' => true]);
        $piutangDagang = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        CashBank::create(['company_id' => $company->id, 'account_id' => $kasTagihanAccount->id, 'scope' => 'company', 'kind' => 'cash', 'name' => 'Kas Tagihan', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/receivable-receipts', [
            'transaction_date' => '2026-05-19',
            'branch_id' => $branch->id,
            'voucher_code' => 'VK-002',
            'receivable_account_id' => $piutangDagang->id,
            'details' => [
                ['account_id' => $kasTagihanAccount->id, 'amount' => 70000000],
                ['account_id' => $kasLain->id, 'amount' => 30000000],
            ],
        ])->assertSessionHasErrors('details');

        $this->assertDatabaseMissing('journal_entries', ['reference_number' => 'VK-002']);
    }

    public function test_bank_in_posts_to_bank_against_receivable(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Bank In', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'bank-in@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.02', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/bank_in', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'cash_bank_id' => $bank->id,
            'counter_account_id' => $receivable->id,
            'amount' => 2500000,
            'description' => 'Transfer toko',
        ])->assertRedirect();

        $transaction = CashBankTransaction::where('type', 'bank_in')->firstOrFail();
        $journal = $transaction->journalEntry;
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'debit' => 2500000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'debit' => 0, 'credit' => 2500000]);
        $this->assertSame(2500000.0, $bank->fresh()->currentBalance());
    }

    public function test_manual_posted_journal_bank_line_appears_in_bank_mutation(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Manual Mutasi', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'manual-mutasi@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.02', 'name' => 'Bank Nobu', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank Nobu', 'opening_balance' => 0, 'is_active' => true]);
        $journal = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-05-18',
            'journal_number' => 'JV-2026-000001',
            'reference_number' => 'VCH-JKT-001',
            'description' => 'Bank masuk manual',
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'description' => 'Bank Nobu masuk', 'debit' => 100000000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'description' => 'Pelunasan piutang', 'debit' => 0, 'credit' => 100000000]);

        $this->assertSame(100000000.0, $bank->fresh()->currentBalance());
        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank&cash_bank_id='.$bank->id)
            ->assertOk()
            ->assertSee('Bank Nobu')
            ->assertSee('VCH-JKT-001')
            ->assertSee('Rp 100.000.000,00');
    }

    public function test_bank_mutation_shows_newest_transaction_first(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Mutasi Order', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'mutasi-order@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.02', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '400.01', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);

        CashBankTransaction::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'cash_bank_id' => $bank->id, 'counter_account_id' => $revenue->id, 'transaction_date' => '2026-05-01', 'type' => 'bank_in', 'reference_number' => 'OLD-BANK-MUTATION', 'description' => 'Transaksi lama', 'amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);
        CashBankTransaction::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'cash_bank_id' => $bank->id, 'counter_account_id' => $revenue->id, 'transaction_date' => '2026-05-22', 'type' => 'bank_in', 'reference_number' => 'NEW-BANK-MUTATION', 'description' => 'Transaksi baru', 'amount' => 2000, 'status' => 'posted', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank&cash_bank_id='.$bank->id)
            ->assertOk()
            ->assertSeeInOrder(['NEW-BANK-MUTATION', 'OLD-BANK-MUTATION']);

        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank&cash_bank_id='.$bank->id.'&sort_direction=oldest')
            ->assertOk()
            ->assertSeeInOrder(['OLD-BANK-MUTATION', 'NEW-BANK-MUTATION']);

        $this->actingAs($user)
            ->get('/cash-banks/'.$bank->id)
            ->assertOk()
            ->assertSeeInOrder(['NEW-BANK-MUTATION', 'OLD-BANK-MUTATION']);
    }

    public function test_manual_posted_journal_creates_missing_bank_master_for_mutation(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Missing Bank', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'missing-bank@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cashAccount = Account::create(['company_id' => $company->id, 'code' => '100.02', 'name' => 'Kas Kecil', 'type' => 'asset', 'is_active' => true]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.04', 'name' => 'Bank Garansi', 'type' => 'asset', 'is_active' => true]);
        CashBank::create(['company_id' => $company->id, 'account_id' => $cashAccount->id, 'scope' => 'company', 'kind' => 'cash', 'name' => 'Kas Kecil', 'opening_balance' => 0, 'is_active' => true]);
        $journal = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-05-18',
            'journal_number' => 'JV-2026-000001',
            'reference_number' => 'VCH-JKT-BANK-001',
            'description' => 'Setor kas ke bank garansi',
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'description' => 'Bank garansi masuk', 'debit' => 500000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $cashAccount->id, 'description' => 'Kas keluar', 'debit' => 0, 'credit' => 500000]);

        $this->assertDatabaseMissing('cash_banks', ['company_id' => $company->id, 'account_id' => $bankAccount->id]);

        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank')
            ->assertOk()
            ->assertSee('Bank Garansi')
            ->assertSee('VCH-JKT-BANK-001')
            ->assertSee('Rp 500.000,00');

        $this->assertDatabaseHas('cash_banks', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'account_id' => $bankAccount->id,
            'kind' => 'bank',
            'name' => 'Bank Garansi',
        ]);
    }

    public function test_manual_posted_journal_shows_single_bank_account_even_when_branch_differs(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Bank Cabang', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $prabu = Branch::create(['company_id' => $company->id, 'code' => 'PRB', 'name' => 'Prabumulih']);
        $palembang = Branch::create(['company_id' => $company->id, 'code' => 'PLG', 'name' => 'Palembang']);
        $user = User::create(['name' => 'Admin', 'email' => 'bank-branch-mismatch@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.08', 'name' => 'BANK MANDIRI PRABU', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $prabu->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'BANK MANDIRI PRABU', 'opening_balance' => 0, 'is_active' => true]);
        $journal = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $palembang->id,
            'transaction_date' => '2026-05-21',
            'journal_number' => 'JV-2026-000001',
            'reference_number' => 'VCH-SS-PLG-202605-0002',
            'description' => 'bank mandiri prabu',
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'description' => 'Bank masuk', 'debit' => 3216000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'description' => 'Piutang', 'debit' => 0, 'credit' => 3216000]);

        $this->assertSame(3216000.0, $bank->fresh()->currentBalance());
        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank')
            ->assertOk()
            ->assertSee('BANK MANDIRI PRABU')
            ->assertSee('VCH-SS-PLG-202605-0002')
            ->assertSee('Rp 3.216.000,00');

        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank&branch_id='.$prabu->id)
            ->assertOk()
            ->assertSee('BANK MANDIRI PRABU')
            ->assertSee('VCH-SS-PLG-202605-0002')
            ->assertSee('Rp 3.216.000,00');
    }

    public function test_cash_bank_mutation_branch_filter_uses_transaction_or_cash_bank_branch(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Filter Bank', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $prabu = Branch::create(['company_id' => $company->id, 'code' => 'PRB', 'name' => 'Prabumulih']);
        $palembang = Branch::create(['company_id' => $company->id, 'code' => 'PLG', 'name' => 'Palembang']);
        $user = User::create(['name' => 'Admin', 'email' => 'bank-filter@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $prabuBankAccount = Account::create(['company_id' => $company->id, 'code' => '110.08', 'name' => 'BANK MANDIRI PRABU', 'type' => 'asset', 'is_active' => true]);
        $plgBankAccount = Account::create(['company_id' => $company->id, 'code' => '110.09', 'name' => 'BANK PLG', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '400.01', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $prabuBank = CashBank::create(['company_id' => $company->id, 'branch_id' => $prabu->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $prabuBankAccount->id, 'name' => 'BANK MANDIRI PRABU', 'opening_balance' => 0, 'is_active' => true]);
        $plgBank = CashBank::create(['company_id' => $company->id, 'branch_id' => $palembang->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $plgBankAccount->id, 'name' => 'BANK PLG', 'opening_balance' => 0, 'is_active' => true]);
        $prabuJournal = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $palembang->id, 'transaction_date' => '2026-05-21', 'journal_number' => 'JV-PRB', 'reference_number' => 'TRX-PRB-BANK', 'description' => 'Bank Prabu', 'status' => 'posted', 'created_by' => $user->id, 'posted_at' => now()]);
        $plgJournal = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $palembang->id, 'transaction_date' => '2026-05-21', 'journal_number' => 'JV-PLG', 'reference_number' => 'TRX-PLG-BANK', 'description' => 'Bank PLG', 'status' => 'posted', 'created_by' => $user->id, 'posted_at' => now()]);

        CashBankTransaction::create(['company_id' => $company->id, 'branch_id' => $palembang->id, 'cash_bank_id' => $prabuBank->id, 'counter_account_id' => $revenue->id, 'journal_entry_id' => $prabuJournal->id, 'transaction_date' => '2026-05-21', 'type' => 'bank_in', 'reference_number' => 'TRX-PRB-BANK', 'description' => 'Masuk Prabu', 'amount' => 1000, 'status' => 'posted', 'created_by' => $user->id]);
        CashBankTransaction::create(['company_id' => $company->id, 'branch_id' => $palembang->id, 'cash_bank_id' => $plgBank->id, 'counter_account_id' => $revenue->id, 'journal_entry_id' => $plgJournal->id, 'transaction_date' => '2026-05-21', 'type' => 'bank_in', 'reference_number' => 'TRX-PLG-BANK', 'description' => 'Masuk PLG', 'amount' => 2000, 'status' => 'posted', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->get('/cash-bank-transactions?kind=bank&branch_id='.$prabu->id)
            ->assertOk()
            ->assertSee('TRX-PRB-BANK')
            ->assertDontSee('TRX-PLG-BANK');
    }

    public function test_cash_bank_transaction_can_be_reversed_with_posted_reversal_journal(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Reverse', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'PRB', 'name' => 'Prabumulih']);
        $user = User::create(['name' => 'Admin', 'email' => 'reverse@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.08', 'name' => 'BANK MANDIRI PRABU', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'BANK MANDIRI PRABU', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/bank_in', [
            'transaction_date' => '2026-05-21',
            'branch_id' => $branch->id,
            'cash_bank_id' => $bank->id,
            'counter_account_id' => $receivable->id,
            'amount' => 3216000,
            'reference_number' => 'REV-ORIG',
            'description' => 'Bank masuk salah',
        ])->assertRedirect();

        $transaction = CashBankTransaction::where('reference_number', 'REV-ORIG')->firstOrFail();
        $this->actingAs($user)
            ->post('/cash-bank-transactions/'.$transaction->id.'/reverse', ['reason' => 'Nominal transaksi salah input'])
            ->assertRedirect();

        $this->assertSame('cancelled', $transaction->fresh()->status);
        $reversal = JournalEntry::where('reference_number', 'REV-REV-ORIG')->firstOrFail();
        $this->assertSame('posted', $reversal->status);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $reversal->id, 'account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 3216000]);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $reversal->id, 'account_id' => $receivable->id, 'debit' => 3216000, 'credit' => 0]);
    }

    public function test_system_settings_can_update_corporate_tax_rate(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Settings', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'settings@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->get('/system-settings/edit')->assertOk()->assertSee('Tarif PPh Badan');
        $this->actingAs($user)->put('/system-settings', ['tax_rate_corporate' => 20])->assertRedirect();

        $this->assertDatabaseHas('system_settings', ['key' => 'tax_rate_corporate', 'value' => '20']);
        $this->assertSame(0.20, corporateTaxRate());
    }

    public function test_company_manage_permission_cannot_update_system_settings(): void
    {
        $role = Role::create(['name' => 'company_manager', 'label' => 'Company Manager']);
        $permission = Permission::create(['code' => 'company.manage', 'module' => 'Master Data', 'label' => 'Kelola Perusahaan']);
        $role->permissionRecords()->attach($permission);
        $company = Company::create(['name' => 'PT Settings Guard', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Company Manager', 'email' => 'settings-guard@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->get('/system-settings/edit')->assertForbidden();
        $this->actingAs($user)->put('/system-settings', ['tax_rate_corporate' => 15])->assertForbidden();
    }

    public function test_tax_reconciliation_has_tax_reports_path_and_legacy_redirect(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Tax Route', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'tax-route@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)->get('/tax-reports/reconciliation')->assertOk();
        $this->actingAs($user)->get('/reports/tax/reconciliation')->assertRedirect('/tax-reports/reconciliation');
    }

    public function test_legacy_urls_redirect_to_canonical_paths(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Legacy URL', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'legacy-url@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)
            ->get('/cash-bank-transactions/mutasi/bank')
            ->assertStatus(301)
            ->assertRedirect('/cash-bank-transactions/type/bank');

        $this->actingAs($user)
            ->get('/accounts-export')
            ->assertStatus(301)
            ->assertRedirect('/accounts/export');
    }

    public function test_custom_error_pages_render_with_app_layout(): void
    {
        $company = Company::create(['name' => 'PT Error Pages', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);
        $user = User::create(['name' => 'Viewer', 'email' => 'error-pages@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)
            ->get('/backups')
            ->assertForbidden()
            ->assertSee('Akses Ditolak')
            ->assertSee('Accounting GL');

        $this->actingAs($user)
            ->get('/halaman-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Accounting GL');
    }

    public function test_cash_flow_includes_manual_journal_cash_bank_rows(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Cash Flow Manual', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'PRB', 'name' => 'Prabumulih']);
        $user = User::create(['name' => 'Admin', 'email' => 'cashflow-manual@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.08', 'name' => 'BANK MANDIRI PRABU', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'BANK MANDIRI PRABU', 'opening_balance' => 0, 'is_active' => true]);
        $journal = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-21', 'journal_number' => 'JV-CF', 'reference_number' => 'CF-MANUAL', 'description' => 'Manual bank masuk', 'status' => 'posted', 'created_by' => $user->id, 'posted_at' => now()]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'description' => 'Bank masuk manual', 'debit' => 3216000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'description' => 'Piutang', 'debit' => 0, 'credit' => 3216000]);

        $this->actingAs($user)
            ->get('/reports/cash-flow?branch_id='.$branch->id)
            ->assertOk()
            ->assertSee('CF-MANUAL')
            ->assertSee('Jurnal Manual Masuk')
            ->assertSee('Rp 3.216.000,00');
    }

    public function test_bank_reconciliation_can_reconcile_manual_journal_bank_row(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Recon Manual', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'PRB', 'name' => 'Prabumulih']);
        $user = User::create(['name' => 'Admin', 'email' => 'recon-manual@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.08', 'name' => 'BANK MANDIRI PRABU', 'type' => 'asset', 'is_active' => true]);
        $receivable = Account::create(['company_id' => $company->id, 'code' => '120.01', 'name' => 'Piutang Dagang', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'BANK MANDIRI PRABU', 'opening_balance' => 0, 'is_active' => true]);
        $journal = JournalEntry::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'transaction_date' => '2026-05-21', 'journal_number' => 'JV-RC', 'reference_number' => 'RC-MANUAL', 'description' => 'Manual bank rekonsiliasi', 'status' => 'posted', 'created_by' => $user->id, 'posted_at' => now()]);
        $bankDetail = JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'description' => 'Bank masuk manual', 'debit' => 3216000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $receivable->id, 'description' => 'Piutang', 'debit' => 0, 'credit' => 3216000]);

        $this->actingAs($user)
            ->get('/bank-reconciliations/create?cash_bank_id='.$bank->id)
            ->assertOk()
            ->assertSee('RC-MANUAL')
            ->assertSee('Jurnal Manual');

        $this->actingAs($user)->post('/bank-reconciliations', [
            'cash_bank_id' => $bank->id,
            'statement_date' => '2026-05-31',
            'bank_statement_balance' => 3216000,
            'movement_keys' => ['journal_detail:'.$bankDetail->id],
            'notes' => 'Cocok',
        ])->assertRedirect();

        $this->assertTrue($bankDetail->fresh()->is_reconciled);
        $this->assertDatabaseHas('bank_reconciliations', [
            'cash_bank_id' => $bank->id,
            'book_balance' => 3216000,
            'difference' => 0,
            'status' => 'reconciled',
        ]);
    }

    public function test_bank_reconciliation_excludes_movements_after_statement_date(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Recon Date', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'recon-date@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);
        $futureTransaction = CashBankTransaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'cash_bank_id' => $bank->id,
            'type' => 'cash_in',
            'transaction_date' => '2026-06-01',
            'amount' => 500,
            'description' => 'Setoran setelah statement',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/bank-reconciliations/create?cash_bank_id='.$bank->id.'&statement_date=2026-05-31')
            ->assertOk()
            ->assertDontSee('Setoran setelah statement');

        $this->actingAs($user)->post('/bank-reconciliations', [
            'cash_bank_id' => $bank->id,
            'statement_date' => '2026-05-31',
            'bank_statement_balance' => 0,
            'transaction_ids' => [$futureTransaction->id],
            'notes' => 'Future movement should be ignored',
        ])->assertRedirect();

        $this->assertFalse($futureTransaction->fresh()->is_reconciled);
        $this->assertDatabaseHas('bank_reconciliations', ['cash_bank_id' => $bank->id, 'book_balance' => 0, 'difference' => 0]);
    }

    public function test_bank_in_from_cash_source_posts_as_transfer_and_credits_cash(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Bank Setor', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'bank-setor@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cashAccount = Account::create(['company_id' => $company->id, 'code' => '100.04', 'name' => 'Kas Tagihan', 'type' => 'asset', 'is_active' => true]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '110.02', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $cash = CashBank::create(['company_id' => $company->id, 'scope' => 'company', 'kind' => 'cash', 'account_id' => $cashAccount->id, 'name' => 'Kas Tagihan', 'opening_balance' => 1000000, 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/bank_in', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'cash_bank_id' => $bank->id,
            'counter_account_id' => $cashAccount->id,
            'amount' => 400000,
            'description' => 'Setor kas tagihan ke bank',
        ])->assertRedirect();

        $transaction = CashBankTransaction::where('type', 'transfer')->firstOrFail();
        $journal = $transaction->journalEntry;
        $this->assertSame($cash->id, $transaction->cash_bank_id);
        $this->assertSame($bank->id, $transaction->target_cash_bank_id);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $bankAccount->id, 'debit' => 400000, 'credit' => 0]);
        $this->assertDatabaseHas('journal_details', ['journal_entry_id' => $journal->id, 'account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 400000]);
        $this->assertSame(600000.0, $cash->fresh()->currentBalance());
        $this->assertSame(400000.0, $bank->fresh()->currentBalance());
    }

    public function test_manual_journal_can_generate_voucher_from_selected_bank(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['code' => 'ABC', 'name' => 'PT Voucher', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'voucher@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'Beban', 'type' => 'expense', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)
            ->get("/journals/voucher-number?cash_bank_id={$bank->id}&branch_id={$branch->id}&transaction_date=2026-05-18")
            ->assertOk()
            ->assertJson(['reference_number' => 'VCH-ABC-JKT-1020-202605-0001']);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-18',
            'branch_id' => $branch->id,
            'status' => 'draft',
            'description' => 'Biaya bank',
            'details' => [
                ['account_id' => $expense->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $bankAccount->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'reference_number' => 'VCH-ABC-JKT-202605-0001',
            'description' => 'Biaya bank',
        ]);
    }

    public function test_super_admin_can_input_journal_for_selected_company(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $companyA = Company::create(['code' => 'A', 'name' => 'PT A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['code' => 'B', 'name' => 'PT B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branchA = Branch::create(['company_id' => $companyA->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $branchB = Branch::create(['company_id' => $companyB->id, 'code' => 'SBY', 'name' => 'Surabaya']);
        $user = User::create(['name' => 'Super', 'email' => 'super-journal-company@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $companyA->id]);
        $cashA = Account::create(['company_id' => $companyA->id, 'code' => '1010', 'name' => 'Kas A', 'type' => 'asset', 'is_active' => true]);
        $cashB = Account::create(['company_id' => $companyB->id, 'code' => '1010', 'name' => 'Kas B', 'type' => 'asset', 'is_active' => true]);
        $revenueB = Account::create(['company_id' => $companyB->id, 'code' => '4000', 'name' => 'Pendapatan B', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/journals/create?company_id='.$companyB->id)
            ->assertOk()
            ->assertSee('B - PT B')
            ->assertSee('SBY - Surabaya')
            ->assertSee('1010 - Kas B')
            ->assertDontSee('JKT - Jakarta')
            ->assertDontSee('1010 - Kas A');

        $this->actingAs($user)->post('/journals', [
            'company_id' => $companyB->id,
            'transaction_date' => '2026-05-18',
            'branch_id' => $branchB->id,
            'status' => 'draft',
            'description' => 'Jurnal company B',
            'details' => [
                ['account_id' => $cashB->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenueB->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $companyB->id,
            'branch_id' => $branchB->id,
            'description' => 'Jurnal company B',
        ]);
    }

    public function test_cash_transfer_creates_balanced_posted_journal(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Transfer', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'transfer@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cashAccount = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $cash = CashBank::create(['company_id' => $company->id, 'account_id' => $cashAccount->id, 'name' => 'Kas Utama', 'opening_balance' => 2000, 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 0, 'is_active' => true]);

        $this->actingAs($user)->post('/cash-bank-transactions/transfer', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'cash_bank_id' => $cash->id,
            'target_cash_bank_id' => $bank->id,
            'amount' => 750,
            'description' => 'Setor kas ke bank',
        ])->assertRedirect('/cash-bank-transactions');

        $this->assertSame(1250.0, $cash->fresh()->currentBalance());
        $this->assertSame(750.0, $bank->fresh()->currentBalance());
    }

    public function test_bank_reconciliation_marks_selected_bank_transactions(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Rekonsiliasi', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'BDG', 'name' => 'Bandung']);
        $user = User::create(['name' => 'Admin', 'email' => 'reconcile@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank BCA', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank BCA', 'opening_balance' => 1000, 'is_active' => true]);
        $transaction = CashBankTransaction::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'cash_bank_id' => $bank->id,
            'type' => 'cash_in',
            'transaction_date' => '2026-05-15',
            'amount' => 500,
            'description' => 'Setoran bank',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post('/bank-reconciliations', [
            'cash_bank_id' => $bank->id,
            'statement_date' => '2026-05-31',
            'bank_statement_balance' => 1500,
            'transaction_ids' => [$transaction->id],
            'notes' => 'Cocok dengan rekening koran',
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_reconciliations', ['cash_bank_id' => $bank->id, 'difference' => 0, 'status' => 'reconciled']);
        $this->assertTrue($transaction->fresh()->is_reconciled);
    }

    public function test_bank_reconciliation_view_only_role_cannot_create_reconciliation(): void
    {
        $role = Role::create([
            'name' => 'bank_viewer',
            'label' => 'Bank Viewer',
            'permissions' => ['bank_reconciliation.view'],
        ]);
        $permission = Permission::create(['code' => 'bank_reconciliation.view', 'module' => 'Transaksi', 'label' => 'Lihat Rekonsiliasi Bank']);
        $role->permissionRecords()->sync([$permission->id]);

        $company = Company::create(['name' => 'PT View Only', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'SBY', 'name' => 'Surabaya']);
        $user = User::create(['name' => 'Viewer', 'email' => 'bank-view-only@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $bankAccount = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Bank Mandiri', 'type' => 'asset', 'is_active' => true]);
        $bank = CashBank::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'scope' => 'branch', 'kind' => 'bank', 'account_id' => $bankAccount->id, 'name' => 'Bank Mandiri', 'opening_balance' => 1000, 'is_active' => true]);

        $this->actingAs($user)->get('/bank-reconciliations')->assertOk();
        $this->actingAs($user)->get('/bank-reconciliations/create')->assertForbidden();
        $this->actingAs($user)->post('/bank-reconciliations', [
            'cash_bank_id' => $bank->id,
            'statement_date' => '2026-05-31',
            'bank_statement_balance' => 1000,
        ])->assertForbidden();
    }

    public function test_direct_submitted_journal_cannot_be_self_approved(): void
    {
        $role = Role::create(['name' => 'manager_internal', 'label' => 'Manager Internal']);
        $company = Company::create(['name' => 'PT Direct Submit', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'direct-submit@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'submitted',
            'description' => 'Direct submitted',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::where('description', 'Direct submitted')->first();
        $this->assertSame($user->id, $journal->submitted_by);

        $this->actingAs($user)
            ->post("/journals/{$journal->id}/approve")
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda tidak dapat menyetujui jurnal yang Anda ajukan sendiri.');
    }

    public function test_tax_category_migration_detects_existing_pph_variants(): void
    {
        $company = Company::create(['name' => 'PT Tax Category', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $pphPs23 = Account::create(['company_id' => $company->id, 'code' => '140.02', 'name' => 'PPh Ps 23 (15%)', 'type' => 'asset', 'is_active' => true]);
        $pphPsDot23 = Account::create(['company_id' => $company->id, 'code' => '210.02', 'name' => 'PPh Ps.23', 'type' => 'liability', 'is_active' => true]);
        $pphPasal23 = Account::create(['company_id' => $company->id, 'code' => '700.01', 'name' => 'Pajak PPh Pasal 23/25/27', 'type' => 'other_expense', 'is_active' => true]);
        $ppnFullName = Account::create(['company_id' => $company->id, 'code' => '210.10', 'name' => 'Pajak Pertambahan Nilai Keluaran', 'type' => 'liability', 'is_active' => true]);

        $this->artisan('tax:migrate-categories')->assertExitCode(0);

        $this->assertSame('pph23', $pphPs23->fresh()->tax_category);
        $this->assertSame('pph23', $pphPsDot23->fresh()->tax_category);
        $this->assertSame('pph23', $pphPasal23->fresh()->tax_category);
        $this->assertSame('ppn', $ppnFullName->fresh()->tax_category);
    }

    public function test_tax_report_uses_tax_category_and_warns_uncategorized_tax_accounts(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Tax Report', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $user = User::create(['name' => 'Admin', 'email' => 'tax-category@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $taggedPpn = Account::create(['company_id' => $company->id, 'code' => '210.01', 'name' => 'Pajak Pertambahan Nilai Keluaran', 'type' => 'liability', 'tax_category' => 'ppn', 'is_active' => true]);
        $untaggedPpn = Account::create(['company_id' => $company->id, 'code' => '210.02', 'name' => 'PPN Belum Dikategori', 'type' => 'liability', 'is_active' => true]);
        $journal = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'transaction_date' => '2026-05-20',
            'journal_number' => 'JV-TAX-001',
            'reference_number' => 'TAX-001',
            'description' => 'Pajak test',
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $cash->id, 'description' => 'Kas', 'debit' => 3000, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $taggedPpn->id, 'description' => 'PPN tagged', 'debit' => 0, 'credit' => 1000]);
        JournalDetail::create(['journal_entry_id' => $journal->id, 'account_id' => $untaggedPpn->id, 'description' => 'PPN untagged', 'debit' => 0, 'credit' => 2000]);

        $this->actingAs($user)
            ->get('/tax-reports/summary?from=2026-05-01&to=2026-05-31&company_id='.$company->id)
            ->assertOk()
            ->assertSee('PPN Belum Dikategori')
            ->assertSee('Rp 1.000,00')
            ->assertDontSee('Rp 3.000,00');
    }

    public function test_mfa_setup_page_renders_inline_qr_code(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT MFA', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'mfa@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->actingAs($user)
            ->get('/mfa/setup')
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64', false)
            ->assertSee('Secret Key');

        $this->assertNotNull($user->fresh()->google2fa_secret);
    }

    public function test_mfa_setup_page_still_renders_when_qr_backend_fails(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT MFA Fallback', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'mfa-fallback@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        app()->instance('pragmarx.google2fa', new class {
            public function generateSecretKey(): string
            {
                return 'ABCDEFGHIJKLMNOP';
            }

            public function getQRCodeInline(): string
            {
                throw new \RuntimeException('QR backend unavailable');
            }
        });

        $this->actingAs($user)
            ->get('/mfa/setup')
            ->assertOk()
            ->assertSee('QR Code tidak tersedia')
            ->assertSee('Secret Key')
            ->assertSee('ABCDEFGHIJKLMNOP');

        $this->assertSame('ABCDEFGHIJKLMNOP', $user->fresh()->google2fa_secret);
    }

    public function test_mfa_challenge_expires_after_five_minutes(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT MFA TTL', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Admin', 'email' => 'mfa-ttl@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);

        $this->withSession([
            'mfa.user_id' => $user->id,
            'mfa.remember' => false,
            'mfa.created_at' => now()->subMinutes(6)->timestamp,
        ])->get('/mfa/challenge')
            ->assertRedirect('/login')
            ->assertSessionMissing('mfa.user_id');
    }

    public function test_global_search_super_admin_without_company_uses_valid_company_scope(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $companyA = Company::create(['name' => 'PT Search A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['name' => 'PT Search B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Super', 'email' => 'search-scope@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => null]);
        Account::create(['company_id' => $companyA->id, 'code' => '110.01', 'name' => 'Bank Search A', 'type' => 'asset', 'is_active' => true]);
        Account::create(['company_id' => $companyB->id, 'code' => '110.02', 'name' => 'Bank Search B', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/search?q=Bank%20Search&company_id='.$companyB->id)
            ->assertOk()
            ->assertSee('Bank Search B')
            ->assertDontSee('Bank Search A');
    }

    public function test_super_admin_reports_use_selected_company_scope(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $companyA = Company::create(['name' => 'PT Report A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['name' => 'PT Report B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branchA = Branch::create(['company_id' => $companyA->id, 'code' => 'A', 'name' => 'A']);
        $branchB = Branch::create(['company_id' => $companyB->id, 'code' => 'B', 'name' => 'B']);
        $user = User::create(['name' => 'Super', 'email' => 'report-scope@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $companyA->id]);
        $cashA = Account::create(['company_id' => $companyA->id, 'code' => '1010', 'name' => 'Kas Report A', 'type' => 'asset', 'is_active' => true]);
        $revenueA = Account::create(['company_id' => $companyA->id, 'code' => '4000', 'name' => 'Pendapatan A', 'type' => 'revenue', 'is_active' => true]);
        $cashB = Account::create(['company_id' => $companyB->id, 'code' => '1010', 'name' => 'Kas Report B', 'type' => 'asset', 'is_active' => true]);
        $revenueB = Account::create(['company_id' => $companyB->id, 'code' => '4000', 'name' => 'Pendapatan B', 'type' => 'revenue', 'is_active' => true]);

        $journalA = JournalEntry::create(['company_id' => $companyA->id, 'branch_id' => $branchA->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-A', 'description' => 'Report A', 'status' => 'posted', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $journalA->id, 'account_id' => $cashA->id, 'debit' => 9999, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journalA->id, 'account_id' => $revenueA->id, 'debit' => 0, 'credit' => 9999]);

        $journalB = JournalEntry::create(['company_id' => $companyB->id, 'branch_id' => $branchB->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-B', 'description' => 'Report B', 'status' => 'posted', 'created_by' => $user->id]);
        JournalDetail::create(['journal_entry_id' => $journalB->id, 'account_id' => $cashB->id, 'debit' => 1500, 'credit' => 0]);
        JournalDetail::create(['journal_entry_id' => $journalB->id, 'account_id' => $revenueB->id, 'debit' => 0, 'credit' => 1500]);

        $this->actingAs($user)
            ->get('/reports/trial-balance?company_id='.$companyB->id)
            ->assertOk()
            ->assertSee('Kas Report B')
            ->assertSee('Rp 1.500,00')
            ->assertDontSee('Kas Report A')
            ->assertDontSee('Rp 9.999,00');
    }

    public function test_super_admin_cash_flow_uses_selected_company_scope(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $companyA = Company::create(['name' => 'PT Cash Flow A', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['name' => 'PT Cash Flow B', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $user = User::create(['name' => 'Super', 'email' => 'cash-flow-scope@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $companyA->id]);
        $accountA = Account::create(['company_id' => $companyA->id, 'code' => '1010', 'name' => 'Kas A', 'type' => 'asset', 'is_active' => true]);
        $accountB = Account::create(['company_id' => $companyB->id, 'code' => '1010', 'name' => 'Kas B', 'type' => 'asset', 'is_active' => true]);
        CashBank::create(['company_id' => $companyA->id, 'account_id' => $accountA->id, 'name' => 'Kas Flow A', 'opening_balance' => 9999, 'is_active' => true]);
        CashBank::create(['company_id' => $companyB->id, 'account_id' => $accountB->id, 'name' => 'Kas Flow B', 'opening_balance' => 1500, 'is_active' => true]);

        $this->actingAs($user)
            ->get('/reports/cash-flow?company_id='.$companyB->id)
            ->assertOk()
            ->assertSee('Rp 1.500,00')
            ->assertDontSee('Rp 9.999,00');
    }

    public function test_closing_entry_creates_posted_journal_and_closes_period(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $company = Company::create(['name' => 'PT Closing', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $period = FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Mei 2026', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31', 'status' => 'open']);
        $user = User::create(['name' => 'Admin', 'email' => 'closing@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $approver = User::create(['name' => 'Approver', 'email' => 'closing-approver@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $company->id]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1010', 'name' => 'Kas', 'type' => 'asset', 'is_active' => true]);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'is_active' => true]);
        $equity = Account::create(['company_id' => $company->id, 'code' => '3000', 'name' => 'Laba Ditahan', 'type' => 'equity', 'is_active' => true]);

        $this->actingAs($user)->post('/journals', [
            'transaction_date' => '2026-05-15',
            'branch_id' => $branch->id,
            'status' => 'submitted',
            'description' => 'Pendapatan Mei',
            'details' => [
                ['account_id' => $cash->id, 'debit' => 2000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 2000],
            ],
        ])->assertRedirect();

        $journal = JournalEntry::where('description', 'Pendapatan Mei')->first();
        $this->actingAs($approver)->post("/journals/{$journal->id}/approve")->assertRedirect();
        $this->actingAs($approver)->post("/journals/{$journal->id}/post")->assertRedirect();

        $this->actingAs($user)
            ->get('/closing-entries/create?fiscal_period_id='.$period->id.'&equity_account_id='.$equity->id.'&description=Closing%20Mei%202026')
            ->assertOk()
            ->assertSee('Preview Closing Entry')
            ->assertSee('Pendapatan')
            ->assertDontSee('Closing entry berhasil dibuat');

        $this->actingAs($user)->post('/closing-entries', [
            'fiscal_period_id' => $period->id,
            'equity_account_id' => $equity->id,
            'description' => 'Closing Mei 2026',
            'confirm_preview' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_entries', ['company_id' => $company->id, 'reference_number' => "CLOSING-{$period->id}", 'status' => 'posted']);
        $this->assertSame('closed', $period->fresh()->status);
    }

    public function test_tax_admin_dashboard_only_shows_their_company(): void
    {
        $role = Role::create(['name' => 'admin_pajak', 'label' => 'Admin Pajak', 'permissions' => ['dashboard.view']]);
        $companyA = Company::create(['code' => 'SS', 'name' => 'PT Sriwijaya Serangkai', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['code' => 'SSD', 'name' => 'PT Sriwijaya Distribution', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branchA = Branch::create(['company_id' => $companyA->id, 'code' => 'SS', 'name' => 'Sriwijaya Serangkai']);
        $branchB = Branch::create(['company_id' => $companyB->id, 'code' => 'SSD', 'name' => 'Sriwijaya Distribution']);
        $user = User::create(['name' => 'Tia', 'email' => 'tia@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $companyA->id]);

        JournalEntry::create(['company_id' => $companyA->id, 'branch_id' => $branchA->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-SS', 'description' => 'Pajak Serangkai', 'status' => 'posted', 'created_by' => $user->id]);
        JournalEntry::create(['company_id' => $companyB->id, 'branch_id' => $branchB->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-SSD', 'description' => 'Pajak Distribution', 'status' => 'posted', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->get('/dashboards/tax?journal_status=posted')
            ->assertOk()
            ->assertSee('PT Sriwijaya Serangkai')
            ->assertSee('Pajak Serangkai')
            ->assertDontSee('Semua Perusahaan')
            ->assertDontSee('Pajak Distribution');
    }

    public function test_tax_manager_dashboard_can_see_all_or_select_one_company(): void
    {
        $role = Role::create(['name' => 'manager_pajak', 'label' => 'Manager Pajak', 'permissions' => ['dashboard.view']]);
        $companyA = Company::create(['code' => 'SS', 'name' => 'PT Sriwijaya Serangkai', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $companyB = Company::create(['code' => 'SSD', 'name' => 'PT Sriwijaya Distribution', 'fiscal_year' => 2026, 'base_currency' => 'IDR']);
        $branchA = Branch::create(['company_id' => $companyA->id, 'code' => 'SS', 'name' => 'Sriwijaya Serangkai']);
        $branchB = Branch::create(['company_id' => $companyB->id, 'code' => 'SSD', 'name' => 'Sriwijaya Distribution']);
        $user = User::create(['name' => 'Manager Pajak', 'email' => 'manager-pajak@test.local', 'password' => 'password', 'role_id' => $role->id, 'company_id' => $companyA->id]);

        JournalEntry::create(['company_id' => $companyA->id, 'branch_id' => $branchA->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-SS', 'description' => 'Pajak Serangkai', 'status' => 'posted', 'created_by' => $user->id]);
        JournalEntry::create(['company_id' => $companyB->id, 'branch_id' => $branchB->id, 'transaction_date' => '2026-05-15', 'journal_number' => 'JV-SSD', 'description' => 'Pajak Distribution', 'status' => 'posted', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->get('/dashboard/tax')
            ->assertRedirect(route('dashboards.tax'));

        $this->actingAs($user)
            ->get('/dashboards/tax?journal_status=posted')
            ->assertOk()
            ->assertSee('Semua Perusahaan')
            ->assertSee('Dashboard Pajak')
            ->assertSee('Pajak Serangkai')
            ->assertSee('Pajak Distribution');

        $this->actingAs($user)
            ->get('/dashboards/tax?company_id='.$companyB->id.'&journal_status=posted')
            ->assertOk()
            ->assertSee('PT Sriwijaya Distribution')
            ->assertSee('Pajak Distribution')
            ->assertDontSee('Pajak Serangkai');
    }
}
